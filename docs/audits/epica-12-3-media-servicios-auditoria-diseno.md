# Épica 12.3 — Auditoría independiente de diseño: Media de servicios

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Fecha:** 2026-07-27  
**Auditor:** Codex (modelo Sol)  
**Documento auditado:** `docs/epicas/epica-12-3-media-servicios-diseno.md`  
**Rama auditada:** `feature/epica-12-content-manager`  
**Commit auditado:** `3de0cd9`  

## 1. Veredicto

🔴 **RECHAZADO — GATE DE DISEÑO CERRADO**

El diseño identifica correctamente los conflictos heredados de 12.1 y acierta al
mantener la política de no borrado físico. Sin embargo, todavía no define un
contrato implementable y seguro para el límite entre media privada, promoción y
render público. Además, afirma que algunos invariantes son “imposibles por
construcción” aunque la base actual no los impone.

No debe comenzar la implementación de 12.3 hasta cerrar los hallazgos críticos y
actualizar la matriz de pruebas.

## 2. Evidencia verificada en código real

### Comandos ejecutados

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict` | ✅ `composer.json` válido. |
| `php artisan route:list --path=admin/frontend --except-vendor` | ✅ 8 rutas; no existe todavía ruta `frontend.services.media`. |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend/FrontendServiceMediaTest.php tests/Feature/Frontend/FrontendServicesRenderTest.php tests/Feature/Frontend/FrontendMediaPromotionTest.php` | ✅ 16 tests, 53 assertions, PostgreSQL real. Es la línea base existente; no valida aún 12.3. |
| `git status --short` | No hay código de 12.3 agregado; sí existen cambios no relacionados previos en el workspace. No fueron modificados. |

### Contratos reales comprobados

- `FrontendService` usa `SoftDeletes`, guarda `image_media_id` y registra la
  colección `image` sin `useDisk`, `singleFile` ni `onlyKeepLatest`:
  `app/Models/FrontendService.php:23-26,28-41,59-67`.
- El formulario permite `image_alt` nullable y el upload acepta PNG/JPEG/WebP,
  máximo 3 MB y un archivo: `app/Filament/Resources/FrontendServiceResource.php:51-73`.
- `EditFrontendService::afterSave()` toma la media más reciente, actualiza
  `image_media_id` y aumenta la generación de caché, pero no marca `pending`, no
  despacha `PromoteFrontendMedia` y no toma locks: 
  `app/Filament/Resources/FrontendServiceResource/Pages/EditFrontendService.php:24-41`.
- `FrontendServicesService::imageUrl()` resuelve cualquier UUID elegible y llama
  directamente a `getUrl()`; `FrontendMediaReference::resolve()` no verifica
  `promoted` ni el disco: `app/Services/Frontend/FrontendServicesService.php:180-188` y
  `app/Services/Frontend/FrontendMediaReference.php:40-70`.
- La migración sólo tiene FK a `media.uuid`; no hay índice único parcial para
  `image_media_id`: `database/migrations/2026_07_23_100000_create_frontend_services_table.php:27-60`.
- La colección `public` expone `/storage`, mientras `frontend-private` no se
  sirve directamente: `config/filesystems.php:41-60`; la configuración global de
  Media Library usa `public`: `config/media-library.php:32-36`.
- El job actual sólo conoce `PublishedMediaReference`, devuelve la cadena de
  locks página→sección→media y requiere métodos de estado que la interfaz
  propuesta no declara: `app/Jobs/PromoteFrontendMedia.php:40-101` y
  `app/Services/Frontend/PublishedMediaReference.php:184-217,222-268`.
- El comando de reporte actual sólo recorre `FrontendSection`/`images`, pese a
  que el diseño afirma que incluirá las versiones antiguas de servicios:
  `app/Console/Commands/ReportUnreferencedFrontendMedia.php:27-62`.
- La ruta privada existente de secciones usa controlador, policy, pertenencia del
  UUID y 404 uniforme; no hay equivalente para servicios:
  `routes/web.php:41-48` y `app/Http/Controllers/FrontendSectionMediaController.php:36-55`.
