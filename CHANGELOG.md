# Changelog

All notable changes to `silavisions/zatca` are documented here. This project adheres to
[Semantic Versioning](https://semver.org) from its own baseline (see below).

## [1.0.0] — Production baseline

First standalone release. Extracted **verbatim** from the ZATCA fork running in `pro-erp`
production (based on upstream `saleh7/php-zatca-xml` v2.2 + `Mappers/` backport + local edits).

- **Source (`src/`) is byte-identical** to the production package. No behaviour was changed,
  refactored, modernized, or "fixed" in this release.
- PHP namespace kept as `Saleh7\Zatca\` for backward compatibility (Composer name is
  `silavisions/zatca`).
- Packaging only (no runtime impact): standalone `composer.json` (own SemVer, no `version`
  field — git tags provide the version), `.gitattributes` (LF + `export-ignore`), `.gitignore`
  (excludes key material), `phpunit.xml.dist`, `NOTICE.md`, `README.md`, this changelog, and
  `KNOWN-ISSUES.md`. Demo key material under `examples/output/` is excluded from the repository.
- The test namespace `Saleh7\Zatca\Tests\` moved from `autoload` to `autoload-dev` (no consumer
  references it; keeps it out of production installs).

Known defects are documented in [`KNOWN-ISSUES.md`](KNOWN-ISSUES.md) and are deliberately left
unfixed in this baseline. Fixes will land in `1.0.x`.

[1.0.0]: https://github.com/silavisions/zatca/releases/tag/v1.0.0