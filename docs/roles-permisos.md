# Prompt documentado: roles y permisos

## Solicitud relevante

> Definir roles en una tabla y permisos en otra para poder asignar permisos a cada tipo de usuario. Implementar el control después de los módulos principales del backend y antes de integrar el asistente con Ollama.

## Por qué se documenta

El proyecto ya protegía algunas acciones comparando el texto `admin` directamente. Las tablas relacionales permiten cambiar permisos sin dispersar reglas por rol en todos los controladores y dejan claro qué puede hacer cada perfil.

## Decisiones implementadas

- `roles` identifica cada rol con un `slug`; `users.role` conserva temporalmente ese slug para no cambiar el contrato actual de autenticación y datos de fábrica. La columna ahora tiene una llave foránea a `roles.slug`.
- `permissions` contiene permisos identificados por slug y `permission_role` asigna permisos a roles con llaves foráneas y una clave primaria compuesta.
- El rol administrador recibe permisos para catálogo, inventario de todas las sucursales, ventas, historial, cancelación y reportes.
- El vendedor recibe acceso al catálogo y a registrar ventas; la API mantiene su límite a la sucursal asignada.
- El login y `GET /api/user` devuelven los slugs de permisos para que React adapte la navegación. La API sigue siendo la autoridad y vuelve a validar cada acción.
- El seeder de roles/permisos es repetible y sincroniza las asignaciones previstas.

## Verificación

La migración preserva los roles existentes y asigna los permisos iniciales para que el API siga autorizado después de actualizar el esquema. `RolePermissionSeeder` es repetible y vuelve a sincronizar nombres, descripciones y asignaciones.

El usuario aplicó la migración `2026_11_25_100000_create_roles_and_permissions_tables`, ejecutó `RolePermissionSeeder` y reportó `RolePermissionTest` aprobado: 2 pruebas y 12 aserciones. Posteriormente reportó toda la suite aprobada: 27 pruebas y 170 aserciones, sin regresiones en los módulos existentes.