- La policy real de servicios es owner-only y no permite restore/delete:
  `app/Policies/FrontendServicePolicy.php:17-57`.
- El scheduler sólo ejecuta la reconciliación existente cada 15 minutos:
  `routes/console.php:15-19`.

## 3. Hallazgos críticos

### C-1 — El límite de render público no está definido y contradice el código actual

**Estado:** CONFIRMADO.

**Evidencia:** El diseño exige nuevas imágenes en `frontend-private` y dice que el
render debe resolver “por estado de promoción” (`docs/epicas/epica-12-3-media-servicios-diseno.md:145-147,175-183`). Pero la interfaz `PromotableMedia` sólo declara
`modelType`, `collection`, `isReferencedByLiveContent`, `lockChainFor` y
`danglingPending` (`:69-86`). No existe en ese contrato `isPromoted`,
`resolvePublic`, `copyToPublicDisk` ni una regla para medios legacy que ya estén en
`public`.

El código que el diseño pretende conservar actualmente hace
`FrontendMediaReference::resolve(...)->getUrl()` sin revisar estado de promoción
(`app/Services/Frontend/FrontendServicesService.php:180-188`). Si el implementador
aplica únicamente `useDisk('frontend-private')`, el renderer puede devolver una URL
inservible o una URL privada; si decide copiar la lógica de páginas, puede crear
una segunda definición del contrato.

**Impacto:** una imagen nueva puede quedar invisible, una URL privada puede salir
al HTML público o una imagen legacy puede dejar de funcionar. Esto rompe el
requisito central de “sólo promovida es pública”.

**Corrección obligatoria:** definir un método normativo de resolución pública y
su fallback (`null`/imagen de fallback), distinguiendo explícitamente:

1. media legacy `disk=public` que ya se sirve;
2. media nueva `disk=frontend-private` no promovida, que nunca se emite;
3. media promovida, cuya URL sí puede renderizarse.

El contrato debe especificar también dónde se comprueba el estado, qué ocurre si
el archivo no existe y cómo se invalida la caché después del flip. Agregar pruebas
de render público antes, durante y después de la promoción.

### C-2 — No existe una secuencia completa save → pending → job → promoción

**Estado:** CONFIRMADO.

**Evidencia:** La opción C sólo dice que el job y los comandos resolverán una
estrategia por `model_type` (`docs/epicas/epica-12-3-media-servicios-diseno.md:65,89-91`).
El flujo real de edición sólo apunta `image_media_id` al último archivo y hace un
`bump()` (`app/Filament/Resources/FrontendServiceResource/Pages/EditFrontendService.php:24-41`).
No está normado si el guardado debe ejecutarse dentro de una transacción, cuándo
se marca `pending`, cuándo se despacha `afterCommit`, cómo se reencola un fallo ni
qué evento inicia la promoción de servicios.

El job existente recibe sólo un UUID y tipa su dependencia como
`PublishedMediaReference` (`app/Jobs/PromoteFrontendMedia.php:48-59`); la
reconciliación también sólo conoce páginas (`app/Console/Commands/ReconcileFrontendMediaPromotions.php:37-80`).

**Impacto:** el sistema puede aceptar una imagen privada que nunca se promueve,
o promoverla sin que `image_media_id` siga apuntando a ella. La reconciliación
periódica no debe ser el mecanismo primario de publicación.

**Corrección obligatoria:** especificar una única secuencia atómica y observable:

- bloquear el servicio incluyendo `withTrashed()` y volver a leer
  `image_media_id`;
- validar UUID, morph, colección y pertenencia;
- marcar `pending` bajo lock;
- confirmar el guardado y despachar el job sólo `afterCommit`;
- copiar, verificar tamaño/existencia, voltear disco/estado y guardar bajo la
  misma cadena de locks;
- hacer idempotente el retry y dejar la media pendiente ante fallo.

