<?php

namespace Saleh7\Zatca;

use Saleh7\Zatca\Exceptions\ZatcaStorageException;
use Saleh7\Zatca\Helpers\QRCodeGenerator;
use Saleh7\Zatca\Helpers\Certificate;
use Saleh7\Zatca\Helpers\InvoiceExtension;
use Saleh7\Zatca\Helpers\InvoiceSignatureBuilder;

class InvoiceSigner
{
    private $signedInvoice;  // Signed invoice XML string
    private $hash;           // Invoice hash (base64 encoded)
    private $qrCode;         // QR Code (base64 encoded)
    private $certificate;    // Certificate used for signing
    private $digitalSignature; // Digital signature (base64 encoded)

    // Private constructor to force usage of signInvoice method
    private function __construct() {}

    /**
     * Signs the invoice XML and returns an InvoiceSigner object.
     *
     * @param string      $xmlInvoice  Invoice XML as a string
     * @param Certificate $certificate Certificate for signing
     * @return self
     */
    public static function signInvoice(string $xmlInvoice, Certificate $certificate): self
    {
        $instance = new self();
        $instance->certificate = $certificate;

        // Convert XML string to DOM
        $xmlDom = InvoiceExtension::fromString($xmlInvoice);

        // Remove unwanted tags per guidelines
        $xmlDom->removeByXpath('ext:UBLExtensions');
        $xmlDom->removeByXpath('cac:Signature');
        $xmlDom->removeParentByXpath('cac:AdditionalDocumentReference/cbc:ID[. = "QR"]');

        // Preliminary hash, computed on the pre-assembly DOM.
        $instance->hash = base64_encode(hash('sha256', $xmlDom->getElement()->C14N(false, false), true));

        // Assemble the signed document, then guarantee the embedded hash matches what ZATCA will
        // recompute from the SUBMITTED document. ZATCA strips UBLExtensions/Signature/QR and C14Ns
        // the result; whitespace introduced while inserting those elements can make that differ
        // from the preliminary hash, which ZATCA rejects as `invoiceHash_QRCODE_INVALID`. So we
        // recompute the hash exactly as ZATCA does and, if it differs, re-sign with that
        // authoritative hash and reassemble. The retained content is independent of the hash value,
        // so this converges in at most one extra pass and leaves already-consistent invoices
        // (whose preliminary hash already matches) byte-for-byte unchanged.
        $signedInvoice = '';
        for ($pass = 0; $pass < 2; $pass++) {
            $instance->digitalSignature = base64_encode(
                $certificate->getPrivateKey()->sign(base64_decode($instance->hash))
            );

            // Prepare UBL Extension with certificate, hash, and signature
            $ublExtension = (new InvoiceSignatureBuilder)
                ->setCertificate($certificate)
                ->setInvoiceDigest($instance->hash)
                ->setSignatureValue($instance->digitalSignature)
                ->buildSignatureXml();

            // Generate QR Code
            $instance->qrCode = QRCodeGenerator::createFromTags(
                $xmlDom->generateQrTagsArray($certificate, $instance->hash, $instance->digitalSignature)
            )->encodeBase64();

            // Insert UBL Extension and QR Code into the XML
            $signedInvoice = str_replace(
                [
                    "<cbc:ProfileID>",
                    '<cac:AccountingSupplierParty>',
                ],
                [
                    "<ext:UBLExtensions>" . $ublExtension . "</ext:UBLExtensions>" . PHP_EOL . "    <cbc:ProfileID>",
                    $instance->getQRNode($instance->qrCode) . PHP_EOL . "    <cac:AccountingSupplierParty>",
                ],
                $xmlDom->toXml()
            );

            // Remove extra blank lines
            $signedInvoice = preg_replace('/^[ \t]*[\r\n]+/m', '', $signedInvoice);

            // Hash as ZATCA will recompute it from this exact document.
            $submittedHash = self::hashAsSubmitted($signedInvoice);
            if ($submittedHash === $instance->hash) {
                break;
            }
            $instance->hash = $submittedHash;
        }

        $instance->signedInvoice = $signedInvoice;

        return $instance;
    }

    /**
     * Recompute the invoice hash the way ZATCA does from a fully-assembled document: strip the
     * UBLExtensions, Signature and QR nodes, canonicalize (C14N) and SHA-256, Base64-encode. Uses a
     * plain DOM parse (no indentation normalization) so the result reflects the exact bytes that
     * will be submitted.
     *
     * @param string $signedXml The assembled signed invoice XML.
     * @return string Base64-encoded SHA-256 hash.
     */
    private static function hashAsSubmitted(string $signedXml): string
    {
        $doc = new \DOMDocument();
        $doc->loadXML($signedXml);
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ext', 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        foreach (['//ext:UBLExtensions', '//cac:Signature', '//cac:AdditionalDocumentReference[cbc:ID="QR"]'] as $query) {
            foreach (iterator_to_array($xpath->query($query)) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        return base64_encode(hash('sha256', $doc->documentElement->C14N(false, false), true));
    }

    /**
     * Saves the signed invoice as an XML file.
     *
     * @param string $filename (Optional) File path to save the XML.
     * @param string|null $outputDir (Optional) Directory name. Set to null if $filename contains the full file path.
     * @return self
     * @throws ZatcaStorageException If the XML file cannot be saved.
     */
    public function saveXMLFile(string $filename = 'signed_invoice.xml', ?string $outputDir = 'output'): self
    {
        (new Storage($outputDir))->put($filename, $this->signedInvoice);
        return $this;
    }

    /**
     * Get the signed XML string.
     *
     * @return string
     */
    public function getXML(): string
    {
        return $this->signedInvoice;
    }

    /**
     * Returns the QR node string.
     *
     * @param string $QRCode
     * @return string
     */
    private function getQRNode(string $QRCode): string
    {
        return "<cac:AdditionalDocumentReference>
        <cbc:ID>QR</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode=\"text/plain\">$QRCode</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
    <cac:Signature>
        <cbc:ID>urn:oasis:names:specification:ubl:signature:Invoice</cbc:ID>
        <cbc:SignatureMethod>urn:oasis:names:specification:ubl:dsig:enveloped:xades</cbc:SignatureMethod>
    </cac:Signature>";
    }
    /**
     * Get signed invoice XML.
     *
     * @return string
     */
    public function getInvoice(): string
    {
        return $this->signedInvoice;
    }

    /**
     * Get invoice hash.
     *
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Get QR Code.
     *
     * @return string
     */
    public function getQRCode(): string
    {
        return $this->qrCode;
    }

    /**
     * Get the certificate used for signing.
     *
     * @return Certificate
     */
    public function getCertificate(): Certificate
    {
        return $this->certificate;
    }
}