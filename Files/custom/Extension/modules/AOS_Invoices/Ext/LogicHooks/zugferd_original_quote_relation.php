<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_array['after_save'][] = [
    30,
    'Copy quotation relation from original invoice',
    'custom/modules/AOS_Invoices/Hooks/OriginalQuoteRelationHook.php',
    'OriginalQuoteRelationHook',
    'afterSave',
];
