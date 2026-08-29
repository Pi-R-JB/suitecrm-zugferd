<?php

declare(strict_types=1);

require_once __DIR__ . '/ZugferdException.php';
require_once __DIR__ . '/ZugferdService.php';

final class ZugferdPdfService
{
    private string $crmRoot;
    private ZugferdService $xmlService;

    public function __construct(
        ?string $crmRoot = null,
        ?ZugferdService $xmlService = null
    ) {
        $this->crmRoot = $crmRoot ?? dirname(__DIR__, 4);

        require_once $this->crmRoot
            . '/vendor/autoload.php';

        require_once __DIR__ . '/ZugferdTcLibPdf.php';

        $this->xmlService =
            $xmlService ?? new ZugferdService($this->crmRoot);
    }

    /**
     * Erzeugt eine hybride ZUGFeRD-PDF/A-3-Rechnung.
     *
     * Wenn $templateId null ist, wird die erste aktive
     * AOS_Invoices-PDF-Vorlage verwendet.
     *
     * @return array{
     *     invoice_id:string,
     *     invoice_number:string,
     *     template_id:string,
     *     xml_file:string,
     *     pdf_file:string,
     *     embedded_filename:string,
     *     xsd_valid:bool
     * }
     */
    public function generate(
        string $invoiceId,
        ?string $templateId = null
    ): array {
        $bean = \BeanFactory::getBean(
            'AOS_Invoices',
            $invoiceId
        );

        if (!$bean || empty($bean->id)) {
            throw new ZugferdException(
                'Rechnung wurde nicht gefunden: ' . $invoiceId
            );
        }

        $template = $this->loadTemplate($templateId);

        /*
         * Erst XML erzeugen und XSD-validieren.
         * Bei Fehler wird kein PDF erzeugt.
         */
        $xmlResult = $this->xmlService->generate($invoiceId);

        $xml = file_get_contents($xmlResult['xml_file']);

        if ($xml === false || $xml === '') {
            throw new ZugferdException(
                'Das erzeugte EN16931-XML konnte nicht gelesen werden.'
            );
        }

        [$header, $footer, $printable] =
            $this->renderTemplateContent($bean, $template);

        $pdf = $this->createPdfA3(
            $template,
            $header,
            $footer
        );

        $pdf->setCreator('SuiteCRM');
        $pdf->setAuthor('SuiteCRM');
        $pdf->setTitle(
            'Rechnung ' . (string)$bean->number
        );
        $pdf->setSubject(
            'ZUGFeRD EN16931 Rechnung'
        );
        $pdf->setKeywords(
            'ZUGFeRD, Factur-X, EN16931, Rechnung'
        );

        /*
         * Factur-X/ZUGFeRD-Metadaten und XML-Anhang.
         */
        $pdf->addFacturXXmp();
        $pdf->embedFacturX($xml);

        /*
         * Standardisierter Dateiname innerhalb des Hybrid-PDF.
         */
        $embeddedFilename = 'factur-x.xml';

        $defaultCss = file_get_contents(
            $this->crmRoot
            . '/lib/PDF/TCPDF/default.css'
        );

        if ($defaultCss === false) {
            $defaultCss = '';
        }

        /*
         * Nach createPdfA3() ist Region 2 die Body-Region.
         */
        $page = $pdf->page->getPage();
        $pdf->page->selectRegion(
            2,
            (int)$page['pid']
        );

        $region = $pdf->page->getRegion(
            (int)$page['pid']
        );

        $html =
            '<style>'
            . $defaultCss
            . '</style>'
            . $printable;

        $pdf->addHTMLCell(
            html: $html,
            posx: (float)$region['RX'],
            posy: (float)$region['RY'],
            width: (float)$region['RW']
        );

        $outputDir =
            $this->crmRoot . '/upload/zugferd';

        if (
            !is_dir($outputDir) &&
            !mkdir($outputDir, 0770, true) &&
            !is_dir($outputDir)
        ) {
            throw new ZugferdException(
                'PDF-Ausgabeverzeichnis konnte nicht angelegt werden.'
            );
        }

        $invoiceNumber = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '_',
            (string)$bean->number
        );

        $pdfFile =
            $outputDir
            . '/zugferd-rechnung-'
            . $invoiceNumber
            . '.pdf';

        $rawPdf = $pdf->getOutPDFString();

        if ($rawPdf === '') {
            throw new ZugferdException(
                'tc-lib-pdf hat keine PDF-Daten erzeugt.'
            );
        }

        if (file_put_contents($pdfFile, $rawPdf) === false) {
            throw new ZugferdException(
                'ZUGFeRD-PDF konnte nicht gespeichert werden.'
            );
        }

        if (!is_file($pdfFile) || filesize($pdfFile) === 0) {
            throw new ZugferdException(
                'ZUGFeRD-PDF wurde nicht erzeugt.'
            );
        }

