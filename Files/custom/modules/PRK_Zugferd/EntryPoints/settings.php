<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

global $current_user;

if (!is_admin($current_user)) {
    sugar_die('Administrator access required.');
}

require_once 'custom/modules/PRK_Zugferd/Config.php';

function prk_zugferd_h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['prk_zugferd_save'])) {
    $allowed = array(
        'seller_name',
        'seller_street',
        'seller_postcode',
        'seller_city',
        'seller_country',
        'seller_vat_id',
        'seller_tax_number',
        'seller_iban',
        'seller_bic',
        'seller_email',
        'currency',
        'default_unit_code',
        'payment_terms',
    );

    $values = array();

    foreach ($allowed as $field) {
        $values[$field] = isset($_POST[$field])
            ? trim((string)$_POST[$field])
            : '';
    }

    PRK_ZugferdConfig::save($values);

    SugarApplication::appendSuccessMessage(
        'ZUGFeRD-Einstellungen wurden gespeichert.'
    );

    SugarApplication::redirect(
        'index.php?entryPoint=prkZugferdSettings'
    );
    exit;
}

$config = PRK_ZugferdConfig::get();

$countries = array(
    'DE' => 'Deutschland',
    'AT' => 'Österreich',
    'CH' => 'Schweiz',
    'BE' => 'Belgien',
    'DK' => 'Dänemark',
    'ES' => 'Spanien',
    'FI' => 'Finnland',
    'FR' => 'Frankreich',
    'GB' => 'Vereinigtes Königreich',
    'IE' => 'Irland',
    'IT' => 'Italien',
    'LU' => 'Luxemburg',
    'NL' => 'Niederlande',
    'NO' => 'Norwegen',
    'PL' => 'Polen',
    'PT' => 'Portugal',
    'SE' => 'Schweden',
);

$currencies = array(
    'EUR' => 'EUR – Euro',
    'CHF' => 'CHF – Schweizer Franken',
    'GBP' => 'GBP – Britisches Pfund',
    'USD' => 'USD – US-Dollar',
);

$units = array(
    'C62' => 'C62 – Stück',
    'HUR' => 'HUR – Stunde',
    'DAY' => 'DAY – Tag',
    'MON' => 'MON – Monat',
    'KGM' => 'KGM – Kilogramm',
    'MTR' => 'MTR – Meter',
);

function prk_zugferd_select(
    string $name,
    array $options,
    string $selected
): string {
    $html = '<select name="' . prk_zugferd_h($name) . '" class="prk-zf-input">';

    foreach ($options as $value => $label) {
        $isSelected = ((string)$value === $selected)
            ? ' selected'
            : '';

        $html .= '<option value="' .
            prk_zugferd_h((string)$value) .
            '"' .
            $isSelected .
            '>' .
            prk_zugferd_h((string)$label) .
            '</option>';
    }

    $html .= '</select>';

    return $html;
}

?>

<style>
.prk-zf-page {
    max-width: 1120px;
    margin: 0 0 40px 0;
    font-family: inherit;
}

.prk-zf-intro {
    margin: 4px 0 24px 0;
    color: #666;
    font-size: 14px;
}

.prk-zf-card {
    background: #fff;
    border: 1px solid #d8d8d8;
    border-radius: 3px;
    margin-bottom: 18px;
    overflow: hidden;
}

.prk-zf-card-title {
    background: #f5f5f5;
    border-bottom: 1px solid #d8d8d8;
    padding: 11px 16px;
    font-size: 15px;
    font-weight: 600;
    color: #444;
}

.prk-zf-card-body {
    padding: 18px 20px 20px 20px;
}

.prk-zf-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 17px 24px;
}

.prk-zf-field {
    min-width: 0;
}

.prk-zf-field-full {
    grid-column: 1 / -1;
}

.prk-zf-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #444;
}

.prk-zf-required {
    color: #c43b3b;
    margin-left: 3px;
}

.prk-zf-hint {
    margin-top: 5px;
    color: #888;
    font-size: 12px;
    line-height: 1.4;
}

.prk-zf-input {
    box-sizing: border-box;
    width: 100%;
    min-height: 34px;
    padding: 6px 9px;
    border: 1px solid #aaa;
    border-radius: 3px;
    background: #fff;
    color: #333;
}

textarea.prk-zf-input {
    min-height: 82px;
    resize: vertical;
}

.prk-zf-input:focus {
    border-color: #777;
    outline: none;
    box-shadow: 0 0 0 1px rgba(0,0,0,.08);
}

.prk-zf-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid #ddd;
}

.prk-zf-save {
    min-width: 120px;
}

.prk-zf-info {
    border-left: 4px solid #888;
    background: #f7f7f7;
    padding: 11px 14px;
    margin-bottom: 20px;
    color: #555;
    line-height: 1.45;
}

