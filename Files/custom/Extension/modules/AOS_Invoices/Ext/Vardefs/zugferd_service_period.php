<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$dictionary['AOS_Invoices']['fields']['beginn_c'] = [
    'name' => 'beginn_c',
    'vname' => 'LBL_SERVICE_PERIOD_START',
    'type' => 'date',
    'source' => 'custom_fields',
    'module' => 'AOS_Invoices',
    'massupdate' => false,
    'duplicate_merge' => 'enabled',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => true,
];

$dictionary['AOS_Invoices']['fields']['ende_c'] = [
    'name' => 'ende_c',
    'vname' => 'LBL_SERVICE_PERIOD_END',
    'type' => 'date',
    'source' => 'custom_fields',
    'module' => 'AOS_Invoices',
    'massupdate' => false,
    'duplicate_merge' => 'enabled',
    'required' => false,
    'reportable' => true,
    'audited' => false,
    'importable' => true,
];
