# Reauditoría de diseño — DEMO multi-inquilino

**Proyecto:** realestatesystemDemo  
**Fecha:** 2026-08-03  
**Auditor:** Codex  
**Documento base:** `docs/audits/auditoria-demo-multi-inquilino.md`  
**Documentos re-auditados:** `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md`, `docs/epicas/epica-demo-multi-inquilino.md`, lotes A–F, `docs/rfcdemo/`, `docs/deployment/DEMO-MULTI-INQUILINO.md`  
**Alcance:** verificar si las correcciones posteriores a la auditoría cerraron los hallazgos y si introdujeron contradicciones nuevas.

## Estado de los hallazgos (respuesta del equipo)

| Hallazgo | Estado |
|---|---|
| C-1 Los documentos altos prometían cierre fuerte | **Corregido por la opción A.** El alcance de la épica ya no dice «aislamiento total» de archivos sino **separación**, y el objetivo de RFC-14 dice «nadie de afuera puede navegarlo» en vez de «no lo ve nadie» |
| M-1 RFC-05 con «todo va en cola» | Corregido: en fase 1 la ejecuta el comando, síncrona |
| M-2 `fallido` sin nota en RFC-01 y lote A | Corregido en los dos |
| M-3 La auditoría anterior contradictoria | Corregido: lleva aviso de documento superado |
| Mn-1 RFC-13 abría con «sin infraestructura de cola» | Corregido en el objetivo |
| Mn-2 `CONNECTION LIMIT 0` sin camino de vuelta | Corregido: abortar restaura el límite, con su test |

**Respuestas a las preguntas pendientes**

1. **Media pública**: decisión de fase 1, no definitiva. RFC-14 dice que se
   revisa si el demo se abre al público.
2. **El aviso en la invitación**: sí, es un paso del comando (RFC-13, paso 6),
   no una nota al operador. La reauditoría tiene razón en que depender de que
   alguien se acuerde no es protección.
3. **La auditoría anterior**: se conserva como histórico con aviso arriba. No se
   reescribe: el rastro de qué se encontró y cuándo vale más que un documento
   prolijo.
4. **Rollback de `CONNECTION LIMIT 0`**: sí, con test y como acción del padrón.

## Evidencia verificada en código real

