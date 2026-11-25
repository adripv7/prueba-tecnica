# Prompt principal del proyecto VentasLite

## Para qué sirve

Este es el prompt base para pedir ayuda de implementación o revisión del MVP. Antes de usarlo, comparte el estado actual del repositorio y acota la tarea concreta. No sustituye la especificación de la prueba técnica.

## Prompt

> Ayúdame a desarrollar VentasLite de forma incremental y explicable. Primero inspecciona el repositorio y las instrucciones existentes; conserva el stack y las decisiones que ya estén implementadas salvo que haya una razón concreta para cambiarlas. La especificación fuente es la prueba técnica «Programador Jr. (React + PHP + IA)» recibida el 24 de noviembre de 2026. El backend es Laravel 12; el frontend se hará completamente por separado en React. Para el asistente de IA se usará Ollama local inicialmente.
>
> Requisitos obligatorios que debes respetar:
>
> - Backend Laravel 12 con API REST JSON en PHP 8.2 o superior; frontend independiente en React 18 o superior; MySQL 8 o MariaDB; autenticación por token. El asistente se conectará inicialmente a Ollama local desde el backend.
> - Crear la base de datos mediante migraciones, con las tablas `users`, `branches`, `categories`, `products`, `sales` y `sale_items`, llaves foráneas e índices necesarios. Guardar dinero como `DECIMAL(12,2)`, nunca `FLOAT`; preservar en cada renglón el precio vendido (`unit_price`).
> - Generar datos con seeders/factories: 1 admin y 8 vendedores, 4 sucursales, 8 categorías, 150 productos y 2,500 ventas con aproximadamente 7,500 renglones. Incluir patrones visibles en reportes, cerca de 5 % de ventas canceladas, consistencia de importes y ejecución repetible sin duplicar catálogos.
> - Proteger rutas y aplicar roles en el backend: el admin consulta todo y el vendedor solo sus propias ventas. Implementar login, logout, bloqueo tras 5 intentos fallidos en 1 minuto, listado paginado de 20 ventas, filtros, detalle, alta transaccional que descuente inventario y cancelación solo por admin que reponga inventario.
> - Calcular reportes con agregaciones SQL en el backend, con filtros por fechas y sucursal. Las ventas canceladas no cuentan. Incluir dashboard y comparación contra el periodo anterior, ventas mensuales, top 10 productos, ranking de vendedores, cruce sucursal/categoría, margen por categoría y exportación CSV o Excel.
> - El asistente de IA solo recibe contexto agregado generado por el backend; sus respuestas deben citar cifras reales. Nunca ejecutar SQL generado por el modelo. Si no hay clave o falla el proveedor, el resto del sistema sigue disponible y se informa el error.
> - Mantener validación del lado servidor, secretos fuera del repositorio, `.env.example` sin secretos, formato mexicano, estados de carga/vacío/error/confirmación, diseño consistente, accesibilidad básica y uso desde 375 px.
> - Documentar instalación en menos de 10 comandos, decisiones y pendientes; preparar wireframe, capturas de escritorio y celular, despliegue y demostración. Hacer cambios pequeños que pueda explicar y registrar los prompts importantes, el resultado de la IA, mis correcciones y el motivo en la bitácora.
>
> Para la tarea actual, trabaja únicamente en: **[describe aquí una tarea concreta]**. No ejecutes comandos, migraciones, seeders, pruebas ni herramientas de terceros sin que el usuario lo solicite; si hacen falta, indica el comando y espera a que el usuario lo ejecute. Antes de editar, resume qué archivos tocarás y cómo la solución cumple las reglas relacionadas. Al terminar, explica el cambio, las decisiones y cualquier requisito pendiente. No inventes requisitos ni credenciales; si un detalle no está especificado, elige una opción sencilla y documenta la decisión.

## Por qué elegí este prompt

La prueba evalúa tanto el funcionamiento como la capacidad de explicar el código. Este prompt pone primero las reglas que más afectan la arquitectura y que pueden causar errores difíciles de corregir después: stack, migraciones y relaciones, precisión monetaria, permisos comprobados en backend, consistencia de inventario y límites de seguridad del asistente de IA. También pide cambios acotados y una bitácora honesta para poder defender el trabajo.

## Alcance y uso

- Es un prompt de referencia; no significa que estas funciones ya estén implementadas.
- Indica una tarea específica cada vez y revisa el resultado antes de conservarlo.
- Registra en `AI_LOG.md` los momentos importantes. La prueba pide al menos 8 entradas; esta bitácora inicia con una y debe crecer conforme avance el proyecto.
- Cuando el usuario solicite una corrección, registra el cambio y su motivo en `docs/correcciones-backend.md`.

## Resumen del alcance de la prueba

La entrega contempla un MVP de ventas con login y roles, sucursales, productos y ventas; 2,500 ventas sembradas con datos realistas; reportes agregados y exportables; asistente en español con IA; interfaz responsiva; repositorio con commits frecuentes; despliegue público con datos y login; README, wireframe, capturas y video de demostración. Docker Compose, pruebas automatizadas, caché y modo oscuro son puntos extra.
