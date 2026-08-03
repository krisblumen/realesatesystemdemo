# Auditoría de diseño — Épica 4 — Inmuebles

**Proyecto:** New Hauz\
**Fecha:** 19 de Junio, 2026\
**Auditor:** Gemini CLI\
**Documento auditado:** `docs/epicas/epica-4-inmuebles.md`

---

## 1. Veredicto

**Rechazado**

El diseño tiene una base correcta: es mayormente aditivo, usa `nullOnDelete`, respeta `zones.polygon NOT NULL`, modela características con catálogo+pivote y centraliza la intención de autorización y estados. Sin embargo, todavía permite romper invariantes comerciales y de seguridad por rutas ordinarias: un inmueble publicado puede quedar sin zona o portada, `status` puede modificarse sin el servicio y un agente puede crear o mover inmuebles fuera de sus zonas. Estos puntos deben corregirse en el diseño antes de implementar.

---

## 2. Hallazgos críticos

### 2.1 La condición de publicación no es un invariante durable

`guardPublish()` valida portada y `zone_id` sólo al entrar en `publicado` (§8.2, líneas 767–780), mientras `scopePublished()` filtra únicamente por estado (§5.6, líneas 447–450). Después de publicar se puede eliminar la portada, asignar `zone_id = null`, cambiar la operación o eliminar lógicamente la zona. El inmueble seguirá en `publicado` y será devuelto por el scope público.

Además, `nullOnDelete` sólo actúa ante borrado físico. `Zone` usa `SoftDeletes`, por lo que una baja normal no pone `zone_id` en `NULL`; la relación queda apuntando a una zona excluida por su global scope. La mitigación R-2 (línea 1339) es, por tanto, incompleta.

**Corrección requerida:** definir y aplicar una política transaccional para mantener el invariante: impedir quitar portada/zona o inactivar/eliminar la zona mientras haya publicaciones, o pausar automáticamente los inmuebles afectados. `guardPublish()` también debe exigir una zona existente, activa y con polígono válido. Añadir tests para cambios posteriores a la publicación y soft delete de zona.

### 2.2 La autorización del agente tiene huecos en creación y edición

`PropertyPolicy::create()` sólo revisa `properties.manage` (líneas 983–986). El formulario oculta `agent_id` al agente, pero no existe una mutación backend que fuerce `agent_id = auth()->id()`; tampoco se restringe `zone_id` a sus zonas (líneas 1103–1108). Un agente puede crear un inmueble sin responsable o asignarlo/moverlo a una zona ajena. Esto contradice QA-027 y la regla agente↔zonas.

**Corrección requerida:** documentar `mutateFormDataBeforeCreate()`/`mutateFormDataBeforeSave()` o una Action de dominio que fuerce el agente autenticado y valide en backend que la zona pertenece al agente. El selector debe filtrar opciones, pero esa restricción de UI no reemplaza la validación de servidor.

### 2.3 El alcance “asignado O por zona” permite afectar inmuebles de otro agente

La Policy y `getEloquentQuery()` autorizan por `agent_id = usuario` **o** por zona asignada (líneas 1016–1021 y 1085–1089). Así, dos agentes de la misma zona pueden editar mutuamente sus inmuebles, aunque QA-039 declara que un agente no edita inmuebles de otro agente/zona.

**Corrección requerida:** cerrar la precedencia. Recomendación segura: si `agent_id` existe, sólo ese agente puede gestionar; la zona concede acceso únicamente a inmuebles sin responsable. Si el negocio desea colaboración por zona, QA-039 y el texto “solo los suyos” deben cambiar explícitamente y agregarse pruebas de ese escenario.

### 2.4 `PropertyStatusService` no es realmente la única puerta

El documento afirma que el servicio es la única vía (§8.2, línea 793), pero `status` está en `$fillable` (líneas 401–408). Cualquier servicio, seeder, comando o endpoint futuro puede ejecutar `update(['status' => 'publicado'])` y omitir portada, zona y transiciones. `slug` también queda expuesto pese a declararse autogenerado.

**Corrección requerida:** excluir `status` y `slug` de asignación masiva y definir un mecanismo de dominio verificable para transiciones. Añadir una prueba que demuestre que una actualización ordinaria no puede publicar ni cambiar el slug. El servicio debe usar transacción y bloqueo de fila para evitar carreras entre transiciones concurrentes.

---

## 3. Hallazgos medios

### 3.1 Activar sólo el cuerpo deja PHPDoc incorrecto

