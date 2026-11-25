# Revisión de estructura y uso de Eloquent

## Prompt relevante

> Revisar que el backend esté bien estructurado, que use correctamente Eloquent ORM y que las consultas estén optimizadas. Mantener presentes las reglas del PDF y registrar los prompts importantes y las correcciones solicitadas.

## Alcance revisado

Se revisaron rutas API, controladores, modelos y relaciones, migraciones, factories, seeders y pruebas Feature existentes. La revisión fue estática: no se ejecutaron comandos de Laravel ni pruebas.

## Evaluación actual

- El backend está separado por modelos, controladores API, migraciones, seeders y pruebas.
- Se retiró el scaffolding de frontend Vite/Blade del proyecto Laravel; el repositorio queda enfocado en la API y React puede vivir en su propio proyecto.
- Las rutas de catálogo requieren Sanctum y los filtros se validan antes de consultar.
- Las consultas del catálogo usan Eloquent, cargan anticipadamente categorías e inventarios y construyen una respuesta explícita que no expone el costo.
- Los seeders usan operaciones por lote (`upsert`/`insert`) para los datos masivos; es una decisión adecuada para no ejecutar miles de escrituras Eloquent individuales.
- Las tablas principales tienen claves foráneas e índices. `inventories` evita duplicar el mismo producto dentro de una sucursal.
- `ProductSeeder` ahora resuelve categorías por nombre para no asignar productos a categorías antiguas que hayan quedado en la base.

## Pendientes relevantes antes de ventas/reportes

1. El catálogo y el registro de ventas limitan a los vendedores a su `branch_id`; historial y cancelación son solo de administrador. La revisión futura de permisos debería centralizar estas reglas.
2. Retirar la duplicidad conceptual de `products.stock` frente a `inventories.stock`, con una migración segura una vez cerrada la transición.
3. `sale_items.unit_cost` ya guarda el costo unitario como snapshot para que el margen histórico no dependa del costo actual del producto. La migración rellena ventas existentes con el costo actual disponible, que no permite reconstruir cambios históricos anteriores.
4. Alinear las categorías y los SKU de seed con el catálogo de herramientas del boceto.
5. La búsqueda actual contiene comodines al inicio (`%texto%`), por lo que un índice B-tree no acelerará esa búsqueda cuando el catálogo crezca. Para los 150 productos requeridos por el PDF es suficiente; se puede cambiar a búsqueda por prefijo o full-text al aumentar el volumen.
6. Los endpoints del dashboard agregan ventas pagadas por periodo y sucursal, productos, vendedores, categorías y margen usando el costo snapshot de las partidas. La exportación CSV reutiliza esos agregados para conservar las mismas reglas y filtros.

## Verificación

- El usuario reportó `CatalogTest` aprobado: 6 pruebas y 38 aserciones.
- El usuario reportó `SalesTest` aprobado: 5 pruebas y 25 aserciones.
- El usuario reportó `SaleHistoryTest` aprobado: 4 pruebas y 22 aserciones.
- Las migraciones de costo unitario y auditoría de cancelación ya se aplicaron en su entorno local.
- El usuario reportó `DashboardReportTest` aprobado: 2 pruebas y 21 aserciones. Se corrigió el fixture para usar una fecha válida de MySQL `TIMESTAMP` y congelar el reloj del test.
- Se añadieron exportaciones CSV administrativas para ventas mensuales, productos, ranking de vendedores, matriz sucursal/categoría y margen. Las nuevas aserciones de descarga están pendientes de ejecución por el usuario.
- Se agregó estructura relacional de roles/permisos y se sustituyeron comparaciones directas con `admin` en las rutas de catálogo, venta, historial y reportes por comprobaciones de permisos. El usuario reportó migración y seeder ejecutados, `RolePermissionTest` aprobado (2 pruebas, 12 aserciones) y suite completa aprobada (27 pruebas, 170 aserciones).
- Se implementó `POST /api/reports/assistant` usando Ollama local, permisos, filtros de dashboard, límites de frecuencia y errores controlados. El usuario reportó `ReportAssistantTest` aprobado: 9 pruebas y 26 aserciones. La cobertura contempla permisos, contexto por tema, resumen ejecutivo, consultas multitema, un intento de inyección que pide datos de clientes y errores de Ollama. El usuario también probó Ollama real: para el periodo 2026-08-25 a 2026-11-25, Qwen3:4b identificó Querétaro con `140,064.81 MXN` como la sucursal con menos ventas. Se recomienda contrastar esa cifra con el dashboard y probar otros tipos de pregunta. El prompt define alcance y privacidad; se eliminan IDs internos del contexto. Las métricas usan ventas pagadas y preservan la diferencia entre importes con IVA y netos. El asistente no ejecuta acciones del POS ni consultas SQL.

## Cierre de pendientes principales

### Caché de reportes

El resultado completo de `DashboardReportService` se cachea por periodo y sucursal durante `REPORTS_CACHE_TTL` (60 segundos por defecto). El CSV y el asistente que usan los reportes reutilizan esa respuesta agregada. `DashboardReportCache` usa versionado para invalidar sin depender de cache tags cuando una venta se registra o cancela. Si el store falla, el dashboard continúa consultando directamente la base y registra una advertencia. Se agregó `DashboardReportCacheTest`; su ejecución queda pendiente.

En la última revisión se agregó `.gitignore`, permiso `sales.view_own` con historial/detalle aislado por vendedor y filtro `seller_id` para el admin, catálogo semilla de ferretería con SKU generados por Faker, migración idempotente de los nombres/códigos semilla previos, `AI_LOG.md` dentro del backend y una normalización adicional de JSON de Ollama. En una revisión posterior se confirmó estáticamente que `.env` no aparece en el índice ni en el commit local `development`; `.env.example` contiene la estructura con valores seguros. La migración incremental y los seeders de categoría/producto aún deben aplicarse en el entorno local.

El asistente conserva una validación preventiva para solicitudes sensibles, pero no formula respuestas analíticas deterministas. Envía todos los agregados compactos del periodo (sin IDs, clientes ni ventas individuales) y deja al modelo interpretar métricas, comparaciones, umbrales, fechas incompatibles y periodos sin ventas. Tras una llamada real que respondió sobre margen ante una pregunta de productos de un mes fuera de los filtros, se reforzó la priorización de tema/periodo, el uso exclusivo de hechos presentes en contexto y el formato de lista. La calidad de esa nueva instrucción está pendiente de comprobarse con Ollama real; las pruebas HTTP simuladas solo verifican el contexto enviado. Groq queda seleccionable para producción y Ollama para desarrollo local. Groq requiere `GROQ_API_KEY` como secreto del hosting.
