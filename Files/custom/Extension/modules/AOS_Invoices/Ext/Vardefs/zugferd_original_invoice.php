<?php

$dictionary['AOS_Invoices']['fields']['original_invoice_id_c'] = [
    'name' => 'original_invoice_id_c',
    'vname' => 'LBL_ORIGINAL_INVOICE_ID',
    'type' => 'id',
    'reportable' => false,
    'source' => 'custom_fields',
];

$dictionary['AOS_Invoices']['fields']['original_invoice_name_c'] = [
    'name' => 'original_invoice_name_c',
    'vname' => 'LBL_ORIGINAL_INVOICE',
    'type' => 'relate',
    'source' => 'non-db',
    'module' => 'AOS_Invoices',
    'bean_name' => 'AOS_Invoices',
    'rname' => 'number',
    'id_name' => 'original_invoice_id_c',
    'table' => 'aos_invoices',
    'link' => false,
    'massupdate' => false,
    'studio' => 'visible',
];