- `.env.testing` fue corregido en el árbol de trabajo: `DB_DATABASE=demo_test` y `DB_PASSWORD=` (`.env.testing:36-41`). El diff local cambia desde `inmo_test`/`hobbit` hacia `demo_test`/vacío.
- `phpunit.xml` también fija `DB_DATABASE=demo_test` para `artisan test` (`phpunit.xml:29-30`).
- La auditoría anterior quedó desactualizada: todavía dice que `.env.testing` apunta a `inmo_test` (`docs/audits/auditoria-demo-multi-inquilino.md:7,34-35`) y mantiene veredicto de no listo por críticos ya corregidos (`docs/audits/auditoria-demo-multi-inquilino.md:55-61`).
- RFC-08 ya corrigió la pieza de Spatie: habla de `path_generator`, no `url_generator` (`docs/rfcdemo/RFC-08-AISLAMIENTO-DE-ARCHIVOS.md:29-35`).
- RFC-09, el consolidado y Lote F convergen en el orden de borrado `CONNECTION LIMIT 0` → terminar sesiones → borrar DB → borrar archivos (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:36-53`; `docs/epicas/epica-demo-multi-inquilino.md:295-306`; `docs/epicas/epica-demo-lotes-d-e-f-diseno.md:166-183`).
- RFC-14 acepta por escrito que la media publicada no está cerrada y se sirve por `/storage` sin pasar por Laravel (`docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:86-112`).

## Veredicto

⚠️ **Casi listo, pero todavía no lo marcaría como listo para implementar.**

La mayoría de los hallazgos anteriores fueron corregidos de verdad: `.env.testing`, borrado, `path_generator`, contraseña, tope duro, nombres de bases y `Panel::domain()` quedaron mejor. Bien ahí: eso no fue maquillaje.

Pero la corrección de media pública cambió el contrato del producto: el demo ya no es “cerrado” en sentido fuerte. Eso puede ser aceptable, pero entonces hay que propagarlo al objetivo y al alcance alto. Si no, el diseño le dice al operador “nadie de afuera ve nada” mientras otra sección dice “si tiene la URL de una imagen, la abre”. Esa contradicción sí puede producir fuga real de datos de prueba.

---

## Estado de hallazgos anteriores

| Hallazgo anterior | Estado reauditoría | Evidencia |
|---|---|---|
| C-1 `.env.testing` a `inmo_test` | **Cerrado en worktree** | `.env.testing:36-41` ahora usa `demo_test`. Falta confirmar commit. |
| C-2 `fallido` con dos contratos | **Cerrado en lo alto; queda ambigüedad menor** | Épica principal aclara “terminal para el ciclo, no para limpieza” (`docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:90-96`); RFC-05/Lote C explican base a medias. RFC-01 y Lote A siguen abreviados. |
| C-3 órdenes de borrado | **Cerrado** | Orden único en RFC-09, consolidado y Lote F. |
| C-4 media publicada no cerrada | **Aceptado, pero mal propagado** | RFC-14 lo acepta; épica/RFC-14 objetivo todavía prometen cierre fuerte. Nuevo C-1 abajo. |
| M-1 ownership/grants | **Suficientemente cerrado para diseño** | Deployment define roles, ownership y verificación antes de activar (`docs/deployment/DEMO-MULTI-INQUILINO.md:84-103`, `:135-145`). |
| M-2 nombres de bases | **Cerrado** | Lote A separa `demo_db`, `demo_central`, `demo_template_vN`, `demo_t_{slug}`, `demo_test` (`docs/epicas/epica-demo-lote-a-diseno.md:48-62`). |
| M-3 cola vs invitación | **Parcial** | RFC-13 aclara worker/cron, pero RFC-05 todavía dice “todo va en cola”. Nuevo M-1 abajo. |
| M-4 tope duro fase 1 | **Cerrado** | RFC-13 exige respetar tope duro desde fase 1 (`docs/rfcdemo/RFC-13-INVITACION.md:109-117`). |
| M-5 reimprimir contraseña | **Cerrado** | Lote C dice que no se puede reimprimir y se regenera aparte (`docs/epicas/epica-demo-lotes-b-c-diseno.md:166-180`). |
| M-6 `Panel::domain()` | **Cerrado** | Consolidado indica no declarar dominio en Filament (`docs/epicas/epica-demo-multi-inquilino.md:129-136`). |
| M-7 `path_generator` | **Cerrado** | RFC-08 explicita `path_generator` y por qué `url_generator` no alcanza. |
| Mn-1 registro central fase 1 | **Cerrado en RFC-14; revisar RFC-06 si se toca** | RFC-14 dice que host central no tiene página anónima (`docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:71-75`). |
| Mn-2 50 vs 48 tablas | **Sin bloqueo** | Ya no lo usaría como señal de completitud. Importan páginas, PostGIS, GIST, usuarios vacíos. |

---

## Hallazgos críticos

### C-1 — El diseño acepta media pública, pero los objetivos altos siguen prometiendo entorno cerrado fuerte

**Qué está mal:** RFC-14 ahora acepta que la media publicada no está cerrada. Eso es una decisión válida si se asume el tradeoff. El problema es que los documentos altos todavía prometen “aislamiento total” y “que no lo vea nadie más”, sin matizar que los bytes publicados quedan fuera de esa promesa.

**Evidencia:**

- `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:56-60`: alcance incluye “Aislamiento total: datos, archivos, caché y sesión” y “Entorno cerrado”.
- `docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:3-23`: objetivo dice que el sitio “no lo vea nadie más” y que completo exige sesión.
- `docs/rfcdemo/RFC-14-ENTORNO-CERRADO.md:86-112`: limita el cierre al HTML, acepta que `/storage` se sirve sin Laravel y advierte que quien tenga URL de imagen publicada entra.
- `docs/rfcdemo/RFC-13-INVITACION.md:41-53`: la invitación imprime aviso de no subir contenido que no pueda ser público.

**Escenario concreto de falla:** el owner lee la épica o el RFC-14 inicial, interpreta que “aislamiento total de archivos” significa confidencialidad, e invita a un prospecto sin repetir verbalmente la advertencia. El prospecto sube fotos reales de un inmueble de cliente. Luego copia una URL desde el HTML o la comparte en una captura; una persona sin sesión abre `/storage/tenants/{slug}/.../foto.webp`. El servidor web sirve la imagen. El sistema actuó como RFC-14 dice, pero contra la promesa del objetivo alto.

**Corrección segura:** elegir una sola verdad y propagarla arriba:

- Opción A: cambiar objetivo/alcance a “HTML cerrado; media publicada no confidencial”.
- Opción B: hacer media publicada privada por controlador en el demo.

No hace falta rediseñar si se elige A, pero sí hay que dejar de llamarlo “aislamiento total de archivos” sin matiz. ACÁ está la trampa: no es un bug técnico, es una promesa falsa en el contrato.

---

## Hallazgos medios

### M-1 — RFC-05 todavía dice “todo va en cola”, aunque fase 1 es comando de consola

**Qué está mal:** RFC-13 y la nota inicial de RFC-05 dicen que en fase 1 el alta la dispara un comando, sin cola para el alta. Pero las reglas de RFC-05 conservan “Todo va en cola”. Quedó una frase vieja en una sección normativa.

**Evidencia:**

- `docs/rfcdemo/RFC-13-INVITACION.md:23-29`: se elimina la cola para el alta, pero worker/cron siguen para expiración/borrado.
- `docs/rfcdemo/RFC-05-ALTA-DE-INQUILINO.md:19-22`: en fase 1 la sección 3 la ejecuta el comando en vez de un trabajo en cola.
- `docs/rfcdemo/RFC-05-ALTA-DE-INQUILINO.md:96-100`: regla 1 todavía dice “Ninguna parte del alta corre en el request. Todo va en cola.”

**Escenario concreto de falla:** se implementa `php artisan demo:invitar` para crear la fila y despachar un job, porque RFC-05 regla 1 lo ordena. El comando ya no puede imprimir credenciales al final de forma confiable: el alta ocurre después, en otro proceso. El usuario que invita recibe una salida incompleta o un “pendiente”, justo lo que RFC-13 quería evitar.

**Corrección segura:** cambiar regla 1 a algo como: “En fase 1 ninguna parte del alta corre en request web; el comando la ejecuta síncrona. En fase 2, el registro público encola.”

### M-2 — La excepción de limpieza para `fallido` no quedó en todos los documentos normativos

**Qué está mal:** la épica principal y Lote C ya corrigen bien que `fallido` puede tener base a medias. Pero RFC-01 y Lote A todavía muestran `fallido` como terminal sin aclarar “terminal para ciclo, no para limpieza”. Esto es más leve que antes, pero sigue siendo una fuente de error porque esos archivos definen modelo/estado.

**Evidencia:**

- Corregido arriba: `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md:90-96`.
- Corregido en alta: `docs/epicas/epica-demo-lotes-b-c-diseno.md:166-180`.
- Aún abreviado: `docs/rfcdemo/RFC-01-BASE-CENTRAL-Y-MODELO-DE-INQUILINO.md:59-70` dice `fallido` terminal sin nota de limpieza.
- Aún abreviado: `docs/epicas/epica-demo-lote-a-diseno.md:157-170` muestra `fallido → terminal` sin nota.

**Escenario concreto de falla:** quien implementa el enum/servicio de estados toma RFC-01/Lote A como fuente y trata `fallido` como “no hacer nada más”. Luego el comando falla después de crear la DB, deja `fallido` y la limpieza programada filtra sólo `expirado`, no `fallido`. La base a medias queda viva.

**Corrección segura:** agregar la misma nota en RFC-01 y Lote A: `fallido` no transiciona para usuarios, pero sí entra al barrido de bases a medias.

### M-3 — La auditoría anterior quedó internamente contradictoria y puede confundir la implementación

**Qué está mal:** el documento de auditoría anterior empieza con una tabla que marca hallazgos corregidos, pero conserva evidencia, veredicto y hallazgos viejos como si siguieran abiertos.

**Evidencia:**

- Tabla de estado dice que C-2/C-3 están corregidos (`docs/audits/auditoria-demo-multi-inquilino.md:13-25`).
- El alcance todavía dice que no se ejecutó suite porque `.env.testing` apunta a `inmo_test` (`docs/audits/auditoria-demo-multi-inquilino.md:7`), pero el archivo actual ya usa `demo_test` (`.env.testing:36-41`).
- El veredicto conserva “No está listo” por contradicciones que la propia tabla marca corregidas (`docs/audits/auditoria-demo-multi-inquilino.md:55-61`).
- La sección de hallazgos sigue desarrollando C-1/C-2/C-3 como abiertos (`docs/audits/auditoria-demo-multi-inquilino.md:86-129`).

**Escenario concreto de falla:** el próximo agente abre `docs/audits/auditoria-demo-multi-inquilino.md`, no lee la tabla inicial con suficiente cuidado o busca por “C-2”, y vuelve a corregir un problema ya cerrado. Peor: puede bloquear implementación diciendo “no listo” por razones vencidas.

**Corrección segura:** no reescribir historia, pero sí agregar arriba un bloque inequívoco: “Documento superado por `docs/audits/reauditoria-demo-multi-inquilino.md`; ver estado vigente allí”.

---

## Hallazgos menores

### Mn-1 — RFC-13 todavía abre con “sin infraestructura de cola” y recién después lo matiza

**Evidencia:**

- `docs/rfcdemo/RFC-13-INVITACION.md:3-6`: objetivo dice “sin registro público ni infraestructura de cola”.
- `docs/rfcdemo/RFC-13-INVITACION.md:26-29`: aclara que es sin cola para el alta, pero worker/cron siguen haciendo falta.

**Escenario concreto de falla:** alguien lee sólo el objetivo/índice del RFC y arma checklist de despliegue fase 1 sin worker/cron. El borrado/expiración no corre. Es similar a M-1, pero en RFC-13 ya está aclarado cuatro líneas después; por eso queda menor.

### Mn-2 — `CONNECTION LIMIT 0` necesita prueba explícita de restauración si el borrado se aborta

**Evidencia:**

- RFC-09 dice que `CONNECTION LIMIT 0` “se deshace igual de fácil si el borrado se aborta” (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:36-40`).
- La matriz de tests prueba borrar con sesión abierta y reintentar borrado interrumpido (`docs/rfcdemo/RFC-09-EXPIRACION-Y-BORRADO.md:79-84`), pero no nombra restaurar el límite si se decide abortar y no borrar.

