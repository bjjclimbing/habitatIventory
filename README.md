# InventoryApp

Aplicacion de inventario con backend en Symfony y frontend en React/Vite. El sistema permite autenticar usuarios con JWT, consultar productos y proveedores, importar compras y ventas, controlar stock por lotes, revisar alertas y sincronizar stock de valijas.

## Stack

- Backend: Symfony 7, Doctrine ORM, Twig, LexikJWTAuthenticationBundle
- Frontend: React 19, React Router, Axios, Vite, Recharts
- Base de datos: MariaDB/MySQL segun `DATABASE_URL`
- Contenedores: Docker Compose con Apache + PHP

## Funcionalidades

- Login con JWT en `POST /api/login`
- Listado y detalle de productos
- Consumo de stock y consulta de movimientos
- Dashboard con metricas de inventario
- Listado de proveedores
- Importacion de compras y ventas desde CSV
- Importacion de inventario desde XLSX por comando
- Alertas de stock bajo, vencimiento y stock en valijas
- Sincronizacion de valijas

## Estructura

```text
.
├── src/                 # Backend Symfony
│   ├── Command/         # Comandos CLI
│   ├── Controller/      # Endpoints HTTP
│   ├── Entity/          # Entidades Doctrine
│   ├── Repository/      # Repositorios
│   └── Service/         # Logica de negocio
├── frontend/            # Frontend React/Vite
│   ├── src/
│   └── public/
├── config/              # Configuracion Symfony
├── public/              # Front controller y build del frontend
├── templates/           # Twig
├── docker/              # Dockerfiles y vhosts
└── tests/
```

## Requisitos

- PHP 8.2 o superior
- Composer
- Node.js 20 o superior
- npm
- MariaDB/MySQL accesible con los datos de `DATABASE_URL`
- Docker y Docker Compose si vas a levantar el proyecto en contenedores

## Configuracion

El proyecto incluye estas variables base en `.env`:

```dotenv
DATABASE_URL="mysql://root:kubiadmin@mariadb:3306/inventory?serverVersion=10.6"
```

Si trabajas fuera de Docker, crea un override local:

```bash
cp .env .env.local
```

Ajusta en `.env.local` al menos:

- `DATABASE_URL`
- configuracion JWT si cambias claves o rutas
- mailer, si vas a enviar alertas por correo

## Instalacion

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

## Ejecucion en desarrollo

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

Para compilar y copiar el frontend al directorio `public/` del backend:

```bash
./build-frontend.sh
```

Ese script hace:

1. `npm install`
2. `npm run build`
3. copia `frontend/dist/*` dentro de `public/`

## Docker

El `docker-compose.yml` levanta un contenedor `app` con Apache y PHP y expone la aplicacion en el puerto `8000`.

```bash
docker network create h3_net
docker compose up --build
```

Notas:

- El compose espera una red externa llamada `h3_net`
- El contenedor monta el proyecto en `/var/www`
- La aplicacion usa el host `mariadb` en `DATABASE_URL`, asi que esa base debe existir y ser accesible desde la red Docker

## Autenticacion

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

El frontend envia automaticamente `Authorization: Bearer <token>` desde `frontend/src/api.js`.

## Endpoints principales

Todas las rutas `/api` requieren autenticacion, excepto `/api/login`. Las rutas de importacion estan restringidas a `ROLE_ADMIN`.

### Productos

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products/{id}/consume`
- `GET /api/products/{id}/movements`

Filtros disponibles en `GET /api/products`:

- `provider=<id>`
- `name=<texto>`
- `page=<n>`

### Dashboard

- `GET /api/dashboard`

### Proveedores

- `GET /api/providers`

### Importaciones

- `POST /api/import/purchases`
- `POST /api/import/sales`

`/api/import/purchases` acepta:

- archivo en campo `file`
- modo opcional `mode` con valores `strict` o `create`

### Alertas

- `GET /api/alerts`
- `GET /api/alerts/details?type=low_stock`

Tipos observados en el sistema:

- `low_stock`
- `warning`
- `expired`
- `valija_low`
- `valija_critical`

### Valijas

- `POST /api/valijas/sync`

## Comandos utiles

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

Opcionalmente puedes indicar la hoja:

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

## Importacion de datos

### Compras y ventas

Las importaciones HTTP y CLI reutilizan servicios dedicados:

- `App\Service\PurchaseCsvImporter`
- `App\Service\SalesCsvImporter`

### Inventario XLSX

El comando `app:import:inventory-xlsx`:

- detecta la fila de cabecera buscando `CODIGO`
- normaliza nombres de columnas
- crea o reutiliza proveedores, categorias y productos
- registra historico de costos
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

Rutas principales del frontend:

- `/login`
- `/`
- `/dashboard`
- `/import`
- `/alerts`
- `/products/:id`
- `/providers/:id`
- `/valijas/:id`

El frontend usa:

- `AuthContext` para persistir token
- `axios` con interceptores para adjuntar JWT
- redireccion a `/login` cuando el backend responde `401`

## Seguridad

Resumen de `config/packages/security.yaml`:

- `/api/login` es publico
- `/api/import*` requiere `ROLE_ADMIN`
- el resto de `/api` requiere `IS_AUTHENTICATED_FULLY`
- el firewall API es stateless y usa JWT

## Tests

Ejecutar tests backend:

```bash
php bin/phpunit
```

Lint del frontend:

```bash
cd frontend
npm run lint
```

## Notas

- El arbol del proyecto contiene tambien archivos generados en `public/assets` y caches en `var/`
- Hay una copia no estandar en `src/Controller/AlertController copy.php`; no forma parte del flujo normal
- Si vas a desplegar sin Vite en desarrollo, recuerda regenerar `public/` con `./build-frontend.sh`