El lote debe incluir una prueba de guardado normal que compruebe que el job se
encola, no sólo pruebas que ejecuten el job manualmente.

### C-3 — “Dos servicios apuntan al mismo UUID: imposible por construcción” es falso

**Estado:** CONFIRMADO.

**Evidencia:** El predicado propuesto consulta sólo
`FrontendService::query()->where('image_media_id', $uuid)->exists()`
(`docs/epicas/epica-12-3-media-servicios-diseno.md:105-128`). La migración actual
permite que dos filas vivas apunten al mismo `media.uuid`: sólo hay FK y no hay
`UNIQUE` parcial en `image_media_id` (`database/migrations/2026_07_23_100000_create_frontend_services_table.php:39,51-60`).
Que `FrontendMediaReference::isEligible()` compruebe `model_id` para una consulta
individual (`app/Services/Frontend/FrontendMediaReference.php:40-51`) no impide
que una asignación directa por SQL o un bug de servicio cree la colisión.

**Impacto:** dos dueños pueden compartir accidentalmente la misma media; una
promoción, una baja o un reporte puede atribuirla al servicio equivocado. La
garantía de propiedad deja de ser física y una carrera de dos ediciones puede
crear un estado ambiguo.

**Corrección obligatoria:** agregar una migración aditiva con índice único parcial
para `image_media_id` no nulo y servicios no borrados, además de validación de
servicio/colección/morph bajo lock. Definir explícitamente si un servicio
soft-deleted libera o conserva la exclusividad. Probar la colisión mediante SQL
directo y dos conexiones PostgreSQL.

### C-4 — `PromotableMedia` no abstrae el pipeline que debe reutilizar

**Estado:** CONFIRMADO.

**Evidencia:** La interfaz propuesta devuelve `?Media` en `lockChainFor()` y sólo
expone cinco métodos (`docs/epicas/epica-12-3-media-servicios-diseno.md:69-86`).
La implementación aprobada de páginas devuelve `[?FrontendPage, ?Media]` y además
usa `isPromoted`, `clearPending`, `markPending`, `markPromoted` y
`isReferencedByPublishedRevision` (`app/Services/Frontend/PublishedMediaReference.php:184-217,222-268`).
El job actual invoca precisamente esos métodos y registra contexto de página
(`app/Jobs/PromoteFrontendMedia.php:59-100`).

**Impacto:** el contrato no permite implementar el job genérico sin agregar
métodos ad hoc, hacer `instanceof`, duplicar la máquina de estados o cambiar
firmas ya aprobadas. La declaración “un solo mecanismo” no es ejecutable tal como
está escrita.

**Corrección obligatoria:** completar la abstracción antes de programar:

- definir un resultado tipado de lock que incluya el dueño y la media;
- declarar las operaciones de estado y promoción necesarias, o definir un
  servicio común explícito para ellas;
- definir la resolución `model_type` → estrategia, incluido el comportamiento
  fail-closed para tipos desconocidos;
- conservar mediante adaptador las firmas y el comportamiento de
  `PublishedMediaReference` ya aprobados;
- agregar un guard estructural que confirme que páginas y servicios pasan por el
  mismo pipeline sin reintroducir `if` por tipo en el job.

## 4. Hallazgos medios

### M-1 — La invalidación de caché posterior a promoción no es un contrato

**Estado:** PROBABLE, requiere cerrar en diseño.

`EditFrontendService::afterSave()` aumenta la generación antes de que exista una
URL pública (`app/Filament/Resources/FrontendServiceResource/Pages/EditFrontendService.php:38-40`).
El observer actual sí invalida después de cambios de `Media` y usa `DB::afterCommit`
(`app/Observers/FrontendMediaObserver.php:12-29,55-62`), pero el diseño no exige
que la estrategia de servicios conserve ese evento ni que el flip de promoción
sea la única segunda invalidación.

**Impacto:** una respuesta cacheada puede conservar `image_url = null`, una URL
privada o una referencia anterior después de promover.

