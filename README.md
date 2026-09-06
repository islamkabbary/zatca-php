# silavisions/zatca

ZATCA (Fatoora) Phase-2 e-invoicing for PHP — UBL 2.1 invoice generation, XAdES signing, QR (TLV),
CSID onboarding, and submission to ZATCA's servers.

> **Provenance.** This is a private, self-owned derivative of
> [saleh7/php-zatca-xml](https://github.com/Saleh7/php-zatca-xml) (MIT), forked at upstream **v2.2**
> plus a `Mappers/` backport and local production edits. See [`NOTICE.md`](NOTICE.md).
>
> **Namespace.** The Composer package is `silavisions/zatca`, but the PHP namespace is kept as
> **`Saleh7\Zatca\`** so existing application code (`use Saleh7\Zatca\...`) keeps working unchanged.
>
> **Baseline release (`v1.0.0`).** Reproduces the exact current production behaviour. Known defects
> are documented in [`KNOWN-ISSUES.md`](KNOWN-ISSUES.md) and are **not** fixed in this release.

## Requirements

- PHP `>= 8.1` with `mbstring`, `dom`, `libxml`, `openssl`, `hash`
- `sabre/xml ^4.0`, `guzzlehttp/guzzle ^7.9`, `phpseclib/phpseclib ^3.0` (installed automatically)

## Installation (private VCS repository)

This package is **private** and installed from its own git repository (not Packagist). In the
consuming project's `composer.json`:

```jsonc
{
  "repositories": [
    { "type": "vcs", "url": "git@github.com:silavisions/zatca.git", "no-api": true }
  ],
  "require": {
    "silavisions/zatca": "^1.0"
  }
}
```

Then:

```bash
composer require silavisions/zatca:^1.0
```

Servers and CI authenticate with a **read-only Deploy Key** (SSH) on the repository — `no-api: true`
makes Composer clone over SSH instead of using the GitHub zip API, so no API token is required.
Never commit keys or `auth.json`.

## Usage

The public API is identical to the production fork. Example (simplified):

```php
use Saleh7\Zatca\GeneratorInvoice;
use Saleh7\Zatca\InvoiceSigner;
use Saleh7\Zatca\Helpers\Certificate;

// build $invoice via the Invoice/InvoiceLine/Party/... objects, then:
$xml    = GeneratorInvoice::invoice($invoice)->getXML();
$signed = InvoiceSigner::signInvoice($xml, $certificate);
```

See [`examples/`](examples/) for end-to-end certificate-request, generation, and signing scripts.

## Versioning

Own SemVer from `v1.0.0`:

| Line | Meaning |
|---|---|
| `v1.0.0` | Production baseline (verbatim). |
| `v1.0.x` | Faithful bug fixes (documented in KNOWN-ISSUES). |
| `v1.x.0` | Backward-compatible additions. |
| `v2.0.0` | Breaking changes (e.g. `SilaVisions\Zatca` namespace with a compatibility shim). |

## License

MIT — see [`LICENSE`](LICENSE). Original © 2023 ~/Saleh; modifications © 2026 Sila Visions.