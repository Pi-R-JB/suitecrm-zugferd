<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$recordId = $_REQUEST['record'] ?? '';

if ($recordId === '') {
    sugar_die('Keine Rechnungs-ID übergeben.');
}

$invoice = BeanFactory::getBean('AOS_Invoices', $recordId);

if (!$invoice || empty($invoice->id)) {
    sugar_die('Rechnung wurde nicht gefunden.');
}

if (!$invoice->ACLAccess('view')) {
    sugar_die('Keine Berechtigung für diese Rechnung.');
}

try {
    require_once
        'custom/modules/AOS_Invoices/zugferd/ZugferdPdfService.php';

    $service = new ZugferdPdfService();
    $result = $service->generate($recordId);

    $pdfFile = (string)$result['pdf_file'];

    if (
        !is_file($pdfFile) ||
        !is_readable($pdfFile) ||
        filesize($pdfFile) < 1
    ) {
        throw new RuntimeException(
            'Die erzeugte ZUGFeRD-PDF konnte nicht gelesen werden.'
        );
    }

    $invoiceNumber = preg_replace(
        '/[^A-Za-z0-9_-]/',
        '_',
        (string)$result['invoice_number']
    );

    $customerName = trim((string)$result['customer']);

    $customerName = preg_replace(
        '/[^A-Za-z0-9ÄÖÜäöüß_-]+/u',
        '-',
        $customerName
    );

    $customerName = trim($customerName, '-_');

    if ($customerName === '') {
        $customerName = 'Kunde';
    }

    $grossAmount = number_format(
        (float)$result['gross_total'],
        2,
        ',',
        ''
    );

    $downloadName =
        $invoiceNumber .
        '_' .
        $customerName .
        '_' .
        $grossAmount .
        '.pdf';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: attachment; filename="' .
        $downloadName .
        '"'
    );

    header('Content-Length: ' . filesize($pdfFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    readfile($pdfFile);
    exit;

} catch (Throwable $e) {
    LoggerManager::getLogger()->error(
        'ZUGFeRD PDF error for invoice ' .
        $recordId .
        ': ' .
        $e->getMessage()
    );

    SugarApplication::appendErrorMessage(
        'ZUGFeRD-Fehler: ' . $e->getMessage()
    );

    SugarApplication::redirect(
        'index.php?module=AOS_Invoices' .
        '&action=DetailView' .
        '&record=' .
        urlencode($recordId)
    );

    exit;
}