**Escenario concreto de falla:** un operador aborta manualmente un borrado por reclamo del usuario después de cerrar la puerta, pero antes de `DROP`. La base queda con `CONNECTION LIMIT 0`; aunque se reactive el tenant, nadie puede conectarse. No es el camino feliz, pero el propio RFC dice que se puede deshacer.

**Corrección segura:** agregar un test o procedimiento de rollback operativo para “cerré puerta pero no borré”.

---

## Sobreingeniería detectada

- **No veo sobreingeniería nueva.** Las correcciones simplificaron: `path_generator` correcto, no `Panel::domain()`, borrado con un único orden, comando de invitación más directo.
- El único costo consciente es aceptar media pública para no servir imágenes por PHP. Es un tradeoff de performance/simpleza, no sobreingeniería. El problema es de contrato, no de exceso técnico.

## Riesgos de implementación

1. Implementar alta por cola en fase 1 por la regla vieja de RFC-05 rompe la entrega inmediata de credenciales.
2. Implementar limpieza sólo para `expirado` deja bases a medias de tenants `fallido`.
3. Seguir la auditoría anterior sin leer esta reauditoría puede reabrir problemas cerrados o bloquear por evidencia obsoleta.
4. Si el aviso de media pública no aparece en la invitación real, el diseño depende de que el operador “se acuerde”. Y ya sabemos: disciplina humana no es protección.

