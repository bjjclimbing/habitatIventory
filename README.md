# InventoryApp

Aplicacion de inventario con backend en Symfony 7.4 y frontend SPA en React 19. El sistema gestiona catalogo de productos, lotes con caducidad, consumo de stock, importaciones masivas, alertas operativas, valijas con reposicion automatica, clientes y presupuestos exportables a Excel/PDF.

Este README esta pensado como documento vivo de arquitectura, operacion y traspaso tecnico. Resume lo que existe hoy en el codigo, incluyendo diferencias entre la intencion funcional y la implementacion real.

## 1. Vision General

### Objetivo funcional

El sistema cubre hoy seis necesidades principales:

1. Mantener inventario central por producto usando lotes con cantidad y fecha de vencimiento.
2. Registrar entradas y salidas de inventario mediante importaciones CSV/XLSX y consumo manual.
3. Gestionar valijas o maletas como stock satelite con reposicion automatica desde el inventario central.
4. Emitir alertas por bajo stock, caducidad y faltantes en valijas.
5. Gestionar clientes para asociarlos a presupuestos.
6. Generar presupuestos con costo historico por producto y exportarlos a Excel y PDF.

### Stack actual

- Backend: PHP 8.2+, Symfony 7.4, Doctrine ORM 3.6, API Platform 4.3, Lexik JWT.
- Frontend: React 19, Vite 8, React Router 7, Axios, Tailwind CSS, Recharts.
- Persistencia: MariaDB/MySQL.
- Infraestructura: Docker + Apache + PHP.
- Importaciones: CSV y XLSX con PhpSpreadsheet.
- Exportaciones: Excel con PhpSpreadsheet, PDF con Dompdf.
- Alertas: Mailer de Symfony.

### Topologia real del repositorio

```text
.
├── src/
│   ├── Command/          # Comandos CLI de importacion, chequeos y sincronizacion
│   ├── Controller/       # Endpoints HTTP custom
│   ├── Entity/           # Modelo persistente
│   ├── Repository/       # Queries y helpers SQL/Doctrine
│   └── Service/          # Logica de negocio principal
├── config/               # Seguridad, Doctrine, JWT, CORS, bundles
├── frontend/             # SPA React/Vite
├── public/               # DocumentRoot Symfony y build servido
├── docker/               # Dockerfile PHP/Apache y vhosts
├── scripts/              # Build/export de imagen
├── templates/            # Twig residual
├── tests/                # Bootstrap de PHPUnit, sin tests funcionales hoy
├── docker-compose.dev.yml
├── docker-compose.prod.yml
├── build-frontend.sh
└── Makefile
```

## 2. Arquitectura Logica

### Estilo arquitectonico real

El proyecto no sigue hexagonal ni DDD formal. La forma real es una arquitectura MVC transaccional con servicios de dominio:

- Las entidades Doctrine concentran gran parte del modelo y varias reglas derivadas.
- Los controladores HTTP construyen JSON manualmente en la mayoria de endpoints.
- API Platform convive con controladores custom, sobre todo para `Provider`.
- Los servicios orquestan reglas criticas de stock, valijas, importacion y alertas.
- El frontend consume contratos HTTP relativamente acoplados a esas respuestas manuales.

### Capas que hoy importan

- `Controller`: contratos HTTP efectivos.
- `Service`: logica de stock, FEFO, valijas, importaciones y alertas.
- `Repository`: agregados SQL y consultas especificas.
- `Entity`: relaciones persistentes e invariantes basicas.
- `Command`: entradas batch/operativas.
- `frontend/src`: SPA autenticada que consume `/api`.

## 3. Modelo de Dominio

### Inventario central

#### `Product`

Entidad principal del catalogo.

- Campos clave: `sku`, `name`, `brand`, `minstock`, `description`.
- Relaciones:
  - `provider`
  - `category`
  - `batches`
  - `movements`
  - `costs`
- `getStock()` calcula stock sumando lotes cargados en memoria.
- `getLastPrice()` devuelve el ultimo costo de la coleccion Doctrine, no una query dedicada.

#### `InventoryBatch`

Representa stock fisico por lote.

- Campos clave: `quantity`, `expirationDate`, `createdAt`, `commissionPercent`.
- Tiene `increase()` y `decrease()` con validacion.
- `expirationDate` puede ser `null`, pero varios flujos de importacion hoy ignoran stock sin fecha.

