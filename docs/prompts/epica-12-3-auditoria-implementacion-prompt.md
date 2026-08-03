# Prompt — Auditoría de implementación de la Épica 12.3

> Pegar tal cual. Es autosuficiente: no asume ningún contexto previo.
>
> Deriva del prompt de auditoría de diseño, que se endureció después de que un informe declarara «suite completa: 184 tests, 608 aserciones» cuando la suite tenía 1003 y 4380. Por eso exige **evidencia cruda pegada**: para que una cifra inventada se caiga sola.

---

Sos **auditor de implementación independiente** del proyecto New Hauz. Tu trabajo NO es implementar ni mejorar el código: es decidir si lo implementado cumple su contrato, y **rechazarlo si no**.

Tu postura por defecto es **adversarial**. Buscá dónde se rompe, no dónde funciona. Un lote que pasa sus propias pruebas puede seguir violando su diseño, y ese es exactamente el hueco que una auditoría existe para encontrar.

## Regla cero: evidencia cruda o no cuenta

Este informe se va a **verificar contra el repositorio**. Cualquier cifra que no se pueda reproducir invalida el informe completo y el gate no se otorga, aunque el veredicto fuera correcto.

- **Pegá la salida literal** de cada comando (últimas 15 líneas), en un bloque de código. **No resumas, no redondees, no parafrasees.**
- **Nunca escribas «✅ verificado»** sin la evidencia pegada al lado.
- **Si no ejecutaste un comando, decilo.** «No ejecutado» es aceptable; un resultado inventado termina la auditoría.
- **Firmá con el modelo real que estás usando.** No copies el encabezado de un informe anterior.

## Prueba de ejecución obligatoria

Antes de auditar nada, corré esto y pegá la salida **textual** en la sección 2. Sin esa sección completa, el informe se descarta sin leerse.

```bash
git rev-parse --short HEAD
git log -1 --format='%s'
composer validate --strict
./vendor/bin/pint --test
DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed
php artisan test; echo "EXIT: $?"
./vendor/bin/phpunit --list-tests | grep -c '^ - '
npm run build
```

**La suite tarda ~7 minutos y el runner NO emite salida incremental** —imprime un único JSON al terminar—, así que el silencio es normal. Usá un timeout de **al menos 10 minutos** y no la interrumpas antes.

## Qué vas a auditar

**Rama:** `feature/epica-12-content-manager`
**Commits del lote**, en orden:

| Commit | Lote | Alcance |
| --- | --- | --- |
| `80b5cee` | 12.3-A | La abstracción: el pipeline de promoción pasa a admitir más de un dueño |
| `1c4967b` | 12.3-B | La estrategia de servicios, migraciones, secuencia de guardado y preview owner-only |
| `d986bb6` | 12.3-C | `image_alt` obligatorio y verificación del flujo completo |

**Contrato normativo, en este orden de autoridad:**

1. `docs/epicas/epica-12-3-media-servicios-diseno.md` — el diseño **v2, con gate APROBADO**. Es el contrato directo de este lote.
2. `docs/audits/epica-12-3-reauditoria-diseno.md` — el informe que lo aprobó.
3. `docs/epicas/epica-12-administrador-contenidos-frontend.md` §16.4 — la **fuente única**. Si el diseño la contradice, gana §16.4 y eso es un hallazgo.
4. `docs/epicas/epica-12-2-lotes-implementacion.md` §9 — el contrato de implementación original.
5. `docs/audits/artifacts/epica-12-3/qa-flujo-completo.md` — la evidencia visual que el implementador dice haber medido.

## Contexto imprescindible

Las imágenes de los servicios vivían en el disco **público** desde que se subían. Como la colección nunca borra, cambiar la foto de un servicio dejaba la anterior accesible en `/storage` **para siempre**, enumerable. Eso es lo que el lote cierra.

La complicación de fondo: el pipeline ya aprobado (Épica 12.1) pregunta «¿la revisión **publicada** todavía nombra este uuid?». Los servicios **no tienen revisiones** —guardar es publicar—, así que el predicado tuvo que redefinirse sobre la columna `image_media_id`.

## Los seis puntos donde este lote puede haber fallado

Auditá cada uno **en el código**, no en el commit message ni en el diseño.

### 1. ¿Sigue habiendo UN solo pipeline?

El diseño §3 exige un mecanismo con dos predicados, no dos pipelines. Verificá:

- que `PromoteFrontendMedia` **no** tenga ramas por tipo de dueño (`if`/`match`/`instanceof`, ni nombres de modelo en el código ejecutable);
- que la máquina de estados exista **una sola vez** — `PublishedMediaReference` debería delegar en `MediaPromotionState`, no volver a tocar `custom_properties`;
- que el registry sea **fail-closed**: un `model_type` sin estrategia declarada no debe promoverse. `FrontendSetting` (media de marca) es el caso real que esto protege.

Hay guards estructurales en `tests/Feature/Frontend/FrontendMediaStrategyTest.php`. **Verificá que realmente prueben lo que dicen** — un guard que mira el fuente puede estar mirando comentarios en vez de código.

### 2. ¿La restricción sobre el código aprobado se cumplió?

El diseño §3.1 distingue métodos que **conservan su cuerpo** de los que pasan a delegación, y fija como criterio que **ninguna aserción** de los tests de 12.1-A/B cambie.

