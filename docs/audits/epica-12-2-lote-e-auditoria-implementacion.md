# Auditoría de implementación — Épica 12.2, Lote E

**Proyecto:** New Hauz — CMS inmobiliario  
**Fecha:** 2026-07-27  
**Auditor:** Codex, auditor de implementación independiente  
**Rama:** `feature/epica-12-content-manager`  
**HEAD auditado:** `29aad35`
**Implementación E:** `eb81d42`
**Contrato:** `docs/epicas/epica-12-2-lotes-implementacion.md` §8  
**Código principal auditado:** `app/Filament/Forms/Components/SectionImageFields.php`, `app/Filament/Resources/FrontendPageResource/RelationManagers/SectionsRelationManager.php`

## 1. Veredicto

### **APROBADO**

El Lote E cierra la corrección visual contratada: los mínimos de imagen son
explícitos por consumidor, el retrato de `team` acepta una imagen cuadrada sin
relajar los paneles apaisados, el editor muestra nombres humanos y las tarjetas
no presentan desborde horizontal en la verificación viva. La precondición
secuencial A→D también está satisfecha; el informe del Lote D contiene
`GATE LOTE D: APROBADO`.

> **GATE LOTE E: APROBADO**

La deuda 12.3 sigue fuera de este gate y requiere su mini-diseño independiente,
como establece el contrato.

## 2. Evidencia real

### 2.1 Verificación base obligatoria

| Verificación | Resultado |
| --- | --- |
| `composer validate --strict && composer install --no-interaction --dry-run` | ✅ código `0`; `composer.json` válido y lock sincronizado |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ código `0`; migraciones y seed PostgreSQL ejecutados limpiamente |
| Focal E acumulado: `FrontendMediaSectionEditorTest.php` + `FrontendSectionEditorClosureTest.php` | ✅ 29/29 pruebas, 372 aserciones, código `0` |
| Suite acumulada: `DB_DATABASE=inmo_test php artisan test` | ✅ 1003/1003 pruebas, 4380 aserciones, código `0` |
| `./vendor/bin/pint --test` | ✅ código `0`; Pint limpio |
| `npm run build` | ✅ código `0`; Vite y tema Filament compilados |

El build emitió únicamente avisos no bloqueantes de Browserslist desactualizado
y de instalación temporal de Tailwind 3.4.19 para el tema de Filament.

### 2.2 Mínimos por consumidor y causa raíz

- `SectionImageFields::make()` exige `minWidth`, `minHeight` y `shape` como
  parámetros obligatorios; no tiene valores por defecto.
- `hero` conserva `1200×675`; `feature_sequence` conserva `1200×675`; `team`
  declara `600×600` con forma **Retrato o cuadrada**.
- Las pruebas focales aceptan `800×800` para `team`, rechazan `300×300` para
  `team` y rechazan la misma imagen cuadrada para `feature_sequence`.
- Esto cierra la causa raíz del defecto: no se relajó globalmente la validación ni
  se volvió a introducir un default compartido.

### 2.3 Verificación viva del panel y DOM

Con un Owner autenticado en el servidor local de pruebas:

- `/admin/frontend/paginas/2/edit` abrió el editor de **Equipo**.
- El formulario mostró **Integrantes**, el botón **Agregar integrante** y, al
  agregar una fila, el texto `Retrato o cuadrada, mín. 600×600 · máx. 3 MB`,
  además de la reserva **Sin imagen**.
- `/admin/frontend/paginas/4/edit` abrió **Ruta de inversión**. El formulario
  mostró **Pasos**, `Entre 1 y 8. Cada paso lleva su imagen.` y, al agregar una
  fila, `Apaisada, mín. 1200×675 · máx. 3 MB`, **Qué se ve en la imagen** y
  **Disposición**.
- En ambos formularios no apareció `Contenido (JSON)` ni `Editar frontend
  section`; las claves internas sólo aparecen como texto secundario en la tabla.
