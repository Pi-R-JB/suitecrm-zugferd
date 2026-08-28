<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class ZugferdDocumentValidationHook
{
    public function validate($bean, $event, $arguments): void
    {
        $documentType = trim(
            (string)($bean->zugferd_document_type_c ?? 'invoice')
        );

        $originalInvoiceId = trim(
            (string)($bean->original_invoice_id_c ?? '')
        );

        /*
         * Storno und Ersatzrechnung benötigen zwingend
         * eine Ursprungsrechnung.
         */
        if (
            in_array(
                $documentType,
                ['cancellation', 'replacement'],
                true
            ) &&
            $originalInvoiceId === ''
        ) {
            throw new RuntimeException(
                'Für Storno und Ersatzrechnung muss eine Ursprungsrechnung ausgewählt werden.'
            );
        }

        /*
         * Eine Rechnung darf sich nicht selbst referenzieren.
         */
        if (
            $originalInvoiceId !== '' &&
            !empty($bean->id) &&
            $originalInvoiceId === (string)$bean->id
        ) {
            throw new RuntimeException(
                'Eine Rechnung kann nicht ihre eigene Ursprungsrechnung sein.'
            );
        }

        /*
         * Wenn eine Ursprungsrechnung angegeben wurde,
         * muss sie tatsächlich existieren.
         */
        if ($originalInvoiceId !== '') {
            $originalInvoice = BeanFactory::getBean(
                'AOS_Invoices',
                $originalInvoiceId
            );

            if (
                !$originalInvoice ||
                empty($originalInvoice->id)
            ) {
                throw new RuntimeException(
                    'Die ausgewählte Ursprungsrechnung wurde nicht gefunden.'
                );
            }
        }
    }
}
