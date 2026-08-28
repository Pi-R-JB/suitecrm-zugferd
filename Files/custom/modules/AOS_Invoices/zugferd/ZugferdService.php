<?php

declare(strict_types=1);

/*
 * If this software helped solve your problem and you'd like to say thanks:
 * https://buymeacoffee.com/pierrerohro
 */

use Easybill\ZUGFeRD2\Builder;
use Easybill\ZUGFeRD2\Validator;
use Easybill\ZUGFeRD2\Model\Amount;
use Easybill\ZUGFeRD2\Model\CreditorFinancialAccount;
use Easybill\ZUGFeRD2\Model\CrossIndustryInvoice;
use Easybill\ZUGFeRD2\Model\DateTime as ZugferdDateTime;
use Easybill\ZUGFeRD2\Model\DocumentContextParameter;
use Easybill\ZUGFeRD2\Model\DocumentLineDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocument;
use Easybill\ZUGFeRD2\Model\ExchangedDocumentContext;
use Easybill\ZUGFeRD2\Model\HeaderTradeAgreement;
use Easybill\ZUGFeRD2\Model\HeaderTradeDelivery;
use Easybill\ZUGFeRD2\Model\HeaderTradeSettlement;
use Easybill\ZUGFeRD2\Model\Id;
use Easybill\ZUGFeRD2\Model\LineTradeAgreement;
use Easybill\ZUGFeRD2\Model\LineTradeDelivery;
use Easybill\ZUGFeRD2\Model\LineTradeSettlement;
use Easybill\ZUGFeRD2\Model\Period;
use Easybill\ZUGFeRD2\Model\Quantity;
use Easybill\ZUGFeRD2\Model\ReferencedDocument;
use Easybill\ZUGFeRD2\Model\SupplyChainEvent;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeLineItem;
use Easybill\ZUGFeRD2\Model\SupplyChainTradeTransaction;
use Easybill\ZUGFeRD2\Model\TaxRegistration;
use Easybill\ZUGFeRD2\Model\TradeAddress;
use Easybill\ZUGFeRD2\Model\TradeParty;
use Easybill\ZUGFeRD2\Model\TradePaymentTerms;
use Easybill\ZUGFeRD2\Model\TradePrice;
use Easybill\ZUGFeRD2\Model\TradeProduct;
use Easybill\ZUGFeRD2\Model\TradeSettlementHeaderMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementLineMonetarySummation;
use Easybill\ZUGFeRD2\Model\TradeSettlementPaymentMeans;
use Easybill\ZUGFeRD2\Model\TradeTax;

require_once __DIR__ . '/ZugferdException.php';
require_once dirname(__DIR__, 2) . '/PRK_Zugferd/Config.php';

final class ZugferdService
{
    private string $crmRoot;
    private array $config;

