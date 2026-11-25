# Prompt documentado: API de catálogo para React

## Solicitud relevante

> Continuar el proyecto por módulos, enfocados en el backend Laravel 12. Revisar el boceto de React para entender los datos que presenta y construir consultas autenticadas de sucursales, categorías y productos que alimenten el punto de venta. Respetar las reglas del PDF y no ejecutar comandos, migraciones ni pruebas sin autorización.

## Por qué se documenta

El catálogo conecta el diseño del punto de venta con una respuesta concreta del API. Documentar los filtros, campos expuestos y decisiones de seguridad evita que React tenga que inventar datos o calcular información autoritativa.

## Alcance implementado

- `GET /api/branches`: sucursales ordenadas por nombre.
- `GET /api/categories`: categorías ordenadas por nombre.
- `GET /api/products`: productos activos, búsqueda por nombre o SKU, filtro opcional por categoría y paginación limitada. Requiere `branch_id` para informar existencias de esa sucursal.
- Las tres rutas requieren autenticación Sanctum.
- La respuesta de producto incluye identificador, SKU, nombre, categoría, precio y existencias de la sucursal seleccionada. No expone el costo.
- Se añadieron pruebas Feature para autenticación, búsqueda, exclusión de productos inactivos, filtros, paginación y permisos de sucursal. El usuario reportó 6 pruebas aprobadas (38 aserciones).

## Pendientes y límites conocidos

- Se añadió `inventories`, con claves foráneas reales, unicidad por sucursal/producto e índice por sucursal y stock. `InventorySeeder` genera existencias para cada combinación existente.
- La columna global `products.stock` queda como dato legado del esquema anterior y no se expone en el catálogo; podrá retirarse en una migración posterior cuando se cierre la transición.
- Los seeders de productos/categorías aún deben alinearse con el giro de herramientas mostrado en el boceto.
- La migración y el seeder de inventario deben aplicarse antes de verificar el endpoint con la prueba Feature.
