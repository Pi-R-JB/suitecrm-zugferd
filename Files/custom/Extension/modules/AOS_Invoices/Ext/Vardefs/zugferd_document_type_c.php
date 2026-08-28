<?php

$dictionary['AOS_Invoices']['fields']['zugferd_document_type_c'] = [
    'name' => 'zugferd_document_type_c',
    'vname' => 'LBL_ZUGFERD_DOCUMENT_TYPE',
    'type' => 'enum',
    'options' => 'zugferd_document_type_list',
    'len' => 100,
    'default' => 'invoice',
    'massupdate' => 0,
    'audited' => true,
    'inline_edit' => true,
    'merge_filter' => 'disabled',
    'reportable' => true,
    'studio' => 'visible',
    'source' => 'custom_fields',
];
