# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Projekt befindet sich aktuell noch in der Beta-Phase. Bis zur ersten stabilen Version `1.0.0` können sich Funktionen, Datenfelder und Installationsabläufe noch ändern.

## [0.2.0-beta.2] - 2026-08-28

Wartungsrelease nach Installation und Kompatibilitätsprüfung mit SuiteCRM 7.15.2.

### Behoben

- `scripts/post_install.php` wird jetzt über `post_execute` im Module-Loader-Manifest tatsächlich ausgeführt.
- Dadurch werden die für ZUGFeRD benötigten Custom-Felder und Umsatzfelder bei der Installation zuverlässig geprüft bzw. initialisiert und bestehende Rechnungen wie vorgesehen nachgezogen.

### Getestet

- Installation über den SuiteCRM Module Loader auf SuiteCRM 7.15.2.
- Wiederholte Installation bzw. Initialisierung mit bereits vorhandenen ZUGFeRD-Datenbankfeldern.
- Composer-Autoload mit `easybill/zugferd-php` 2.1.1 und `tecnickcom/tcpdf` 6.10.1.

### Hinweis

- Installationsspezifische Mail-/Microsoft-365-Anpassungen sind ausdrücklich nicht Bestandteil dieses Pakets.
- Das Paket überschreibt weder `custom/modules/Emails` noch `custom/modules/AOS_PDF_Templates/generatePdf.php`.

---

## [0.2.0-beta.1] - 2026-08-28

Erste öffentliche Beta-Version.

### Hinzugefügt

- ZUGFeRD-/Factur-X-Erzeugung für SuiteCRM AOS Invoices
- EN-16931-XML
- PDF/A-3 mit eingebettetem `factur-x.xml`
- Nutzung bestehender AOS-PDF-Templates
- Dokumenttypen:
  - Rechnung
  - Stornorechnung
  - Ersatzrechnung
  - Gutschrift
- Verknüpfung mit einer Ursprungsrechnung
- Validierung für Storno- und Ersatzrechnungen
- separate Umsatzfelder für Reports:
  - `revenue_amount_c`
  - `revenue_amount_usdollar_c`
- negative Umsatzbewertung von Stornorechnungen
- Initialisierung bestehender Rechnungen bei Installation
- zentrale ZUGFeRD-Konfiguration im Adminbereich
- deutsche und englische Labels
- UI-Integration über Hook statt Überschreiben der kompletten `detailviewdefs.php`
- Installations-, Deinstallations- und Prüfskripte

### Geändert

- Leistungszeitraum wird aus den Angebotsfeldern `beginn_c` und `ende_c` übernommen.
- Stornorechnungen wirken sich negativ auf Umsatzreports aus, ohne die sichtbaren Rechnungsbeträge zu verändern.

### Bekannte Einschränkungen

- Gutschriften werden in `0.2.0-beta.1` noch positiv in den Umsatzfeldern behandelt.
- Bestehende AOR-Reports werden nicht automatisch umgestellt.
- Composer-Abhängigkeiten müssen nach der Modulinstallation separat aktualisiert werden.
- Noch keine vollständige XRechnung-/Peppol-Unterstützung.
- Noch keine breite Kompatibilitätsprüfung über verschiedene SuiteCRM-7-Versionen und individuelle Themes hinweg.

---

## Versionsschema

Geplant ist ungefähr folgende Entwicklung:

```text
0.2.0-beta.1
0.2.0-beta.2
0.3.0-beta.1
...
0.9.0-rc.1
1.0.0
```
