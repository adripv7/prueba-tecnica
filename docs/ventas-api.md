# Prompt documentado: registro de ventas desde React

## Solicitud relevante

> Continuar el backend Laravel 12 por módulos. Implementar el registro real de una venta desde el punto de venta de React, respetando el PDF: tomar precios del servidor, validar inventario de la sucursal, guardar importes y partidas, y calcular IVA al 16 %. Guardar los costos aplicados para conservar los márgenes históricos. No ejecutar Artisan ni pruebas sin autorización.

## Por qué se documenta

La interfaz permite construir un ticket, pero el servidor debe ser la autoridad para precio, disponibilidad, cálculo y persistencia. Registrar el flujo evita ventas parciales o montos alterados desde el navegador.

## Contrato y reglas implementados

- `POST /api/sales`, autenticado con Sanctum.
- Solicita `branch_id`, método de pago, y entre 1 y 6 partidas con `product_id` y `quantity`. El nombre del cliente es opcional y por defecto se usa `Público en General`.
- Un vendedor solo puede vender en su sucursal asignada; un administrador puede elegir cualquier sucursal.
- El servidor lee precio, costo y existencias desde la base. Ignora cualquier importe enviado por el frontend.
- Productos inactivos o inventario insuficiente rechazan la operación.
- Producto, inventario, venta y partidas se procesan en una transacción; se bloquean filas de producto e inventario para prevenir sobreventa concurrente.
- Subtotal, IVA (16 %) y total se calculan en centavos enteros. Se descuenta el inventario de la sucursal.
- `sale_items.unit_cost` conserva el costo aplicado a la fecha de venta. Ese costo no se devuelve al frontend.
- Folio generado con ULID y prefijo `VTA-`.
- Los seeders usan Faker `es_MX` también para seleccionar los productos de las ventas de prueba.

## Historial y cancelación

- `GET /api/sales` ofrece búsqueda por folio/cliente, filtros por estado, sucursal y fechas, y paginación; solo administrador.
- `GET /api/sales/{sale}` devuelve detalle, sin exponer el costo de cada partida.
- `PATCH /api/sales/{sale}/cancel` solo permite administrador, conserva auditoría de fecha/usuario/motivo y repone existencias en una transacción. Una venta cancelada no puede cancelarse de nuevo.
- El usuario reportó `SaleHistoryTest` aprobado: 4 pruebas y 22 aserciones.
- El usuario reportó `SalesTest` aprobado: 5 pruebas y 25 aserciones.

## Pendiente

Las migraciones de costo unitario y auditoría de cancelación ya se aplicaron en el entorno local del usuario. El registro, historial y cancelación quedaron verificados por sus pruebas Feature.
