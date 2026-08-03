# Reauditoría de diseño — Épica 12.3 Media de servicios

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Fecha:** 2026-07-27  
**Auditor:** Codex  
**Rama auditada:** `feature/epica-12-content-manager`  
**Commit auditado:** `c662ebb` — `docs(prompts): nombre neutro para el prompt de auditoría de 12.3`  
**Documento auditado:** `docs/epicas/epica-12-3-media-servicios-diseno.md` v2  
**Auditoría base:** `docs/audits/epica-12-3-media-servicios-auditoria-diseno.md`

## 1. Identidad y veredicto

**Veredicto:** APROBADO.

La v2 del diseño cierra los cuatro críticos de la auditoría anterior: define el límite de render público, completa la secuencia guardar → pending → job → promoción, respalda la unicidad con índice parcial SQL y reemplaza la abstracción incompleta por un contrato implementable con lock tipado, estado común y registry fail-closed.

No encontré bloqueantes de diseño. La suite completa no cerró en tiempo razonable sin emitir salida, por lo que queda registrada como observación operativa; no la cuento como verde. Las verificaciones focales de frontend/media sí pasaron contra PostgreSQL real.

## 2. Prueba de ejecución

### Identidad del commit y tamaño del documento

```text
HEAD=c662ebb
SUBJECT=docs(prompts): nombre neutro para el prompt de auditoría de 12.3
TEST_COUNT=1003
DESIGN_LINES=     398 docs/epicas/epica-12-3-media-servicios-diseno.md
./composer.json is valid
{"tool":"pint","result":"passed"}
```

### Migración y seed sobre PostgreSQL real

Comando ejecutado con `DB_DATABASE=inmo_test`:

```text
Dropping all tables ........................................... 68.55ms DONE
...
2026_07_24_100200_seed_frontend_canonical_pages ............... 24.14ms DONE

INFO  Seeding database.
...
Database\Seeders\ZoneSeeder ..................................... 12 ms DONE
Database\Seeders\OwnerSeeder ................................... 252 ms DONE
Database\Seeders\AgentSeeder ................................... 470 ms DONE
```

Resultado: **exit 0**.

### Suite completa

Primera corrida en sandbox: falló por restricción de conexión local a PostgreSQL, no por aserciones del proyecto.

```text
{"tool":"phpunit","result":"failed","tests":1003,"passed":28,"assertions":92,"duration_ms":17074,"errors":975,
"message":"SQLSTATE[08006] [7] connection to server at \"127.0.0.1\", port 5432 failed: Operation not permitted"}
EXIT: 2
```

Repetida fuera del sandbox: quedó más de 5 minutos sin salida ni cierre y fue interrumpida para no dejar proceso colgado.

```text
<sin salida durante >5 minutos>
^C
EXIT: 130
```

**Conclusión:** suite completa **no concluyente** en esta reauditoría. No se reporta como verde.

### Pruebas focales de frontend/media

```text
{"tool":"phpunit","result":"passed","tests":34,"passed":34,"assertions":116,"duration_ms":6091}
EXIT: 0
```

Incluyó:

- `FrontendMediaPromotionTest.php`
- `FrontendMediaPromotionConcurrencyTest.php`
- `FrontendSectionMediaPrivacyTest.php`
- `FrontendServiceMediaTest.php`
- `FrontendServicePartialIndexTest.php`
- `FrontendServicesRenderTest.php`

### Build frontend

```text
> build
> vite build && npm run build:filament
...
✓ built in 539ms
...
Done in 728ms.
```

Resultado: **exit 0**.

## 3. Verificación de §1 del diseño contra código real

