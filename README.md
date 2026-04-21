# InventoryApp

InventoryApp is a Symfony 7.4 inventory backend for importing product data from CSV files and exposing product records through a JSON API.

The application stores products, providers, hierarchical categories, product cost history, and inventory batches with Doctrine ORM.

## Features

- CSV inventory import command: `app:import:inventory`
- Product listing endpoint: `GET /api/products`
- Provider normalization by name
- Category tree import from procedure, group, and subgroup columns
- Product cost history records with direct, shipping, and total costs
- Doctrine ORM entities using PHP attributes
- Symfony Console, Twig, API Platform, Messenger, and PHPUnit dependencies

## Requirements

- PHP 8.2 or newer
- Composer
- A database supported by the configured `DATABASE_URL`
- Docker and Docker Compose, if running through containers

The current `.env` file is configured for MariaDB:

```dotenv
DATABASE_URL="mysql://root:kubiadmin@mariadb:3306/inventory?serverVersion=10.6"
```

The PHP Docker image includes MySQL PDO extensions. The Symfony-generated `compose.yaml` also defines a PostgreSQL service, but it does not match the current `DATABASE_URL`. Use one database setup consistently before running the app.

## Installation

Install dependencies:

```bash
composer install
```

Create a local environment override if needed:

```bash
cp .env .env.local
```

Update `DATABASE_URL` in `.env.local` for your database.

Create or update the database schema. This project currently has no committed Doctrine migrations, so for local development you can use:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
```

For production, generate and review migrations instead:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## Running Locally

Start the Symfony development server if it is installed:

```bash
symfony server:start
```

Or use PHP's built-in server:

```bash
php -S 127.0.0.1:8000 -t public
```

The product endpoint will be available at:

```text
GET http://127.0.0.1:8000/api/products
```

## Running With Docker

The `docker-compose.yml` file builds an Apache/PHP container and exposes the app on port `8000`:

```bash
docker compose -f docker-compose.yml up --build
```

The container expects an external Docker network named `h3_net`:

```bash
docker network create h3_net
```

Make sure a database container is reachable with the hostname used in `DATABASE_URL` before starting the application. With the current `.env`, that hostname is `mariadb`.

## CSV Import

Import inventory with:

```bash
php bin/console app:import:inventory path/to/inventory.csv
```

Expected CSV columns:

```text
CODIGO
PRODUCTO
MARCA
PROCEDIMIENTO
GRUPO
SUBGRUPO
COSTO_DIRECTO
ENVIO_NACIONALIZACION
COSTE_TOTAL
```

Import behavior:

- Rows without `CODIGO` or `PRODUCTO` are skipped.
- `MARCA` is used as the provider name and product brand.
- `PROCEDIMIENTO`, `GRUPO`, and `SUBGRUPO` are imported as a category hierarchy.
- New products are created with a default minimum stock of `10`.
- A `ProductCost` row is created when `COSTE_TOTAL` is greater than `0`.
- The import flushes and clears Doctrine every 100 rows.

## API

### List Products

```http
GET /api/products
```

Response shape:

```json
[
  {
    "id": 1,
    "sku": "ABC-123",
    "name": "Example Product",
    "brand": "Example Brand",
    "minStock": 10
  }
]
```

## Domain Model

- `Product`: SKU, name, brand, minimum stock, provider, category, and inventory batches.
- `Provider`: normalized provider name plus optional email, phone, contact person, and address.
- `Category`: self-referencing category tree with unique name and parent combinations.
- `ProductCost`: cost history for a product, including direct cost, shipping/import cost, total cost, and creation date.
- `InventoryBatch`: product quantity batches with optional expiration dates.

## Tests

Run PHPUnit:

```bash
php bin/phpunit
```

At the time this README was created, the test suite only contained `tests/bootstrap.php`; no application tests were present.

## Useful Commands

```bash
php bin/console debug:router
php bin/console list app
php bin/console doctrine:schema:validate
php bin/console cache:clear
```

## Notes

- The project requires PHP `>=8.2`. Running console commands with PHP 7.4 will fail during Composer platform checks.
- `vendor/` and `var/` are present in this working copy. In a normal repository, they are usually excluded from version control.
- There are no committed Doctrine migration files yet.
