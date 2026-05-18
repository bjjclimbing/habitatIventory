# InventoryApp

Aplicacion de inventario con backend en Symfony 7.4 y frontend SPA en React 19. El sistema centraliza catalogo de productos, lotes con caducidad, movimientos de stock, importaciones masivas, alertas operativas, valijas con reposicion automatica y presupuestos exportables.

Este README esta escrito como documento de arquitectura y traspaso tecnico. Su objetivo no es solo explicar como arrancar el proyecto, sino dejar claro como esta pensado, que contratos existen hoy y donde estan los puntos delicados para continuar el desarrollo.

## 1. Vision General

### Objetivo funcional

El sistema cubre cuatro necesidades principales:

1. Mantener stock global por producto usando lotes con cantidad y fecha de vencimiento.
2. Registrar entradas y salidas de inventario a traves de importaciones y consumo manual.
3. Gestionar valijas como stock satelite con un minimo configurado por producto y sincronizacion desde el inventario central.
4. Generar presupuestos usando el ultimo costo conocido de cada producto.

### Stack actual

- Backend: PHP 8.2+, Symfony 7.4, Doctrine ORM, API Platform, Lexik JWT.
- Frontend: React 19, Vite 8, React Router 7, Axios, Tailwind CSS, Recharts.
- Persistencia: MariaDB/MySQL.
- Infraestructura: Apache + PHP en Docker.
- Importaciones: CSV y XLSX con PhpSpreadsheet.

### Topologia del repositorio

```text
.
├── src/                  # Backend Symfony
│   ├── Controller/       # Endpoints HTTP
│   ├── Entity/           # Modelo de dominio persistente
│   ├── Repository/       # Consultas especificas
│   ├── Service/          # Logica de negocio
│   └── Command/          # Procesos batch y tareas CLI
├── config/               # Seguridad, Doctrine, bundles, CORS, JWT
├── frontend/             # SPA React/Vite
├── public/               # DocumentRoot Symfony y artefactos estaticos
├── docker/               # Dockerfile PHP/Apache y vhosts
├── scripts/              # Scripts de build y export
├── templates/            # Twig residual
├── tests/                # Tests backend
├── docker-compose.dev.yml
├── docker-compose.prod.yml
├── build-frontend.sh
└── Makefile
```

## 2. Arquitectura Logica

### Vista de capas

- `Controller`: adapta HTTP a objetos de dominio y servicios.
- `Service`: concentra reglas de negocio, especialmente stock, alertas y valijas.
- `Repository`: encapsula consultas que no son triviales.
- `Entity`: define el modelo persistente y varias invariantes operativas.
- `Command`: abre entradas batch para importacion, chequeos y sincronizaciones.
- `frontend/src`: implementa la SPA autenticada y consume la API relativa `/api`.

### Estilo arquitectonico real

No es un DDD estricto ni una arquitectura hexagonal formal. El proyecto esta mas cerca de una arquitectura MVC transaccional con servicios de dominio:

- Doctrine y las entidades son el centro del modelo.
- Los controladores forman respuestas JSON manualmente en varios casos.
- API Platform solo se usa parcialmente; convive con controladores custom.
- Los servicios contienen la logica critica de negocio.

Esta mezcla funciona, pero implica que cualquier refactor futuro debe decidir si consolidar la API alrededor de:

1. controladores custom manuales, o
2. recursos API Platform mas consistentes.

Hoy coexisten ambos enfoques.

## 3. Modelo de Dominio

### Inventario central

#### `Product`

Entidad base del catalogo.

- Campos clave: `sku`, `name`, `brand`, `minstock`.
- Relaciones:
  - `provider`
  - `category`
  - `batches`
  - `movements`
  - `costs`
- El stock global se calcula agregando cantidades de `InventoryBatch`.

#### `InventoryBatch`

Representa stock fisico disponible en un lote.

- Cada lote pertenece a un `Product`.
- Guarda `quantity`, `expirationDate`, `createdAt`.
- Incluye `commissionPercent`, usado en importacion de compras.
- Soporta `increase()` y `decrease()` con validacion basica.

#### `StockMovement`

Auditoria del inventario central.

- Tipos: `IN`, `OUT`.
- Relacion opcional con `InventoryBatch`.
- Se genera al anadir stock o consumirlo.