| # | Afirmación del diseño | Evidencia real | Estado |
| --- | --- | --- | --- |
| 1 | `FrontendService.image` hereda disco público actual | `app/Models/FrontendService.php:66` → `$this->addMediaCollection('image');` | Confirmado |
| 2 | La fuente editorial es `image_media_id` | `app/Models/FrontendService.php:36` → `'image_media_id',` | Confirmado |
| 3 | Servicios usan estrategia “guardar = publicar” | `app/Models/FrontendService.php:20-21` → `"Save is publishing"... no draft/published split` | Confirmado |
| 4 | No hay índice único en `image_media_id` actual | `database/migrations/2026_07_23_100000_create_frontend_services_table.php:39,52` → columna UUID + FK, sin unique | Confirmado |
| 5 | `afterSave()` actual no marca pending ni despacha job | `app/Filament/Resources/FrontendServiceResource/Pages/EditFrontendService.php:24-40` → asigna `image_media_id` y hace `bump()` | Confirmado |
| 6 | Render actual emite URL sin mirar promoción | `app/Services/Frontend/FrontendServicesService.php:186-187` → `resolve(...)?->getUrl()` | Confirmado |
| 7 | Uploader no destructivo ya existe | `app/Filament/Resources/FrontendServiceResource.php:69-72` → `NonDestructiveMediaUpload::make('image')...uuidColumn('image_media_id')` | Confirmado |
| 8 | Colección no usa `singleFile()` | `app/Models/FrontendService.php:61-66` → comentario prohíbe `singleFile()/onlyKeepLatest()` y agrega colección normal | Confirmado |
| 9 | `PublishedMediaReference` hoy excluye servicios | `app/Services/Frontend/PublishedMediaReference.php:31-32` → scope `FrontendSection/images only`, `FrontendService.image` deliberadamente fuera | Confirmado |
| 10 | `danglingPending()` acotado a secciones | `app/Services/Frontend/PublishedMediaReference.php:151-157` → filtra `FrontendSection` + `COLLECTION` | Confirmado |
| 11 | Test TA-12 exige no tocar servicios | `tests/Feature/Frontend/FrontendMediaPromotionTest.php:252-268` → media de servicio conserva `PENDING` | Confirmado |
| 12 | Policy no permite restore | `app/Policies/FrontendServicePolicy.php:44-46` → `restore()` devuelve `false` | Confirmado |
| 13 | Invalidación vigente por observer + publisher | `app/Observers/FrontendMediaObserver.php:61` → `DB::afterCommit(fn () => app(FrontendPublisher::class)->invalidate())` | Confirmado |
| 14 | Ruta owner-only existe sólo para secciones | `routes/web.php:47-48` → `/admin/frontend/secciones/{section}/media/{uuid}` | Confirmado |
| 15 | Reconciliación programada cada 15 min | `routes/console.php:15-19` verificado en lectura local | Confirmado |

## 4. Estado de C-1, C-2, C-3, C-4

| Hallazgo | Estado | Evidencia y decisión |
| --- | --- | --- |
| C-1 — límite de render público indefinido | CERRADO | `docs/epicas/epica-12-3-media-servicios-diseno.md:222-240` fija una regla única: sólo `promoted` llega al HTML público; pendiente, inválida, ajena, faltante o ausente caen al fallback vigente. Esto corrige el render actual de `FrontendServicesService.php:186-187`. |
| C-2 — secuencia save → pending → job incompleta | CERRADO | `docs/epicas/epica-12-3-media-servicios-diseno.md:163-189` define transacción, lock del servicio, validación, `markPending`, commit, dispatch `afterCommit`, relectura bajo lock e idempotencia. La reconciliación queda explícitamente como red de seguridad, no mecanismo primario. |
| C-3 — unicidad falsa de `image_media_id` | CERRADO | `docs/epicas/epica-12-3-media-servicios-diseno.md:196-215` exige índice único parcial en SQL crudo: `WHERE deleted_at IS NULL AND image_media_id IS NOT NULL`, más pruebas por SQL directo y dos conexiones PostgreSQL reales. |
| C-4 — abstracción no implementable | CERRADO | `docs/epicas/epica-12-3-media-servicios-diseno.md:72-159` introduce `MediaLockChain`, `MediaPromotionState`, `PromotableMediaOwner`, registry `model_type → estrategia` fail-closed y preservación precisa de firmas/cuerpos de `PublishedMediaReference`. El job actual usa exactamente lock, estado y predicado de referencia (`PromoteFrontendMedia.php:59-93`), cubiertos por la nueva abstracción. |

