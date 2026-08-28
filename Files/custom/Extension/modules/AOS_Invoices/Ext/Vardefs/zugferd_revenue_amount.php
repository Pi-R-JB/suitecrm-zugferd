<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/*
 * Umsatzwirksame Rechnungsbeträge für Auswertungen.
 *
 * Die eigentlichen Rechnungsbeträge bleiben unverändert.
 *
 * Normale Rechnung:
 *     positiver Betrag
 *
 * Stornorechnung:
 *     negativer Betrag
 */

$dictionary['AOS_Invoices']['fields']['revenue_amount_c'] = [
    'name' => 'revenue_amount_c',
    'vname' => 'LBL_REVENUE_AMOUNT',
    'type' => 'currency',
    'source' => 'custom_fields',
    'required' => false,
    'massupdate' => 0,
    'audited' => false,
    'reportable' => true,
    'len' => '26,6',
    'studio' => [
        'editview' => false,
        'detailview' => false,
        'quickcreate' => false,
    ],
];

$dictionary['AOS_Invoices']['fields']['revenue_amount_usdollar_c'] = [
    'name' => 'revenue_amount_usdollar_c',
    'vname' => 'LBL_REVENUE_AMOUNT_USDOLLAR',
    'type' => 'currency',
    'source' => 'custom_fields',
    'group' => 'revenue_amount_c',
    'disable_num_format' => true,
    'audited' => false,
    'reportable' => true,
    'len' => '26,6',
    'studio' => [
        'editview' => false,
        'detailview' => false,
        'quickcreate' => false,
    ],
];