Los contratos actuales declaran `HasMany<User, $this>` en `User::properties()` y `HasMany<Zone, $this>` en `Zone::properties()`. Al devolver `Property`, esos genéricos quedan falsos y pueden fallar en Larastan. La excepción aditiva debe permitir actualizar comentario y PHPDoc a `HasMany<Property, $this>`, además del cuerpo.

### 3.2 Falta índice inverso para filtrar características

El pivote tiene PK `(property_id, feature_id)` (líneas 638–645). Ese índice no optimiza búsquedas cuyo primer criterio es `feature_id`, precisamente el patrón prometido para Épicas 6/7. PostgreSQL no crea automáticamente un índice útil sobre la columna referenciante.

**Corrección requerida:** agregar índice `feature_id` o compuesto `(feature_id, property_id)` y cubrir el plan de consulta esperado.

### 3.3 La generación de slug no cubre concurrencia

El ciclo `exists()` + `insert` (líneas 825–843) tiene condición de carrera: dos altas simultáneas pueden elegir el mismo slug y una fallará por el índice único. La regeneración tampoco excluye el registro actual.

**Corrección requerida:** excluir el `id` actual al regenerar y definir reintento acotado ante violación única. Mantener el índice único como garantía final.

### 3.4 Integridad numérica confiada a Filament

La migración no impide `price <= 0`, baños/áreas negativos ni áreas cero incoherentes. `minValue()` sólo protege la UI y no `price`, `land_area` ni `construction_area` (líneas 1111–1118).

**Corrección requerida:** reglas backend y CHECK constraints para valores no negativos/positivos según negocio.

### 3.5 Flujos declarados sin acción completa

Se declara restauración y reapertura `vendido|rentado → borrador`, pero la tabla sólo define publicar, pausar, vender, rentar, regenerar slug, editar y eliminar (líneas 1168–1212). No aparecen `RestoreAction` ni una acción de reapertura.

**Corrección requerida:** especificar ambas acciones, sus Policies y pruebas; al restaurar una propiedad publicada se deben revalidar los invariantes o volverla a borrador.

### 3.6 La matriz QA no cubre los riesgos anteriores

QA-026→QA-040 no contempla: agente creando/moviendo a zona ajena, dos agentes en la misma zona, bypass directo de `status`, eliminación de portada/zona tras publicar, soft delete de zona, concurrencia de slug, restore de publicado ni reapertura. Sin esos casos, la cobertura puede quedar verde con fallas de seguridad reales.

---

## 4. Hallazgos menores

### 4.1 Conversión `web` depende del procesamiento de cola

`thumb` es `nonQueued()`, pero `web` no. El diseño debe declarar si la cola está garantizada en producción o hacer ambas conversiones síncronas para evitar un `og:image` inexistente inmediatamente después de subir.

### 4.2 El seeder es idempotente pero no convergente

`firstOrCreate()` evita duplicados, pero no actualiza `name` o `icon` si cambia el catálogo. `updateOrCreate()` por slug expresa mejor el estado deseado.

### 4.3 Índices parcialmente redundantes

Los índices simples de `status` y `operation_type` junto con `(status, operation_type)` pueden ser válidos según consultas reales, pero deben justificarse con `EXPLAIN` antes de conservar los tres.

---

## 5. Sobreingeniería detectada

- El `PropertyObserver` expuesto como servicio invocable desde Filament mezcla ciclo de vida con una operación de aplicación. Una clase `PropertySlugGenerator` reutilizable reduciría acoplamiento sin duplicar lógica.
- Registrar Policies manualmente con `Gate::policy()` es innecesario si el proyecto ya usa auto-discovery; no es un bloqueo, pero debe elegirse un único patrón.
- El comando futuro de limpieza de media no debe presentarse como solución a media “huérfana”: mientras el inmueble esté soft-deleted, esos archivos son recuperables y no están huérfanos.

---

## 6. Riesgos de implementación

- Las reglas que atraviesan `Property`, `Zone` y Media Library requieren transacciones y una estrategia explícita de eventos; implementarlas sólo en callbacks del Resource las volvería frágiles.
- Los factories `published()` pueden fabricar estados imposibles sin zona/portada y ocultar errores. Deben construir fixtures válidos o nombrarse como estado deliberadamente inválido.
- La actualización de PHPDoc en los contratos diferidos es necesaria para que PHPStan/Larastan conserve tipos correctos.
- La creación del slug basada en una relación `zone` dispara consulta durante `creating`; debe probarse con `zone_id`, con zona ausente y con soft-deleted.
- Cambiar `operation_type` después de `vendido`/`rentado` puede crear combinaciones inválidas si no se bloquea o reabre primero.