        return [
            'invoice_id' => (string)$bean->id,
            'invoice_number' => (string)$bean->number,
            'customer' => (string)$xmlResult['customer'],
            'gross_total' => (float)$xmlResult['gross_total'],
            'template_id' => (string)$template->id,
            'xml_file' => (string)$xmlResult['xml_file'],
            'pdf_file' => $pdfFile,
            'embedded_filename' => $embeddedFilename,
            'xsd_valid' => (bool)$xmlResult['xsd_valid'],
               ];
    }

    private function loadTemplate(?string $templateId)
    {
        if ($templateId !== null && $templateId !== '') {
            $template = \BeanFactory::getBean(
                'AOS_PDF_Templates',
                $templateId
            );

            if (
                !$template ||
                empty($template->id) ||
                (string)$template->type !== 'AOS_Invoices'
            ) {
                throw new ZugferdException(
                    'Die angegebene PDF-Vorlage wurde nicht gefunden '
                    . 'oder ist keine Rechnungsvorlage.'
                );
            }

            return $template;
        }

        $template = \BeanFactory::newBean(
            'AOS_PDF_Templates'
        );

        $sql =
            "SELECT id "
            . "FROM aos_pdf_templates "
            . "WHERE deleted = 0 "
            . "AND type = 'AOS_Invoices' "
            . "AND active = 1 "
            . "ORDER BY date_modified DESC "
            . "LIMIT 1";

        $result = $template->db->query($sql);
        $row = $template->db->fetchByAssoc($result);

        if (empty($row['id'])) {
            throw new ZugferdException(
                'Keine aktive AOS-Invoice-PDF-Vorlage gefunden.'
            );
        }

        $template->retrieve($row['id']);

        if (empty($template->id)) {
            throw new ZugferdException(
                'PDF-Vorlage konnte nicht geladen werden.'
            );
        }

        return $template;
    }

    /**
     * Rendert dieselben AOS-Template-Daten wie generatePdf.php,
     * ohne die Core-Datei zu verändern.
     *
     * @return array{0:string,1:string,2:string}
     */
    private function renderTemplateContent(
        $bean,
        $template
    ): array {
        require_once $this->crmRoot
            . '/modules/AOS_PDF_Templates/templateParser.php';

        require_once __DIR__
            . '/AosPdfTemplateHelper.php';

        $variableName = strtolower($bean->module_dir);

        $lineItemsGroups = [];
        $lineItems = [];

        $sql =
            "SELECT pg.id, pg.product_id, pg.group_id "
            . "FROM aos_products_quotes pg "
            . "LEFT JOIN aos_line_item_groups lig "
            . "ON pg.group_id = lig.id "
            . "WHERE pg.parent_type = '"
            . $bean->db->quote($bean->object_name)
            . "' "
            . "AND pg.parent_id = '"
            . $bean->db->quote($bean->id)
            . "' "
            . "AND pg.deleted = 0 "
            . "ORDER BY lig.number ASC, pg.number ASC";

        $res = $bean->db->query($sql);

        while ($row = $bean->db->fetchByAssoc($res)) {
            $lineItemsGroups[$row['group_id']][$row['id']] =
                $row['product_id'];

            $lineItems[$row['id']] =
                $row['product_id'];
        }

        $objectArr = [];
        $objectArr[$bean->module_dir] = $bean->id;
        $objectArr['Accounts'] =
            $bean->billing_account_id ?? '';
        $objectArr['Contacts'] =
            $bean->billing_contact_id ?? '';
        $objectArr['Users'] =
            $bean->assigned_user_id ?? '';
        $objectArr['Currencies'] =
            $bean->currency_id ?? '';

        $search = [
            '/<script[^>]*?>.*?<\/script>/si',
            '/<[\/\!]*?[^<>]*?>/si',
            '/([\r\n])[\s]+/',
            '/&(quot|#34);/i',
            '/&(amp|#38);/i',
            '/&(lt|#60);/i',
            '/&(gt|#62);/i',
            '/&(nbsp|#160);/i',
            '/&(iexcl|#161);/i',
            '/<address[^>]*?>/si',
            '/&(apos|#0*39);/',
            '/&#(\d+);/',
        ];

        $replace = [
            '',
            '',
            '\1',
            '"',
            '&',
            '<',
            '>',
            ' ',
            chr(161),
            '<br>',
            "'",
            'chr(%1)',
        ];

        $header = preg_replace(
            $search,
            $replace,
            (string)$template->pdfheader
        );

        $footer = preg_replace(
            $search,
            $replace,
            (string)$template->pdffooter
        );

        $text = preg_replace(
            $search,
            $replace,
            (string)$template->description
        );

        $text = str_replace(
            '<p><pagebreak /></p>',
            '<pagebreak />',
            $text
        );

        $text = preg_replace_callback(
            '/\{DATE\s+(.*?)\}/',
            static function ($matches) {
                return date($matches[1]);
            },
            $text
        );

        $text = str_replace(
            '$aos_quotes',
            '$' . $variableName,
            $text
        );

        $text = str_replace(
            '$aos_invoices',
            '$' . $variableName,
            $text
        );

        $text = str_replace(
            '$total_amt',
            '$' . $variableName . '_total_amt',
            $text
        );

        $text = str_replace(
            '$discount_amount',
            '$' . $variableName . '_discount_amount',
            $text
        );

        $text = str_replace(
            '$subtotal_amount',
            '$' . $variableName . '_subtotal_amount',
            $text
        );

        $text = str_replace(
            '$tax_amount',
            '$' . $variableName . '_tax_amount',
            $text
        );

        $text = str_replace(
            '$shipping_amount',
            '$' . $variableName . '_shipping_amount',
            $text
        );

        $text = str_replace(
            '$total_amount',
            '$' . $variableName . '_total_amount',
            $text
        );

        /*
         * populate_group_lines() wird von templateParser.php bereitgestellt
         * und entspricht dem bestehenden SuiteCRM-PDF-Generator.
         */
        $text = populate_group_lines(
            $text,
            $lineItemsGroups,
            $lineItems
        );

        $converted = \templateParser::parse_template(
            $text,
            $objectArr
        );

        $header = \templateParser::parse_template(
            $header,
            $objectArr
        );

        $footer = \templateParser::parse_template(
            $footer,
            $objectArr
        );

        $header = $this->resolveLocalImageSources(
            (string)$header
        );

        $footer = $this->resolveLocalImageSources(
            (string)$footer
        );

        $converted = $this->resolveLocalImageSources(
            (string)$converted
        );

        $printable = str_replace(
            "\n",
            '<br />',
            (string)$converted
        );

        return [
            (string)$header,
            (string)$footer,
            $printable,
        ];
    }

    /**
     * Löst lokale Bildreferenzen aus AOS-PDF-Templates auf.
     *
     * URLs wie
     *   http://host/public/logo.png
     *   https://host/public/logo.png
     *   /public/logo.png
     *   public/logo.png
     *
     * werden auf eine tatsächlich vorhandene Datei innerhalb
     * der aktuellen SuiteCRM-Installation umgesetzt.
     */
    private function resolveLocalImageSources(string $html): string
    {
        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i',
            function (array $matches): string {
                $src = html_entity_decode(
                    $matches[2],
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );

                $path = parse_url($src, PHP_URL_PATH);

                if (!is_string($path) || $path === '') {
                    $path = $src;
                }

                $path = ltrim($path, '/');

                /*
                 * Keine Pfadnavigation außerhalb des CRM-Verzeichnisses.
                 */
                if (
                    $path === ''
                    || str_contains($path, '..')
                ) {
                    return $matches[0];
                }

                $localFile =
                    $this->crmRoot . '/' . $path;

                if (!is_file($localFile)) {
                    return $matches[0];
                }

                return
                    $matches[1]
                    . $localFile
                    . $matches[3];
            },
            $html
        ) ?? $html;
    }

    private function createPdfA3(
        $template,
        string $header,
        string $footer
    ): ZugferdTcLibPdf {
        $pdf = new ZugferdTcLibPdf();

        /*
         * Header und Footer müssen vor der ersten addPage()-Ausführung
         * gesetzt sein, weil defaultPageContent() beim Anlegen der Seite
         * bereits aufgerufen wird.
         */
        $pdf->setHtmlHeader($header);
        $pdf->setHtmlFooter($footer);

        $pdf->setHeaderMargin(
            (float)$template->margin_header
        );

        $pdf->setFooterMargin(
            (float)$template->margin_footer
        );

        $font = $pdf->font->insert(
            $pdf->pon,
            'gisha',
            '',
            10
        );

        $pageWidth = 210.0;
        $pageHeight = 297.0;

        $marginLeft = (float)$template->margin_left;
        $marginRight = (float)$template->margin_right;
        $marginTop = (float)$template->margin_top;
        $marginBottom = (float)$template->margin_bottom;
        $marginHeader = (float)$template->margin_header;
        $marginFooter = (float)$template->margin_footer;

        $page = $pdf->addPage([
            'format' => 'A4',
            'region' => [
                [
                    'RX' => $marginLeft,
                    'RY' => $marginHeader,
                    'RW' => $pageWidth
                        - $marginLeft
                        - $marginRight,
                    'RH' => $marginTop
                        - $marginHeader,
                ],
                [
                    'RX' => $marginLeft,
                    'RY' => $pageHeight
                        - $marginBottom,
                    'RW' => $pageWidth
                        - $marginLeft
                        - $marginRight,
                    'RH' => $marginBottom
                        - $marginFooter,
                ],
                [
                    'RX' => $marginLeft,
                    'RY' => $marginTop,
                    'RW' => $pageWidth
                        - $marginLeft
                        - $marginRight,
                    'RH' => $pageHeight
                        - $marginTop
                        - $marginBottom,
                ],
            ],
        ]);

        $pdf->page->addContent(
            $font['out'],
            (int)$page['pid']
        );

        $pdf->page->selectRegion(
            2,
            (int)$page['pid']
        );

        return $pdf;
    }


}
