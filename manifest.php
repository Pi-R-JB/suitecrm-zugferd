<?php

$manifest = array(
    'name' => 'ZUGFeRD for AOS Invoices',
    'description' => 'ZUGFeRD / Factur-X PDF/A-3b mit EN16931 XML für SuiteCRM AOS_Invoices.',
    'version' => '0.2.0-beta.1',
    'author' => 'Pierre Rohr',
    'readme' => 'README.txt',
    'acceptable_sugar_flavors' => array('CE'),
    'acceptable_sugar_versions' => array(
        'exact_matches' => array(),
        'regex_matches' => array('6\.5\..*'),
    ),
    'is_uninstallable' => true,
    'published_date' => '2026-08-28',
    'type' => 'module',
    'remove_tables' => false,
);

$installdefs = array(
    'id' => 'prk_zugferd_aos_invoices',

    'copy' => array(
        array(
            'from' => '<basepath>/Files/custom/modules/AOS_Invoices/zugferd',
            'to' => 'custom/modules/AOS_Invoices/zugferd',
        ),
        array(
            'from' => '<basepath>/Files/custom/modules/AOS_Invoices/Hooks/ZugferdDocumentValidationHook.php',
            'to' => 'custom/modules/AOS_Invoices/Hooks/ZugferdDocumentValidationHook.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/modules/AOS_Invoices/Hooks/RevenueAmountHook.php',
            'to' => 'custom/modules/AOS_Invoices/Hooks/RevenueAmountHook.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/modules/PRK_Zugferd',
            'to' => 'custom/modules/PRK_Zugferd',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/application/Ext/EntryPointRegistry/PRK_Zugferd.php',
            'to' => 'custom/Extension/application/Ext/EntryPointRegistry/PRK_Zugferd.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/application/Ext/Composer/PRK_Zugferd/AddToComposer.json',
            'to' => 'custom/Extension/application/Ext/Composer/PRK_Zugferd/AddToComposer.json',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_document_type_c.php',
            'to' => 'custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_document_type_c.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_original_invoice.php',
            'to' => 'custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_original_invoice.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_revenue_amount.php',
            'to' => 'custom/Extension/modules/AOS_Invoices/Ext/Vardefs/zugferd_revenue_amount.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/modules/AOS_Invoices/Ext/LogicHooks/zugferd_revenue_amount.php',
            'to' => 'custom/Extension/modules/AOS_Invoices/Ext/LogicHooks/zugferd_revenue_amount.php',
        ),
        array(
            'from' => '<basepath>/Files/custom/Extension/modules/AOS_Invoices/Ext/LogicHooks/zugferd_document_validation.php',
            'to' => 'custom/Extension/modules/AOS_Invoices/Ext/LogicHooks/zugferd_document_validation.php',
        ),
    ),

    'language' => array(
        array(
            'from' => '<basepath>/language/application/de_DE.prk_zugferd.php',
            'to_module' => 'application',
            'language' => 'de_DE',
        ),
        array(
            'from' => '<basepath>/language/application/en_us.prk_zugferd.php',
            'to_module' => 'application',
            'language' => 'en_us',
        ),
        array(
            'from' => '<basepath>/language/AOS_Invoices/de_DE.prk_zugferd.php',
            'to_module' => 'AOS_Invoices',
            'language' => 'de_DE',
        ),
        array(
            'from' => '<basepath>/language/AOS_Invoices/en_us.prk_zugferd.php',
            'to_module' => 'AOS_Invoices',
            'language' => 'en_us',
        ),
    ),

    'logic_hooks' => array(
        array(
            'module' => '',
            'hook' => 'after_ui_frame',
            'order' => 90,
            'description' => 'Replace AOS invoice PDF action with ZUGFeRD PDF generation',
            'file' => 'custom/modules/PRK_Zugferd/Hooks/UiHook.php',
            'class' => 'PRK_ZugferdUiHook',
            'function' => 'addInvoiceButton',
        ),
    ),

    'administration' => array(
        array(
            'from' => '<basepath>/administration/prk_zugferd_admin.php',
        ),
    ),

    'pre_execute' => array(
        '<basepath>/scripts/pre_install.php',
    ),

    'post_uninstall' => array(
        '<basepath>/scripts/post_uninstall.php',
    ),
);
