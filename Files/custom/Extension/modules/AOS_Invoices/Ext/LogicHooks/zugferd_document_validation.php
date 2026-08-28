<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_array['before_save'][] = [
    10,
    'Validate ZUGFeRD document type',
    'custom/modules/AOS_Invoices/Hooks/ZugferdDocumentValidationHook.php',
    'ZugferdDocumentValidationHook',
    'validate',
];
