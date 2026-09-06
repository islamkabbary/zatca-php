# Known Issues — `v1.0.0` baseline

> These are **documented, not fixed.** The `v1.0.0` baseline reproduces production behaviour exactly.
> Fixes are scheduled for `1.0.x` and later. Full analysis and evidence live in the ZATCA audit
> report ("تشريح الـ PAC").

## Confirmed by ZATCA in production (rejections on the simplified/B2C path)

These four fire together on invoices built through the local B2C service path; ZATCA's validator
returned them on real rejected documents.

| ZATCA code | Description | Location |
|---|---|---|
| `XML-INVOICE-ERROR` | "XML submitted using reporting API is not a simplified document" — simplified invoices are stamped with the standard subtype `0100000`. | `src/InvoiceType.php` (`'simplified'` arm → `STANDARD_INVOICE`) |
| `invoiceHash_QRCODE_INVALID` | Invoice XML hash ≠ QR hash — whitespace injected after the hash is computed. | `src/InvoiceSigner.php` (`PHP_EOL . "    "` in the post-hash splice) |
| `invoiceTimeStamp_QRCODE_INVALID` | QR timestamp ≠ invoice IssueTime — XML `IssueTime` lacks the trailing `Z` that the QR adds. | `src/Invoice.php` (`IssueTime` format) |
| `BR-CO-*` tax rounding | VAT amount ≠ taxable × rate rounded to 2 dp — invoice-level `TaxAmount` written with 1 decimal. | `src/Invoice.php` (`number_format(..., 1, ...)`) |

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