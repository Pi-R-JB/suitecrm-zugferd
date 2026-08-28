<?php

declare(strict_types=1);

use SuiteCRM\PDF\TCPDF\SuiteTCPDF;

/**
 * TCPDF-Erweiterung für ZUGFeRD / Factur-X.
 *
 * TCPDF 6.10.1 legt eingebettete Dateien bereits im /AF-Katalog ab,
 * verwendet aber fest /AFRelationship /Source.
 * Für die XML-Sicht einer hybriden ZUGFeRD-Rechnung wird /Alternative verwendet.
 */
final class ZugferdTCPDF extends SuiteTCPDF
{
    /**
     * Schreibt die eingebetteten Dateien mit /AFRelationship /Alternative.
     *
     * Basierend auf TCPDF 6.10.1::_putEmbeddedFiles(), ausschließlich
     * mit geänderter AFRelationship.
     */
    protected function _putEmbeddedFiles()
    {
        if ($this->pdfa_mode && $this->pdfa_version != 3) {
            return;
        }

        reset($this->embeddedfiles);

        foreach ($this->embeddedfiles as $filename => $filedata) {
            $data = false;

            if (isset($filedata['file']) && !empty($filedata['file'])) {
                $data = $this->getCachedFileContents($filedata['file']);
            } elseif (
                isset($filedata['content']) &&
                !empty($filedata['content'])
            ) {
                $data = $filedata['content'];
            }

            if ($data === false) {
                continue;
            }

            $rawsize = strlen($data);

            if ($rawsize <= 0) {
                continue;
            }

            // Referenz im Names-Tree und später automatisch im /AF-Katalog.
            $this->efnames[$filename] = $filedata['f'] . ' 0 R';

            // File specification object.
            $out = $this->_getobj($filedata['f']) . "\n";
            $out .= '<</Type /Filespec';
            $out .= ' /F ' . $this->_datastring(
                $filename,
                $filedata['f']
            );
            $out .= ' /UF ' . $this->_datastring(
                $filename,
                $filedata['f']
            );

            // ZUGFeRD/Factur-X: XML und PDF sind alternative Darstellungen
            // derselben Rechnung.
            $out .= ' /AFRelationship /Alternative';

            $out .= ' /EF <</F ' . $filedata['n'] . ' 0 R>> >>';
            $out .= "\nendobj";

            $this->_out($out);

            $filter = '';

            if ($this->compress) {
                $data = gzcompress($data);
                $filter .= ' /Filter /FlateDecode';
            }

            if ($this->pdfa_version == 3) {
                $filter .= ' /Subtype /text#2Fxml';
            }

            $stream = $this->_getrawstream(
                $data,
                $filedata['n']
            );

            $out = $this->_getobj($filedata['n']) . "\n";
            $out .= '<< /Type /EmbeddedFile'
                . $filter
                . ' /Length '
                . strlen($stream)
                . ' /Params <</Size '
                . $rawsize
                . '>> >>';

            $out .= " stream\n"
                . $stream
                . "\nendstream\nendobj";

            $this->_out($out);
        }
    }
}