- La inspección DOM reportó `horizontalOverflow=false` con viewport de 1280 px.
- El encabezado del modal de inversión apareció como **Ruta de inversión**, no
  como `investment_path`.

### 2.4 Evidencia visual reproducible

`docs/audits/artifacts/epica-12-2-lote-e/qa-visual.md` conserva el método y las
mediciones comparables del cambio:

- fila `feature_sequence`: 585 → 441 px;
- fila `team`: 253 → aproximadamente 215 px;
- ayuda de imagen: de tres líneas a una o dos;
- el estado sin imagen reserva su lugar con un recuadro;
- los spans de cada fila suman 12 y no producen desborde horizontal.

La verificación viva confirmó los textos, la reserva visual y la ausencia de
desborde; el artefacto conserva las mediciones de densidad de la corrección.

### 2.5 Regresión pública

Desde el servidor local de pruebas, las cinco rutas respondieron HTTP 200 y no
contuvieron `Whoops`, `Exception` ni `Stack trace`:

| Ruta | HTTP | Resultado |
| --- | ---: | --- |
| `/` | 200 | OK |
| `/nosotros` | 200 | OK |
| `/servicios` | 200 | OK |
| `/inversionistas` | 200 | OK |
| `/contacto` | 200 | OK |

## 3. Hallazgos críticos

Ninguno.

## 4. Hallazgos medios

Ninguno. La corrección obligatoria histórica —suite con código de salida no
cero por warnings de A— está cerrada: la corrida actual termina con código `0`.

## 5. Hallazgos menores

Ninguno propio del Lote E.

Los avisos de Browserslist/Tailwind del build son higiene de dependencias y no
bloquean este lote.

## 6. Regresiones

No se observaron regresiones en:

- mínimos del hero y de `feature_sequence`;
- formularios de `team` y `feature_sequence`;
- nombres humanos de las secciones;
- pipeline de media privada, preview owner-only y promoción heredados de 12.1;
- las cinco rutas públicas;
- los gates aprobados de los lotes A, B, C y D.

No se modificó código durante esta auditoría. Los archivos sucios no relacionados
que ya existían en el workspace no fueron revertidos ni incluidos en el informe.

## 7. Riesgos de seguridad

El lote no relaja el pipeline de media: conserva `frontend-private`, la ruta de
preview owner-only y la promoción posterior. La validación de dimensiones, MIME,
tamaño y `alt` continúa server-side. No se agregó borrado físico ni una segunda
ruta de subida.

## 8. Riesgos de mantenimiento

El patrón seguro depende de que cada nuevo consumidor declare explícitamente sus
dimensiones y forma. La firma sin defaults de `SectionImageFields::make()` evita
que un consumidor futuro herede silenciosamente el mínimo del hero.

`section_labels` debe seguir evolucionando junto con
`config('frontend-sections.pages')`; la prueba que exige un nombre humano por
sección canónica debe mantenerse como barrera de regresión.

## 9. Tests faltantes

No faltan TB2E-1 a TB2E-6 para el alcance del contrato. La cobertura focal, la
suite completa y la verificación viva cubren mínimos por consumidor, lenguaje,
densidad observable y render público.

## 10. Correcciones obligatorias

Ninguna.

## 11. Correcciones recomendadas

- Mantener `minWidth`, `minHeight` y `shape` obligatorios al agregar nuevos
  consumidores de imágenes.
- Mantener el artefacto `qa-visual.md` junto con futuras correcciones que nazcan
  de inspección visual.
- Añadir una regla de CI que trate warnings de PHPUnit como fallo visible, aunque
  la corrida actual ya termina con código `0`.
- No iniciar la implementación de 12.3 sin su mini-diseño y gate propio.

## 12. Decisión explícita del gate

> **GATE LOTE E: APROBADO.** Los lotes A→E están aprobados. La deuda 12.3 no
> queda habilitada automáticamente: debe completar su diseño y auditoría de
> diseño antes de implementarse.
