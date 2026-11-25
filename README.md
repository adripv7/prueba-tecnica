# VentasLite API

Backend REST para el sistema VentasLite, desarrollado con Laravel 12, Eloquent ORM y Laravel Sanctum. El frontend React se mantiene separado.

## Requisitos

- PHP 8.2 o superior con las extensiones requeridas por Laravel y PDO MySQL.
- Composer.
- MySQL.

## Instalación local

1. Copia `.env.example` a `.env` y configura la conexión MySQL.
2. Instala las dependencias PHP con `composer install`.
3. Genera la llave de aplicación con `php artisan key:generate`.
4. Aplica migraciones y carga los datos de prueba con `php artisan migrate --seed`.
5. Inicia el servidor API con `php artisan serve`.

El proyecto no necesita Node.js, npm, Vite ni Blade para servir el API. Conserva `routes/web.php` únicamente para responder una comprobación JSON en `/`. `.gitignore` excluye `.env`, SQLite local, dependencias, cachés y artefactos de pruebas; solo `.env.example` debe incluirse.

## Credenciales locales de prueba

- Administrador: `admin@ventaslite.test` / `password`.
- Vendedores: `vendedor.sNN.NN@ventaslite.test` / `password` (ejemplo: `vendedor.s01.01@ventaslite.test`).

Estas credenciales son solo para desarrollo y evaluación local.

## Rutas principales

Todas las rutas protegidas requieren `Authorization: Bearer <token>`. El login y `GET /api/user` incluyen el slug del rol y la lista de permisos asignados.

| Método | Ruta | Uso |
| --- | --- | --- |
| `POST` | `/api/login` | Iniciar sesión y obtener token Sanctum |
| `GET` | `/api/user` | Consultar usuario autenticado |
| `POST` | `/api/logout` | Revocar token actual |
| `GET` | `/api/branches` | Listar sucursales accesibles |
| `GET` | `/api/categories` | Listar categorías |
| `GET` | `/api/products?branch_id=1` | Buscar productos activos e inventario por sucursal |
| `POST` | `/api/sales` | Registrar venta y descontar existencias |
| `GET` | `/api/sales?from=2026-11-01&to=2026-11-25&branch_id=1&seller_id=2&status=pagada` | Historial paginado: admin consulta todo y vendedor solo sus ventas |
| `GET` | `/api/sales/{sale}` | Detalle: admin consulta todo y vendedor solo su venta |
| `PATCH` | `/api/sales/{sale}/cancel` | Cancelar venta y reponer inventario |
| `GET` | `/api/reports/dashboard` | Resumen y agregados administrativos del dashboard |
| `GET` | `/api/reports/dashboard/export?section=...` | Descargar CSV de una sección de reportes |
| `POST` | `/api/reports/assistant` | Consultar el asistente de reportes agregados |

La exportación requiere `section`: `sales_by_month`, `top_products`, `seller_ranking`, `branch_category_matrix` o `gross_margin_by_category`. Para productos también requiere `top_products_by=amount` o `top_products_by=units`. Acepta los mismos filtros opcionales `from`, `to` y `branch_id` del dashboard.

El asistente recibe JSON con `question` (8 a 500 caracteres) y puede recibir `from`, `to` y `branch_id`. Requiere el permiso `reports.view`, responde con `data.answer`, `data.model` y `data.filters`, y permite hasta 5 solicitudes por minuto. Los reportes excluyen ventas canceladas. El alcance incluye KPIs, comparación de periodos, meses, top de productos, ranking agregado de vendedores, sucursales, matriz por categoría y margen. Laravel prepara agregados compactos según el tema de la pregunta; no envía tablas completas, IDs internos, ventas crudas ni datos de clientes. El asistente sí puede nombrar vendedores al comparar métricas agregadas de rendimiento; rechaza domicilio u otros datos privados. Los umbrales y extremos de rendimiento por vendedor se calculan en Laravel; el top de productos proviene de las agregaciones del dashboard y la IA redacta la respuesta con el tema y los filtros correspondientes. Si no hay ventas, o el mes mencionado contradice los filtros, responde con una explicación directa sin inventar cifras. Ningún proveedor genera SQL y Laravel nunca ejecuta SQL generado por IA; las métricas se calculan exclusivamente con los servicios de reportes existentes. El asistente no realiza operaciones del POS ni consulta inventario operativo.