#### `ProductCost`

Historico de costos por producto.

- Guarda `directCost`, `shippingCost`, `totalCost`.
- Los presupuestos toman el ultimo costo disponible.

### Clasificacion y terceros

#### `Provider`

Proveedor del producto.

- Esta expuesto por API Platform.
- `setName()` normaliza a mayusculas.

#### `Category`

Arbol jerarquico de categorias.

- Modelo padre/hijo.
- Se usa en importaciones para construir niveles `PROCEDIMIENTO -> GRUPO -> SUBGRUPO`.
- Restriccion unica por `name + parent_id`.

### Valijas

#### `Valija`

Contenedor logico de stock satelite.

- Define una lista de productos objetivo (`ValijaProduct`).
- Mantiene stock fisico transferido (`ValijaStock`).

#### `ValijaProduct`

Definicion de producto requerido en una valija.

- Relacion `valija + product`.
- Campo clave: `stockMin`.
- Restriccion unica por pareja `valija_id + product_id`.

#### `ValijaStock`

Stock real cargado dentro de una valija.

- Relaciona `valija`, `product`, `batch`, `quantity`.
- Importante: la valija conserva trazabilidad por lote origen.

#### `ValijaMovement`

Auditoria de movimientos de valija.

- Tipos: `consume`, `replenish`, `expire`.
- Hoy se registran sobre todo consumos y reposiciones.

### Presupuestos y usuarios

#### `Budget` / `BudgetItem`

- `Budget` agrupa items y calcula total agregando `BudgetItem::getTotal()`.
- `BudgetItem` guarda `product`, `quantity`, `unitPrice`, `total`.
- El total del item se recalcula en `prePersist/preUpdate`.

#### `User`

- Autenticacion por email y password hasheado.
- Roles almacenados en JSON.
- Siempre incorpora `ROLE_USER` en `getRoles()`.

## 4. Flujos de Negocio Criticos

### 4.1 Entradas de inventario

Camino principal:

1. `PurchaseCsvImporter` lee CSV de compras.
2. Crea o reutiliza proveedor, categorias y producto segun modo.
3. Registra historico de `ProductCost` si hubo cambios.
4. Llama a `StockService::addStock()`.
5. `StockService::addStock()` crea `InventoryBatch` + `StockMovement(IN)`.

Caracteristicas relevantes:

- Modos:
  - `create`: crea productos inexistentes.
  - `strict`: falla si el producto no existe.
- La fecha de expiracion es opcional.
- Si el costo total no viene, se deriva de `direct + shipping`.

### 4.2 Salidas de inventario

Camino principal:

1. `SalesCsvImporter` procesa ventas por SKU.
2. Resuelve el `Product`.
3. Llama a `StockService::consume(product, qty)`.
4. El consumo recorre lotes con `quantity > 0` ordenados por `expirationDate ASC`.
5. Registra `StockMovement(OUT)` por cada lote afectado.
6. Luego dispara `ValijaSyncService::syncAffectedValijas(product)`.

Observacion importante:

- El comentario del codigo habla de FIFO, pero realmente el orden es por vencimiento ascendente. En la practica esto es FEFO.

### 4.3 Sincronizacion de valijas

La regla es: cada valija define un minimo por producto y el sistema intenta reponerla desde el inventario central.

Camino principal:

1. `ValijaSyncService::sync(valija)` busca los `ValijaProduct`.
2. Calcula stock actual en valija por producto usando `ValijaStockRepository`.
3. Calcula faltante `stockMin - current`.
4. Busca lotes disponibles del producto, ordenados por vencimiento.
5. Descuenta del batch central.
6. Incrementa o crea `ValijaStock` por batch.
7. Registra `ValijaMovement::TYPE_REPLENISH`.

Consecuencias de diseño:

- El stock de valija no es independiente; se descuenta del inventario central en el momento de sincronizacion.
- La valija mantiene granularidad por lote, lo que preserva trazabilidad.
- Si no hay stock suficiente, solo se hace `dump()`; no existe aun una politica de notificacion robusta.

### 4.4 Consumo desde valija

`ValijaService::consumeFromValija()`:

1. Consume stock dentro de `ValijaStock`.
2. Registra `ValijaMovement::TYPE_CONSUME`.
3. Ejecuta `syncService->sync(valija)` al final para reponer automaticamente.