**Corrección:** fijar la invalidación como parte del contrato de promoción,
después del commit exitoso, y probar `generation_before < generation_after` junto
con el cambio de disco y del estado.

### M-2 — El reporte prometido no cubre servicios

**Estado:** CONFIRMADO.

El diseño dice que las versiones antiguas de servicios aparecerán en
`frontend:media:report-unreferenced` (`docs/epicas/epica-12-3-media-servicios-diseno.md:140-143,165-171`), pero el comando real filtra únicamente
`FrontendSection` y la colección `images` (`app/Console/Commands/ReportUnreferencedFrontendMedia.php:27-62`).

**Impacto:** el operador no puede medir la deuda residual que el propio diseño
acepta, y un supuesto control de seguridad queda sin evidencia.

**Corrección:** definir el conjunto de referencias de servicios: servicio vivo,
servicio soft-deleted, UUID actual, colecciones y estado. Definir formato de
salida, conteo de bytes, idempotencia y una prueba que compruebe que una imagen
reemplazada aparece y la actual no.

### M-3 — La migración puede marcar como promovida una fila que no está sirviendo

**Estado:** PROBABLE.

La migración propuesta decide por `disk = public` y por el UUID actual
(`docs/epicas/epica-12-3-media-servicios-diseno.md:136-147`), pero no exige verificar
que el archivo original exista, que su tamaño sea válido ni que la transformación
sea repetible. El job actual sí verifica origen, destino y tamaño antes de cambiar
estado (`app/Jobs/PromoteFrontendMedia.php:113-137`); el diseño de migración no
hereda esa garantía.

**Corrección:** definir migración forward-only, idempotente y transaccional para
los metadatos; verificar existencia de archivos antes de marcar `promoted`,
registrar filas omitidas y no convertir silenciosamente una media huérfana en
estado terminal.

### M-4 — Restore y soft-delete no tienen un disparador operativo definido

**Estado:** CONFIRMADO.

El diseño exige `withTrashed()` para locks y afirma que restaurar debe re-promover
sin intervención manual (`docs/epicas/epica-12-3-media-servicios-diseno.md:93-101,122-128`).
Sin embargo, la policy vigente no permite `restore` y la única reconciliación
automática registrada corre cada 15 minutos (`app/Policies/FrontendServicePolicy.php:39-51`,
`routes/console.php:15-19`).

**Corrección:** aclarar si restore es una operación de dominio, SQL administrativo
o una capacidad futura. Definir el evento que la marca/reencola, su SLA máximo y
la expectativa durante la ventana anterior al siguiente sweep. T3-8 debe probar
el camino real autorizado, no sólo llamar un comando interno.

### M-5 — La ruta de preview de servicios no tiene contrato de controlador suficiente

**Estado:** CONFIRMADO como ausencia de especificación ejecutable.

El diseño indica ruta sin middleware y 404 uniforme (`docs/epicas/epica-12-3-media-servicios-diseno.md:151-161`), pero no fija el método exacto de policy (`Gate::allows('view', $service)`), el tratamiento de modelos soft-deleted en el binding, la colección exacta y la prohibición de devolver la URL pública como sustituto.

**Corrección:** especificar el comportamiento normativo siguiendo
`FrontendSectionMediaController`: autenticación, policy owner-only, UUID
normalizado, pertenencia al servicio/colección y `404` indistinguible para anónimo,
no-owner, servicio inexistente, UUID inválido y UUID ajeno. Agregar pruebas HTTP
reales, incluida respuesta inline y `Content-Type` seguro.

### M-6 — `image_alt` queda deliberadamente indeciso

**Estado:** CONFIRMADO.

El diseño propone hacerlo obligatorio, pero deja abierto “si el gate prefiere
dejarlo fuera” (`docs/epicas/epica-12-3-media-servicios-diseno.md:185-189`). El
modelo y formulario actuales lo mantienen nullable (`app/Models/FrontendService.php:28-41`,
`app/Filament/Resources/FrontendServiceResource.php:55-56`).

