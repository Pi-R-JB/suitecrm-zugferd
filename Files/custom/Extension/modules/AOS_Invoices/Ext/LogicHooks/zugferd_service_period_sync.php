<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$hook_array['before_save'][] = [
    25,
    'Copy service period from linked quotation',
    'custom/modules/AOS_Invoices/Hooks/ServicePeriodSyncHook.php',
    'ServicePeriodSyncHook',
    'beforeSave',
];

$hook_array['after_relationship_add'][] = [
    25,
    'Copy service period when quotation is linked',
    'custom/modules/AOS_Invoices/Hooks/ServicePeriodSyncHook.php',
    'ServicePeriodSyncHook',
    'afterRelationshipAdd',
];
