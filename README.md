ZUGFeRD for AOS Invoices
Version 0.2.0-beta.1
================================


FUNKTIONSUMFANG
---------------

- SuiteCRM 7.x / AOS_Invoices
- ZUGFeRD / Factur-X Hybrid-PDF
- Profil EN16931
- PDF/A-3b
- eingebettete factur-x.xml
- AFRelationship /Alternative
- bestehende AOS_PDF_Templates als sichtbares Rechnungsdesign
- Bestellreferenz aus po_numberinv_c
- IBAN / Zahlungsart 58
- USt-Gruppierung
- XSD-Prüfung über easybill/zugferd-php
- Download direkt aus der Rechnungs-Detailansicht
- Verkäufer- und Bankdaten über Admin-Konfigurationsseite
- Leistungszeitraum aus dem verknüpften Angebot
- Unterstützung für Rechnung, Stornorechnung, Ersatzrechnung und Gutschrift
- Referenz auf die Ursprungsrechnung bei Storno und Ersatzrechnung
- umsatzwirksame Beträge für korrekte Statistiken bei Stornorechnungen
- keine Änderung an custom/modules/AOS_Invoices/controller.php
- keine Überschreibung von detailviewdefs.php


VORAUSSETZUNGEN
----------------

- SuiteCRM 7.x
- AOS_Invoices
- AOS_PDF_Templates
- PHP >= 8.1
- ext-dom
- Composer
- easybill/zugferd-php 2.1.1
- tecnickcom/tcpdf 6.10.1


INSTALLATION
------------

1. ZIP über

   Admin -> Module Loader

   hochladen und installieren.

2. Anschließend ausführen:

   Admin -> Repair -> Quick Repair and Rebuild

3. Im SuiteCRM-Hauptverzeichnis die Composer-Abhängigkeiten aktualisieren:

   composer update easybill/zugferd-php tecnickcom/tcpdf --with-all-dependencies --no-dev --ignore-platform-req=php

   Der Parameter --ignore-platform-req=php kann bei älteren
   SuiteCRM-Installationen erforderlich sein, deren composer.json noch
   eine ältere PHP-Plattform vorgibt.

4. Prüfen, ob folgende Versionen installiert wurden:

   easybill/zugferd-php 2.1.1
   tecnickcom/tcpdf      6.10.1

5. Unter

   Admin -> ZUGFeRD -> ZUGFeRD Einstellungen

   die Konfiguration prüfen bzw. pflegen:

   - Firmenname
   - Straße
   - PLZ
   - Ort
   - Land (ISO-2, z. B. DE)
   - USt-ID
   - Steuernummer optional
   - IBAN
   - BIC optional
   - E-Mail optional
   - Währung
   - Standard-Mengeneinheit
   - Zahlungsbedingung

6. Rechnung öffnen und über die bisherige PDF-Aktion das
   ZUGFeRD-PDF erzeugen.

   Das Modul ersetzt die Standard-PDF-Aktion in der
   Rechnungs-Detailansicht dynamisch.

   Eine vorhandene oder über Studio angepasste
   detailviewdefs.php wird dabei nicht überschrieben.


LEISTUNGSZEITRAUM
-----------------

Der Leistungszeitraum wird aus dem mit der Rechnung verknüpften
Angebot übernommen.

AOS_Quotes:

- beginn_c -> EN16931 BT-73
- ende_c   -> EN16931 BT-74
- ende_c   -> tatsächliches Leistungs-/Lieferdatum BT-72

Die Rechnung muss über die SuiteCRM-Beziehung

aos_quotes_aos_invoices

mit einem Angebot verknüpft sein.

Ein zusätzliches Leistungsdatum auf der Rechnung ist nicht erforderlich.


DOKUMENTTYPEN
-------------

Das Modul unterstützt folgende Dokumenttypen:

- Rechnung
- Stornorechnung
- Ersatzrechnung
- Gutschrift

Für Stornorechnungen und Ersatzrechnungen muss eine
Ursprungsrechnung angegeben werden.

Eine Rechnung kann nicht auf sich selbst als Ursprungsrechnung
verweisen.


UMSATZSTATISTIK
---------------

Für Umsatzstatistiken stellt das Modul zwei zusätzliche Felder bereit:

- revenue_amount_c
- revenue_amount_usdollar_c

Diese Felder verändern die eigentlichen Rechnungsbeträge nicht.

Normale Rechnung:

    positiver Umsatz

Stornorechnung:

    negativer Umsatz

Beispiel:

    Rechnung        +1.000,00 EUR
    Stornorechnung  -1.000,00 EUR
    ---------------------------
    Umsatz               0,00 EUR

Bei der Installation werden die Umsatzfelder auch für bereits
bestehende Rechnungen berechnet.

Vorhandene AOR-Umsatzreports werden aus Sicherheitsgründen nicht
automatisch verändert.

Bestehende Reports sollten für Umsatzsummen bei Bedarf von

    total_amount
    total_amount_usdollar

auf

    revenue_amount_c
    revenue_amount_usdollar_c

umgestellt werden.

Die Behandlung des Dokumenttyps "credit_note" als negativer Umsatz
ist in dieser Beta-Version noch nicht implementiert. Er wird für die
Umsatzfelder derzeit wie eine normale Rechnung behandelt.


VALIDIERUNG
-----------

Im Referenzsystem wurden erzeugte ZUGFeRD-Dateien unter anderem mit
folgenden Werkzeugen geprüft:

- veraPDF 1.30.2
  PDF/A-3b compliant

- Mustang CLI 2.25.0
  PDF valid
  XML valid
  Gesamt valid

Mustang und veraPDF gehören bewusst nicht zu den
Runtime-Abhängigkeiten des Moduls.


BETA-HINWEISE
-------------

- Fokus ist derzeit die Erzeugung von ZUGFeRD-/Factur-X-Dokumenten
  im Profil EN16931.

- Das Modul erzeugt keine XRechnung.

- XRechnung-, Peppol- und BR-DE-spezifische Pflichtangaben werden
  daher nicht erzwungen.

- Vor einem produktiven Rollout sollten insbesondere Testfälle mit
  Rabatten, Aufschlägen, mehreren Steuersätzen, Gutschriften und
  weiteren kaufmännischen Sonderfällen validiert werden.

- Der vorhandene Standard-PDF-Button der AOS_Invoices-Detailansicht
  wird über einen Application Logic Hook auf die ZUGFeRD-Erzeugung
  umgeleitet.

- Die kundenspezifische AOS_Invoices-detailviewdefs.php wird vom
  Modul nicht überschrieben.

- Vorhandene kundenspezifische Umsatzreports werden nicht automatisch
  geändert.


DEINSTALLATION
--------------

Bei der Deinstallation werden bereits erzeugte ZUGFeRD-PDF- und
XML-Dateien nicht automatisch gelöscht.

Die Composer-Pakete

- easybill/zugferd-php
- tecnickcom/tcpdf

werden ebenfalls nicht automatisch entfernt.

Bereits angelegte Datenbankfelder bleiben zur Vermeidung von
Datenverlust erhalten.


LIZENZ
------

GNU General Public License Version 3 oder später
(GPL-3.0-or-later).

Für die Software besteht keine Gewährleistung, soweit dies gesetzlich
zulässig ist.

easybill/zugferd-php und TCPDF werden nicht mit diesem Modul
mitgeliefert und unterliegen ihren jeweiligen eigenen Lizenzen.
