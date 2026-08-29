<?php

declare(strict_types=1);

if (!defined('K_PATH_FONTS')) {
    define(
        'K_PATH_FONTS',
        dirname(__DIR__, 4)
        . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/'
    );
}

use Com\Tecnick\Pdf\AFRelationship;
use Com\Tecnick\Pdf\Tcpdf;

final class ZugferdTcLibPdf extends Tcpdf
{
    private string $htmlHeader = '';

    private string $htmlFooter = '';

    private float $headerMargin = 9.0;

    private float $footerMargin = 9.0;

    public function __construct()
    {
        $crmRoot = dirname(__DIR__, 4);

        parent::__construct(
            unit: 'mm',
            isunicode: true,
            subsetfont: true,
            compress: true,
            mode: 'pdfa3',
            fileOptions: [
                'allowedPaths' => [
                    $crmRoot,
                ],
            ]
        );

        $this->enableDefaultPageContent();
    }

    public function getHtmlHeader(): string
    {
        return $this->htmlHeader;
    }

    public function setHtmlHeader(string $htmlHeader): void
    {
        $this->htmlHeader = $htmlHeader;
    }

    public function getHtmlFooter(): string
    {
        return $this->htmlFooter;
    }

    public function setHtmlFooter(string $htmlFooter): void
    {
        $htmlFooter = preg_replace_callback(
            '/{DATE\s+(.*?)}/',
            static function (array $matches): string {
                return date($matches[1]);
            },
            $htmlFooter
        ) ?? $htmlFooter;

        $this->htmlFooter = $htmlFooter;
    }

    public function setHeaderMargin(float $margin): void
    {
        $this->headerMargin = $margin;
    }

    public function setFooterMargin(float $margin): void
    {
        $this->footerMargin = $margin;
    }

    /**
     * Wiederkehrender Seiteninhalt.
     *
     * Wird von tc-lib automatisch bei jeder neu angelegten Seite
     * ausgeführt und direkt in deren Content-Stream eingefügt.
     */
    public function defaultPageContent(int $pid = -1): string
    {
        $page = $this->page->getPage($pid);
        $pageNumber = ((int)$page['pid']) + 1;

        $out = '';

        /*
         * Region 0 = Header
         */
        if ($this->htmlHeader !== '') {
            $this->page->selectRegion(0, $pid);
            $region = $this->page->getRegion($pid);

            $out .= $this->getHTMLCell(
                html: $this->htmlHeader,
                posx: (float)$region['RX'],
                posy: (float)$region['RY'],
                width: (float)$region['RW'],
                height: (float)$region['RH']
            );
        }

        /*
         * Region 1 = Footer
         */
        if ($this->htmlFooter !== '') {
            $this->page->selectRegion(1, $pid);
            $region = $this->page->getRegion($pid);

            $footer = str_replace(
                '{PAGENO}',
                (string)$pageNumber,
                $this->htmlFooter
            );

            $out .= $this->getHTMLCell(
                html: $footer,
                posx: (float)$region['RX'],
                posy: (float)$region['RY'],
                width: (float)$region['RW'],
                height: (float)$region['RH']
            );
        }

        /*
         * Region 2 = Body.
         *
         * Wichtig: Der Body ist absichtlich die letzte Region.
         * Bei einem Überlauf erzeugt tc-lib deshalb unmittelbar
         * eine Folgeseite, statt in Header oder Footer zu laufen.
         */
        $this->page->selectRegion(2, $pid);

        return $out;
    }

    public function embedFacturX(string $xml): void
    {
        $this->addContentAsEmbeddedFile(
            file: 'factur-x.xml',
            content: $xml,
            mime: 'text/xml',
            afrel: AFRelationship::Alternative,
            desc: 'Factur-X/ZUGFeRD electronic invoice'
        );
    }

    public function addFacturXXmp(): void
    {
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

        $this->setCustomXMP(
            'x:xmpmeta.rdf:RDF',
            $rdf
        );

        $this->setCustomXMP(
            'x:xmpmeta.rdf:RDF.rdf:Description.pdfaExtension:schemas.rdf:Bag',
            $schema
        );
    }
}