Nota:

- Actualmente este flujo existe en servicio pero no aparece expuesto claramente en un endpoint dedicado.

### 4.5 Alertas

`AlertService` arma dos familias:

- Inventario global:
  - `low_stock`
  - `warning`
  - `expired`
- Valijas:
  - `valija_low`
  - `valija_critical`

Criterios actuales:

- `low_stock`: `product.stock <= product.minStock`
- `warning`: lote con vencimiento en <= 182 dias
- `expired`: lote vencido
- `valija_low`: la valija esta por debajo del minimo, pero el inventario global aun tiene stock
- `valija_critical`: la valija esta por debajo del minimo y el producto global no tiene stock

### 4.6 Presupuestos

`BudgetController`:

1. Crea presupuesto.
2. Para cada item, toma el ultimo costo del producto.
3. Genera `BudgetItem`.
4. Permite listar, filtrar, ver detalle y exportar a Excel.

Regla de negocio relevante:

- El precio del presupuesto queda desacoplado de cambios posteriores de costo, porque se guarda en `unitPrice`.

## 5. Superficie HTTP Actual

## Autenticacion

- `POST /api/login`

## Productos

- `GET /api/products`
- `GET /api/products/{id}`
- `POST /api/products/{id}/consume`
- `GET /api/products/{id}/movements`

Filtros actuales:

- `provider=<id>`
- `name=<texto>`
- `page=<n>`

## Dashboard

- `GET /api/dashboard`

## Proveedores

- `GET /api/providers`
- `GET /api/providers/{id}` via API Platform para `Provider`

## Importaciones

- `POST /api/import/purchases`
- `POST /api/import/sales`

## Alertas

- `GET /api/alerts`
- `GET /api/alerts/details?type=<tipo>`
- `GET /api/alerts/summary`

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
- `DELETE /api/budgets/{id}` documentado en README anterior, pero no localizado en el controlador actual
- `GET /api/budgets/{id}/export/excel`

## Usuarios

- `POST /api/users`

## Seguridad

Configuracion actual en `config/packages/security.yaml`:

- `POST /api/login` es publico.
- `^/api/import` requiere `ROLE_ADMIN`.
- El resto de `^/api` requiere autenticacion JWT stateless.
- Adicionalmente, `BudgetController` y `UserController` usan `#[IsGranted('ROLE_ADMIN')]`.

JWT:

- Bundle: LexikJWTAuthenticationBundle.
- TTL actual: `3600` segundos.
- El frontend lo guarda en `localStorage`.

## 6. Frontend SPA

### Router actual

Definido principalmente en `frontend/src/main.jsx`.

Rutas protegidas:

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

Ruta publica:

- `/login`

### Patron de autenticacion

- `AuthProvider` guarda el token en `localStorage`.
- `useAuth()` parsea el JWT en cliente para obtener `user.roles`.
- `api.js`:
  - anade `Authorization: Bearer <token>`
  - si recibe `401`, elimina token y redirige a `/login`

### Proxy en desarrollo

`frontend/vite.config.js` apunta `/api` a:

```text
http://localhost:8480
```

Esto implica que el backend esperado durante desarrollo local con Vite es el expuesto por Docker o cualquier servidor escuchando en ese puerto.

### Modulos UI relevantes

- `App.jsx`: listado principal de productos con filtro por proveedor y busqueda.
- `ProductDetail.jsx`: detalle de producto, lotes y consumo manual.
- `ProviderDetail.jsx`: ficha del proveedor y lista de productos asociados.
- `Dashboard.jsx`: KPIs simples y listado de criticos.
- `ImportPage.jsx`: subida de CSV de compras/ventas.
- `ValijasList.jsx` / `ValijaDetail.jsx`: gestion de valijas.
- `BudgetsList.jsx` / `BudgetPage.jsx`: listado y creacion de presupuestos.
- `AlertsPage.jsx`: detalle de alertas por tipo.
- `UserCreatePage.jsx`: alta de usuarios desde frontend.

## 7. Persistencia e Invariantes

### Reglas que hoy asume el sistema