## 5. Hallazgos críticos

Ninguno.

## 6. Hallazgos medios

Ninguno bloqueante.

**M-OBS-1 — Suite completa no concluyente en esta sesión.** → **CERRADA fuera del informe.**

Evidencia original: corrida fuera del sandbox quedó sin salida durante más de 5 minutos y fue interrumpida con exit 130. Impacto: no permitía afirmar salud global en este informe.

**Cierre (2026-07-27, sobre el mismo commit `c662ebb`):** la suite completa corrió hasta el final y terminó en verde.

```text
EXIT CODE: 0
{"tool":"phpunit","result":"passed","tests":1003,"passed":1003,"assertions":4380,"duration_ms":404491}
```

**Causa de la observación:** la suite tarda **~6 min 45 s** contra PostgreSQL real y el runner de este proyecto **no emite salida incremental** —imprime un único JSON al terminar—, así que una espera de cinco minutos es indistinguible de un proceso colgado. No hubo tal cuelgue.

**Consecuencia operativa, que sí se conserva:** todo auditor futuro debe correr la suite con un timeout de al menos **10 minutos** y saber que el silencio es normal. Recogido en `M-P3` de `epica-12-3-reauditoria-diseno-prompt-auditoria.md`.

## 7. Hallazgos menores

Ninguno que requiera corrección de diseño antes de implementar.

## 8. Riesgos de seguridad

- **Media privada en HTML público:** mitigado por §6; sólo `promoted` se emite.
- **IDOR en preview:** mitigado por §8.1 con 404 uniforme para anónimo, no-owner, servicio inexistente, UUID mal formado y UUID ajeno.
- **Carrera de promoción:** mitigada por §4.3 con relectura del predicado bajo lock antes de copiar/marcar.
- **Histórico público existente:** declarado como deuda residual en §2.1 y medido por §9.1; correcto que no se prometa aislamiento retroactivo porque v1 prohíbe borrado físico.

## 9. Regresiones y compatibilidad con 12.1

- El diseño conserva `PublishedMediaReference` como frontera aprobada y exige que los tests de 12.1-A/B pasen sin modificarse si sólo se extrae estado.
- El cambio intencional de TA-12 está documentado: `FrontendService` entra al alcance, `FrontendSetting` sigue fuera.
- No introduce borrado físico, `singleFile()`, `onlyKeepLatest()` ni un segundo protocolo de caché.
- No toca `Property`, `Project`, contratos, lead form ni media de marca.

## 10. Tests faltantes antes de habilitar implementación

La matriz T3-1…T3-19 del diseño es suficiente y debe implementarse completa. Especial atención a:

1. T3-6/T3-7 — colisión por SQL directo y dos conexiones PostgreSQL reales.
2. T3-8/T3-13 — `afterCommit`, retry y fallo de copia sin falso `promoted`.
3. T3-14 — fallback público ante media pendiente/inválida/ajena/reemplazada/ausente.
4. T3-2 — preview con 404 uniforme real.
5. T3-19 — regresión explícita del pipeline 12.1 sin modificar sus tests base.

## 11. Correcciones obligatorias

Ninguna. El diseño queda habilitado para implementación.

## 12. Qué se buscó y no se encontró

- **Carreras de concurrencia:** el diseño exige lock del dueño + media y prueba con dos conexiones reales.
- **Fugas de media privada al render público:** §6 evita emitir cualquier media no `promoted`.
- **Rutas de borrado físico:** §10.1 prohíbe borrar y conserva la política de §18.13.
- **Huecos de autorización en preview:** §8.1 replica el patrón de secciones con 404 uniforme.
- **Contradicciones con §16.4:** no encontré contradicción vigente; 12.3 se declara como cierre de la deuda abierta para `FrontendService.image`.
- **Afirmaciones sin respaldo en código:** las afirmaciones de §1 fueron verificadas contra archivos reales.

## 13. Gate explícito

**GATE DE DISEÑO 12.3: APROBADO**