---

## 7. Riesgos de seguridad

- Un agente puede intentar manipular `zone_id`, `agent_id` y `status` mediante payload directo; ocultar campos no es autorización.
- El criterio por zona puede ampliar acceso horizontal a registros asignados a terceros.
- `getEloquentQuery()` protege Filament, pero no otras consultas backend futuras. Conviene encapsular el alcance en un scope reutilizable `visibleTo(User)` y probar Policy + query conjuntamente.
- `delete()`/`restore()` se basan sólo en nombres de rol y no verifican `properties.manage`; documentar esta excepción o exigir permiso más rol para mantener coherencia con el contrato de Épica 2.

---

## 8. Recomendaciones obligatorias

1. Convertir portada, zona activa y coherencia de operación en invariantes durables, no sólo precondiciones de publicación.
2. Definir el efecto de soft-delete/inactivación de `Zone` sobre inmuebles publicados y cubrirlo transaccionalmente.
3. Forzar `agent_id` y validar zonas del agente en backend durante create/update.
4. Resolver la precedencia entre propiedad por agente y acceso por zona; alinear Policy, query y QA-039.
5. Impedir cambios ordinarios de `status`/`slug`; asegurar transiciones concurrentes con transacción y lock.
6. Corregir PHPDoc de `User::properties()` y `Zone::properties()` al activar los contratos.
7. Agregar índice inverso del pivote, constraints numéricos y validaciones backend.
8. Completar restore/reapertura y ampliar QA con todos los escenarios críticos y medios.

---

## 9. Recomendaciones opcionales

1. Extraer `PropertySlugGenerator` del Observer.
2. Usar `updateOrCreate()` en `FeatureSeeder`.
3. Confirmar estrategia de cola para conversiones `web`.
4. Medir índices con `EXPLAIN (ANALYZE, BUFFERS)` cuando exista volumen representativo.
5. Considerar historial de slugs/redirects en la épica de frontend si se habilita regeneración manual.

---

## 10. Preguntas abiertas

- ¿Un agente debe colaborar sobre todos los inmuebles de sus zonas o sólo sobre los asignados a él?
- ¿Qué debe ocurrir con un inmueble publicado al eliminar/inactivar su zona o retirar su portada: bloquear, pausar o pasar a borrador?
- ¿Puede owner/admin publicar en una zona inactiva? La recomendación es no.
- ¿Restaurar un inmueble conserva el estado previo o siempre vuelve a `borrador`?
- ¿La reapertura desde `vendido`/`rentado` requiere motivo o auditoría?

---

## 11. Checklist de corrección para Claude (agente de implementación)

- [ ] Reescribir el invariante de publicación para cubrir modificaciones posteriores y soft delete de zona.
- [ ] Definir precedencia agente↔zona y actualizar Policy, scope y QA-039.
- [ ] Añadir contrato backend para forzar `agent_id` y validar `zone_id` en create/update.
- [ ] Retirar `status` y `slug` de asignación masiva y definir transición transaccional.
- [ ] Permitir actualizar PHPDoc de los dos contratos diferidos.
- [ ] Añadir índice `(feature_id, property_id)` y CHECK constraints numéricos.
- [ ] Especificar acciones Restore y Reopen.
- [ ] Ampliar QA y riesgos con los escenarios señalados.
- [ ] Mantener `zones.polygon NOT NULL`; no crear ni alterar esa columna.

---

## 12. Checklist de implementación para Codex (agente de programación)

- [ ] No iniciar el Lote A hasta que los hallazgos críticos estén resueltos en el diseño.
- [ ] Implementar por lotes con tests de seguridad antes del Resource.
- [ ] Verificar migraciones sobre PostgreSQL/PostGIS limpio con `migrate:fresh --seed`.
- [ ] Probar `nullOnDelete` físico y el comportamiento separado de SoftDeletes.
- [ ] Ejecutar escenarios de agente propio, zona propia, zona ajena y mismo equipo de zona.
- [ ] Probar que un publicado no puede perder portada/zona ni quedar ligado a zona inactiva.
- [ ] Probar concurrencia/reintento de slug y unicidad contra soft-deleted.
- [ ] Ejecutar `php artisan test`, Pint, PHPStan/Larastan y build antes del cierre.
- [ ] Verificar regresión completa de Épicas 1/2/3 y contratos Eloquent reales.

---

*Fin del reporte de auditoría.*
