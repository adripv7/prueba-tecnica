# Correcciones registradas

## 2026-11-25 — Error al cargar existencias por sucursal

**Reporte:** `php artisan test --filter=CatalogTest` terminó con 3 pruebas aprobadas y 1 fallida. La consulta de productos devolvió HTTP 500 al cargar la relación de inventario.

**Causa:** el callback de `with('inventories', ...)` estaba tipado como `Eloquent\Builder`, pero Laravel le entrega una relación `HasMany` para configurar la carga anticipada.

**Corrección aplicada:** se cambió el tipo del callback a `Illuminate\Database\Eloquent\Relations\HasMany`. El usuario volvió a ejecutar `CatalogTest` y reportó 4 pruebas aprobadas (27 aserciones); tras añadir las reglas de sucursal, reportó 6 pruebas aprobadas (38 aserciones).

## 2026-11-25 — Ajustes en DashboardReportTest

**Reporte:** el test falló al insertar `sold_at` con fecha de 2099 porque MySQL `TIMESTAMP` no admite ese año. Después, el porcentaje calculado como `40` falló contra una comparación estricta con `40.0`.

**Corrección aplicada:** se cambió el fixture a febrero de 2037 y se congeló/restableció el reloj durante el test para que la venta aparezca en el rango de 12 meses. La aserción del porcentaje ahora compara el número sin exigir una representación decimal específica. El usuario reportó posteriormente 2 pruebas aprobadas y 21 aserciones.

## 2026-11-25 — Reducir la demora percibida de Qwen3

**Reporte:** al probar `qwen3:4b` desde la consola, el modelo tardó en una pregunta sencilla y mostró un razonamiento extenso antes de responder.

**Causa:** Qwen3 tiene modo de razonamiento que puede estar activo en la sesión interactiva de Ollama.

**Corrección aplicada:** se recomendó `/set nothink` para la sesión de consola. La petición HTTP del backend envía `think=false`, limita el contexto a 4096 y la salida a 160 tokens; el prompt exige una respuesta directa de máximo cuatro frases. Se eligió `qwen3:4b` como modelo predeterminado por el equilibrio entre memoria y velocidad esperado en el hardware reportado. La conexión real con Ollama queda por probar.

## 2026-11-25 — Evitar respuestas de razonamiento sin conclusión

**Reporte:** el endpoint devolvió un análisis en inglés y truncado, en vez de una conclusión breve en español.

**Corrección aplicada:** se cambió la petición a salida JSON estructurada con una sola propiedad `answer`, se redujo el máximo de salida a 160 tokens y Laravel valida que el JSON y la propiedad tengan el tipo esperado antes de responder. Si el modelo devuelve texto libre o un formato inválido, el API responde con error controlado y no filtra ese contenido. Se agregó un test para este caso; queda pendiente su ejecución junto con los tests actualizados del asistente.

## 2026-11-25 — Respuesta genérica de Ollama a consultas de sucursal

**Reporte:** al preguntar qué sucursal tuvo menos ventas y el total, Qwen3 describió el JSON en inglés y truncó la respuesta, sin contestar la pregunta.

**Causa probable:** se enviaba el dashboard completo para cualquier pregunta, con secciones extensas y distractoras para un modelo pequeño.

**Corrección aplicada:** el backend arma un contexto compacto con KPIs y solo la sección relacionada con la pregunta (sucursales, productos, vendedores, meses, márgenes o matriz). Para sucursales incluye ventas totales con IVA y ventas netas antes de IVA, e incluye también las sucursales sin ventas con valor cero. El prompt exige contestar directamente, citar cifras reales y señalar cuando los datos no permiten explicar una causa.

**Reglas del PDF consideradas:** el servicio de reportes ya filtra únicamente ventas pagadas, por lo que las canceladas no participan en métricas ni contexto de IA. El modelo solo recibe resúmenes agregados; no recibe tablas completas, ventas individuales ni SQL. El resumen ejecutivo usa los KPIs del periodo seleccionado.

**Verificación:** se actualizó `ReportAssistantTest` para revisar el contexto sucursal y la exclusión de secciones irrelevantes. El usuario debe ejecutar la prueba y volver a probar la petición real contra Ollama.

## 2026-11-25 — Falso rechazo de una consulta permitida

**Reporte:** al preguntar por la sucursal con menos ventas y su total, Ollama respondió el texto fijo de rechazo, pese a ser una comparación agregada contemplada por el MVP.

**Corrección aplicada:** se aclaró en el prompt que comparar una métrica presente no está fuera de alcance. Cuando el backend detecta una consulta de sucursal, le agrega una guía concreta para comparar ventas totales con IVA, identificar sucursal y ciudad, citar el periodo y mencionar empates. La guía positiva no se agrega si la pregunta también contiene términos sensibles.

**Verificación:** se añadió una aserción para confirmar que la guía de comparación de sucursales llega al mensaje de Ollama. El usuario debe volver a ejecutar `php artisan test --filter=ReportAssistantTest` y probar el endpoint real; no se ejecutaron comandos localmente.

