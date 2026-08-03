# Prompt — Reauditoría independiente del diseño de la Épica 12.3

> **v2 — endurecido.** Sirve para cualquier auditor, humano o modelo. La v1 produjo un informe que declaró «suite completa: 184 tests, 608 aserciones» cuando la suite tiene 1003 y 4380, se firmó con el nombre de otro auditor y repitió afirmaciones del documento auditado como si las hubiera verificado. Esta versión exige **evidencia cruda pegada**, de modo que un número inventado se caiga solo.
>
> El endurecimiento no apunta a una herramienta sino a un **modo de fallar** —dar por verificado lo que sólo se leyó— que no es exclusivo de ningún modelo. Los requisitos extra le cuestan minutos a un auditor que ya trabaja con rigor, y dejan los números cotejables desde afuera.
>
> Pegar tal cual. Es autosuficiente: no asume ningún contexto previo.

---

Sos **auditor de diseño independiente** del proyecto New Hauz. Tu trabajo NO es implementar ni mejorar el diseño: es decidir si puede implementarse de forma segura, y **rechazarlo si no**.

Tu postura por defecto es **adversarial**. Un diseño que suena bien y no se puede ejecutar es peor que ninguno, porque el implementador descubre los huecos cuando ya escribió el código. No busques confirmar que está bien; buscá dónde se rompe.

## Regla cero: evidencia cruda o no cuenta

Este informe se va a **verificar contra el repositorio**. Cualquier cifra que no se pueda reproducir invalida el informe completo y el gate no se otorga, aunque el veredicto fuera correcto.

Por lo tanto:

- **Pegá la salida literal** de cada comando (últimas 15 líneas), dentro de un bloque de código. **No resumas, no redondees, no parafrasees.**
- **Nunca escribas «✅ verificado»** sin la evidencia pegada al lado.
- **Si no ejecutaste un comando, decilo.** «No ejecutado» es una respuesta aceptable; un resultado inventado no lo es y termina la auditoría.
- **Firmá con el modelo real que estás usando.** No copies el encabezado de un informe anterior.

## Prueba de ejecución obligatoria

Antes de auditar nada, corré esto y pegá la salida **textual** en la sección 2 del informe. Sin esta sección completa, el informe se descarta sin leerse.

```bash
git rev-parse --short HEAD
git log -1 --format='%s'
php artisan test; echo "EXIT: $?"
./vendor/bin/phpunit --list-tests | grep -c '^ - '
wc -l docs/epicas/epica-12-3-media-servicios-diseno.md
```

Los cinco valores son verificables por quien recibe el informe. El conteo de tests, en particular, es un número exacto y conocido: si el tuyo no coincide, la corrida no ocurrió.

## Qué vas a auditar

**Documento:** `docs/epicas/epica-12-3-media-servicios-diseno.md` (versión **v2**)
**Rama:** `feature/epica-12-content-manager`
**Tipo de gate:** **DISEÑO**, no implementación. **No existe código de la Épica 12.3 todavía** — no lo busques ni lo reclames como faltante. Se audita si el documento es implementable, seguro y **verdadero respecto del código actual**.

## Contexto imprescindible

**Qué hace este lote.** Las imágenes de los servicios (`FrontendService.image`) hoy viven en el disco **público** desde que se suben. Cuando el administrador reemplaza una foto, la anterior deja de usarse pero **su URL pública sigue viva para siempre**, enumerable en `/storage/`. El lote propone llevarlas al pipeline privado ya aprobado para las imágenes de páginas: disco privado, preview owner-only, promoción al disco público, y **nunca borrado físico**.

**La complicación central.** El pipeline aprobado (Épica 12.1) pregunta «¿la revisión **publicada** todavía nombra este uuid?». Los servicios **no tienen revisiones**: en ellos guardar es publicar. Esa pregunta ahí no aplica y hubo que redefinir el predicado.

**Lecturas obligatorias, en este orden:**

1. `docs/epicas/epica-12-3-media-servicios-diseno.md` — el diseño a auditar
2. `docs/audits/epica-12-3-media-servicios-auditoria-diseno.md` — la auditoría que **RECHAZÓ la v1**, con cuatro críticos
3. `docs/epicas/epica-12-2-lotes-implementacion.md` §9 — el contrato de implementación
4. `docs/epicas/epica-12-administrador-contenidos-frontend.md` §16.4 — la **fuente única** normativa
5. `docs/epicas/epica-12-1-mejora-ux-hero.md` §0.8 y §0.9 — la deuda que este lote cierra

Si el diseño contradice §16.4, **gana §16.4** y eso es un hallazgo.

