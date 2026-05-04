# InventoryApp

Aplicación de inventario con backend en Symfony 7.4 y frontend en React 19 + Vite. El sistema cubre autenticación con JWT, gestión de productos y proveedores, importación de compras y ventas, control de stock por lotes, alertas de inventario y sincronización de valijas.

## Stack

- Backend: Symfony 7.4, Doctrine ORM, API Platform, LexikJWTAuthenticationBundle
- Frontend: React 19, React Router 7, Axios, Vite, Tailwind CSS, Recharts
- Base de datos: MariaDB/MySQL
- Infraestructura: Docker Compose con Apache + PHP

## Funcionalidades

- Login con JWT en `POST /api/login`
- Listado y detalle de productos
- Consumo de stock y consulta de movimientos
- Dashboard con métricas de inventario
- Listado y detalle de proveedores
- Importación de compras y ventas desde CSV
- Importación de inventario desde XLSX por comando
- Alertas de stock bajo, vencimientos y estado de valijas
- Gestión de valijas:
  - listado de valijas
  - configuración de productos por valija
  - edición de stock mínimo por producto
  - sincronización individual o masiva

## Estructura

```text
.
├── src/                 # Backend Symfony
│   ├── Command/         # Comandos CLI
│   ├── Controller/      # Endpoints HTTP
│   ├── Entity/          # Entidades Doctrine
│   ├── Repository/      # Repositorios
│   └── Service/         # Lógica de negocio
├── frontend/            # Frontend React/Vite
│   ├── src/
│   └── public/
├── config/              # Configuración Symfony
├── public/              # Front controller y build del frontend
├── templates/           # Plantillas Twig
├── docker/              # Dockerfiles y vhosts
└── tests/
```

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js 20 o superior
- npm
- MariaDB/MySQL accesible desde `DATABASE_URL`
- Docker y Docker Compose si vas a levantar el proyecto en contenedores

## Configuración

El proyecto incluye esta base en `.env`:

```dotenv
DATABASE_URL="mysql://root:kubiadmin@mariadb:3306/inventory?serverVersion=10.6"
```

Si trabajas fuera de Docker:

```bash
cp .env .env.local
```

Ajusta en `.env.local` al menos:

- `DATABASE_URL`
- configuración JWT si cambias claves o rutas
- correo/notificaciones si vas a automatizar alertas

## Instalación