- `Product.sku` identifica funcionalmente un producto, aunque no vi una restriccion unica explicita en el codigo mostrado.
- El stock real del producto es la suma de sus lotes.
- Los movimientos son append-only y sirven de auditoria.
- Las valijas no tienen stock "magico": cada unidad de valija sale de un batch real del inventario central.
- El costo de producto es historico, no mutable.
- Categoria y proveedor se normalizan a mayusculas al importarse/guardarse.

### Riesgos de integridad a tener presentes

- Parte del sistema calcula stock recorriendo colecciones Doctrine cargadas, no siempre via SQL agregado.
- Hay `flush()` dentro de importadores y servicios; cualquier refactor batch debe cuidar memoria, transacciones y rendimiento.
- Varias respuestas JSON se construyen manualmente, por lo que cambios de contrato exigen revisar backend y frontend juntos.

## 8. Instalacion y Puesta en Marcha

### Requisitos

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- MariaDB/MySQL
- Docker y Docker Compose para el flujo contenedorizado

### Variables de entorno relevantes

El proyecto usa `.env` y acepta overrides en `.env.local`.

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

Si el esquema esta desalineado y se necesita una puesta al dia rapida:

```bash
php bin/console doctrine:schema:update --force
```

### Frontend local

```bash
cd frontend
npm install
npm run dev
```

### Arranque del backend

Opcion 1:

```bash
symfony server:start
```

Opcion 2:

```bash
php -S 127.0.0.1:8000 -t public
```

Si usas Vite en paralelo, revisa que el proxy `/api` apunte al backend correcto o adapta `frontend/vite.config.js`.

## 9. Docker, Build y Despliegue

### Desarrollo

`docker-compose.dev.yml`:

- contenedor: `inventory_app_dev`
- puerto: `8480:80`
- volumen: `.:/var/www`
- build args: `APACHE_ENV=dev`, `APP_ENV=dev`

Comando:

```bash
docker network create h3_net
docker compose -f docker-compose.dev.yml up --build
```

### Produccion

`scripts/build_and_export.sh prod`:

1. borra `public/build`
2. hace build del frontend
3. copia `frontend/dist/*` a `public/build/`
4. construye la imagen Docker
5. exporta `inventory_app_<fecha>.tar.gz`

`docker-compose.prod.yml` espera la imagen `inventory_app:latest`.

### Makefile

```bash
make dev
make dev-logs
make dev-down
make prod
```

### Inconsistencia de build a tener en cuenta

Existen dos flujos de copia del frontend:

- `build-frontend.sh` copia a `public/`
- `scripts/build_and_export.sh prod` copia a `public/build/`

Esto debe unificarse. Hoy el repositorio soporta ambos porque ha evolucionado por dos caminos distintos.

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
php bin/console app:import:inventory-xlsx /ruta/inventario.xlsx "costos-venta detallado"
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

### XLSX de inventario

`ImportInventoryCommand`:

- detecta la fila de cabecera buscando `CODIGO`
- normaliza cabeceras "sucias"
- crea o reutiliza proveedor, categorias y productos
- registra `ProductCost`
- reemplaza batches previos del producto cuando importa stock nuevo

Columnas importantes observadas:

- `CODIGO`
- `PRODUCTO`
- `MARCA`
- `PROCEDIMIENTO`
- `GRUPO`
- `SUBGRUPO`
- `EXISTENCIA`
- `FECHA_VENCIMIENTO`
- `COSTO_DIRECTO`
- `ENVIO_NACIONALIZACION`
- `COSTE_TOTAL`

## 12. Observaciones de Arquitectura y Deuda Tecnica

Esta seccion es deliberadamente franca. Son los puntos que un siguiente arquitecto deberia revisar primero.

### 1. Contratos backend/frontend no siempre alineados

- `App.jsx` espera `res.data.member` para proveedores, pero `GET /api/providers` devuelve un array plano desde `ProviderController`.
- `ProductDetail.jsx` usa `stock` y `minStock`, pero `GET /api/products/{id}` no los devuelve hoy.
- `BudgetDetail.jsx` espera `budget.total`, pero el detalle backend no lo expone.

### 2. Rutas de presupuesto inconsistentes

- En `frontend/src/main.jsx`, la ruta `/budgets/:id` renderiza `BudgetPage`, no `BudgetDetail`.
- `BudgetDetail.jsx` intenta exportar PDF con `/api/budgets/{id}/export/pdf`, endpoint no localizado en backend.

