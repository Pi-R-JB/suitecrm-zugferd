<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class PRK_ZugferdUiHook
{
    public function addInvoiceButton($event, $arguments): void
    {
        $module = (string)($_REQUEST['module'] ?? '');
        $action = (string)($_REQUEST['action'] ?? '');

        if (
            $module !== 'AOS_Invoices' ||
            $action !== 'DetailView'
        ) {
            return;
        }

        $recordId = trim((string)($_REQUEST['record'] ?? ''));

        if ($recordId === '') {
            return;
        }

        $recordIdJs = json_encode(
            $recordId,
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        );

        echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('input.button');

    for (var i = 0; i < buttons.length; i++) {
        var button = buttons[i];
        var handler = button.getAttribute('onclick') || '';

        if (
            handler.indexOf("showPopup('pdf')") === -1 &&
            handler.indexOf('showPopup(\\'pdf\\')') === -1 &&
            handler.indexOf('showPopup("pdf")') === -1
        ) {
            continue;
        }

        button.value = 'ZUGFeRD-PDF';

        button.onclick = function () {
            window.location.href =
                'index.php?entryPoint=prkZugferdDownload&record=' +
                encodeURIComponent({$recordIdJs});

            return false;
        };

        break;
    }
});
</script>
HTML;
    }
}
