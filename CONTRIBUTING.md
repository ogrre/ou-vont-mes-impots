# Contributing

Thank you for helping make French public-finance data easier to understand and
verify. Contributions should remain factual, neutral, reproducible, and
traceable to authoritative sources.

This project is at an early stage: its domain schema, imports, and REST API are
still under development.

## Reporting a data-quality problem

Open a GitHub issue and describe:

- the affected figure, endpoint, dataset, or file;
- the expected and observed values;
- the official source URL and, where possible, the relevant table or page;
- the reporting period, unit, accounting scope, and institution;
- whether the issue appears to concern extraction, transformation,
  classification, rounding, source metadata, or a source revision;
- enough steps or evidence to reproduce the discrepancy.

Do not report an ordinary incorrect figure as a security vulnerability. Use
the security process only if the problem results from or reveals a security
weakness.

## Proposing a new official data source

Open an issue before implementing a substantial new importer. Include:

- the publisher and responsible public institution;
- the canonical source and download URLs;
- the dataset or publication title;
- publication and retrieval dates;
- licence or reuse terms;
- covered reporting periods;
- accounting scope and perimeter;
- units, classifications, and update frequency;
- available formats and known quality limitations;
- how the source complements or differs from existing sources.

A source proposal should also explain how it can be imported repeatably and
how revisions or replaced files can be detected.

## Reporting a bug

Search existing issues first. A useful report includes the expected behaviour,
actual behaviour, reproduction steps, relevant route or command, environment
details, and a minimal error excerpt with secrets removed.

Do not post credentials, private environment files, access tokens, or
unredacted sensitive logs.

## Development setup

The recommended setup uses Docker Compose:

```bash
cp .env.example .env
docker compose -f docker-compose-dev.yml up -d --build
docker compose -f docker-compose-dev.yml exec app composer install
docker compose -f docker-compose-dev.yml exec app php artisan key:generate
docker compose -f docker-compose-dev.yml exec app php artisan migrate
```

The application is then available at `http://localhost:8080`; check it with:

```bash
curl http://localhost:8080/up
```

The PHP container does not include Node.js. If a change affects the existing
Vite assets, install and build them on a host with Node.js and npm:

```bash
npm install
npm run build
```

## Coding standards

- Follow the existing Laravel conventions and PSR-4 namespace layout.
- Use Laravel Pint for PHP formatting.
- Keep controllers focused and put source-specific parsing and normalisation in
  appropriately tested classes when those layers are introduced.
- Prefer explicit types, clear names, and small deterministic transformations.
- Keep public-finance descriptions neutral and distinguish source facts from
  project transformations.
- Update documentation whenever routes, commands, configuration, schema, or
  operational steps change.

Check PHP formatting with:

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
```

Apply formatting with:

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint
```

No static-analysis or Markdown-linting tool is currently configured.

## Data import requirements

Every newly imported dataset must document:

- origin and responsible institution;
- canonical page URL and direct download URL, where available;
- publication date and retrieval date;
- accounting scope and institutional perimeter;
- reporting period;
- unit and scale;
- source format and relevant sheet, table, or page;
- all extraction, mapping, filtering, conversion, aggregation, sign, rounding,
  and normalisation rules;
- revision behaviour and known limitations.

Preserve the raw imported representation or sufficient acquisition evidence to
audit normalised records. Every published value should remain linked to its
source, dataset, import run, reporting period, and applicable institution.

Do not make undocumented manual corrections to financial data. Corrections
must be reproducible in code or a reviewed, versioned mapping with a documented
rationale and supporting source.

Never compare or combine figures across the French State budget, local
authorities, social-security administrations, or all public administrations
without explicitly validating compatibility of their accounting perimeters.

## Testing requirements

Run the current test suite:

```bash
docker compose -f docker-compose-dev.yml exec app composer test
```

New behaviour should include focused tests. Import work should test parsing,
units, transformations, validation failures, idempotency, and provenance.
API work should test response contracts, filters, error cases, and protection
against accidental writes. Add regression fixtures that are as small as
possible and whose redistribution terms allow inclusion.

The existing tests are only Laravel examples; contributors should not treat
their passing as domain-data validation.

## Pull-request expectations

- Keep each pull request focused and explain why the change is needed.
- Link related issues and identify any migration, configuration, or
  compatibility impact.
- Summarise tests and formatting checks performed.
- Update the README and other documentation for user-visible or operational
  changes.
- For data work, include the required source metadata and transformation rules,
  plus reconciliation or validation evidence.
- Do not commit `.env`, credentials, generated secrets, dependency directories,
  or unrelated source files.
- Clearly label incomplete work and follow-up tasks.

Maintainers may request smaller fixtures or removal of source files whose
licence or provenance is unclear.

## Security

Report vulnerabilities privately according to
[`.github/SECURITY.md`](.github/SECURITY.md), not through a public issue.

## Licence

By contributing, you agree that your contribution may be distributed under
the project's [MIT License](LICENSE). Data files remain subject to their
applicable source licences and reuse conditions.