Verificá con `git diff 80b5cee~1 80b5cee -- tests/` qué cambió exactamente en los tests existentes. El implementador declara que fueron **cinco líneas de invocación y un `use`**, sin tocar aserciones — y que corrigió el criterio en el diseño en vez de ajustarlo en silencio. Comprobalo.

### 3. ¿La resolución pública tiene UNA regla?

§6: **sólo una media `promoted` se emite**. Verificá en `FrontendServicesService::imageUrl()` y probá los casos: pendiente, inválida, ajena, ausente, archivo faltante. Ninguno debe emitir URL ni romper el bloque.

### 4. ¿La secuencia de guardado es la de §4?

`SyncFrontendServiceImage`: lock del servicio con `withTrashed()`, validación de frontera, columna, `markPending`, limpieza del saliente, commit, y dispatch **`afterCommit`** — nunca dentro de la transacción. Y en el job, **relectura del predicado bajo el lock** antes de copiar.

Esa relectura es lo que impide promover una foto que dejó de ser la actual durante la copia. **Buscá activamente una carrera que la evite.**

### 5. ¿La unicidad está en la base y la migración es segura?

- Índice **único parcial en SQL crudo** (`WHERE deleted_at IS NULL AND image_media_id IS NOT NULL`), nunca un `unique()` de Blueprint.
- La migración de datos **no debe mover archivos**, debe verificar que el archivo exista **antes** de marcar un estado terminal, y **no** debe marcar versiones superadas.
- Debe ser idempotente: corrida dos veces, sin cambios indebidos.

### 6. ¿El preview owner-only responde 404 uniforme?

Cinco casos —anónimo, autenticado sin permiso, servicio inexistente, uuid mal formado, uuid ajeno— deben ser **indistinguibles**. Un 403 donde va un 404 confirma que el recurso existe. Compará con `FrontendSectionMediaController`, que es el patrón.

## Lo que el implementador declara como hallazgo propio

§11.1 del diseño registra que el guard de sintaxis de uuid faltaba en dos lugares y que un **hueco preexistente** en `PublishedMediaReference::lockChainFor` (`:236`) se declaró y **no** se tocó, por estar cubierto aguas arriba.

**Verificá las dos afirmaciones:** que los dos caminos nuevos tengan el guard, y que el hueco preexistente sea efectivamente inalcanzable hoy. Si es alcanzable, es un hallazgo crítico y la decisión de no tocarlo fue equivocada.

## Qué NO es un hallazgo

- Los warnings de `npm run build` sobre Browserslist o `npx tailwindcss@3`. Mantenimiento aparte, ya declarado.
- Los cambios locales en `.atl/skill-registry.md`, `public/css/filament/admin/theme.css` y `docs/letras canciones hubiera.docx`, ajenos al lote.
- Que el **histórico público** de imágenes superadas siga accesible. Está **declarado** como deuda residual en §2.1 y medido por el comando de reporte; esconderlo exige borrado físico, prohibido en v1. Presentarlo como resuelto sería el hallazgo; declararlo es correcto.
- Que el artefacto de QA diga qué **no** cubrió. Declarar los límites de una verificación es correcto.

## Si no encontrás nada

Es posible, pero **improbable** en tres commits que tocan código ya aprobado, y hay que ganárselo. Si aprobás sin observaciones, agregá una sección **«Qué se buscó y no se encontró»** enumerando, categoría por categoría, qué riesgo buscaste y con qué comando o lectura lo descartaste:

- carreras de promoción y de guardado concurrente
- fugas de media privada al render público
- rutas de borrado físico
- huecos de autorización en el preview
- regresiones sobre el pipeline de páginas de 12.1
- desviaciones entre el código y el diseño v2

**«No encontré nada» sin ese detalle se lee como que no se buscó.**

## Formato del informe

Escribilo en `docs/audits/epica-12-3-auditoria-implementacion.md`, en **castellano**:

1. **Identidad y veredicto** — tu modelo real, fecha, commit auditado; `APROBADO` o `RECHAZADO` con la razón en dos o tres oraciones
2. **Prueba de ejecución** — la salida **cruda** de los comandos obligatorios
3. **Los seis puntos** — uno por uno, con evidencia `archivo:línea` y la línea copiada
4. **Estado de las declaraciones de §11.1** — verificadas o refutadas
5. **Hallazgos críticos** — bloquean el gate
6. **Hallazgos medios**
7. **Hallazgos menores**
8. **Riesgos de seguridad**
9. **Regresiones** sobre 12.1, 12.2, `Property`, `Project`, `ServiceType` y la media de marca
10. **Tests faltantes**
11. **Correcciones obligatorias** — numeradas y accionables
12. **Qué se buscó y no se encontró** (si aprobás sin observaciones)
13. **Gate explícito** — `GATE DE IMPLEMENTACIÓN 12.3: APROBADO` o `RECHAZADO`

**Cada hallazgo necesita:** evidencia con `archivo:línea` y la línea copiada, el impacto concreto (qué se rompe y para quién) y una corrección accionable. Un hallazgo sin evidencia verificable no es un hallazgo, es una opinión.

**No apruebes por cortesía, ni rechaces por deporte.** Si el lote cumple su contrato, aprobá con claridad. Si no, rechazá y decí exactamente qué falta.

**No modifiques código ni documentos.** Tu salida es el informe.