#### `StockMovement`

Auditoria del inventario central.

- Tipos: `IN`, `OUT`.
- Relacion opcional con `InventoryBatch`.
- `StockService::consume()` registra un movimiento por lote afectado.

#### `ProductCost`

Historico de costos del producto.

- Campos observados: `directCost`, `shippingCost`, `totalCost`, `createdAt`.
- Los presupuestos guardan el costo historico original del momento.

### Clasificacion y terceros

#### `Provider`

- Entidad persistida y expuesta por API Platform.
- `setName()` normaliza a mayusculas.
- Tambien existe `ProviderController` con `GET /api/providers`, por lo que conviven dos superficies HTTP para proveedor.

#### `Category`

- Modelo jerarquico padre/hijo.
- Restriccion unica: `name + parent_id`.
- `setName()` normaliza a mayusculas.

#### `Client`

Entidad nueva frente a versiones anteriores del README.

- Campos: `name`, `rif`, `address`, `phone`, `email`.
- `rif` es unico.
- Se usa desde presupuestos y tiene CRUD HTTP propio.

### Valijas

#### `Valija`

- Contenedor logico de stock satelite.
- Tiene relaciones `products` y `stocks`.

#### `ValijaProduct`

- Define que producto debe existir en una valija y con que `stockMin`.
- Conceptualmente es la configuracion objetivo.

#### `ValijaStock`

- Stock real dentro de una valija por producto y lote origen.
- Conserva trazabilidad hacia `InventoryBatch`.

#### `ValijaMovement`

- Auditoria de valijas.
- Tipos: `consume`, `replenish`, `expire`.

### Presupuestos

#### `Budget`

- Tiene `name`, `createdAt`, `client` e `items`.
- `createdAt` se autocompleta en `PrePersist`.
- `getTotal()` suma el total de los items en memoria.

#### `BudgetItem`

- Guarda `product`, `quantity`, `unitPrice`, `customUnitPrice`, `priceModificationReason`, `total`.
- `unitPrice` es el costo historico original.
- `customUnitPrice` es el precio efectivamente cotizado.
- `getEffectiveUnitPrice()` usa `customUnitPrice ?? unitPrice`.
- `total` se recalcula en `PrePersist/PreUpdate`.

### Usuarios

#### `User`

- Autenticacion por email y password hasheado.
- Roles en JSON.
- `getRoles()` siempre agrega `ROLE_USER`.

## 4. Flujos de Negocio Criticos

### 4.1 Entradas de inventario via CSV de compras

Camino principal:

1. `ImportController::importPurchases()` recibe multipart con `file` y `mode`.
2. `PurchaseCsvImporter` lee el CSV, valida filas y resuelve proveedor/categorias.
3. Si el producto no existe:
   - `create`: lo crea.
   - `strict`: falla.
4. Si el costo cambia, registra `ProductCost`.
5. Usa `StockService::addOrUpdateStock(..., MODE_INCREMENTAL)`.

Detalles relevantes:

- El importador ya no usa `addStock()` para compras; usa `addOrUpdateStock()`.
- Si la fila no trae fecha de expiracion, `addOrUpdateStock()` no hace nada y ese stock no se registra.
- La comision `prRvn` se persiste en el lote como `commissionPercent`.
- El importador hace `flush()` al crear productos nuevos y otro `flush()` al final.

### 4.2 Entradas de inventario via XLSX

Camino principal:

1. `ImportInventoryCommand` abre un XLSX y busca la hoja indicada.
2. Detecta dinamicamente la fila cabecera buscando columnas tipo `COD` y `PROD`.
3. Mapea columnas en forma heuristica.
4. Crea o reutiliza proveedor, categoria y producto.
5. Si encuentra descripcion, actualiza `Product.description`.
6. Si encuentra costo, crea un nuevo `ProductCost`.
7. Si encuentra columnas `VALIJA:` o `MALETA:`, crea/configura `ValijaProduct`.
8. Si encuentra stock con fecha, usa `StockService::addOrUpdateStock(..., MODE_INCREMENTAL)`.

Observaciones importantes:

- Aunque el README historico hablaba de reemplazar batches previos, el comando actual usa `MODE_INCREMENTAL`, no `MODE_SYNC`.
- Si una fila trae stock pero no fecha, el comando imprime `SKIP batch sin fecha`.
- El comando hace `flush()` y `clear()` cada 200 filas.

