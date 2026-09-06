# Known Issues

> The `v1.0.0`/`v1.0.1` baseline reproduced production behaviour exactly (bugs documented, not fixed).
> Faithful bug fixes land in the `1.0.x` line — see **Fixed in `v1.0.2`** below. Remaining items are
> still documented, not fixed. Full analysis and evidence live in the ZATCA audit report
> ("تشريح الـ PAC").

## Fixed in `v1.0.2` (simplified/B2C clearance)

Three of the four ZATCA rejections were fixed in `v1.0.2`, verified offline against a ZATCA-accurate
hash recomputation on real production invoices (embedded hash == ZATCA's own strip-ext/sig/QR → C14N
→ SHA-256; QR TLV, signature, and timestamp checked). **Verify on the ZATCA sandbox before relying on
them in production.**

| ZATCA code | Fix |
|---|---|
| `XML-INVOICE-ERROR` | `src/InvoiceType.php` — the `'simplified'` arm now maps to `SIMPLIFIED_INVOICE` (`0200000`) instead of `STANDARD_INVOICE` (`0100000`). This also makes the QR include the mandatory certificate-signature tag for simplified invoices. |
| `invoiceHash_QRCODE_INVALID` | `src/InvoiceSigner.php` — after assembly the hash is recomputed exactly as ZATCA does (strip `UBLExtensions`/`Signature`/`QR` → C14N → SHA-256); if whitespace introduced while inserting those elements changed it, the QR + signature are rebuilt once with that authoritative hash. Invoices whose hash was already consistent (the previously-cleared documents) are left unchanged. |
| `invoiceTimeStamp_QRCODE_INVALID` | `src/Helpers/InvoiceExtension.php` — the QR timestamp now mirrors `cbc:IssueTime` verbatim instead of force-appending a `Z` the XML lacks (KSA-25). |

## Confirmed by ZATCA in production — still open

| ZATCA code | Description | Location |
|---|---|---|
| `BR-CO-*` tax rounding | VAT amount ≠ taxable × rate rounded to 2 dp — invoice-level `TaxAmount` written with 1 decimal. | `src/Invoice.php` (`number_format(..., 1, ...)`) |

> **Not** fixed in `v1.0.2`: this touches monetary formatting across several fields, needs its own
> review + sandbox verification, and did **not** fire in the 2026-09-06 rejection that prompted the
> other three fixes.

## Other documented defects

- **`Storage::$basePath` is a static that `saveXMLFile()` poisons** for the whole process and never
  restores — later reads can resolve against the wrong base path. (`src/Storage.php`,
  `src/GeneratorInvoice.php`)
- **`Mappers/CustomerMapper.php`** throws (swallowed upstream in the app) for a buyer without a VAT
  number, so such credit notes are silently not produced.
- **`Tag.php` TLV length** uses `sprintf("%02X", …)` which corrupts the QR when any tag value exceeds
  255 bytes (e.g. a long Arabic seller name).
- **HTTP Basic auth uses single base64** of the binary security token (a different contract from
  upstream v2.4's double-base64) — relevant only to a future upstream upgrade.

## Framework coupling (installability caveat)

`src/ZatcaAPI.php` uses `Illuminate\Support\Facades\Log` (`Log::channel('zatca_logs')`) on its
error/`sendRequest`-failure paths, but does **not** declare `illuminate/support` as a dependency.

- Inside a Laravel app that defines a `zatca_logs` channel (the current consumers), this works.
- In a non-Laravel context, code paths that hit those `Log::` calls will fatal.

Decoupling to PSR-3 (`Psr\Log\LoggerInterface`) is planned for a later, framework-independence
release and is intentionally **not** done in the baseline.

## Tests

The test suite is shipped as-is. Some tests are known to be red against this fork (they assert
upstream-v2.4 contracts the fork deliberately changed). CI runs them as **informational** for the
baseline; making them green would require changing code/tests and is out of scope for `v1.0.0`.