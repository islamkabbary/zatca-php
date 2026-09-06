# silavisions/zatca-php

ZATCA (Fatoora) Phase-2 e-invoicing for PHP — UBL 2.1 invoice generation, XAdES signing, QR (TLV),
CSID onboarding, and submission to ZATCA's servers.

> **Provenance.** A self-owned derivative of
> [saleh7/php-zatca-xml](https://github.com/Saleh7/php-zatca-xml) (MIT), forked at upstream **v2.2**
> plus a `Mappers/` backport and local production edits. See [`NOTICE.md`](NOTICE.md).
>
> **Package name vs PHP namespace — read this.** The Composer package is **`silavisions/zatca-php`**,
> but the PHP namespace is intentionally kept as **`Saleh7\Zatca\`**. A Composer package name and its
> PHP namespace do not have to match, and here they differ **on purpose**: existing application code
> that already does `use Saleh7\Zatca\...` keeps working unchanged after adopting this package — no
> find-and-replace, no breakage. That backward compatibility is the whole point of keeping the old
> namespace.
>
> **Baseline behaviour (`v1.0.0`).** Reproduces the exact current production behaviour. Known defects
> are documented in [`KNOWN-ISSUES.md`](KNOWN-ISSUES.md) and are **not** fixed. `v1.0.1` is a
> metadata/packaging correction only (package rename + link updates) — **no runtime source changed**.

## Requirements

- PHP `>= 8.1` with `mbstring`, `dom`, `libxml`, `openssl`, `hash`
- `sabre/xml ^4.0`, `guzzlehttp/guzzle ^7.9`, `phpseclib/phpseclib ^3.0` (installed automatically)

## Installation

This package is **not on Packagist yet**, so install it from its **public** GitHub repository by
adding a Composer **VCS repository** to the consuming project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/islamkabbary/zatca-php"
        }
    ]
}
```

Then require it. The repository is **public**, so no SSH key, Deploy Key, or access token is needed:

```bash
composer require silavisions/zatca-php:^1.0
```

The current release is **`v1.0.1`**. To pin an exact version instead of tracking the `1.0.x` line,
use:

```bash
composer require silavisions/zatca-php:1.0.1
```

## Packagist

This package is **not published on Packagist yet**, so the VCS-repository entry above is required —
`composer require silavisions/zatca-php` on its own will **not** resolve without it.

Once the package is published to Packagist, the `repositories` entry can be removed and installation
becomes simply:

```bash
composer require silavisions/zatca-php
```

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
| `v1.0.0` | Production baseline (verbatim extraction). |
| `v1.0.1` | Metadata/packaging correction — package renamed to `silavisions/zatca-php`, links updated. No runtime change. |
| `v1.0.x` | Faithful bug fixes (documented in KNOWN-ISSUES). |
| `v1.x.0` | Backward-compatible additions. |
| `v2.0.0` | Breaking changes (e.g. a `SilaVisions\Zatca` namespace with a compatibility shim). |

## License

MIT — see [`LICENSE`](LICENSE). Original © 2023 ~/Saleh; modifications © 2026 Sila Visions.
