# InventoryApp

Aplicación web de inventario con backend en Symfony 7.4 y frontend en React + Vite. El proyecto centraliza productos, proveedores, lotes con vencimiento, importaciones de compras y ventas, alertas operativas, presupuestos y sincronización de valijas.

## Resumen

- Backend API en Symfony 7.4 con Doctrine, JWT y API Platform
- Frontend SPA en React 19 con React Router, Axios, Tailwind CSS y Recharts
- Base de datos MariaDB/MySQL
- Despliegue con Docker + Apache + PHP 8.2
- Build del frontend empaquetado dentro de `public/` o `public/build/` según el flujo usado

## Funcionalidades principales

- Autenticación JWT en `POST /api/login`
- Catálogo de productos con búsqueda, paginación y filtro por proveedor
- Consulta de stock por lotes y movimientos de inventario
- Importación de compras y ventas desde CSV
- Importación masiva de inventario desde XLSX por consola
- Alertas de stock bajo, vencimientos y valijas
- Gestión de valijas y sincronización de stock
- Gestión de presupuestos con exportación a Excel
- Alta de usuarios administradores y usuarios estándar

## Stack técnico

### Backend

- PHP 8.2
- Symfony 7.4
- Doctrine ORM
- API Platform
- LexikJWTAuthenticationBundle
- Symfony Mailer
- PhpSpreadsheet

### Frontend

- React 19
- Vite
- React Router DOM 7
- Axios
- Tailwind CSS
- Recharts

### Infraestructura

- Docker Compose
- Apache
- MariaDB/MySQL

## Estructura del proyecto

```text
.
├── src/                  # Backend Symfony: controladores, entidades, servicios y comandos
├── config/               # Configuración de Symfony, seguridad, Doctrine y bundles
├── frontend/             # Aplicación React/Vite
├── public/               # Punto de entrada HTTP y builds estáticos
├── docker/               # Dockerfile y vhosts de Apache
├── scripts/              # Scripts auxiliares de build/export
├── templates/            # Plantillas Twig
├── tests/                # Tests backend
├── docker-compose.dev.yml
├── docker-compose.prod.yml
├── build-frontend.sh
└── Makefile
```

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- MariaDB/MySQL
- Docker y Docker Compose si vas a ejecutar en contenedores

## Configuración

El proyecto usa `.env` como base y admite overrides con `.env.local`.

Variables relevantes:

- `APP_ENV`
- `APP_SECRET`
- `DATABASE_URL`
- `MAILER_DSN`
- `JWT_SECRET_KEY`
- `JWT_PUBLIC_KEY`
- `JWT_PASSPHRASE`
- `CORS_ALLOW_ORIGIN`

Flujo recomendado para entorno local:

```bash
cp .env .env.local
```

Después ajusta en `.env.local` al menos:

- conexión de base de datos
- claves JWT
- correo SMTP si vas a usar alertas por email

## Instalación local

### 1. Backend

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

Si no estás usando migraciones o necesitas alinear rápido el esquema:

```bash
php bin/console doctrine:schema:update --force
```

### 2. Frontend

```bash
cd frontend
npm install
```

## Ejecución en desarrollo

### Backend Symfony

Con Symfony CLI:

```bash
symfony server:start
```

O con el servidor embebido de PHP:

```bash
php -S 127.0.0.1:8000 -t public
```

### Frontend Vite

En otra terminal:

```bash
cd frontend
npm run dev
```

El frontend consume la API usando `baseURL: "/api"`, así que en producción funciona detrás del mismo host y en desarrollo depende de la configuración de Vite/proxy o del mismo origen con archivos construidos.

## Build del frontend

Para compilar el frontend y copiarlo a `public/`:

```bash
./build-frontend.sh
```

Ese script:

1. entra en `frontend/`
2. ejecuta `npm install`
3. ejecuta `npm run build`
4. copia `frontend/dist/*` dentro de `public/`

## Docker

El proyecto incluye dos composiciones:

- `docker-compose.dev.yml`
- `docker-compose.prod.yml`

Ambas esperan una red Docker externa llamada `h3_net`.

### Desarrollo con Docker

```bash
docker network create h3_net
docker compose -f docker-compose.dev.yml up --build
```

Detalles:

- contenedor: `inventory_app_dev`
- puerto publicado: `8480:80`
- volumen montado: `.:/var/www`
- build con `APACHE_ENV=dev` y `APP_ENV=dev`

### Producción con Docker

El `docker-compose.prod.yml` no construye la imagen; usa `inventory_app:latest`. La imagen se genera con el script de exportación:

```bash
./scripts/build_and_export.sh prod
docker compose -f docker-compose.prod.yml up -d
```

Detalles:

- contenedor: `inventory_app_prod`
- puerto publicado: `8480:80`
- imagen esperada: `inventory_app:latest`

## Scripts y Makefile

### Makefile

```bash
make dev
make dev-logs
make dev-down
make prod
```

### `scripts/build_and_export.sh`

Acepta `dev` o `prod`:

```bash
./scripts/build_and_export.sh dev
./scripts/build_and_export.sh prod
```

Comportamiento:

- `dev`: levanta `docker-compose.dev.yml`
- `prod`:
  - limpia `public/build`
  - compila el frontend
  - copia `frontend/dist/*` a `public/build/`
  - construye la imagen Docker
  - exporta la imagen a un archivo `inventory_app_YYYYMMDD_HHMM.tar.gz`

## API y seguridad

Reglas principales de seguridad:

- `POST /api/login` es público
- `/api/import*` requiere `ROLE_ADMIN`
- el resto de `/api` requiere autenticación JWT
- el firewall API es stateless

### Endpoints principales

#### Autenticación

- `POST /api/login`

#### Productos

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products/{id}/consume`
- `GET /api/products/{id}/movements`

Filtros disponibles en `GET /api/products`:

- `provider=<id>`
- `name=<texto>`
- `page=<n>`

La respuesta incluye:

- `data`
- `total`
- `page`
- `limit`

#### Dashboard

- `GET /api/dashboard`

#### Proveedores

- `GET /api/providers`

La entidad `Provider` también está expuesta por API Platform, por lo que existen endpoints REST automáticos adicionales según la configuración del bundle.

#### Importaciones

- `POST /api/import/purchases`
- `POST /api/import/sales`

`/api/import/purchases` acepta:

- archivo en el campo `file`
- `mode` opcional: `strict` o `create`

`/api/import/sales` acepta:

- archivo en el campo `file`

#### Alertas

- `GET /api/alerts`
- `GET /api/alerts/details?type=<tipo>`
- `GET /api/alerts/summary`

Tipos observados:

- `low_stock`
- `warning`
- `expired`
- `valija_low`
- `valija_critical`

#### Valijas

- `GET /api/valijas`
- `POST /api/valijas`
- `GET /api/valijas/{id}`
- `DELETE /api/valijas/{id}`
- `POST /api/valijas/{id}/products`
- `PUT /api/valijas/products/{id}`
- `DELETE /api/valijas/products/{id}`
- `POST /api/valijas/{id}/sync`
- `POST /api/valijas/sync`

Ejemplo de alta de producto en valija:

```json
{
  "productId": 123,
  "stockMin": 10
}
```

#### Presupuestos

- `POST /api/budgets`
- `GET /api/budgets`
- `GET /api/budgets/{id}`
- `PUT /api/budgets/{id}`
- `DELETE /api/budgets/{id}`
- `GET /api/budgets/{id}/export/excel`

Estas rutas están protegidas con `ROLE_ADMIN`.

#### Usuarios

- `POST /api/users`

También requiere `ROLE_ADMIN`.

## Frontend

Rutas visibles en la SPA:

- `/login`
- `/`
- `/products/:id`
- `/providers/:id`
- `/dashboard`
- `/import`
- `/valijas`
- `/valijas/:id`
- `/alerts`
- `/budgets`
- `/budgets/new`
- `/budgets/:id`
- `/users/new`

Comportamiento relevante:

- el token JWT se guarda en `localStorage`
- Axios añade automáticamente `Authorization: Bearer <token>`
- si la API responde `401`, el frontend elimina el token y redirige a `/login`
- las rutas administrativas se protegen en cliente con `ROLE_ADMIN`

## Comandos Symfony útiles

### Usuarios

```bash
php bin/console app:create:user user@example.com secret
php bin/console app:create:user admin@example.com secret --admin
```

### Importaciones

```bash
php bin/console app:import:purchases /ruta/archivo.csv create
php bin/console app:import:purchases /ruta/archivo.csv strict
php bin/console app:import:sales /ruta/ventas.csv
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx "costos-venta detallado"
```

### Inventario y valijas

```bash
php bin/console app:inventory:check
php bin/console app:valija:sync
```

## Importación de datos

### CSV

Las importaciones HTTP y CLI reutilizan:

- `App\Service\PurchaseCsvImporter`
- `App\Service\SalesCsvImporter`

### XLSX

El comando `app:import:inventory-xlsx`:

- detecta la fila de cabecera buscando `CODIGO`
- normaliza cabeceras del archivo
- crea o reutiliza proveedores, categorías y productos
- registra históricos de costo
- reemplaza lotes previos del producto cuando importa stock nuevo

Columnas relevantes detectadas:

- `CODIGO`
- `PRODUCTO`
- `MARCA`
- `PROCEDIMIENTO`
- `GRUPO`
- `SUBGRUPO`
- `COSTO_DIRECTO`
- `ENVIO_NACIONALIZACION`
- `COSTE_TOTAL`
- `EXISTENCIA`
- `FECHA_VENCIMIENTO`

## Tests y comprobaciones

Backend:

```bash
php bin/phpunit
```

Frontend:

```bash
cd frontend
npm run lint
```

## Observaciones

- El repositorio incluye builds generados dentro de `public/` y `frontend/public/assets/`
- También hay un artefacto exportado de Docker: `inventory_app_20260513_1552.tar.gz`
- Existe un `SendAlertsCommand`, pero actualmente comparte el mismo nombre de comando Symfony que `InventoryCheckCommand`; conviene corregirlo antes de documentar un comando separado para envío de alertas