## Riesgos de seguridad

1. El mayor riesgo residual es comunicacional: la media publicada es pública por decisión, pero los documentos altos todavía suenan a confidencialidad total.
2. `trustProxies(at: '*')` sigue siendo un bloqueo de despliegue antes del primer invitado; el diseño lo tiene documentado, pero hay que verificarlo en servidor real.
3. `demo_app`/`demo_provisioner` está mejor diseñado; falta validarlo en implementación con una comprobación real antes de marcar tenant `activo`.

## Lo que está bien resuelto

- `.env.testing` ya no apunta a `inmo_test` en el árbol de trabajo.
- El orden de borrado quedó unificado.
- La decisión de no usar `Panel::domain()` está mejor que la anterior: evita una segunda fuente de verdad.
- `path_generator` quedó identificado como la pieza correcta para evitar colisión física.
- La contraseña ya no se promete reimprimir: se regenera.
- El tope duro rige desde fase 1, que era lo correcto.

## Preguntas pendientes

1. ¿La media publicada pública es una decisión definitiva de producto o sólo una concesión temporal del demo cerrado?
2. ¿La invitación real va a imprimir el aviso de media pública en un texto suficientemente visible para que no dependa del operador?
3. ¿Se va a limpiar la auditoría anterior o se va a dejar como histórico superado por esta reauditoría?
4. ¿`CONNECTION LIMIT 0` tendrá comando de rollback operativo cuando se aborte un borrado?
