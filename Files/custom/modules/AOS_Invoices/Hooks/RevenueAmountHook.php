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

        $totalAmount =
            (float)($bean->total_amount ?? 0);

        $totalAmountUsdollar =
            (float)($bean->total_amount_usdollar ?? 0);

        switch ($documentType) {
            case 'cancellation':
                $bean->revenue_amount_c =
                    -abs($totalAmount);

                $bean->revenue_amount_usdollar_c =
                    -abs($totalAmountUsdollar);
                break;

            case 'replacement':
                /*
                 * Eine Korrekturrechnung stellt nur die Differenz
                 * zur Ursprungsrechnung dar.
                 *
                 * Positive Summe  = zusätzliche Forderung
                 * Negative Summe  = Entlastung des Kunden
                 */
                $bean->revenue_amount_c =
                    $totalAmount;

                $bean->revenue_amount_usdollar_c =
                    $totalAmountUsdollar;
                break;

            default:
                $bean->revenue_amount_c =
                    abs($totalAmount);

                $bean->revenue_amount_usdollar_c =
                    abs($totalAmountUsdollar);
                break;
        }
    }
}
