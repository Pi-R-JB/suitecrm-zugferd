<?php

declare(strict_types=1);

final class PRK_ZugferdConfig
{
    private const CATEGORY = 'prk_zugferd';

    public static function get(): array
    {
        $admin = BeanFactory::newBean('Administration');
        $admin->retrieveSettings(self::CATEGORY);

        $get = static function (string $name, string $default = '') use ($admin): string {
            $key = self::CATEGORY . '_' . $name;

            if (!isset($admin->settings[$key])) {
                return $default;
            }

            return trim((string)$admin->settings[$key]);
        };

        return array(
            'seller' => array(
                'name' => $get('seller_name'),
                'street' => $get('seller_street'),
                'postcode' => $get('seller_postcode'),
                'city' => $get('seller_city'),
                'country' => strtoupper($get('seller_country', 'DE')),
                'vat_id' => $get('seller_vat_id'),
                'tax_number' => $get('seller_tax_number'),
                'iban' => preg_replace('/\s+/', '', $get('seller_iban')),
                'bic' => preg_replace('/[\s-]+/', '', $get('seller_bic')),
                'email' => $get('seller_email'),
            ),
            'invoice' => array(
                'currency' => strtoupper($get('currency', 'EUR')),
                'default_unit_code' => strtoupper($get('default_unit_code', 'C62')),
                'payment_terms' => $get(
                    'payment_terms',
                    'Zahlbar ohne Abzug bis zum Fälligkeitsdatum.'
                ),
            ),
        );
    }

    public static function save(array $values): void
    {
        $admin = BeanFactory::newBean('Administration');

        foreach ($values as $name => $value) {
            $admin->saveSetting(
                self::CATEGORY,
                $name,
                trim((string)$value
                )
            );
        }
    }

    public static function validate(array $config): void
    {
        $required = array(
            array('seller', 'name', 'Firmenname'),
            array('seller', 'street', 'Straße'),
            array('seller', 'postcode', 'PLZ'),
            array('seller', 'city', 'Ort'),
            array('seller', 'country', 'Land'),
            array('seller', 'vat_id', 'USt-ID'),
            array('seller', 'iban', 'IBAN'),
            array('invoice', 'currency', 'Währung'),
            array('invoice', 'default_unit_code', 'Standard-Mengeneinheit'),
            array('invoice', 'payment_terms', 'Zahlungsbedingung'),
        );

        foreach ($required as $definition) {
            [$group, $field, $label] = $definition;

            if (
                !isset($config[$group][$field]) ||
                trim((string)$config[$group][$field]) === ''
            ) {
                throw new RuntimeException(
                    'ZUGFeRD-Einstellung fehlt: ' . $label
                );
            }
        }

        if (!preg_match('/^[A-Z]{2}$/', (string)$config['seller']['country'])) {
            throw new RuntimeException(
                'Land muss als ISO-3166-1-Alpha-2-Code angegeben werden, z. B. DE.'
            );
        }

        if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', (string)$config['seller']['iban'])) {
            throw new RuntimeException(
                'IBAN hat kein plausibles Format.'
            );
        }
    }
}
