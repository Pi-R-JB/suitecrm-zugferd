<?php

declare(strict_types=1);

use SuiteCRM\PDF\TCPDF\SuiteTCPDF;

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
            . '/lib/PDF/TCPDF/SuiteTCPDF.php';

        require_once __DIR__ . '/ZugferdTCPDF.php';

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

        $pdf = $this->createPdfA3($template);

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

        $pdf->setPrintHeader(true);
        $pdf->setHtmlHeader($header);

        $pdf->setPrintFooter(true);
        $pdf->setHtmlFooter($footer);

        $this->addFacturXXmp($pdf);

        /*
         * Standardisierter Dateiname innerhalb des Hybrid-PDF.
         */
        $embeddedFilename = 'factur-x.xml';

        $pdf->EmbedFileFromString(
            $embeddedFilename,
            $xml
        );

        $pdf->AddPage();

        $defaultCss = file_get_contents(
            $this->crmRoot
            . '/lib/PDF/TCPDF/default.css'
        );

        if ($defaultCss === false) {
            $defaultCss = '';
        }

        $pdf->writeHTML(
            $printable
            . '<style>'
            . $defaultCss
            . '</style>'
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

        /*
         * Direkt TCPDF verwenden, da SuiteCRMs PDFWrapper
         * PDF/A-3 nicht aktiviert.
         */
        $pdf->Output($pdfFile, 'F');

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

    private function createPdfA3($template): ZugferdTCPDF
    {
        /*
         * Letzter Konstruktorparameter = 3 aktiviert PDF/A-3.
         */
        $pdf = new ZugferdTCPDF(
            (string)$template->orientation,
            'mm',
            (string)$template->page_size,
            true,
            'UTF-8',
            false,
            3
        );

        $pdf->SetMargins(
            (float)$template->margin_left,
            (float)$template->margin_top,
            (float)$template->margin_right
        );

        $pdf->setHtmlVSpace([
            'div' => [
                ['h' => 0, 'n' => 0],
                ['h' => 0, 'n' => 0],
            ],
        ]);

        $pdf->setHeaderMargin(
            (float)$template->margin_header
        );

        $pdf->setFooterMargin(
            (float)$template->margin_footer
        );

        $pdf->SetAutoPageBreak(
            true,
            (float)$template->margin_bottom
        );

        $pdf->setImageScale(1.25);

        /*
         * TrueTypeUnicode-Schrift, damit sie im PDF/A eingebettet wird.
         */
        $pdf->SetFont(
            'dejavusanscondensed',
            '',
            10
        );

        $pdf->setHeaderFont([
            'dejavusanscondensed',
            '',
            10,
        ]);

        $pdf->setFooterFont([
            'dejavusanscondensed',
            '',
            10,
        ]);

        return $pdf;
    }

    /**
     * Ergänzt Factur-X/ZUGFeRD-XMP-Metadaten für Profil EN16931.
     */
    private function addFacturXXmp(
        ZugferdTCPDF $pdf
    ): void {
        $namespace =
            'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';

        $rdf = <<<XML
<rdf:Description rdf:about=""
    xmlns:fx="{$namespace}">
    <fx:DocumentType>INVOICE</fx:DocumentType>
    <fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>
    <fx:Version>1.0</fx:Version>
    <fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>
</rdf:Description>
XML;

        $pdf->setExtraXMPRDF($rdf);

        /*
         * PDF/A Extension Schema für die vier Factur-X-Felder.
         * TCPDF fügt diesen Block in pdfaExtension:schemas/rdf:Bag ein.
         */
        $schema = <<<XML
<rdf:li rdf:parseType="Resource">
    <pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>
    <pdfaSchema:namespaceURI>{$namespace}</pdfaSchema:namespaceURI>
    <pdfaSchema:prefix>fx</pdfaSchema:prefix>
    <pdfaSchema:property>
        <rdf:Seq>
            <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>DocumentFileName</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>Name of the embedded XML invoice file</pdfaProperty:description>
            </rdf:li>
            <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>DocumentType</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>Type of the hybrid document</pdfaProperty:description>
            </rdf:li>
            <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>Version</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>Factur-X schema version</pdfaProperty:description>
            </rdf:li>
            <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>ConformanceLevel</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>Factur-X conformance level</pdfaProperty:description>
            </rdf:li>
        </rdf:Seq>
    </pdfaSchema:property>
</rdf:li>
XML;

        $pdf->setExtraXMPPdfaextension($schema);
    }
}