### 4.3 Salidas de inventario

Camino principal:

1. `SalesCsvImporter` procesa ventas por `sku`.
2. Resuelve el producto.
3. Llama a `StockService::consume(product, qty)`.
4. `consume()` recorre lotes con `quantity > 0` ordenados por `expirationDate ASC`.
5. Registra `StockMovement(OUT)` por lote consumido.
6. Luego dispara `ValijaSyncService::syncAffectedValijas(product)`.

Importante:

- El comentario del servicio dice FIFO, pero la politica real es FEFO por fecha de vencimiento.
- `consume()` usa lock pesimista por lote dentro de una transaccion manual.

### 4.4 Sincronizacion de valijas

Regla actual: cada valija define un minimo por producto y el sistema intenta completarlo consumiendo stock del inventario central.

Camino principal:

1. `ValijaSyncService::sync(valija)` obtiene la definicion `ValijaProduct`.
2. Calcula el stock actual en valija por producto con `ValijaStockRepository::getTotalByValijaAndProduct()`.
3. Calcula faltante `stockMin - current`.
4. Busca lotes disponibles por producto ordenados por expiracion.
5. Descuenta stock del batch central.
6. Incrementa o crea `ValijaStock` por lote.
7. Registra `ValijaMovement::TYPE_REPLENISH`.

Consecuencias:

- El stock de valija no es virtual: se descuenta del stock central en la sincronizacion.
- La trazabilidad por lote se conserva.
- Si falta stock, hoy solo hace `dump()`. No existe una politica formal de notificacion desde este servicio.

### 4.5 Consumo desde valija

`ValijaService::consumeFromValija()`:

1. Busca `ValijaStock` por valija y producto.
2. Descuenta cantidades.
3. Registra `ValijaMovement::TYPE_CONSUME`.
4. Si falta stock en valija, falla.
5. Llama a `syncService->sync(valija)` para reposicion automatica.

Estado actual:

- Existe a nivel de servicio, pero no hay endpoint HTTP dedicado visible.

### 4.6 Alertas

La API publica usa `AlertService::getAlertsGrouped()` con familias:

- `low_stock`
- `warning`
- `expired`
- `valija_low`
- `valija_critical`

Criterios reales en la API:

- `low_stock`: `product.getStock() <= product.getMinStock()`
- `warning`: lote con vencimiento en `<= 182` dias
- `expired`: lote vencido
- `valija_low`: valija bajo minimo, pero el producto aun tiene stock global
- `valija_critical`: valija bajo minimo y el producto no tiene stock global

Inconsistencia a documentar:

- `AlertService::getAlerts()` usa warning a `<= 7` dias, pero `getAlertsGrouped()` usa `<= 182` dias.
- La API usa `getAlertsGrouped()`, por lo que el comportamiento visible hoy es 182 dias.

### 4.7 Presupuestos

Camino principal:

1. `BudgetController::create()` crea un `Budget`.
2. Resuelve `clientId`.
3. Por cada item:
   - busca producto
   - lee ultimo costo historico
   - guarda `unitPrice` original
   - guarda `customUnitPrice` si el frontend manda override
   - guarda `priceModificationReason`
4. Persiste y permite editar, listar, exportar y borrar.

Regla clave:

- El presupuesto congela el precio historico y opcionalmente el precio cotizado ajustado.

## 5. Superficie HTTP Actual

## Autenticacion

- `POST /api/login`

Retorna:

- `{ "token": "<jwt>" }`

