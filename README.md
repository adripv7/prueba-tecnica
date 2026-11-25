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

El proyecto no necesita Node.js, npm, Vite ni Blade para servir el API. Conserva `routes/web.php` únicamente para responder una comprobación JSON en `/`.

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
| `GET` | `/api/sales` | Historial administrativo con filtros y paginación |
| `GET` | `/api/sales/{sale}` | Detalle administrativo de venta |
| `PATCH` | `/api/sales/{sale}/cancel` | Cancelar venta y reponer inventario |
| `GET` | `/api/reports/dashboard` | Resumen y agregados administrativos del dashboard |
| `GET` | `/api/reports/dashboard/export?section=...` | Descargar CSV de una sección de reportes |

La exportación requiere `section`: `sales_by_month`, `top_products`, `seller_ranking`, `branch_category_matrix` o `gross_margin_by_category`. Para productos también requiere `top_products_by=amount` o `top_products_by=units`. Acepta los mismos filtros opcionales `from`, `to` y `branch_id` del dashboard.

El endpoint de salud de Laravel está disponible en `/up`; `/` devuelve un JSON breve de identificación del API.

## Datos de prueba

Los seeders usan Faker con locale `es_MX` y datos locales definidos en código. Ejecutar de nuevo `php artisan db:seed` actualiza los catálogos y usuarios; las ventas con folio `SEED-` se regeneran.

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

## Roles y permisos

Los roles disponibles inicialmente son `admin` y `vendedor`; cada usuario conserva su slug en `users.role`, con llave foránea a `roles.slug`. Los permisos se relacionan con roles mediante `permission_role`. El seeder asigna el conjunto administrativo al admin y permisos de catálogo y venta al vendedor. Las reglas de sucursal siguen limitando a cada vendedor a su sucursal.

Después de aplicar migraciones, los permisos pueden volver a sincronizarse sin duplicados con `php artisan db:seed --class=RolePermissionSeeder`. Las comprobaciones del API usan permisos, no comparaciones directas del slug de rol.
