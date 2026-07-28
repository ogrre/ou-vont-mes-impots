# Mais où vont mes impôts ? — API

[![PHP requirement](https://img.shields.io/badge/PHP-%5E8.3-777bb4.svg)](composer.json)
[![Laravel requirement](https://img.shields.io/badge/Laravel-%5E13.0-ff2d20.svg)](composer.json)

The Laravel API for **“Mais où vont mes impôts ?”**, an open-source educational
project that aims to make French public revenue and expenditure easier to
understand through neutral, accessible, and traceable official data.

This repository contains the backend only. The frontend is maintained
separately.

> **Development status:** this repository is currently an early Laravel
> skeleton. The public-finance schema, source importers, and REST API endpoints
> described as planned below have not yet been implemented. The only
> application route is the default welcome page at `GET /`; Laravel also
> exposes its health route at `GET /up`.

## Project goals

- Explain where French public money comes from and how revenue is collected.
- Show how expenditure is distributed across healthcare, education, defence,
  social protection, immigration-related policies, public debt, and other
  public services.
- Make changes over time understandable without hiding changes in accounting
  scope, definitions, or methodology.
- Link every published figure to enough provenance information for it to be
  checked against its original official source.
- Present public-finance information without political opinion or
  interpretation.

## Key features

The following capabilities are planned and under development:

- A public, read-only REST API with no visitor accounts or authentication.
- Imports from official CSV, Excel, PDF-derived datasets, and open-data
  portals.
- Normalisation of heterogeneous source data into a consistent relational
  model.
- Historical series and expenditure/revenue breakdowns.
- Source, institution, reporting-period, and import-level traceability.

At present, none of these domain features is exposed by the application.

## Architecture overview

The current codebase is a Laravel 13 application backed by PostgreSQL in the
development environment. Docker Compose defines three services:

- `app`: PHP-FPM 8.4 with the PostgreSQL, Redis, Xdebug, and other PHP
  extensions required by the development image;
- `web`: Nginx, published on port `8080` by default;
- `pgsql`: PostgreSQL 16 with a persistent Docker volume.

Laravel is configured with database-backed queues, cache, and sessions by
default. These are framework defaults at this stage, not evidence of an
implemented asynchronous import pipeline or visitor session feature.

The repository still includes Laravel's default `User` model and user-related
migration. No authentication routes or visitor account functionality are
registered.

## Data model and data-normalisation approach

The domain data model is not implemented yet. The intended approach is to keep
two conceptually separate layers:

1. **Raw imported data** preserves source records or extracted payloads as
   faithfully as practical, together with import diagnostics and acquisition
   metadata.
2. **Normalised application data** represents institutions, datasets,
   reporting periods, accounting scopes, classifications, units, and financial
   observations in relational form suitable for consistent API queries.

PostgreSQL will store the normalised relational data. JSONB may be used for
source-specific metadata or raw imported payloads where their shape varies, but
it should not replace well-defined relational fields used for filtering,
linking, or validation.

Transformation rules should be deterministic, documented, and tested. Units,
sign conventions, classifications, revisions, missing values, and rounding
must be handled explicitly. Raw values should remain available so a normalised
figure can be audited without relying on an undocumented manual correction.

## Data provenance and traceability

Every published figure should remain linked to:

- its official source and source URL;
- the specific dataset or publication;
- the import run that acquired or produced it;
- its reporting period and publication date;
- the applicable public institution;
- its accounting scope, classification, and unit;
- the transformations applied between raw and normalised values.

Imports should record source versions, retrieval times, and integrity
information where available. If a PDF table is converted into a structured
dataset, the extraction method and the location of the original table should
also be documented.

This provenance model is planned; the current migrations contain only the
default Laravel users, password-reset, sessions, cache, and queue tables.

## Technology stack

- PHP `^8.3`
- Laravel `^13.0`
- PostgreSQL 16 in Docker Compose
- Nginx 1.27 and PHP-FPM 8.4 in the development containers
- PHPUnit `^12.5`
- Laravel Pint `^1.27`
- Vite 8 and Tailwind CSS 4 for the current Laravel welcome-page assets

## Requirements

For the recommended container-based setup:

- Docker with Docker Compose
- Git

For a native setup:

- PHP 8.3 or later with the extensions required by Laravel and PostgreSQL
- Composer 2
- PostgreSQL
- Node.js and npm only if building the current Vite assets

## Local installation

Clone the repository, then prepare the environment:

```bash
git clone [TODO: add the repository URL]
cd ou-vont-mes-impots
cp .env.example .env
docker compose -f docker-compose-dev.yml up -d --build
docker compose -f docker-compose-dev.yml exec app composer install
docker compose -f docker-compose-dev.yml exec app php artisan key:generate
```

The checked-in PHP image includes Composer. It does not include Node.js, so run
`npm install` and `npm run build` on the host if you need to rebuild the
welcome-page assets.

## Environment configuration

`.env.example` contains the development defaults. The main variables are:

| Variable | Purpose | Development default |
| --- | --- | --- |
| `APP_URL` | Base application URL | `http://localhost:8080` |
| `APP_DEBUG` | Detailed local error output | `true` |
| `DB_CONNECTION` | Laravel database driver | `pgsql` |
| `DB_HOST` | Database host inside Compose | `pgsql` |
| `DB_PORT` | Database port inside Compose | `5432` |
| `DB_DATABASE` | PostgreSQL database name | `ovmi` |
| `DB_USERNAME` | PostgreSQL user | `ovmi` |
| `DB_PASSWORD` | Local PostgreSQL password | `ovmi` |
| `DOCKER_WEB_PORT` | Host port for Nginx | `8080` |
| `DOCKER_DB_PORT` | Host-bound PostgreSQL port | `5432` |

The example credentials are for local development only. Never commit a real
`.env` file or production credentials.

## Database setup

Start the containers and run the existing migrations:

```bash
docker compose -f docker-compose-dev.yml up -d
docker compose -f docker-compose-dev.yml exec app php artisan migrate
```

The migrations currently create only Laravel's default framework tables. No
public-finance tables or seed data are implemented. `DatabaseSeeder` creates a
sample user if explicitly run; it is not needed to start the application.

## Running data imports

**Under development.** No custom import command or import service exists yet.
Do not treat files in `data/` as imported or validated merely because they are
present in the repository.

When import commands are added, document their exact syntax, expected source
files, idempotency behaviour, validation rules, and generated provenance
records here.

## Running the API locally

With Docker Compose:

```bash
docker compose -f docker-compose-dev.yml up -d
curl http://localhost:8080/up
```

For a native PHP environment with dependencies and `.env` configured:

```bash
php artisan serve
```

The native server defaults to `http://127.0.0.1:8000`.

## API documentation and endpoint overview

The REST API is **not implemented yet**. There is no `routes/api.php` file.
Current application routes are:

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/` | Default Laravel welcome page |
| `GET` | `/up` | Laravel health check |

`/up` is framework infrastructure rather than a public-finance endpoint.

Planned API documentation should define response schemas, filters, pagination,
units, accounting scopes, reporting periods, provenance links, errors, and
versioning. [TODO: add the production API URL when available]

## Running tests

The PHPUnit configuration uses an in-memory SQLite database for tests:

```bash
docker compose -f docker-compose-dev.yml exec app composer test
```

For a native installation:

```bash
composer test
```

The current suite contains only Laravel's example unit and feature tests.
Domain, import, provenance, and API contract tests remain to be written.

## Code quality tools

Laravel Pint is installed for PHP formatting:

```bash
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint --test
docker compose -f docker-compose-dev.yml exec app ./vendor/bin/pint
```

The first command checks formatting without changing files; the second applies
formatting. No static analyser, coverage service, or Markdown linter is
currently configured.

## Project structure

```text
app/                 Laravel application code (currently framework skeleton)
bootstrap/           Laravel application bootstrap
config/              Application and service configuration
data/                Candidate source files; not yet imported or validated
database/            Migrations, factories, and seeders
docker/              PHP-FPM image and Nginx configuration
public/              HTTP entry point and public assets
resources/           Current welcome-page CSS, JavaScript, and Blade view
routes/              Web and console routes; no API routes yet
tests/               PHPUnit unit and feature tests
docker-compose-dev.yml
```

## Data sources

The `data/` directory currently contains candidate CSV, XLS, XLSX, and PDF
files. The repository does not yet include a source catalogue, machine-readable
provenance, import implementation, or enough documentation to verify each
file's publisher and acquisition URL.

[TODO: document each approved official source, publisher, canonical URL,
publication date, licence/reuse terms, accounting scope, reporting period, and
retrieval date]

Only official or otherwise clearly authoritative data should support published
figures. Adding a file to `data/` does not by itself approve it as a source.

## Data accuracy and limitations

Public-finance figures are sensitive to definitions and accounting perimeter.
Figures from different public accounting scopes must not be compared without
first checking that their perimeter, period, unit, classification, and
methodology are compatible.

“Public expenditure” may refer to the French State budget, local authorities,
social-security administrations, or all public administrations. These scopes
are not interchangeable. Sources may also report commitments, payments,
appropriations, consolidated expenditure, national-accounts measures, or
revised estimates.

The API should expose these qualifications alongside values. Imported data may
contain source errors, revisions, extraction errors, gaps, or rounding
differences. Users should verify consequential uses against the cited original
publication.

## Contributing

Contributions are welcome, especially improvements to source traceability,
normalisation rules, tests, and documentation. Read
[`CONTRIBUTING.md`](CONTRIBUTING.md) before opening an issue or pull request.
Data contributions must identify their official origin and document every
transformation; undocumented manual corrections are not accepted.

## Security

Do not disclose vulnerabilities in a public issue. Follow the private reporting
instructions in [`.github/SECURITY.md`](.github/SECURITY.md).

Incorrect figures, classifications, or source metadata are normally
data-quality issues rather than security vulnerabilities, unless they result
from or expose a security weakness.

## Roadmap

- Define the public-finance domain schema and provenance constraints.
- Catalogue and license-check the candidate official sources.
- Implement repeatable, validated imports for supported formats.
- Add domain and import tests, including reconciliation checks.
- Design and implement the versioned, read-only REST API.
- Publish an API specification and connect the separate frontend.
- Add continuous integration and automated quality checks.

These items are planned, not completed commitments or release dates.

## Licence

This project is licensed under the **MIT License**. See [`LICENSE`](LICENSE).
Source datasets and publications may have their own licences or reuse
conditions; the MIT License does not override them.

## Disclaimer

> **This independent project is not affiliated with, endorsed by, or operated
> by the French government or any public administration.**

It is an educational aid, not an official accounting publication, legal
advice, financial advice, or a substitute for consulting the original sources.
The project aims for neutrality and traceability but cannot guarantee that all
figures are complete, current, or free from error.