## Productos

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products/{id}/consume`
- `GET /api/products/{id}/movements`

Filtros soportados realmente por `GET /api/products`:

- `provider=<id>`
- `name=<texto>`
- `page=<n>`

Respuesta real del listado:

```json
{
  "data": [],
  "total": 0,
  "page": 1,
  "limit": 20
}
```

Observacion:

- El detalle `GET /api/products/{id}` no expone hoy `stock`, `minStock`, `brand` ni `description`, aunque el frontend intenta usarlos.

## Dashboard

- `GET /api/dashboard`

Devuelve:

- `total`
- `critical`
- `lowStock`
- `noStock`

## Proveedores

- `GET /api/providers` desde `ProviderController`
- `GET /api/providers/{id}` via API Platform para `Provider`

Observacion:

- `GET /api/providers` devuelve un array plano, no Hydra (`member`).

## Clientes

- `GET /api/clients`
- `POST /api/clients`
- `PUT /api/clients/{id}`
- `DELETE /api/clients/{id}`

Observaciones:

- El frontend envia `?search=...`, pero el backend hoy ignora ese filtro.
- No hay anotacion `IsGranted` especifica; quedan protegidos por el firewall general JWT.

## Importaciones

- `POST /api/import/purchases`
- `POST /api/import/sales`

No existe endpoint HTTP para importacion XLSX. Ese flujo es solo CLI.

## Alertas

- `GET /api/alerts`
- `GET /api/alerts/details?type=<tipo>`
- `GET /api/alerts/summary`

`GET /api/alerts` y `GET /api/alerts/summary` hoy entregan practicamente el mismo conteo agregado.

## Valijas

- `GET /api/valijas`
- `POST /api/valijas`
- `GET /api/valijas/{id}`
- `DELETE /api/valijas/{id}`
- `POST /api/valijas/{id}/products`
- `PUT /api/valijas/products/{id}`
- `DELETE /api/valijas/products/{id}`
- `POST /api/valijas/{id}/sync`
- `POST /api/valijas/sync`

## Presupuestos

- `POST /api/budgets`
- `GET /api/budgets`
- `GET /api/budgets/{id}`
- `PUT /api/budgets/{id}`
- `DELETE /api/budgets/{id}`
- `GET /api/budgets/{id}/export/excel`
- `GET /api/budgets/{id}/export/pdf`
- `GET /api/logo`

Observaciones:

- `GET /api/budgets` solo soporta filtro `search`. El frontend envia `from` y `to`, pero el backend los ignora.
- `GET /api/budgets/{id}` no expone `total`, aunque `BudgetDetail.jsx` lo espera.
- `GET /api/logo` devuelve `'/path/DispormedLogo.png'`, una ruta placeholder inconsistente con el resto del controlador.

## Usuarios

- `POST /api/users`

## Seguridad

Configuracion actual en `config/packages/security.yaml`:

- `POST /api/login` es publico.
- `^/api/import` requiere `ROLE_ADMIN`.
- Todo el resto de `^/api` requiere JWT stateless.
- `BudgetController` y `UserController` usan ademas `#[IsGranted('ROLE_ADMIN')]`.

JWT:

- Bundle: LexikJWTAuthenticationBundle.
- TTL actual: `3600` segundos.
- El frontend guarda el token en `localStorage`.

## 6. Frontend SPA

### Router actual

Definido principalmente en `frontend/src/main.jsx`.

Rutas publicas:

- `/login`

Rutas autenticadas:

- `/`
- `/products/:id`
- `/providers/:id`
- `/dashboard`
- `/import`
- `/valijas`
- `/valijas/:id`
- `/alerts`

Rutas admin:

- `/budgets`
- `/budgets/new`
- `/budgets/:id`
- `/users/new`

Ruta especial:

- `/clients`

Observacion importante:

- `/clients` no usa `PrivateLayout`; monta `Layout` directamente. En la practica depende de que `Layout` y la API fallen si no hay JWT, pero la proteccion es inconsistente respecto al resto.

### Patron de autenticacion

- `AuthProvider` guarda el token en `localStorage`.
- `useAuth()` parsea el JWT en cliente para derivar usuario y roles.
- `api.js` agrega `Authorization: Bearer <token>`.
- Si recibe `401`, elimina token y redirige a `/login`.

### Proxy en desarrollo

`frontend/vite.config.js` apunta `/api` a:

```text
http://localhost:8480
```

### Modulos UI relevantes

- `App.jsx`: listado principal con scroll infinito, filtro por proveedor y busqueda.
- `ProductDetail.jsx`: detalle, consumo manual y grafico por vencimiento.
- `ProviderDetail.jsx`: ficha del proveedor y listado de productos asociados.
- `Dashboard.jsx`: KPIs y tabla de productos criticos.
- `ImportPage.jsx`: subida de CSV de compras/ventas.
- `ValijasList.jsx` / `ValijaDetail.jsx`: creacion, configuracion y sync de maletas.
- `BudgetsList.jsx` / `BudgetPage.jsx`: listado, creacion y edicion de presupuestos.
- `BudgetDetail.jsx`: componente residual; no esta conectado al router actual.
- `ClientPage.jsx`: CRUD de clientes.
- `AlertsPage.jsx`: detalle de alertas por tipo.
- `UserCreatePage.jsx`: alta de usuarios.
- `Layout.jsx`: sidebar, navegacion y polling de alertas cada 30 segundos.