## 2026-11-25 — Ampliar la asistencia analítica para administración

**Solicitud:** reforzar el asistente para que cubra las preguntas administrativas del punto de venta, consulte los agregados existentes y compare/calculе cifras con mayor consistencia.

**Corrección aplicada:** se amplió el alcance documentado a KPIs, IVA, operaciones, ticket, periodos, meses, sucursales, productos, vendedores, categorías, matriz y margen. El backend clasifica temas, entrega la sección agregada pertinente y usa hallazgos compactos para resumen ejecutivo. Para sucursales prepara mínimos, máximos y empates; para el resto el prompt instruye qué valores comparar. Se especifica que el ranking de productos solo incluye top 10, que no se deben sumar dos veces importes con IVA y que las métricas no prueban causalidad.

**Límites conservados:** el asistente de reportes no recibe tablas crudas, ventas individuales, clientes ni IDs internos; no genera SQL ni ejecuta acciones de POS. Los reportes usan solo ventas pagadas. Consultas de inventario operativo y acciones de venta quedan fuera de este asistente hasta definir sus métricas y endpoints.

**Verificación:** se añadieron pruebas del contexto de productos, resumen ejecutivo, pregunta multitema y un intento de inyección que solicita clientes. Toda la nueva cobertura queda pendiente de ejecución por el usuario.

## 2026-11-25 — Aserción desactualizada en ReportAssistantTest

**Reporte:** el usuario ejecutó `php artisan test --filter=ReportAssistantTest`: 8 pruebas pasaron y falló `admin can ask about aggregated reports without sending raw sales` porque Laravel no encontró una petición que satisficiera el callback de `Http::assertSent`.

**Causa:** el test seguía buscando la frase `Contesta directamente lo que pregunta el usuario`, que se reemplazó al ampliar el prompt. El comportamiento y la petición HTTP se ejecutaron, pero la aserción de texto no correspondía al prompt actual.

**Corrección aplicada:** el test ahora busca `Contesta exactamente lo preguntado`, presente en el prompt implementado.

**Verificación:** el usuario volvió a ejecutar `php artisan test --filter=ReportAssistantTest` y reportó 9 pruebas aprobadas (26 aserciones).

## 2026-11-25 — Primera validación con Ollama local real

**Prueba manual:** pregunta por la sucursal con menos ventas del 2026-08-25 al 2026-11-25.

**Resultado reportado:** Qwen3:4b contestó directamente: Querétaro, `140,064.81 MXN`, con las fechas del filtro. La respuesta ya no fue genérica ni de rechazo. El monto debe contrastarse con el total del dashboard para confirmar que ambos usan el mismo concepto (ventas totales con IVA).

**Siguiente validación sugerida:** probar el resumen ejecutivo y consultas de ranking/productos/margen con el mismo filtro.

## 2026-11-25 — JSON inválido en pregunta multitema de Ollama

**Reporte:** después de recibir una respuesta correcta a la consulta por sucursal, una pregunta que combinaba KPIs, periodo anterior, sucursales, producto y margen devolvió `OLLAMA_INVALID_RESPONSE`.

**Causa probable:** la respuesta estructurada excedió el presupuesto de salida configurado (`num_predict=160` y `maxLength=600`), dejando JSON incompleto. No se recibió el contenido crudo de Ollama para confirmar la causa.

**Corrección aplicada:** se amplió el máximo estructurado a 1000 caracteres y el presupuesto de generación a 320 tokens. El prompt sigue limitando la respuesta a cuatro frases y Ollama continúa sin razonamiento visible (`think=false`). Se agregó una aserción para proteger el nuevo límite.

**Verificación:** el usuario debe ejecutar `php artisan test --filter=ReportAssistantTest` y repetir la pregunta multitema con Ollama local. El ajuste es una hipótesis razonable, pendiente de confirmar con la respuesta real.

## 2026-11-25 — Revisar `.env` y completar `.env.example`

**Solicitud:** verificar si `.env` está versionado y reflejar su configuración en `.env.example`, como pide la prueba.

**Resultado:** se inspeccionó el índice local de Git sin ejecutar comandos; `.env` y `.env.example` no aparecen versionados actualmente. Se completaron en `.env.example` todas las claves presentes en `.env`, se conservaron las variables de Ollama y se sustituyeron valores sensibles por vacíos o valores genéricos. No se leyó ni compartió ningún valor secreto en la conversación.

## 2026-11-25 — Reforzar exclusión de archivos `.env`

**Solicitud:** corregir `.gitignore` porque el usuario observó que `.env` se estaba enviando al repositorio.

**Corrección:** se anclaron reglas explícitas para excluir `/.env`, `/.env.*` y variantes anidadas, dejando como única excepción `.env.example`.

**Comprobación estática:** el `.env` no aparece ni en el índice actual ni en el árbol del commit `development` de este checkout. Si VS Code aún lo muestra, se debe confirmar que Source Control está abierto en este repositorio y no en otro checkout/índice; las reglas nuevas tampoco pueden quitar del índice un archivo que ya se hubiera agregado explícitamente.