@media (max-width: 800px) {
    .prk-zf-grid {
        grid-template-columns: 1fr;
    }

    .prk-zf-field-full {
        grid-column: auto;
    }
}
</style>

<div class="moduleTitle">
    <h2>ZUGFeRD Einstellungen</h2>
</div>

<div class="prk-zf-page">

    <div class="prk-zf-intro">
        Verkäufer-, Bank- und Standarddaten für elektronische Rechnungen
        im ZUGFeRD-/Factur-X-Profil EN16931.
    </div>

    <div class="prk-zf-info">
        Die hier hinterlegten Angaben werden für die strukturierten
        Rechnungsdaten verwendet. Pflichtfelder sind mit
        <span class="prk-zf-required">*</span> gekennzeichnet.
    </div>

    <form method="post" action="index.php?entryPoint=prkZugferdSettings">

        <input type="hidden" name="prk_zugferd_save" value="1">

        <div class="prk-zf-card">
            <div class="prk-zf-card-title">
                Unternehmen
            </div>

            <div class="prk-zf-card-body">
                <div class="prk-zf-grid">

                    <div class="prk-zf-field prk-zf-field-full">
                        <label class="prk-zf-label">
                            Firmenname
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_name"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['name']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field prk-zf-field-full">
                        <label class="prk-zf-label">
                            Straße und Hausnummer
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_street"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['street']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            PLZ
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_postcode"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['postcode']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            Ort
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_city"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['city']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            Land
                            <span class="prk-zf-required">*</span>
                        </label>

                        <?php
                        echo prk_zugferd_select(
                            'seller_country',
                            $countries,
                            (string)$config['seller']['country']
                        );
                        ?>

                        <div class="prk-zf-hint">
                            ISO-3166-1-Alpha-2-Ländercode
                        </div>
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            E-Mail
                        </label>

                        <input
                            type="email"
                            name="seller_email"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['email']); ?>"
                        >
                    </div>

                </div>
            </div>
        </div>


        <div class="prk-zf-card">
            <div class="prk-zf-card-title">
                Steuerdaten
            </div>

            <div class="prk-zf-card-body">
                <div class="prk-zf-grid">

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            USt-ID
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_vat_id"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['vat_id']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            Steuernummer
                        </label>

                        <input
                            type="text"
                            name="seller_tax_number"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['tax_number']); ?>"
                        >

                        <div class="prk-zf-hint">
                            Optional
                        </div>
                    </div>

                </div>
            </div>
        </div>


        <div class="prk-zf-card">
            <div class="prk-zf-card-title">
                Bankverbindung
            </div>

            <div class="prk-zf-card-body">
                <div class="prk-zf-grid">

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            IBAN
                            <span class="prk-zf-required">*</span>
                        </label>

                        <input
                            type="text"
                            name="seller_iban"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['iban']); ?>"
                            required
                        >
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            BIC
                        </label>

                        <input
                            type="text"
                            name="seller_bic"
                            class="prk-zf-input"
                            value="<?php echo prk_zugferd_h((string)$config['seller']['bic']); ?>"
                        >

                        <div class="prk-zf-hint">
                            Optional
                        </div>
                    </div>

                </div>
            </div>
        </div>


        <div class="prk-zf-card">
            <div class="prk-zf-card-title">
                Rechnungsstandard
            </div>

            <div class="prk-zf-card-body">
                <div class="prk-zf-grid">

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            Währung
                            <span class="prk-zf-required">*</span>
                        </label>

                        <?php
                        echo prk_zugferd_select(
                            'currency',
                            $currencies,
                            (string)$config['invoice']['currency']
                        );
                        ?>
                    </div>

                    <div class="prk-zf-field">
                        <label class="prk-zf-label">
                            Standard-Mengeneinheit
                            <span class="prk-zf-required">*</span>
                        </label>

                        <?php
                        echo prk_zugferd_select(
                            'default_unit_code',
                            $units,
                            (string)$config['invoice']['default_unit_code']
                        );
                        ?>

                        <div class="prk-zf-hint">
                            UNECE Recommendation 20
                        </div>
                    </div>

                    <div class="prk-zf-field prk-zf-field-full">
                        <label class="prk-zf-label">
                            Zahlungsbedingung
                            <span class="prk-zf-required">*</span>
                        </label>

                        <textarea
                            name="payment_terms"
                            class="prk-zf-input"
                            required
                        ><?php echo prk_zugferd_h((string)$config['invoice']['payment_terms']); ?></textarea>

                        <div class="prk-zf-hint">
                            Text, der in die strukturierten Rechnungsdaten übernommen wird.
                        </div>
                    </div>

                </div>
            </div>
        </div>


        <div class="prk-zf-actions">
            <button
                type="submit"
                class="button primary prk-zf-save"
            >
                Speichern
            </button>
        </div>

    </form>

</div>