### Desalineaciones frontend/backend activas

- `App.jsx` espera `res.data.member` para proveedores, pero `GET /api/providers` devuelve array plano.
- `ProductDetail.jsx` usa `stock` y `minStock`, pero el detalle backend no los devuelve.
- `ProductDetail.jsx` etiqueta lotes como FIFO; el backend usa FEFO.
- `BudgetDetail.jsx` espera `budget.total`, pero el detalle backend no lo expone.
- `BudgetsList.jsx` envia filtros `from` y `to`, sin soporte backend.
- El router de `/budgets/:id` carga `BudgetPage`, no `BudgetDetail`.

## 7. Persistencia e Invariantes

### Reglas que hoy asume el sistema

- `sku` identifica funcionalmente un producto, pero no hay restriccion unica visible en la entidad.
- El stock del producto es la suma de `InventoryBatch`.
- Los movimientos de stock y de valija son append-only.
- Cada unidad en valija proviene de un lote real del inventario central.
- El costo es historico; se agrega una nueva fila ante cambios.
- Proveedores y categorias se normalizan a mayusculas.
- `Client.rif` es unico.

### Riesgos e inconsistencias de integridad

- Parte del sistema calcula stock recorriendo colecciones Doctrine en memoria.
- Hay transacciones manuales mezcladas con `flush()` distribuidos.
- `StockService::addOrUpdateStock()` en `MODE_SYNC` elimina lotes sin registrar `StockMovement`.
- El importador CSV y el XLSX descartan stock sin fecha al usar `addOrUpdateStock()`.
- `ValijaStock::decrease()` no valida negativos.
- `InventoryBatchRepository` y `ValijaStockRepository` tienen docblocks/clases heredadas inconsistentes, aunque funcionan.

## 8. Instalacion y Puesta en Marcha

### Requisitos

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- MariaDB/MySQL
- Docker y Docker Compose para flujo contenedorizado

### Variables de entorno relevantes

El proyecto usa `.env` y overrides locales.

Variables clave:

- `APP_ENV`
- `APP_SECRET`
- `DATABASE_URL`
- `MAILER_DSN`
- `JWT_SECRET_KEY`
- `JWT_PUBLIC_KEY`
- `JWT_PASSPHRASE`
- `CORS_ALLOW_ORIGIN`

### Backend local

```bash
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
```

Si el esquema esta desalineado y se necesita una actualizacion rapida:

```bash
php bin/console doctrine:schema:update --force
```

### Frontend local

```bash
cd frontend
npm install
npm run dev
```

### Arranque backend

Opcion 1:

```bash
symfony server:start
```

Opcion 2:

```bash
php -S 127.0.0.1:8000 -t public
```

Si usas Vite en paralelo, confirma que el proxy `/api` apunte al backend correcto.

## 9. Docker, Build y Despliegue

### Desarrollo

`docker-compose.dev.yml` expone:

- contenedor: `inventory_app_dev`
- puerto: `8480:80`

Comandos:

```bash
docker network create h3_net || true
docker compose -f docker-compose.dev.yml up --build -d
```

O via `Makefile`:

```bash
make dev
make dev-logs
make dev-down
```

### Produccion

Flujo principal hoy:

```bash
./scripts/build_and_export.sh prod
```

Comportamiento real actual del script:

1. Borra `public/build`.
2. Ejecuta `npm install` y `npm run build` en `frontend/`.
3. Copia `frontend/dist/*` a `public/`, no a `public/build/`.
4. Construye imagen Docker:
   - `inventory_app:<YYYYMMDD_HHMM>`
   - `inventory_app:latest`
5. Exporta la imagen con `docker save` a `.tar`.
6. La compresion `xz` esta comentada; hoy no genera `.tar.xz`.

Inconsistencias a tener presentes:

- El script crea `public/build` pero luego copia el frontend a `public/`.
- `build-frontend.sh` tambien copia a `public/`.
- El README historico hablaba de `.tar.xz`; el script actual deja `.tar`.

### Despliegue en servidor final

Si construyes en otra maquina:

```bash
./scripts/build_and_export.sh prod
scp inventory_app_<YYYYMMDD_HHMM>.tar usuario@servidor:/ruta/deploy/
docker load -i inventory_app_<YYYYMMDD_HHMM>.tar
docker network create h3_net || true
docker compose -f docker-compose.prod.yml up -d
```

Si construyes en el mismo servidor:

```bash
./scripts/build_and_export.sh prod
docker network create h3_net || true
docker compose -f docker-compose.prod.yml up -d
```

## 10. Comandos Operativos

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
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx "Nombre Hoja"
```

### Inventario y valijas

```bash
php bin/console app:inventory:check
php bin/console app:valija:sync
```

## 11. Formatos de Importacion

### CSV de compras

`PurchaseCsvImporter` espera columnas equivalentes a:

1. `sku`
2. `name`
3. `brand`
4. `providerName`
5. `procedureName`
6. `groupName`
7. `subGroupName`
8. `quantity`
9. `expirationDate`
10. `costDirect`
11. `costShipping`
12. `costTotal`
13. `prRvn`

### CSV de ventas

`SalesCsvImporter` espera:

1. `sku`
2. `quantity`
3. `reference`
4. `comments`

Hoy `reference` y `comments` se leen pero no impactan persistencia.

### XLSX de inventario

`ImportInventoryCommand` detecta cabecera en forma heuristica y busca columnas tipo:

- `COD*`
- `PROD*`
- `MARCA`
- `SUBGRUPO`
- `INVENTARIO` o `STOCK`
- `FECHA`
- `DESCRIP*`
- `COSTE TOTAL` o `PRECIO`
- `VALIJA:<nombre>` o `MALETA:<nombre>`

## 12. Observaciones de Arquitectura y Deuda Tecnica

### 1. Contratos backend/frontend no siempre alineados

- Proveedores: array plano vs expectativa `member`.
- Producto detalle: backend incompleto para lo que el frontend muestra.
- Budget detail: sin `total` aunque el componente residual lo espera.
- Filtros `from/to` en presupuestos sin soporte backend.
- Search de clientes enviado desde frontend sin soporte backend.

### 2. Rutas de presupuesto inconsistentes

- `/budgets/:id` renderiza `BudgetPage`, no `BudgetDetail`.
- Existe export PDF backend y tambien un componente `BudgetDetail`, pero no estan conectados en el router.

### 3. Duplicidad de comandos

- `InventoryCheckCommand` y `SendAlertsCommand` comparten `app:inventory:check`.
- En Symfony esto debe corregirse antes de automatizar cron.

### 4. API mixta

- `Provider` existe como `ApiResource`.
- A la vez hay `ProviderController`.
- El resto es mayormente manual.
- No hay convencion unica de serializacion, errores o paginacion.

### 5. Calidad transaccional desigual

- `StockService` y `ValijaSyncService` usan transacciones manuales.
- Importadores y controladores hacen `flush()` en distintos puntos.
- No hay estrategia clara de idempotencia ni de concurrencia entre procesos batch.

### 6. Alertas inconsistentes

- La API usa ventanas de 182 dias para warning.
- El metodo plano usa 7 dias.
- `AlertMailer` espera una clave `critical` que `AlertService` no genera; hoy el mailer trataria esa seccion como vacia.

### 7. Build ambiguo

- `public/build` y `public/` se usan de forma inconsistente.
- El frontend ya compila artefactos directamente a `public/`.
- El script documentado y el real no coinciden respecto a `.tar` vs `.tar.xz`.

### 8. Testing casi inexistente

- Hay bootstrap de PHPUnit.
- No hay tests de negocio, contrato ni UI.

## 13. Traspaso Tecnico Completo

### Estado actual resumido para otro ingeniero

El nucleo de negocio ya existe y funciona sobre tres ideas fuertes:

1. inventario central por lotes;
2. consumo FEFO real;
3. valijas que se reponen consumiendo stock central.

Encima de eso se montaron importadores, alertas, clientes y presupuestos. El sistema no necesita reescritura; necesita consolidacion de contratos y endurecimiento operativo.

### Donde empezar a leer

Orden recomendado:

1. `src/Service/StockService.php`
2. `src/Service/ValijaSyncService.php`
3. `src/Service/PurchaseCsvImporter.php`
4. `src/Command/ImportInventoryCommand.php`
5. `src/Service/SalesCsvImporter.php`
6. `src/Controller/ProductController.php`
7. `src/Controller/BudgetController.php`
8. `frontend/src/main.jsx`
9. `frontend/src/Layout.jsx`
10. la pantalla frontend que vayas a tocar

### Mapa mental rapido del sistema

- El stock visible sale de sumar lotes.
- Las compras y el XLSX no registran stock sin fecha de expiracion cuando entran por `addOrUpdateStock()`.
- Las ventas consumen central y luego disparan sync de valijas afectadas.
- Las valijas no reservan stock: lo consumen del central al sincronizar.
- Los presupuestos se apoyan en `ProductCost`.
- El frontend depende de payloads JSON armados a mano; cualquier cambio exige revisar ambos lados.

### Decisiones que debes preservar salvo cambio explicito de negocio

- Trazabilidad por lote.
- Historico de costos por producto.
- Separacion entre definicion de valija (`ValijaProduct`) y stock real (`ValijaStock`).
- Reposicion automatica de valijas despues de consumo desde valija.

### Decisiones que debes revisar explicitamente antes de extender

- Si FEFO es la politica correcta o si negocio realmente quiere FIFO.
- Si el sistema debe aceptar stock sin fecha de vencimiento en importaciones.
- Si la sincronizacion de valijas debe seguir descontando inventario central o migrar a reservas.
- Si se va a mantener API Platform solo para proveedor o si se unificara toda la API.
- Si los filtros de presupuesto por fecha deben existir de verdad o eliminarse del frontend.

### Puntos delicados que rompen facil

- Cambiar payloads de `ProductController`, `BudgetController` o `ProviderController` sin revisar frontend.
- Tocar `addOrUpdateStock()` sin revisar importador CSV y comando XLSX.
- Tocar valijas sin revisar auditoria `ValijaMovement`.
- Tocar presupuestos sin revisar export Excel y PDF.
- Tocar seguridad sin revisar `AdminRoute`, `Layout` y el interceptor Axios.

### Lista de verificacion al tomar el proyecto

1. Levantar backend y frontend localmente.
2. Crear un usuario admin.
3. Probar login.
4. Importar un CSV de compras de muestra.
5. Verificar listado de productos y proveedor.
6. Consumir stock manualmente y confirmar movimientos.
7. Crear una valija, agregar productos y sincronizarla.
8. Crear un cliente.
9. Crear un presupuesto con cliente y exportarlo a Excel/PDF.
10. Revisar alertas y, si aplica, el comando CLI.

### Prioridad tecnica sugerida

#### Fase 1: estabilizacion de contratos

- Arreglar payload de proveedores en frontend o backend.
- Completar `GET /api/products/{id}` con `stock`, `minStock`, `description` y `brand`.
- Decidir si `BudgetDetail` vive o se elimina.
- Corregir filtros fantasmas de `budgets` y `clients`.

#### Fase 2: saneamiento operativo

- Separar `SendAlertsCommand` con nombre unico.
- Corregir `AlertMailer` para usar claves reales.
- Unificar build frontend y artefacto de despliegue.
- Reemplazar `dump()` en sync de valijas por logging/notificacion formal.

#### Fase 3: endurecimiento

- Tests de importadores.
- Tests de consumo FEFO.
- Tests de sync de valijas.
- Tests de seguridad por rol.
- Tests de contrato API usados por la SPA.

### Preguntas abiertas para negocio

- Que significa exactamente `minStock` para producto y para valija.
- Si warning de caducidad debe ser 7, 30, 182 dias u otro valor por categoria.
- Si los presupuestos deben congelar solo costo o tambien margen, datos del cliente y descripcion del producto.
- Si las importaciones deben ser idempotentes, acumulativas o reconciliadoras.
- Que debe ocurrir con stock sin fecha de expiracion.
- Si `prRvn` afecta precio, costo, auditoria o reportes.

## 14. Resumen Ejecutivo

La base funcional es valida:

- inventario por lotes,
- consumo FEFO,
- reposicion automatica de valijas,
- costos historicos,
- clientes,
- presupuestos exportables,
- importaciones masivas.

Lo urgente no es reescribir, sino consolidar:

- contratos backend/frontend,
- reglas de importacion de stock sin fecha,
- comandos y alertas inconsistentes,
- pipeline de build/despliegue,
- tests de negocio.

Si el siguiente ciclo se enfoca en esas areas, el sistema es mantenible y ampliable sin cambios radicales de arquitectura.