## La tarea central: verificar §1 línea por línea

La sección **§1 «Estado verificado en el código»** del diseño es una tabla de ~15 afirmaciones, cada una con archivo y línea. **Comprobá cada una abriendo el archivo.**

Para cada fila, tu informe debe traer **la línea literal del código**, copiada:

| # | Afirmación | Archivo:línea | **Línea real, copiada del archivo** | Estado |
| --- | --- | --- | --- | --- |
| 1 | … | `app/Models/FrontendService.php:66` | `        $this->addMediaCollection('image');` | ✅ / ❌ |

Una fila sin la línea copiada **no cuenta como verificada**. Esto no es burocracia: la v1 del diseño fue rechazada por afirmar que dos servicios con la misma imagen era «imposible por construcción», cuando la tabla **no tiene índice único** en `image_media_id`. Se declaró una garantía que la base de datos no da, y sólo se descubrió abriendo la migración.

Hacé lo mismo con toda cita `archivo:línea` del resto del documento. Una cita que apunta a otra cosa invalida el argumento que sostiene.

## Entorno y comandos

**Stack:** Laravel 13, PHP 8.3, Filament 3, **PostgreSQL con PostGIS** (no SQLite), PHPUnit 12.

```bash
composer validate --strict
./vendor/bin/pint --test
DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed
php artisan test; echo "EXIT: $?"
npm run build
```

**Cuatro advertencias operativas:**

1. **Evaluá el CÓDIGO DE SALIDA, no el JSON del reporter.** El reporter de este proyecto puede imprimir `"result":"passed"` mientras el proceso termina en `1`. Fue un hallazgo real de una auditoría anterior.
2. **No agregues `--no-output`.** `php artisan test` ya lo pasa; duplicarlo emite un warning y, con `failOnWarning` activo en `phpunit.xml`, devuelve `1`.
3. **Para ver warnings ocultos:** `php artisan test --log-events-verbose-text /tmp/eventos.txt` y buscá `Test Triggered PHPUnit Warning`.
4. **No corras la suite completa con un servidor de preview levantado**: se queda sin memoria (exit 137).

**Ignorá estos archivos**, tienen cambios locales ajenos al lote:
`.atl/skill-registry.md` · `public/css/filament/admin/theme.css` · `docs/letras canciones hubiera.docx`

## Los cuatro críticos que la v2 dice haber cerrado

Verificá cada cierre **en el código y en el texto**, no en la afirmación del autor.

| # | Qué falló en la v1 | Qué debe demostrar la v2 |
| --- | --- | --- |
| **C-1** | El límite del render público no estaba definido; el código emite `getUrl()` sin mirar promoción | Una regla **única** de resolución pública, con fallback definido, y qué pasa con media pendiente, inválida, ajena, ausente o con archivo faltante |
| **C-2** | No existía la secuencia guardar → pending → job → promoción. `afterSave()` sólo apunta la columna | Secuencia atómica y observable: lock, validación, `pending`, commit, dispatch **`afterCommit`**, relectura del predicado **bajo lock**, retry idempotente. Y que la reconciliación **NO** sea el mecanismo primario |
| **C-3** | Afirmó una unicidad que la base no impone | Índice único parcial en **SQL crudo** (no `unique()` de Blueprint), decisión explícita sobre soft-delete, y pruebas por SQL directo **y dos conexiones PostgreSQL reales** |
| **C-4** | La interfaz propuesta no era implementable: firma equivocada y faltaban los métodos de estado que el job usa | Abstracción **ejecutable**: resultado tipado de lock, máquina de estados común, resolución `model_type → estrategia` **fail-closed**, y una restricción precisa sobre qué se conserva de `PublishedMediaReference` |

### Sobre C-4, que es donde más fácil se aprueba de más

El diseño impone una restricción sobre `app/Services/Frontend/PublishedMediaReference.php`. **Leé §3.1 completo y comprobá que sea internamente coherente**: la restricción distingue métodos que conservan su cuerpo de métodos que pasan a delegación. Verificá que esa distinción sea exacta contra la clase real, y que la lista de métodos que menciona coincida con los que existen.

Después abrí `app/Jobs/PromoteFrontendMedia.php` y confirmá que **todo** lo que el job invoca esté declarado en la interfaz de §3.1 o en la máquina de estados común. Si el job usa algo que ninguna de las dos declara, la abstracción no es implementable y C-4 sigue **ABIERTO**.

*(Nota: una versión anterior de este documento tenía una contradicción exactamente acá y un informe previo no la detectó porque copió la afirmación en vez de verificarla. Ya está corregida — pero es el punto que más atención merece.)*

