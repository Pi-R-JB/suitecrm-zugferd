<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class RevenueAmountHook
{
    public function calculate($bean, $event, $arguments): void
    {
        $documentType = trim(
            (string)($bean->zugferd_document_type_c ?? 'invoice')
        );

        $factor = ($documentType === 'cancellation') ? -1 : 1;

        $totalAmount = (float)($bean->total_amount ?? 0);
        $totalAmountUsdollar = (float)($bean->total_amount_usdollar ?? 0);

        $bean->revenue_amount_c =
            $factor * abs($totalAmount);

        $bean->revenue_amount_usdollar_c =
            $factor * abs($totalAmountUsdollar);
    }
}
