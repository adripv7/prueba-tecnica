# Prompt documentado: datos semilla para pruebas

## Solicitud relevante

> Generar seeders realistas para sucursales, trabajadores vendedores, categorías, productos, ventas y partidas de venta, siguiendo las cantidades y reglas del PDF. Usar factories y Faker en español de México. Los datos son para pruebas locales; las operaciones reales se harán después mediante la API.

## Por qué se documenta

Este prompt fija el alcance de los datos de prueba y evita confundirlos con registros operativos creados por usuarios desde React. Los seeders preparan un conjunto reproducible para probar listados, inventario, ventas y reportes; los endpoints de la API serán responsables de las altas y cambios reales.

## Reglas aplicadas

- 1 administrador con correo `admin@ventaslite.test` y contraseña `password`.
- 7 sucursales en ciudades de México; 4 vendedores asignados a cada una (28 en total), por encima del mínimo del PDF de 4 sucursales y 8 vendedores.
- 8 categorías y 150 productos; cada producto tiene precio mayor al costo y 15 productos inactivos (10 %).
- 2,500 ventas dentro de los últimos 18 meses, con más registros en diciembre y aproximadamente 5 % canceladas (125).
- 7,500 partidas de venta en total, con entre 1 y 6 productos por venta.
- Importes calculados en centavos enteros y el impuesto configurado al 16 %.
- Faker en locale `es_MX`; claves estables y folios `SEED-` permiten regenerar los datos de prueba.

## Estado

Los seeders y factories están preparados. La migración de `branch_id` en usuarios debe ejecutarse antes de sembrar. Aún no se han ejecutado los seeders desde este flujo; después de aplicar la migración, cargar los datos con `php artisan db:seed` o `php artisan migrate --seed` si también quedan migraciones pendientes.
