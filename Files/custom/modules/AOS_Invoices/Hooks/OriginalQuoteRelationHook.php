<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

final class OriginalQuoteRelationHook
{
    private static bool $running = false;

    public function afterSave($bean, $event, $arguments = null): void
    {
        if (self::$running) {
            return;
        }

        $documentType = trim(
            (string)($bean->zugferd_document_type_c ?? 'invoice')
        );

        if (
            !in_array(
                $documentType,
                ['cancellation', 'replacement'],
                true
            )
        ) {
            return;
        }

        $originalInvoiceId = trim(
            (string)($bean->original_invoice_id_c ?? '')
        );

        if ($originalInvoiceId === '') {
            return;
        }

        $originalInvoice = BeanFactory::getBean(
            'AOS_Invoices',
            $originalInvoiceId
        );

        if (
            !$originalInvoice ||
            empty($originalInvoice->id)
        ) {
            return;
        }

        if (
            !$originalInvoice->load_relationship(
                'aos_quotes_aos_invoices'
            )
        ) {
            return;
        }

        $originalQuotes =
            $originalInvoice
                ->aos_quotes_aos_invoices
                ->getBeans();

        if (!$originalQuotes) {
            return;
        }

        if (
            !$bean->load_relationship(
                'aos_quotes_aos_invoices'
            )
        ) {
            return;
        }

        $existingIds =
            $bean
                ->aos_quotes_aos_invoices
                ->get();

        if (!is_array($existingIds)) {
            $existingIds = [];
        }

        self::$running = true;

        try {
            foreach ($originalQuotes as $quote) {
                if (empty($quote->id)) {
                    continue;
                }

                $quoteId = (string)$quote->id;

                if (in_array($quoteId, $existingIds, true)) {
                    continue;
                }

                $bean
                    ->aos_quotes_aos_invoices
                    ->add($quoteId);
            }
        } finally {
            self::$running = false;
        }
    }
}
