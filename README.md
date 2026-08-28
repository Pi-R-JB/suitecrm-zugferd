# ZUGFeRD for SuiteCRM AOS Invoices

[![Release](https://img.shields.io/github/v/release/Pi-R-JB/suitecrm-zugferd?include_prereleases)](https://github.com/Pi-R-JB/suitecrm-zugferd/releases)
[![License](https://img.shields.io/github/license/Pi-R-JB/suitecrm-zugferd)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.1-blue)
![SuiteCRM](https://img.shields.io/badge/SuiteCRM-7.x-lightgrey)

An open-source **ZUGFeRD / Factur-X extension for SuiteCRM 7 AOS Invoices**.

The extension creates an **EN 16931 compliant XML invoice** from an existing SuiteCRM invoice and embeds it as `factur-x.xml` into a PDF/A-3 document.

The important part: you can continue using your existing **AOS PDF Template** as the visible invoice. The module adds the structured electronic invoice without replacing the existing invoice workflow.

> **Current release: `0.2.0-beta.1`**
>
> This project is currently in beta. Testing is very welcome, but please use a test instance and validate generated invoices before using them in production.

---

## Why this project?

This project started with a fairly practical problem: I needed to generate structured electronic invoices from an existing SuiteCRM installation without rebuilding the complete AOS invoice and PDF workflow.

A few things were important to me:

- existing AOS PDF Templates should continue to work;
- existing SuiteCRM customizations should be touched as little as possible;
- seller and payment information should be configurable instead of hard-coded;
- cancellation invoices should behave correctly in revenue reports;
- installation should work like a normal SuiteCRM module.

What started as a solution for one installation eventually became useful enough to turn into an open-source project.

---

## Features

Version `0.2.0-beta.1` currently provides:

- ZUGFeRD / Factur-X hybrid invoices
- EN 16931 profile
- PDF/A-3 with embedded `factur-x.xml`
- existing AOS PDF Templates remain the visible invoice design
- invoice document types:
  - Invoice
  - Cancellation invoice
  - Replacement invoice
  - Credit note
- linking cancellation and replacement invoices to their original invoice
- validation of the original invoice relationship
- negative revenue treatment for cancellation invoices
- dedicated revenue fields for SuiteCRM reports
- initialization of existing invoices during installation
- billing/service period taken from the linked quotation
- central ZUGFeRD configuration in the SuiteCRM administration area
- German and English labels
- UI integration through a hook instead of replacing the complete AOS Invoices `detailviewdefs.php`

---

## How it works

The basic process is:

1. SuiteCRM loads the existing AOS invoice.
2. The extension generates the EN 16931 XML.
3. The XML is validated.
4. AOS PDF Templates generates the visible invoice as usual.
5. The XML is embedded as `factur-x.xml`.
6. PDF/A and Factur-X metadata are added.
7. The finished ZUGFeRD PDF is provided for download.

The invoice layout therefore stays where it already belongs: in your AOS PDF Template.

---

## Requirements

The extension is intended for:

- SuiteCRM 7.x
- PHP 8.1 or newer
- AOS Invoices
- AOS PDF Templates
- PHP extension `dom`
- Composer

Required Composer packages:

```text
easybill/zugferd-php 2.1.1
tecnickcom/tcpdf 6.10.1
```

TCPDF 6.10.1 is required because the extension uses functionality needed to add the PDF/A and Factur-X XMP metadata.

The project is still in beta, so feedback from different SuiteCRM 7 environments is especially useful.

---

## Installation

### 1. Make a backup

Yes, the boring warning has to be here. :-)

Before installing a beta version into an existing SuiteCRM installation, please back up at least:

- your SuiteCRM files
- your database

Ideally, test the extension on a separate test instance first.

### 2. Install the module

Download the current release ZIP from the GitHub Releases page and install it using the normal SuiteCRM Module Loader:

**Admin → Module Loader**

### 3. Quick Repair and Rebuild

After installation run:

**Admin → Repair → Quick Repair and Rebuild**

### 4. Install the Composer dependencies

Run the following command from the SuiteCRM root directory:

```bash
composer update \
  easybill/zugferd-php \
  tecnickcom/tcpdf \
  --with-all-dependencies \
  --no-dev \
  --ignore-platform-req=php
```

Some older SuiteCRM 7 installations contain an artificially fixed PHP version in `composer.json`.

Changing that globally to PHP 8.x can cause Composer to attempt to update a large number of old SuiteCRM dependencies. That is not what we want.

The command above therefore updates only the two packages required by this extension.

### 5. Configure ZUGFeRD

After installation, open:

**Admin → ZUGFeRD → ZUGFeRD Settings**

and configure the required seller and payment information.

---

## Billing / service period

The extension currently takes the billing/service period from the quotation linked to the invoice.

Used SuiteCRM fields:

```text
beginn_c
ende_c
```

EN 16931 mapping:

| EN 16931 field | SuiteCRM |
|---|---|
| BT-72 Actual delivery date | `ende_c` |
| BT-73 Billing period start date | `beginn_c` |
| BT-74 Billing period end date | `ende_c` |

Other SuiteCRM installations may store service dates differently. Making this configurable is therefore a possible future improvement.

---

## Invoice document types

### Invoice

```text
invoice
```

A normal invoice. Its amount is treated as positive revenue.

### Cancellation invoice

```text
cancellation
```

A cancellation invoice must reference an original invoice.

The extension prevents:

- a cancellation without an original invoice,
- a reference to a non-existing original invoice,
- an invoice referencing itself as its original invoice.

For revenue reporting, cancellation invoices are treated as negative amounts.

Example:

```text
Invoice              +1,000.00
Cancellation invoice -1,000.00
------------------------------
Revenue                   0.00
```

The visible invoice totals themselves are not converted to negative values.

Instead, the negative treatment is handled through separate revenue fields.

### Replacement invoice

```text
replacement
```

A replacement invoice also requires an original invoice.

### Credit note

```text
credit_note
```

Credit notes are already available as a document type.

> **Known limitation:** In `0.2.0-beta.1`, credit notes are still treated as positive amounts in the additional revenue fields.

---

## Revenue reports

The extension adds two dedicated fields for revenue reporting:

```text
revenue_amount_c
revenue_amount_usdollar_c
```

Current treatment:

```text
invoice       -> positive
replacement   -> positive
credit_note   -> positive
cancellation  -> negative
```

This allows a normal invoice and its full cancellation to correctly result in zero revenue.

Existing AOR reports are deliberately **not modified automatically**. SuiteCRM installations and reporting structures differ too much for a safe automatic migration.

If an existing report calculates revenue from `total_amount` or `total_amount_usdollar`, it should therefore be reviewed and, where appropriate, changed to one of the new revenue fields.

---

## Validation

During development, generated documents have been tested with:

- veraPDF
- Mustangproject

A test document successfully passed **PDF/A-3b validation**, and the embedded XML was validated against the **EN 16931 schema**.

Please note that some validators also check additional requirements from standards or networks such as XRechnung or Peppol.

Warnings related specifically to those requirements do not necessarily mean that the ZUGFeRD / EN 16931 document itself is invalid.

Full XRechnung or Peppol support is currently not the goal of this beta.

---

## Known limitations

This is a beta. It would be suspicious if this list were empty. :-)

Current known limitations include:

- credit notes are still treated as positive amounts in the additional revenue fields;
- existing AOR reports must be adapted manually where necessary;
- Composer dependencies must be updated separately after module installation;
- compatibility has not yet been tested across a wide range of SuiteCRM 7 versions;
- heavily customized AOS Invoices views or themes may require additional testing;
- full XRechnung and Peppol support is currently outside the scope of this beta.

There are almost certainly things I simply haven't found yet.

That's what the beta is for.

---

## Beta testers wanted

If you have a SuiteCRM 7 test installation using AOS Invoices, feedback is very welcome.

I'm particularly interested in tests with:

- different SuiteCRM 7 versions;
- PHP 8.1 or newer;
- MariaDB and MySQL;
- customized AOS Invoices modules;
- custom AOS PDF Templates;
- different currencies and VAT scenarios;
- cancellation and replacement invoices.

Even a short report such as:

```text
Works with SuiteCRM x.x, PHP x.x and MariaDB x.x
```

is useful.

Please test on a non-production system first and validate generated electronic invoices before using them in a real accounting workflow.

---

## Found a bug?

Great. Really. :-)

For a beta project, a well-described bug is more useful than another successful test on my own system.

Please open a GitHub Issue and include as much of the following information as possible:

```text
ZUGFeRD extension version:
SuiteCRM version:
PHP version:
MariaDB/MySQL version:
Operating system:

What did you do?

What did you expect?

What happened instead?

Error message:

Reproducible:
Always / sometimes / once

Relevant SuiteCRM customizations:
```

Useful additional information includes:

- relevant excerpts from `suitecrm.log`;
- validator reports;
- screenshots, where appropriate.

**Please remove all customer data, personal data, credentials, tokens and other confidential information before publishing logs or screenshots in a GitHub Issue.**

---

## Versioning

The project will remain below `1.0.0` until it is considered production-ready.

The planned version progression is roughly:

```text
0.2.0-beta.1
0.2.0-beta.2
0.3.0-beta.1
...
0.9.0-rc.1
1.0.0
```

**`1.0.0` means the first release considered ready for production use.**

---

## License

This project is distributed under the **GNU General Public License v3.0**.

See [LICENSE](LICENSE) for details.

The software is provided without warranty. Generated invoices and the corresponding configuration must be checked and validated before production use.

Especially with electronic invoices:

**Just because the PDF looks right doesn't necessarily mean the XML inside it is doing what you expect.**

Validate, test, and only then use it in production.

---

## Author

**Pierre Rohr**

This project grew out of a real SuiteCRM use case and eventually became useful enough to share.

Bug reports, compatibility reports, improvements and contributions are welcome.

If the project is useful to you, a GitHub star is appreciated.

---

## Trademark notice

ZUGFeRD, Factur-X, SuiteCRM and other product or project names mentioned here may be trademarks or protected names of their respective owners.

This is an independent open-source extension and is not an official release of SuiteCRM or the organizations responsible for the ZUGFeRD / Factur-X standards.
