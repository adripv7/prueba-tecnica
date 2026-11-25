# VentasLite — Backend

API REST de VentasLite. Está construido con Laravel 12, PHP, Eloquent ORM, MySQL y Laravel Sanctum. El frontend React vive por separado en `../frontend-ventaslite`.

## Requisitos

- PHP 8.2 o superior y extensiones de Laravel/PDO MySQL.
- Composer 2.
- MySQL 8 compatible.
- Opcional para responder preguntas de reportes en local: Ollama instalado y el modelo configurado descargado.

## Preparar y ejecutar el backend

Abre una terminal en esta carpeta (`backend-ventaslite`) y ejecuta:

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Antes de migrar, crea una base MySQL llamada `ventas-lite` (por ejemplo con phpMyAdmin o MySQL Workbench). Revisa el `.env` y configura `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` para que coincidan con tu instalación. Después:

```bash
php artisan migrate --seed
php artisan serve
```

Laravel queda escuchando normalmente en `http://127.0.0.1:8000`. Mantén esta terminal abierta. Para reiniciar el servidor, vuelve a ejecutar `php artisan serve`.

El seeder crea catálogos, usuarios, inventarios y ventas de muestra. Si ya migraste antes, `php artisan migrate --seed` aplica solo migraciones pendientes y ejecuta los seeders configurados.

## Iniciar el sistema completo

El frontend y el backend son proyectos y repositorios independientes. Con el API funcionando, abre otra terminal:

```bash
cd ../frontend-ventaslite
npm install
npm run dev
```

Abre la URL que muestre Vite, normalmente `http://localhost:5173`. En desarrollo, Vite reenvía `/api` a `http://127.0.0.1:8000`; no hace falta configurar CORS entre esos dos procesos locales. Si Laravel usa otro host o puerto, configura `VITE_PROXY_API_TARGET` en `frontend-ventaslite/.env.local`.

## Usuarios locales

- Administrador: `admin@ventaslite.test` / `password`.
- Vendedor de ejemplo: `vendedor.s01.01@ventaslite.test` / `password`.
- Los demás vendedores siguen el formato `vendedor.sNN.NN@ventaslite.test` y usan `password` en datos semilla.

Son credenciales de desarrollo; cámbialas o elimínalas antes de publicar un entorno real.

## Proveedor de IA

La configuración de ejemplo usa Ollama local (`AI_PROVIDER=ollama`, `OLLAMA_URL=http://127.0.0.1:11434`, `OLLAMA_MODEL=qwen3:4b`). Para habilitarlo, arranca Ollama y descarga el modelo una sola vez:

```bash
ollama pull qwen3:4b
```

Para una prueba puntual con Groq, configura `AI_PROVIDER=groq`, `GROQ_MODEL` y `GROQ_API_KEY` en el `.env` local. En producción, configura la llave como secreto del servicio de hosting. Nunca la pongas en React, en una variable `VITE_*` ni en Git. Groq es un servicio externo: requiere internet y sus cuotas/límites dependen de la cuenta y el modelo.

El asistente de IA solo recibe el resumen agregado de reportes preparado por Laravel; no recibe tablas completas, SQL, ventas crudas ni datos de clientes. Las ventas canceladas se excluyen de todos los reportes. Si el proveedor no responde o se alcanza una cuota, el API devuelve un error controlado y los demás módulos siguen disponibles.

## Pruebas automatizadas

La base y sus migraciones deben estar preparadas antes de correr las pruebas. Las pruebas HTTP se ejecutan con:

```bash
php artisan test
```

Para probar un módulo:

```bash
php artisan test --filter=CatalogTest
php artisan test --filter=SalesTest
php artisan test --filter=SaleHistoryTest
php artisan test --filter=DashboardReportTest
php artisan test --filter=DashboardReportCacheTest
php artisan test --filter=ReportAssistantTest
php artisan test --filter=RolePermissionTest
```

Las pruebas Feature pasan por rutas, autenticación, validación, controladores y base de datos. Usan `DatabaseTransactions` para revertir los registros de cada prueba; `phpunit.xml` apunta a la base MySQL `ventas-lite` y usa caché en memoria. Mantén migraciones al día. No uses una base compartida con datos que no quieras utilizar en pruebas.

## Rutas principales

Las rutas API están en `routes/api.php`. Las rutas privadas usan Sanctum y requieren `Authorization: Bearer <token>`.

| Método | Ruta | Función |
| --- | --- | --- |
| `POST` | `/api/login` | Inicia sesión y emite token |
| `GET` | `/api/user` | Devuelve la sesión, rol y permisos |
| `POST` | `/api/logout` | Revoca el token actual |
| `GET` | `/api/branches` | Lista sucursales permitidas |
| `GET` | `/api/categories` | Lista categorías |
| `GET` | `/api/products` | Busca productos por texto, SKU, categoría y sucursal |
| `POST` | `/api/sales` | Registra venta y descuenta existencias |
| `GET` | `/api/sales` | Historial paginado con filtros |
| `GET` | `/api/sales/{sale}` | Detalle de venta autorizado |
| `PATCH` | `/api/sales/{sale}/cancel` | Cancela y restituye inventario con auditoría |
| `GET` | `/api/reports/dashboard` | Resumen y reportes agregados |
| `GET` | `/api/reports/dashboard/export` | Exporta una sección como CSV |
| `GET` | `/api/reports/assistant/status` | Estado del proveedor y uso mensual informado |
| `POST` | `/api/reports/assistant` | Pregunta sobre los reportes y genera resumen ejecutivo |

## Configuración del frontend y despliegue

En producción, define `VITE_API_BASE_URL` en Vercel con la URL HTTPS del backend y sufijo `/api`. En el `.env` del backend, incluye el dominio del frontend en `CORS_ALLOWED_ORIGINS`. El API debe permitir los headers de autorización que utiliza Sanctum. El hosting del backend también debe tener PHP, MySQL, Composer, variables de entorno, migraciones y seeders configurados.

## Mapa del código

- `routes/api.php`: mapa de URLs y control de acceso por token.
- `app/Http/Controllers/Api`: reciben la petición HTTP y producen JSON/CSV.
- `app/Http/Requests/Api`: validan entradas antes de la lógica de negocio.
- `app/Http/Resources/Api`: definen la forma pública de las respuestas.
- `app/Models`: modelos Eloquent y relaciones entre tablas.
- `app/Services`: reglas de ventas, reportes, caché y asistente IA.
- `database/migrations`: tablas, llaves foráneas e índices.
- `database/factories` y `database/seeders`: datos de muestra repetibles.
- `tests/Feature/Api`: pruebas del comportamiento de los endpoints.
- `docs/`: decisiones técnicas, requerimientos y registro de correcciones.
- `AI_LOG.md`: bitácora de uso de IA relacionada con el proyecto.