**Impacto:** el implementador no sabe si una imagen sin alt es inválida, si es
decorativa o si debe caer al título; accesibilidad y contrato de payload pueden
divergir.

**Corrección:** elegir una regla v1 única, fijar longitud y sanitización, y
definir qué pasa con registros existentes. Si no entra en 12.3, eliminarlo del
alcance y dejar la deuda en una tarea explícita.

## 5. Hallazgos menores

### Mn-1 — No se fija el uso de `getMorphClass()` en la estrategia de servicios

El código vigente centraliza el morph real con `$owner->getMorphClass()`
(`app/Services/Frontend/FrontendMediaReference.php:46-49`), mientras el predicado
del diseño consulta directamente `FrontendService` sin explicar si `model_type`
usa FQCN o morph map (`docs/epicas/epica-12-3-media-servicios-diseno.md:108-116`).
La estrategia debe declarar que usa el morph class configurado, no un string
hardcodeado.

### Mn-2 — No se documenta el contrato de conversiones

El job actual asume que `images` no tiene conversiones y advierte que cualquier
derivado futuro debe promocionarse como familia (`app/Jobs/PromoteFrontendMedia.php:104-111`).
12.3 debe repetir esa invariante para `image` o definir una estrategia de
conversiones; de lo contrario una futura miniatura puede quedar privada mientras
el original ya es público.

### Mn-3 — El fallback visual del servicio no está expresado como contrato

Hoy `FrontendServicesService` emite `image_url = null` y usa el título como
fallback de `image_alt` (`app/Services/Frontend/FrontendServicesService.php:151-152`).
El diseño debe declarar que una media pendiente, inválida, ajena o ausente nunca
rompe el bloque y produce el mismo fallback visual existente.

## 6. Sobreingeniería y decisiones razonables

- La prohibición de `singleFile()`/`onlyKeepLatest()` y de borrado físico es
  correcta: la media reemplazada debe sobrevivir para auditoría y limpieza futura.
- Separar el predicado de “vigente” por dueño puede ser razonable porque páginas y
  servicios tienen fuentes distintas. Pero la interfaz propuesta hoy es más
  abstracta que el pipeline que realmente debe encapsular; no se debe añadir una
  jerarquía de servicios, comandos y jobs paralelos para compensar esa omisión.
- La reconciliación automática cada 15 minutos es útil como red de seguridad, no
  como sustituto del flujo transaccional de guardado.
- `image_alt` obligatorio puede ser una mejora válida, pero dejarlo como opción
  dentro del mismo diseño aumenta la superficie sin cerrar el contrato. Debe
  decidirse o diferirse explícitamente.

## 7. Riesgos de seguridad

1. **Exposición de media privada:** si el render sigue usando `getUrl()` sin estado
   de promoción, puede publicar una referencia privada o fallar de forma no
   controlada.
2. **Acceso directo a histórico público:** el diseño acepta que las versiones
   antiguas sigan en `/storage`. Es una deuda de confidencialidad y debe quedar
   visible en el modelo de amenazas, con reporte y decisión de retención; no debe
   presentarse como aislamiento completo.
3. **Confusión de propietario:** sin unicidad DB, una referencia directa puede
   reasignar el UUID de otra media y crear una colisión entre servicios.
4. **IDOR en preview:** la ruta correcta debe ocultar existencia con 404 uniforme y
   revalidar policy y pertenencia en servidor; ocultarla del menú no alcanza.
5. **Carrera de promoción:** dos guardados o un reemplazo durante la copia pueden
   promover una imagen que ya no es la actual si no se relee `image_media_id` bajo
   el lock del servicio.

## 8. Regresiones y compatibilidad

- `PublishedMediaReference` y el pipeline de `FrontendSection` ya tienen pruebas
  de estado, lock y no borrado. 12.3 debe usar un adaptador compatible y no meter
  ramas de servicios que cambien su semántica.
