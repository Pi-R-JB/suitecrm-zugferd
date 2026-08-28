<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

final class ServicePeriodSyncHook
{
    private static bool $syncing = false;

    public function beforeSave($bean, string $event, array $arguments = []): void
    {
        if (self::$syncing) {
            return;
        }

        $this->copyFromLinkedQuote($bean);
    }

    public function afterRelationshipAdd($bean, string $event, array $arguments = []): void
    {
        if (self::$syncing) {
            return;
        }

        if (($arguments['related_module'] ?? '') !== 'AOS_Quotes') {
            return;
        }

        if (!$this->copyFromLinkedQuote($bean)) {
            return;
        }

        self::$syncing = true;

        try {
            $bean->save();
        } finally {
            self::$syncing = false;
        }
    }

    private function copyFromLinkedQuote($bean): bool
    {
        if (!$bean || ($bean->module_dir ?? '') !== 'AOS_Invoices') {
            return false;
        }

        if (
            empty($bean->field_defs['beginn_c']) ||
            empty($bean->field_defs['ende_c'])
        ) {
            return false;
        }

        if (!$bean->load_relationship('aos_quotes_aos_invoices')) {
            return false;
        }

        $quoteBeans = $bean->aos_quotes_aos_invoices->getBeans();

        if (!$quoteBeans) {
            return false;
        }

        $quoteBean = reset($quoteBeans);

        if (!$quoteBean || empty($quoteBean->id)) {
            return false;
        }

        $beginn = $this->normalizeDateToDb((string)($quoteBean->beginn_c ?? ''));
        $ende = $this->normalizeDateToDb((string)($quoteBean->ende_c ?? ''));

        if ($beginn === '' || $ende === '') {
            return false;
        }

        $changed = false;

        if ((string)($bean->beginn_c ?? '') !== $beginn) {
            $bean->beginn_c = $beginn;
            $changed = true;
        }

        if ((string)($bean->ende_c ?? '') !== $ende) {
            $bean->ende_c = $ende;
            $changed = true;
        }

        return $changed;
    }

    private function normalizeDateToDb(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '0000-00-00') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        global $timedate;

        if (!$timedate) {
            return '';
        }

        $dbDate = $timedate->to_db_date($value, false);

        if (!is_string($dbDate) || $dbDate === '' || $dbDate === '0000-00-00') {
            return '';
        }

        return $dbDate;
    }
}
