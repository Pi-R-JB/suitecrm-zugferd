<?php

function post_install()
{
    global $db;

    echo '<strong>ZUGFeRD for AOS Invoices 0.2.0-beta.1 wurde installiert.</strong><br>';

    /*
     * Auf Installationen ohne bisherige AOS_Invoices-Custom-Felder
     * kann die Custom-Tabelle noch fehlen.
     */
    $tableResult = $db->query(
        "SHOW TABLES LIKE 'aos_invoices_cstm'"
    );

    if (!$db->fetchByAssoc($tableResult)) {
        $db->query(
            "CREATE TABLE aos_invoices_cstm (
                id_c CHAR(36) NOT NULL,
                PRIMARY KEY (id_c)
            ) ENGINE=InnoDB"
        );

        echo 'Custom-Tabelle aos_invoices_cstm wurde angelegt.<br>';
    }

    /*
     * Benötigte Datenbankspalten.
     *
     * Die Vardefs werden zusätzlich über das Paket installiert.
     * Hier stellen wir sicher, dass die physischen Spalten bereits
     * unmittelbar nach der Modulinstallation vorhanden sind.
     */
    $columns = array(
        'zugferd_document_type_c' => "
            ALTER TABLE aos_invoices_cstm
            ADD COLUMN zugferd_document_type_c
            VARCHAR(100) NULL DEFAULT 'invoice'
        ",
        'original_invoice_id_c' => "
            ALTER TABLE aos_invoices_cstm
            ADD COLUMN original_invoice_id_c
            CHAR(36) NULL
        ",
        'revenue_amount_c' => "
            ALTER TABLE aos_invoices_cstm
            ADD COLUMN revenue_amount_c
            DECIMAL(26,6) NULL
        ",
        'revenue_amount_usdollar_c' => "
            ALTER TABLE aos_invoices_cstm
            ADD COLUMN revenue_amount_usdollar_c
            DECIMAL(26,6) NULL
        ",
    );

    foreach ($columns as $column => $sql) {
        $result = $db->query(
            "SHOW COLUMNS
             FROM aos_invoices_cstm
             LIKE '" . $db->quote($column) . "'"
        );

        if (!$db->fetchByAssoc($result)) {
            $db->query($sql);

            echo 'Datenbankfeld '
                . htmlspecialchars($column, ENT_QUOTES, 'UTF-8')
                . ' wurde angelegt.<br>';
        } else {
            echo 'Datenbankfeld '
                . htmlspecialchars($column, ENT_QUOTES, 'UTF-8')
                . ' ist bereits vorhanden.<br>';
        }
    }

    /*
     * Sicherstellen, dass für jede vorhandene Rechnung auch ein
     * Datensatz in aos_invoices_cstm existiert.
     *
     * INSERT IGNORE macht diesen Schritt wiederholbar.
     */
    $db->query(
        "INSERT IGNORE INTO aos_invoices_cstm (id_c)
         SELECT i.id
         FROM aos_invoices i
         WHERE i.deleted = 0"
    );

    /*
     * Bestehende Rechnungen ohne Dokumenttyp werden als normale
     * Rechnung behandelt.
     */
    $db->query(
        "UPDATE aos_invoices_cstm
         SET zugferd_document_type_c = 'invoice'
         WHERE zugferd_document_type_c IS NULL
            OR zugferd_document_type_c = ''"
    );

    /*
     * Umsatzwirksame Beträge rückwirkend berechnen.
     *
     * cancellation:
     *     negativer Umsatz
     *
     * alle anderen Dokumenttypen:
     *     positiver Umsatz
     *
     * credit_note wird bewusst noch nicht gesondert behandelt.
     */
    $db->query(
        "UPDATE aos_invoices i
         INNER JOIN aos_invoices_cstm ic
             ON ic.id_c = i.id
         SET
             ic.revenue_amount_c =
                 CASE
                     WHEN ic.zugferd_document_type_c = 'cancellation'
                         THEN -ABS(i.total_amount)
                     ELSE ABS(i.total_amount)
                 END,
             ic.revenue_amount_usdollar_c =
                 CASE
                     WHEN ic.zugferd_document_type_c = 'cancellation'
                         THEN -ABS(i.total_amount_usdollar)
                     ELSE ABS(i.total_amount_usdollar)
                 END
         WHERE i.deleted = 0"
    );

    echo 'Bestehende Rechnungen wurden für die Umsatzstatistik aktualisiert.<br>';

    echo '<br>';
    echo '<strong>Weitere Schritte:</strong><br>';
    echo '1. Admin -> Reparieren -> Schnellreparatur und Neuaufbau ausführen.<br>';
    echo '2. Im SuiteCRM-Hauptverzeichnis Composer-Abhängigkeiten aktualisieren:<br>';
    echo '<code>composer update easybill/zugferd-php tecnickcom/tcpdf --with-all-dependencies --no-dev --ignore-platform-req=php</code><br>';
    echo '3. Admin -> ZUGFeRD -> ZUGFeRD Einstellungen öffnen und Konfiguration prüfen.<br>';
    echo '4. Vorhandene Umsatzreports bei Bedarf auf revenue_amount_c bzw. revenue_amount_usdollar_c umstellen.<br>';
}
