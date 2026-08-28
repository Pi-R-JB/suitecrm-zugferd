<?php

$admin_option_defs = array();

$admin_option_defs['Administration']['prk_zugferd_settings'] = array(
    'Administration',
    'LBL_PRK_ZUGFERD_SETTINGS',
    'LBL_PRK_ZUGFERD_SETTINGS_DESC',
    './index.php?entryPoint=prkZugferdSettings',
);

$admin_group_header[] = array(
    'LBL_PRK_ZUGFERD_ADMIN_GROUP',
    '',
    false,
    $admin_option_defs,
    'LBL_PRK_ZUGFERD_ADMIN_GROUP_DESC'
);
