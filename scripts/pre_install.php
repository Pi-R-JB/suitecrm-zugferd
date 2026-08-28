<?php

function pre_install()
{
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        die('ZUGFeRD for AOS Invoices requires PHP 8.1 or newer.');
    }

    if (!extension_loaded('dom')) {
        die('ZUGFeRD for AOS Invoices requires PHP ext-dom.');
    }

    if (!is_dir('modules/AOS_Invoices')) {
        die('ZUGFeRD for AOS Invoices requires AOS_Invoices.');
    }

    if (!is_dir('modules/AOS_PDF_Templates')) {
        die('ZUGFeRD for AOS Invoices requires AOS_PDF_Templates.');
    }

    if (!is_file('lib/PDF/TCPDF/SuiteTCPDF.php')) {
        die('ZUGFeRD for AOS Invoices requires SuiteCRM TCPDF.');
    }

    echo 'ZUGFeRD for AOS Invoices pre-install checks passed.<br>';
}
