# Prompt documentado: reportes del dashboard

## Solicitud relevante

> Implementar el API de reportes que necesita el dashboard React: filtros por fecha y sucursal, ventas por mes, productos más vendidos, ranking de vendedores, matriz sucursal/categoría y margen bruto. Excluir cancelaciones y calcular margen con el costo snapshot en cada partida. Mantener Laravel 12 como backend y no ejecutar pruebas ni comandos sin autorización.

## Por qué se documenta

Los reportes agregan información financiera e inventario para varias vistas del frontend. El backend centraliza los filtros y cálculos para que los totales no dependan de agregaciones inconsistentes en React.

## Contrato implementado

- `GET /api/reports/dashboard?from=YYYY-MM-DD&to=YYYY-MM-DD&branch_id=ID`.
- Solo administrador; rutas protegidas también requieren Sanctum.
- Periodo predeterminado: últimos 30 días. El gráfico mensual devuelve los últimos 12 meses.
- KPIs incluyen total con IVA, subtotal antes de IVA, IVA, cantidad de ventas, ticket promedio y comparación con el periodo anterior de igual duración.
- Todas las agregaciones excluyen ventas canceladas.
- Productos destacados incluyen ranking por importe y unidades; ranking de vendedores incluye ventas, total y ticket promedio.
- La matriz suma partidas por sucursal y categoría. El margen suma `line_total - unit_cost * quantity` usando el costo histórico guardado.
- Los montos se acumulan como DECIMAL en consultas SQL y se serializan con dos decimales.
- La ruta `GET /api/reports/dashboard/export` descarga CSV en UTF-8 con BOM para abrir correctamente acentos en hojas de cálculo. Requiere `section` (`sales_by_month`, `top_products`, `seller_ranking`, `branch_category_matrix` o `gross_margin_by_category`) y reutiliza los filtros `from`, `to` y `branch_id`.
- Para `section=top_products`, se debe indicar `top_products_by=amount` o `top_products_by=units`. Los encabezados distinguen importes con IVA de ingresos antes de IVA.

## Verificación

`DashboardReportTest` aprobado por el usuario: 2 pruebas y 21 aserciones. Verifica el acceso exclusivo de administrador, los agregados de ventas pagadas y las secciones del reporte.

El test congela el reloj en febrero de 2037 para usar una fecha compatible con MySQL `TIMESTAMP` y comprobar el gráfico de 12 meses. La aserción del margen compara el valor numérico sin exigir que JSON preserve `.0` cuando el porcentaje es entero.

Se añadieron verificaciones para permisos, nombre del archivo descargado, encabezados de las cinco secciones CSV y rechazo de secciones inválidas. El usuario reportó posteriormente la suite completa aprobada: 27 pruebas y 170 aserciones.