    public function __construct(?string $crmRoot = null, ?array $config = null)
    {
        $this->crmRoot = $crmRoot ?? dirname(__DIR__, 4);

        $this->config = $config ?? PRK_ZugferdConfig::get();

        try {
            PRK_ZugferdConfig::validate($this->config);
        } catch (Throwable $e) {
            throw new ZugferdException(
                $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function generate(string $invoiceId): array
    {
        $invoiceBean = \BeanFactory::getBean('AOS_Invoices', $invoiceId);

        if (!$invoiceBean || empty($invoiceBean->id)) {
            throw new ZugferdException('Rechnung wurde nicht gefunden: ' . $invoiceId);
        }

        $this->validateInvoiceHeader($invoiceBean);

        $account = \BeanFactory::getBean('Accounts', (string)$invoiceBean->billing_account_id);
        if (!$account || empty($account->id)) {
            throw new ZugferdException('Der zur Rechnung gehörende Account konnte nicht geladen werden.');
        }

if (!$invoiceBean->load_relationship('aos_quotes_aos_invoices')) {
    throw new ZugferdException(
        'Das zur Rechnung gehörende Angebot konnte nicht geladen werden.'
    );
}

$quoteBeans = $invoiceBean->aos_quotes_aos_invoices->getBeans();

if (!$quoteBeans) {
    throw new ZugferdException(
        'Der Rechnung ist kein Angebot zugeordnet.'
    );
}

$quoteBean = reset($quoteBeans);

if (!$quoteBean || empty($quoteBean->id)) {
    throw new ZugferdException(
        'Das zur Rechnung gehörende Angebot konnte nicht geladen werden.'
    );
}

if (empty($quoteBean->beginn_c)) {
    throw new ZugferdException(
        'Leistungsbeginn fehlt im zugehörigen Angebot.'
    );
}

if (empty($quoteBean->ende_c)) {
    throw new ZugferdException(
        'Leistungsende fehlt im zugehörigen Angebot.'
    );
}

        if (!$invoiceBean->load_relationship('aos_products_quotes')) {
            throw new ZugferdException('Rechnungspositionen konnten nicht geladen werden.');
        }

        $lineBeans = $invoiceBean->aos_products_quotes->getBeans();
        if (!$lineBeans) {
            throw new ZugferdException('Die Rechnung enthält keine Positionen.');
        }

        $invoice = $this->buildInvoice(
    $invoiceBean,
    $account,
    $lineBeans,
    $quoteBean
);
        $xml = Builder::create()->transform($invoice);

        $validationError = (new Validator())->validateAgainstXsd($xml, Validator::SCHEMA_EN16931);
        if ($validationError !== null) {
            throw new ZugferdException("EN16931-XSD-Validierung fehlgeschlagen:\n" . $validationError);
        }

        $outputFile = $this->writeXml((string)$invoiceBean->number, $xml);

        return [
            'invoice_id' => (string)$invoiceBean->id,
            'invoice_number' => (string)$invoiceBean->number,
            'customer' => (string)$invoiceBean->billing_account,
            'position_count' => count($lineBeans),
            'net_total' => $this->money($invoiceBean->subtotal_amount),
            'tax_total' => $this->money($invoiceBean->tax_amount),
            'gross_total' => $this->money($invoiceBean->total_amount),
            'xml_file' => $outputFile,
            'xsd_valid' => true,
            'validation_error' => null,
        ];
    }

    private function buildInvoice(
    $invoiceBean,
    $account,
    array $lineBeans,
    $quoteBean
): CrossIndustryInvoice {

        $invoice = new CrossIndustryInvoice();

        $invoice->exchangedDocumentContext = new ExchangedDocumentContext();
        $invoice->exchangedDocumentContext->documentContextParameter = new DocumentContextParameter();
        $invoice->exchangedDocumentContext->documentContextParameter->id = Builder::GUIDELINE_SPECIFIED_DOCUMENT_CONTEXT_ID_EN16931;

        $invoice->exchangedDocument = new ExchangedDocument();
        $invoice->exchangedDocument->id = (string)$invoiceBean->number;
        $invoice->exchangedDocument->typeCode = '380';
        $invoice->exchangedDocument->issueDateTime = ZugferdDateTime::create(102, $this->zugferdDate((string)$invoiceBean->invoice_date));

        $invoice->supplyChainTradeTransaction = new SupplyChainTradeTransaction();

        [$taxGroups, $calculatedNetTotal, $calculatedTaxTotal] = $this->addLineItems($invoice, $lineBeans);
        $this->assertInvoiceTotals($invoiceBean, $calculatedNetTotal, $calculatedTaxTotal);
        $this->addTradeAgreement($invoice, $invoiceBean, $account);

$invoice->supplyChainTradeTransaction->applicableHeaderTradeDelivery =
    new HeaderTradeDelivery();

$invoice->supplyChainTradeTransaction
    ->applicableHeaderTradeDelivery
    ->chainEvent = new SupplyChainEvent();

$invoice->supplyChainTradeTransaction
    ->applicableHeaderTradeDelivery
    ->chainEvent
    ->date = ZugferdDateTime::create(
        102,
        $this->zugferdDate((string)$quoteBean->ende_c)
    );

$this->addSettlement(
    $invoice,
    $invoiceBean,
    $taxGroups,
    $quoteBean
);

        return $invoice;
    }

    private function addLineItems(CrossIndustryInvoice $invoice, array $lineBeans): array
    {
        $taxGroups = [];
        $positionNo = 1;
        $calculatedNetTotal = 0.0;
        $calculatedTaxTotal = 0.0;

        foreach ($lineBeans as $lineBean) {
            $this->validateLine($lineBean, $positionNo);

            $quantity = (float)$lineBean->product_qty;
            $unitPrice = (float)$lineBean->product_unit_price;
            $taxRate = (float)$lineBean->vat;
            $lineNet = (float)$lineBean->product_total_price;
            $lineTaxAmount = round($lineNet * ($taxRate / 100), 2);

            $item = new SupplyChainTradeLineItem();
            $item->associatedDocumentLineDocument = DocumentLineDocument::create((string)$positionNo);

            $item->specifiedTradeProduct = new TradeProduct();
            $item->specifiedTradeProduct->name = (string)$lineBean->name;
            if (!empty($lineBean->part_number)) {
                $item->specifiedTradeProduct->sellerAssignedID = (string)$lineBean->part_number;
            }

            $item->tradeAgreement = new LineTradeAgreement();
            $item->tradeAgreement->grossPrice = TradePrice::create($this->price($unitPrice));
            $item->tradeAgreement->netPrice = TradePrice::create($this->price($unitPrice));

            $item->delivery = new LineTradeDelivery();
            $item->delivery->billedQuantity = Quantity::create(
                $this->qty($quantity),
                (string)$this->config['invoice']['default_unit_code']
            );

            $item->specifiedLineTradeSettlement = new LineTradeSettlement();
            $lineTax = new TradeTax();
            $lineTax->typeCode = 'VAT';
            $lineTax->categoryCode = 'S';
            $lineTax->rateApplicablePercent = $this->money($taxRate);
            $item->specifiedLineTradeSettlement->tradeTax[] = $lineTax;
            $item->specifiedLineTradeSettlement->monetarySummation = TradeSettlementLineMonetarySummation::create($this->money($lineNet));

            $invoice->supplyChainTradeTransaction->lineItems[] = $item;

            $taxKey = $this->money($taxRate);
            if (!isset($taxGroups[$taxKey])) {
                $taxGroups[$taxKey] = ['basis' => 0.0, 'tax' => 0.0];
            }

            $taxGroups[$taxKey]['basis'] += $lineNet;
            $taxGroups[$taxKey]['tax'] += $lineTaxAmount;
            $calculatedNetTotal += $lineNet;
            $calculatedTaxTotal += $lineTaxAmount;
            $positionNo++;
        }

        return [$taxGroups, $calculatedNetTotal, $calculatedTaxTotal];
    }

    private function addTradeAgreement(CrossIndustryInvoice $invoice, $invoiceBean, $account): void
    {
        $agreement = new HeaderTradeAgreement();

        if (!empty($invoiceBean->po_numberinv_c)) {
            $agreement->buyerOrderReferencedDocument = new ReferencedDocument();
            $agreement->buyerOrderReferencedDocument->issuerAssignedID = Id::create((string)$invoiceBean->po_numberinv_c);
        }

        $seller = new TradeParty();
        $seller->name = (string)$this->config['seller']['name'];
        $seller->postalTradeAddress = new TradeAddress();
        $seller->postalTradeAddress->postcode = (string)$this->config['seller']['postcode'];
        $seller->postalTradeAddress->lineOne = (string)$this->config['seller']['street'];
        $seller->postalTradeAddress->city = (string)$this->config['seller']['city'];
        $seller->postalTradeAddress->countryCode = (string)$this->config['seller']['country'];

        if (!empty($this->config['seller']['tax_number'])) {
            $seller->taxRegistrations[] = TaxRegistration::create((string)$this->config['seller']['tax_number'], 'FC');
        }
        $seller->taxRegistrations[] = TaxRegistration::create((string)$this->config['seller']['vat_id'], 'VA');
        $agreement->sellerTradeParty = $seller;

        $buyer = new TradeParty();
        $buyer->id = Id::create((string)$account->id);
        $buyer->name = (string)$invoiceBean->billing_account;
        $buyer->postalTradeAddress = new TradeAddress();
        $buyer->postalTradeAddress->postcode = (string)$invoiceBean->billing_address_postalcode;
        $buyer->postalTradeAddress->lineOne = (string)$invoiceBean->billing_address_street;
        $buyer->postalTradeAddress->city = (string)$invoiceBean->billing_address_city;
        $buyer->postalTradeAddress->countryCode = $this->countryCode((string)$invoiceBean->billing_address_country);

        if (!empty($account->ustid_c)) {
            $buyer->taxRegistrations[] = TaxRegistration::create((string)$account->ustid_c, 'VA');
        }

        $agreement->buyerTradeParty = $buyer;
        $invoice->supplyChainTradeTransaction->applicableHeaderTradeAgreement = $agreement;
    }

    private function addSettlement(
    CrossIndustryInvoice $invoice,
    $invoiceBean,
    array $taxGroups,
    $quoteBean
): void {

        $settlement = new HeaderTradeSettlement();
        $settlement->currency = (string)$this->config['invoice']['currency'];

	$billingPeriod = new Period();

$billingPeriod->startDatetime = ZugferdDateTime::create(
    102,
    $this->zugferdDate((string)$quoteBean->beginn_c)
);

$billingPeriod->endDatetime = ZugferdDateTime::create(
    102,
    $this->zugferdDate((string)$quoteBean->ende_c)
);

$settlement->billingSpecifiedPeriod = $billingPeriod;

        $paymentMeans = new TradeSettlementPaymentMeans();
        $paymentMeans->typeCode = '58';
        $paymentMeans->payeePartyCreditorFinancialAccount = new CreditorFinancialAccount();
        $paymentMeans->payeePartyCreditorFinancialAccount->ibanId = Id::create((string)$this->config['seller']['iban']);
        $settlement->specifiedTradeSettlementPaymentMeans[] = $paymentMeans;

        foreach ($taxGroups as $rate => $taxData) {
            $headerTax = new TradeTax();
            $headerTax->typeCode = 'VAT';
            $headerTax->categoryCode = 'S';
            $headerTax->basisAmount = Amount::create($this->money($taxData['basis']));
            $headerTax->calculatedAmount = Amount::create($this->money($taxData['tax']));
            $headerTax->rateApplicablePercent = (string)$rate;
            $settlement->tradeTaxes[] = $headerTax;
        }

        $paymentTerms = new TradePaymentTerms();
        $paymentTerms->description = (string)$this->config['invoice']['payment_terms'];
        if (!empty($invoiceBean->due_date)) {
            $paymentTerms->dueDate = ZugferdDateTime::create(102, $this->zugferdDate((string)$invoiceBean->due_date));
        }
        $settlement->specifiedTradePaymentTerms[] = $paymentTerms;

        $netTotal = (float)$invoiceBean->subtotal_amount;
        $taxTotal = (float)$invoiceBean->tax_amount;
        $grossTotal = (float)$invoiceBean->total_amount;

        $summation = new TradeSettlementHeaderMonetarySummation();
        $summation->lineTotalAmount = Amount::create($this->money($netTotal));
        $summation->chargeTotalAmount = Amount::create('0.00');
        $summation->allowanceTotalAmount = Amount::create('0.00');
        $summation->taxBasisTotalAmount[] = Amount::create($this->money($netTotal));
        $summation->taxTotalAmount[] = Amount::create($this->money($taxTotal), (string)$this->config['invoice']['currency']);
        $summation->grandTotalAmount[] = Amount::create($this->money($grossTotal));
        $summation->totalPrepaidAmount = Amount::create('0.00');
        $summation->duePayableAmount = Amount::create($this->money($grossTotal));

        $settlement->specifiedTradeSettlementHeaderMonetarySummation = $summation;
        $invoice->supplyChainTradeTransaction->applicableHeaderTradeSettlement = $settlement;
    }

    private function validateInvoiceHeader($invoiceBean): void
    {
        $required = [
            'number' => 'Rechnungsnummer',
            'invoice_date' => 'Rechnungsdatum',
            'billing_account_id' => 'Kunde',
            'billing_account' => 'Kundenname',
            'billing_address_street' => 'Rechnungsstraße',
            'billing_address_postalcode' => 'Rechnungs-PLZ',
            'billing_address_city' => 'Rechnungsort',
            'billing_address_country' => 'Rechnungsland',
                    ];

        foreach ($required as $field => $label) {
            if (empty($invoiceBean->$field)) {
                throw new ZugferdException($label . ' fehlt.');
            }
        }
    }

    private function validateLine($lineBean, int $positionNo): void
    {
        if (empty($lineBean->name)) {
            throw new ZugferdException("Position {$positionNo}: Bezeichnung fehlt.");
        }
        if (!isset($lineBean->product_qty) || (float)$lineBean->product_qty <= 0) {
            throw new ZugferdException("Position {$positionNo}: Menge ist ungültig.");
        }
        if (!isset($lineBean->product_unit_price)) {
            throw new ZugferdException("Position {$positionNo}: Einzelpreis fehlt.");
        }
        if (!isset($lineBean->product_total_price)) {
            throw new ZugferdException("Position {$positionNo}: Positionssumme fehlt.");
        }
        if (!isset($lineBean->vat) || !is_numeric($lineBean->vat)) {
            throw new ZugferdException("Position {$positionNo}: Steuersatz fehlt oder ist ungültig.");
        }
    }

    private function assertInvoiceTotals($invoiceBean, float $calculatedNetTotal, float $calculatedTaxTotal): void
    {
        $invoiceNet = round((float)$invoiceBean->subtotal_amount, 2);
        $invoiceTax = round((float)$invoiceBean->tax_amount, 2);
        $invoiceGross = round((float)$invoiceBean->total_amount, 2);

        if (abs(round($calculatedNetTotal, 2) - $invoiceNet) > 0.01) {
            throw new ZugferdException(sprintf(
                'Netto-Summenabweichung: Positionen %s EUR, Rechnung %s EUR.',
                $this->money($calculatedNetTotal), $this->money($invoiceNet)
            ));
        }

        if (abs(round($calculatedTaxTotal, 2) - $invoiceTax) > 0.01) {
            throw new ZugferdException(sprintf(
                'Steuer-Summenabweichung: Positionen %s EUR, Rechnung %s EUR.',
                $this->money($calculatedTaxTotal), $this->money($invoiceTax)
            ));
        }

        $expectedGross = round($invoiceNet + $invoiceTax, 2);
        if (abs($expectedGross - $invoiceGross) > 0.01) {
            throw new ZugferdException(sprintf(
                'Brutto-Summenabweichung: Netto + Steuer %s EUR, Rechnung %s EUR.',
                $this->money($expectedGross), $this->money($invoiceGross)
            ));
        }
    }

    private function writeXml(string $invoiceNumber, string $xml): string
    {
        $outputDir = $this->crmRoot . '/upload/zugferd';
        if (!is_dir($outputDir) && !mkdir($outputDir, 0770, true) && !is_dir($outputDir)) {
            throw new ZugferdException('Ausgabeverzeichnis konnte nicht angelegt werden: ' . $outputDir);
        }

        $safeInvoiceNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', $invoiceNumber);
        $outputFile = $outputDir . '/zugferd-' . $safeInvoiceNumber . '.xml';

        if (file_put_contents($outputFile, $xml) === false) {
            throw new ZugferdException('XML-Datei konnte nicht gespeichert werden: ' . $outputFile);
        }

        return $outputFile;
    }

    private function zugferdDate(string $value): string
    {
        foreach (['m/d/Y', 'Y-m-d', 'd.m.Y', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTime) {
                return $date->format('Ymd');
            }
        }
        throw new ZugferdException('Datum konnte nicht gelesen werden: ' . $value);
    }

    private function countryCode(string $country): string
    {
        $normalized = trim(mb_strtolower($country));
        $map = [
            'deutschland' => 'DE', 'germany' => 'DE', 'de' => 'DE',
            'österreich' => 'AT', 'austria' => 'AT', 'at' => 'AT',
            'schweiz' => 'CH', 'switzerland' => 'CH', 'ch' => 'CH',
            'vereinigtes königreich' => 'GB', 'großbritannien' => 'GB', 'grossbritannien' => 'GB',
            'united kingdom' => 'GB', 'uk' => 'GB', 'gb' => 'GB',
            'frankreich' => 'FR', 'france' => 'FR', 'fr' => 'FR',
            'niederlande' => 'NL', 'netherlands' => 'NL', 'nl' => 'NL',
            'belgien' => 'BE', 'belgium' => 'BE', 'be' => 'BE',
            'italien' => 'IT', 'italy' => 'IT', 'it' => 'IT',
            'spanien' => 'ES', 'spain' => 'ES', 'es' => 'ES',
            'polen' => 'PL', 'poland' => 'PL', 'pl' => 'PL',
        ];

        if (!isset($map[$normalized])) {
            throw new ZugferdException(
                'Unbekanntes Rechnungsland: ' . $country .
                '. Bitte einen ISO-3166-1-Alpha-2-Code oder ein bekanntes Land verwenden.'
            );
        }

        return $map[$normalized];
    }

    private function money($value): string
    {
        return number_format((float)$value, 2, '.', '');
    }

    private function price($value): string
    {
        return number_format((float)$value, 4, '.', '');
    }

    private function qty($value): string
    {
        return number_format((float)$value, 4, '.', '');
    }
}