Para desarrollo local se conserva Ollama: `AI_PROVIDER=ollama`, `OLLAMA_URL=http://127.0.0.1:11434` y `OLLAMA_MODEL=qwen3:4b`. La API local debe estar ejecutándose y el modelo descargado en Ollama. En el hosting de producción configura `AI_PROVIDER=groq`, `GROQ_MODEL=openai/gpt-oss-20b` y `GROQ_API_KEY` como secreto del servicio; nunca guardes la llave en Git ni la compartas con React. El backend solo envía a Groq el contexto agregado necesario. Los límites gratuitos pueden variar por modelo/cuenta; si el proveedor responde 429, la API devuelve un error controlado.

El endpoint de salud de Laravel está disponible en `/up`; `/` devuelve un JSON breve de identificación del API.

## Datos de prueba

Los seeders usan Faker con locale `es_MX`, incluidos SKU únicos por categoría, y datos definidos en código. El catálogo contiene ocho categorías de ferretería alineadas con el boceto. Por decisión del proyecto hay siete sucursales y cuatro vendedores por sucursal (28 vendedores); supera los mínimos del PDF y conserva la instrucción inicial del usuario. Ejecutar de nuevo `php artisan db:seed` actualiza catálogos, usuarios e inventarios; las ventas con folio `SEED-` se regeneran. El stock operativo por sucursal se toma de `inventories.stock`; `products.stock` se conserva por compatibilidad con los campos mínimos del enunciado.

## Pruebas

Ejecuta la suite con `php artisan test`. Los tests Feature usan transacciones para revertir sus registros.

## Estructura

- `app/Http/Controllers/Api`: controladores HTTP del API.
- `app/Http/Requests/Api`: validaciones de entrada.
- `app/Http/Resources/Api`: formato de respuestas JSON.
- `app/Models`: modelos y relaciones Eloquent.
- `app/Services`: lógica de ventas, cancelaciones y agregados del dashboard.
- `database/migrations`: esquema, claves foráneas e índices.
- `database/seeders` y `database/factories`: datos iniciales para pruebas.
- `routes/api.php`: endpoints REST.
- `tests/Feature/Api`: pruebas de autenticación, catálogo y ventas.
- `AI_LOG.md`: bitácora obligatoria de prompts, resultados y correcciones.

El backend invoca la API local de chat de Ollama en `/api/chat` con `think=false`, respuesta JSON estructurada, contexto de 4096 y un máximo de 320 tokens de salida para acomodar respuestas multitema sin perder el formato.

## Roles y permisos

Los roles disponibles inicialmente son `admin` y `vendedor`; cada usuario conserva su slug en `users.role`, con llave foránea a `roles.slug`. Los permisos se relacionan con roles mediante `permission_role`. El admin tiene `sales.view_all`; el vendedor tiene `sales.view_own`, que limita historial y detalle a ventas donde él es el vendedor. El vendedor no puede cancelar. Las reglas de sucursal limitan su catálogo y el registro de ventas a su sucursal.

Después de aplicar migraciones, los permisos pueden volver a sincronizarse sin duplicados con `php artisan db:seed --class=RolePermissionSeeder`. Las comprobaciones del API usan permisos, no comparaciones directas del slug de rol.

## Decisiones y estado de entrega

- El prototipo compartido muestra seis sucursales; el seeder conserva siete y cuatro vendedores por sucursal, como se pidió durante el desarrollo. La séptima sucursal es Tijuana.
- Las categorías y productos semilla se alinean con la distribuidora de herramientas del prototipo. Faker genera variantes/modelos y SKU únicos reproducibles; no se importa un catálogo externo.
- El backend sigue local. URL de despliegue e instrucciones específicas del proveedor y CORS para el dominio React se completarán al elegir hosting y levantar el frontend.
- Las capturas finales de escritorio/celular y el video se agregan después de terminar React. Las imágenes compartidas durante el diseño no sustituyen los entregables guardados en el repositorio.
