<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_array['before_save'][] = [
    20,
    'Calculate ZUGFeRD revenue amount',
    'custom/modules/AOS_Invoices/Hooks/RevenueAmountHook.php',
    'RevenueAmountHook',
    'calculate',
];