### 3. Duplicidad/incoherencia en comandos

- `SendAlertsCommand` esta registrado con el mismo nombre `app:inventory:check` que `InventoryCheckCommand`.
- Eso debe corregirse antes de automatizar alertas por cron.

### 4. Mezcla de API Platform y controladores manuales

- `Provider` vive como `ApiResource`.
- Productos, valijas, dashboard, alertas e importaciones usan controladores propios.
- No hay una convencion unica de serializacion, paginacion ni error handling.

### 5. Ausencia de una capa formal de contratos

- No vi DTOs, OpenAPI custom, validadores dedicados ni tests de contrato.
- Mucha logica de forma JSON esta embebida en controladores.

### 6. Calidad transaccional desigual

- `StockService` y `ValijaSyncService` gestionan transacciones explicitamente.
- Otros flujos hacen `flush()` frecuentes o distribuidos.
- Conviene revisar idempotencia y concurrencia si habra multiples operadores o procesos batch simultaneos.

### 7. Testing escaso

- Existe carpeta `tests/`, pero la arquitectura actual necesita al menos:
  - tests de importadores
  - tests de consumo FEFO
  - tests de sincronizacion de valijas
  - tests de permisos
  - tests de contratos API usados por la SPA

## 13. Traspaso Para El Proximo Arquitecto

### Donde empezar a leer

Orden recomendado:

1. `src/Service/StockService.php`
2. `src/Service/ValijaSyncService.php`
3. `src/Service/PurchaseCsvImporter.php`
4. `src/Service/SalesCsvImporter.php`
5. `src/Controller/*` relacionados con los flujos que quieras tocar
6. `frontend/src/main.jsx`, `api.js`, `Layout.jsx` y la pantalla afectada

### Decisiones de diseño que debes preservar o revisar explicitamente

- Preservar:
  - trazabilidad por lote
  - historico de costos por producto
  - separacion entre definicion de valija (`ValijaProduct`) y stock real (`ValijaStock`)
  - consumo por vencimiento ascendente

- Revisar explicitamente:
  - si FEFO es realmente la politica deseada o si debe ser FIFO real
  - si las valijas deben seguir descontando stock central en la sincronizacion o pasar a modelo de reservas
  - si API Platform se va a adoptar de forma amplia o se va a retirar del camino principal
  - si el frontend seguira siendo SPA independiente o se integrara mas con el pipeline Symfony

### Roadmap tecnico recomendado

#### Fase 1: estabilizacion

- Unificar contratos backend/frontend mas rotos.
- Corregir rutas de presupuestos.
- Separar `SendAlertsCommand` con un nombre unico.
- Unificar flujo de build frontend.

#### Fase 2: endurecimiento

- Anadir tests de integracion para importadores y sincronizacion de valijas.
- Introducir validacion de requests.
- Reducir respuestas JSON manuales repetidas.
- Normalizar formato de errores y paginacion.

#### Fase 3: consolidacion arquitectonica

- Extraer DTOs o serializers dedicados.
- Documentar OpenAPI real de endpoints custom.
- Revisar uso de eventos de dominio o mensajes para alertas/sincronizaciones.
- Evaluar si conviene modularizar inventario, valijas y presupuestos.

### Preguntas abiertas que conviene resolver con negocio

- Que significa exactamente "stock minimo" para producto y para valija.
- Si la caducidad debe tratarse a 7 dias, 30 dias, 182 dias o multiple por tipo de producto.
- Si los presupuestos deben congelar costo, margen y datos del proveedor en el momento de emision.
- Si las importaciones deben ser idempotentes o historicas.
- Si la comision (`prRvn`) debe afectar costos, precios o solo auditoria.

## 14. Resumen Ejecutivo

El proyecto ya tiene un nucleo de negocio claro:

- inventario por lotes,
- consumo por vencimiento,
- reposicion automatica de valijas,
- costos historicos,
- importaciones masivas.

Lo que mas necesita no es una reescritura, sino consolidacion:

- contratos estables,
- tests de negocio,
- un unico pipeline de frontend,
- y una decision arquitectonica clara sobre la API.

Si el siguiente ciclo de desarrollo se enfoca en esas cuatro areas, la base actual es perfectamente recuperable y escalable.