### Backend

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
```

### Frontend

```bash
cd frontend
npm install
```

## Desarrollo

### Backend Symfony

Con Symfony CLI:

```bash
symfony server:start
```

O con PHP:

```bash
php -S 127.0.0.1:8000 -t public
```

### Frontend Vite

En otra terminal:

```bash
cd frontend
npm run dev
```

## Build del frontend

Para compilar y copiar el frontend a `public/`:

```bash
./build-frontend.sh
```

Ese script:

1. entra en `frontend/`
2. ejecuta `npm install`
3. ejecuta `npm run build`
4. copia `frontend/dist/*` dentro de `public/`

## Docker

El `docker-compose.yml` levanta un contenedor `app` con Apache y PHP y expone la aplicación en el puerto `8000`.

```bash
docker network create h3_net
docker compose up --build
```

Notas:

- El compose espera una red externa llamada `h3_net`
- El contenedor monta el proyecto en `/var/www`
- La aplicación usa el host `mariadb` en `DATABASE_URL`, por lo que esa base debe existir y ser accesible desde la red Docker

## Autenticación

Login:

```http
POST /api/login
Content-Type: application/json
```

Body:

```json
{
  "email": "admin@example.com",
  "password": "secret"
}
```

Respuesta:

```json
{
  "token": "jwt-token"
}
```

El frontend usa `axios` con interceptores para:

- añadir `Authorization: Bearer <token>`
- limpiar el token y redirigir a `/login` cuando el backend responde `401`

## Endpoints principales

Todas las rutas `/api` requieren autenticación, excepto `/api/login`. Las rutas de importación HTTP requieren `ROLE_ADMIN`.

### Productos

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products/{id}/consume`
- `GET /api/products/{id}/movements`

Filtros disponibles en `GET /api/products`:

- `provider=<id>`
- `name=<texto>`
- `page=<n>`

La respuesta del listado incluye:

- `data`
- `total`
- `page`
- `limit`

### Dashboard

- `GET /api/dashboard`

### Proveedores

- `GET /api/providers`
- `GET /api/providers/{id}`

### Importaciones

- `POST /api/import/purchases`
- `POST /api/import/sales`

`/api/import/purchases` acepta:

- archivo en el campo `file`
- modo opcional `mode` con valores `strict` o `create`

`/api/import/sales` acepta:

- archivo en el campo `file`

### Alertas

- `GET /api/alerts`
- `GET /api/alerts/details?type=<tipo>`

Tipos observados en el sistema:

- `low_stock`
- `warning`
- `expired`
- `valija_low`
- `valija_critical`

### Valijas

- `GET /api/valijas`
- `GET /api/valijas/{id}`
- `POST /api/valijas/{id}/products`
- `PUT /api/valijas/products/{id}`
- `DELETE /api/valijas/products/{id}`
- `POST /api/valijas/{id}/sync`
- `POST /api/valijas/sync`

`POST /api/valijas/{id}/products` acepta:

```json
{
  "productId": 123,
  "stockMin": 10
}
```

## Comandos útiles

### Usuarios

Crear usuario normal:

```bash
php bin/console app:create:user user@example.com secret
```

Crear administrador:

```bash
php bin/console app:create:user admin@example.com secret --admin
```

### Importaciones

Importar compras:

```bash
php bin/console app:import:purchases /ruta/archivo.csv create
```

Importar ventas:

```bash
php bin/console app:import:sales /ruta/ventas.csv
```

Importar inventario desde Excel:

```bash
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx
```

Indicando la hoja:

```bash
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx "costos-venta detallado"
```

### Stock y alertas

Revisar inventario:

```bash
php bin/console app:inventory:check
```

Sincronizar valijas:

```bash
php bin/console app:valija:sync
```

## Importación de datos

### Compras y ventas

Las importaciones HTTP y CLI reutilizan servicios dedicados:

- `App\Service\PurchaseCsvImporter`
- `App\Service\SalesCsvImporter`

### Inventario XLSX

El comando `app:import:inventory-xlsx`:

- detecta la fila de cabecera buscando `CODIGO`
- normaliza nombres de columnas
- crea o reutiliza proveedores, categorías y productos
- registra histórico de costos
- sincroniza lotes con stock y fecha de vencimiento

Columnas relevantes detectadas por el importador:

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

## Frontend

Rutas principales:

- `/login`
- `/`
- `/dashboard`
- `/import`
- `/alerts`
- `/valijas`
- `/valijas/:id`
- `/products/:id`
- `/providers/:id`

Comportamiento relevante:

- las rutas privadas usan un layout protegido por token
- el header muestra contadores de alertas con polling cada 30 segundos
- la vista de alertas permite sincronizar todas las valijas desde la UI
- la vista de detalle de valija permite añadir productos, editar mínimos y sincronizar la valija individualmente

## Seguridad

Resumen de `config/packages/security.yaml`:

- `/api/login` es público
- `/api/import*` requiere `ROLE_ADMIN`
- el resto de `/api` requiere `IS_AUTHENTICATED_FULLY`
- el firewall API es stateless y usa JWT

## Tests y checks

Tests backend:

```bash
php bin/phpunit
```

Lint frontend:

```bash
cd frontend
npm run lint
```

## Notas

- El árbol del proyecto contiene también archivos generados en `public/assets` y cachés en `var/`
- Si vas a desplegar sin Vite en desarrollo, recuerda regenerar `public/` con `./build-frontend.sh`