## Qué más mirar

- **§7 (migración de lo existente).** Hoy hay imágenes sirviéndose en público. ¿La migración las rompe? ¿Es idempotente? ¿Verifica que el archivo exista antes de marcar un estado terminal? ¿Qué hace con las versiones superadas?
- **§6 (resolución pública).** ¿La regla cubre los cinco casos? ¿El fallback es el que ya existe o inventa uno nuevo?
- **§8.1 (preview owner-only).** ¿Especifica **404 uniforme** en los cinco casos —anónimo, sin permiso, servicio inexistente, uuid mal formado, uuid ajeno—? Un 403 donde va un 404 confirma que el recurso existe. Compará con `app/Http/Controllers/FrontendSectionMediaController.php`.
- **§9.2.** El diseño dice que hay que **modificar** un test existente. Verificá que exista, que el cambio esté justificado y que **conserve** la regresión de `FrontendSetting`, que sigue fuera de alcance.
- **§10.2 (`image_alt`).** ¿Está **decidido** o sigue siendo una opción? Un diseño que delega la decisión al implementador no está terminado.
- **§11 (matriz T3-1…T3-19).** ¿Cubre concurrencia real en PostgreSQL, HTTP y caché? ¿Hay guards estructurales sobre el fuente donde una aserción de comportamiento no alcanza?
- **Seguridad.** Exposición de media privada, acceso al histórico público, confusión de propietario, IDOR en el preview, carreras de promoción.
- **Sobreingeniería.** Si una abstracción es más grande que el problema, decilo. Pero distinguí «abstracción innecesaria» de «abstracción incompleta»: la v1 falló por lo segundo.

## Qué NO es un hallazgo

- Que no exista código de 12.3. Es un gate de diseño.
- Los warnings de `npm run build` sobre Browserslist o `npx tailwindcss@3`.
- Los tres archivos locales listados arriba.
- Que el diseño **declare** deuda residual —el histórico público que no se puede esconder sin borrado físico, prohibido en v1—. Declarar una limitación con honestidad es correcto; **presentarla como resuelta** sería el hallazgo.

## Si no encontrás nada

Un documento de ~340 líneas sin un solo hallazgo es posible, pero **improbable**, y hay que ganárselo. Si tu veredicto es aprobar sin observaciones, agregá una sección **«Qué se buscó y no se encontró»** enumerando, categoría por categoría, qué riesgo buscaste y con qué comando o lectura lo descartaste:

- carreras de concurrencia
- fugas de media privada al render público
- rutas de borrado físico
- huecos de autorización en el preview
- inconsistencias entre el diseño y §16.4
- afirmaciones del diseño no respaldadas por el código

**«No encontré nada» sin ese detalle se lee como que no se buscó.**

## Formato del informe

Escribilo en `docs/audits/epica-12-3-reauditoria-diseno.md`, en **castellano**:

1. **Identidad y veredicto** — tu modelo real, fecha, commit auditado; `APROBADO` o `RECHAZADO` con la razón en dos o tres oraciones
2. **Prueba de ejecución** — la salida **cruda** de los cinco comandos obligatorios
3. **Verificación de §1** — la tabla con la línea de código **copiada** en cada fila
4. **Estado de C-1, C-2, C-3, C-4** — `CERRADO` o `ABIERTO`, con evidencia
5. **Hallazgos críticos** — bloquean el gate
6. **Hallazgos medios**
7. **Hallazgos menores**
8. **Riesgos de seguridad**
9. **Regresiones y compatibilidad** con el pipeline aprobado de 12.1
10. **Tests faltantes** antes de habilitar la implementación
11. **Correcciones obligatorias** — numeradas y accionables
12. **Qué se buscó y no se encontró** (si aprobás sin observaciones)
13. **Gate explícito** — `GATE DE DISEÑO 12.3: APROBADO` o `RECHAZADO`

**Cada hallazgo necesita:** evidencia con `archivo:línea` y la línea copiada, el impacto concreto (qué se rompe y para quién) y una corrección accionable. Un hallazgo sin evidencia verificable no es un hallazgo, es una opinión.

**No apruebes por cortesía.** Si los cuatro críticos están cerrados y el resto es menor, aprobá con claridad. Si alguno sigue abierto, rechazá y decí exactamente qué falta. Un gate que aprueba lo dudoso no protege a nadie — y uno que aprueba con evidencia inventada es peor que no tener gate.

**No modifiques código ni el documento de diseño.** Tu salida es el informe.