- Debe actualizarse intencionalmente el test existente que hoy exige que la
  reconciliación no toque `FrontendService.image`:
  `tests/Feature/Frontend/FrontendMediaPromotionTest.php:252-268`. Mantenerlo
  sin cambiar produciría una contradicción falsa; eliminarlo sin reemplazo
  perdería la regresión de `FrontendSetting`.
- `ServiceType` debe seguir siendo la fuente operativa de elegibilidad. Este lote
  no debe alterar permisos, lead form, `Property`, `Project` ni la media de
  contratos.
- La invalidación debe conservar el mecanismo durable existente de generación y
  `DB::afterCommit`; no introducir `Cache::forget` puntual ni otro contador.

## 9. Tests faltantes antes de habilitar implementación

Como mínimo, el diseño corregido debe exigir:

1. Upload de servicio en `frontend-private`; ningún `/storage` ni `image_url`
   público antes de promoción.
2. Guardado normal del Owner que marca pending y despacha el job sólo después del
   commit.
3. Promoción idempotente de servicio, con copia verificada, cambio de disco,
   estado y caché en la misma evidencia.
4. Fallo de copia que deja pending y permite retry, sin marcar promovida.
5. Dos servicios con el mismo `image_media_id` rechazados por SQL real; dos
   conexiones que actualizan simultáneamente no producen duplicidad.
6. UUID mal formado, UUID ajeno, servicio inexistente, usuario anónimo y usuario
   no-owner devuelven el mismo 404 en la ruta de preview.
7. Servicio soft-deleted/restaurado según el camino operativo definido, incluyendo
   el comportamiento de su media y de la reconciliación.
8. Migración legacy idempotente: imagen actual pública marcada; imagen antigua
   no marcada; archivo ausente reportado; segunda ejecución sin cambios indebidos.
9. `frontend:media:report-unreferenced` incluye media de servicios y sigue
   excluyendo `FrontendSetting` sólo si esa exclusión continúa siendo decisión.
10. Fallback de render estable cuando la media está pendiente, es inválida, fue
    reemplazada o no existe.
11. Concurrencia real en PostgreSQL con orden `service → media` y relectura del
    UUID bajo lock.
12. Regresión completa de páginas, media de contratos, roles y ServiceType.

## 10. Correcciones obligatorias

1. Cerrar C-1: contrato de resolución pública, estados legacy/nuevo/promovido y
   fallback.
2. Cerrar C-2: flujo transaccional de guardado, pending, dispatch `afterCommit`,
   promoción, retry y observabilidad.
3. Cerrar C-3: índice único parcial y definición de ownership/soft-delete.
4. Cerrar C-4: completar el contrato de estrategia o reducirlo a un adaptador
   explícito que el job pueda ejecutar sin duplicar la máquina de estados.
5. Corregir M-2: actualizar el diseño del reporte y sus casos de prueba.
6. Resolver M-4 y M-5: restore/reconciliación y ruta/controller owner-only con
   404 uniforme.
7. Resolver M-6: decisión normativa para `image_alt`.
8. Actualizar la matriz T3 para que incluya pruebas de PostgreSQL, HTTP y caché
   de los caminos anteriores.

## 11. Recomendaciones opcionales

- Incluir un resultado tipado de promoción con `uuid`, dueño, estado anterior,
  estado final y generación de caché para mejorar observabilidad sin exponer datos
  de la inmobiliaria.
- Emitir métricas separadas para `pending`, `promoted`, archivo faltante y legacy
  público; ayudan a decidir la futura limpieza física.
- Documentar explícitamente que no se deben agregar conversiones a `image` sin
  modificar el contrato de promoción de familia.

## 12. Gate

**GATE DE DISEÑO 12.3: RECHAZADO**

Los hallazgos C-1, C-2, C-3 y C-4 bloquean la implementación. Claude debe corregir
el diseño y actualizar la matriz T3; después Codex debe realizar una reauditoría
independiente contra el código real. Hasta entonces no se habilita ningún lote de
implementación de 12.3.

