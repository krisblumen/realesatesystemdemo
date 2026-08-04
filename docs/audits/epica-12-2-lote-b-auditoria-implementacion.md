# Reauditoría de implementación — Épica 12.2, Lote B

**Proyecto:** New Hauz — CMS inmobiliario
**Fecha:** 2026-07-27
**Auditor:** Codex, auditor de implementación independiente
**Rama:** `feature/epica-12-content-manager`
**HEAD auditado:** `29aad35`
**Contrato:** `docs/epicas/epica-12-2-lotes-implementacion.md` §5
**Código principal auditado:** `1bf3fb6`, `eb81d42`, `9bd6261`

## 1. Veredicto

### **APROBADO**

El bloqueo heredado de la auditoría anterior quedó resuelto por la aprobación del
Lote A y por la corrección `9bd6261` del `DataProvider`. El Lote B pasa sus
pruebas focales, la suite acumulada y las comprobaciones funcionales en vivo de
los dos tipos con media. No quedan correcciones obligatorias abiertas.

> **GATE LOTE B: APROBADO**

El Lote C queda habilitado.

## 2. Evidencia real

### 2.1 Corrección del bloqueo heredado

- El Lote A quedó aprobado en `docs/audits/epica-12-2-lote-a-auditoria-implementacion.md`
  con `GATE LOTE A: APROBADO`.
- `9bd6261` separa el `DataProvider` de un argumento del provider de cuatro
  argumentos y activa `failOnWarning`/`failOnNotice` en `phpunit.xml`.
- La suite acumulada actual termina con código de salida `0`; no se reinterpretó
  el JSON del reporter como sustituto del estado real del proceso.

### 2.2 Verificación base

| Verificación | Resultado observado |
| --- | --- |
| `composer validate --strict` | ✅ `composer.json is valid` |
| `composer install --no-interaction --dry-run` | ✅ lock sincronizado; nada que instalar, actualizar o eliminar |
| `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed` | ✅ limpio contra PostgreSQL real |
| `DB_DATABASE=inmo_test php artisan test tests/Feature/Frontend/FrontendMediaSectionEditorTest.php` | ✅ 19/19 pruebas, 131 aserciones, código de salida `0` |
| `DB_DATABASE=inmo_test php artisan test` | ✅ 1003/1003 pruebas, 4380 aserciones, código de salida `0` |
| `./vendor/bin/pint --test` | ✅ limpio |
| `npm run build` | ✅ Vite y `build:filament` completados |

El build deja avisos no bloqueantes de Browserslist desactualizado y de `npx`
instalando temporalmente `tailwindcss@3.4.19` para el tema Filament.

### 2.3 Verificación funcional en vivo

Con Owner autenticado en el panel:

- `/admin/frontend/paginas/2/edit` mostró la sección **Equipo**. Al agregar una
  fila, el DOM mostró `Sin imagen`, el campo de imagen, el requisito
  `Retrato o cuadrada, mín. 600×600 · máx. 3 MB`, nombre obligatorio y puesto
  opcional. La ayuda confirma que la foto es opcional.
- `/admin/frontend/paginas/4/edit` mostró la sección **Ruta de inversión**. Al
  agregar un paso, el DOM mostró `Imagen*`, `Qué se ve en la imagen*`,
  `Título del paso*`, `Disposición` y la ayuda
  `Apaisada, mín. 1200×675 · máx. 3 MB`. El selector expuso únicamente las
  disposiciones allowlisted.
- Ninguno de los dos modales mostró `Contenido (JSON)`.

Con Agente autenticado, el acceso directo respondió con heading `403` y
`Forbidden` en:

- `/admin/frontend/paginas`
- `/admin/frontend/servicios`

Las rutas públicas `/`, `/nosotros`, `/servicios`, `/inversionistas` y `/contacto`
respondieron HTTP `200`. En DOM cada una emitió exactamente un `h1` y no mostró
`Whoops` ni `Exception`. La revisión visual móvil de la home no mostró desborde
del header, hero ni navegación.

### 2.4 Contrato técnico de media verificado

- `app/Filament/Forms/Components/SectionImageFields.php:25-73` exige
  `minWidth`, `minHeight` y `shape` sin valores implícitos, usa el
  `FileUpload` base sobre `frontend-private`, acepta solo imágenes permitidas y
  limita a 3 MB.
- `SectionsRelationManager.php:282-344` configura `team` con 600×600 y foto
  opcional, y `feature_sequence` con 1200×675, imagen obligatoria y entre 1 y
  8 pasos.
- `SectionPayloadCompiler.php:306-329` mantiene un único punto de conversión
  de upload a media mediante `addMediaFromDisk(..., 'frontend-private')`.
- `FrontendSectionMediaController.php:40-64` exige autenticación, policy,
  pertenencia de la media a la sección y responde 404 uniforme ante fallos;
  no publica una URL `/storage/`.
- `resources/views/frontend/sections/` ya no contiene el partial muerto
  `gallery.blade.php`; la allowlist y las vistas están alineadas.

## 3. Hallazgos críticos

No se encontraron hallazgos críticos abiertos.

## 4. Hallazgos medios

### M-B-1 — RESUELTO: suite acumulada bloqueada por el Lote A

La auditoría anterior mantenía B rechazado porque A terminaba con código `1` por
warnings del provider. Tras `9bd6261` y la aprobación de A, la suite acumulada
actual pasó 1003/1003 con código `0`. El bloqueo heredado queda cerrado.

No quedan hallazgos medios abiertos propios de B.

## 5. Hallazgos menores

- `npm run build` conserva avisos de Browserslist y de la instalación temporal de
  Tailwind; no bloquean el lote.
- El árbol local conserva cambios ajenos a la auditoría en
  `.atl/skill-registry.md`, `public/css/filament/admin/theme.css` y
  `docs/letras canciones hubiera.docx`. No fueron incluidos ni deben incluirse
  en el commit del lote.

## 6. Regresiones

No se observaron regresiones en los formularios de `hero`, `team` o
`feature_sequence`, en la navegación del panel, en el aislamiento del agente ni
en las cinco páginas públicas. La corrección visual de `eb81d42` conserva el
mínimo específico de `team` y no relaja el mínimo de la secuencia.

## 7. Riesgos de seguridad

No se detectó una regresión de seguridad:

- la media de borrador vive en `frontend-private` y fuera del webroot;
- el preview usa la ruta owner-only, no `/storage/`;
- el controlador valida autenticación, policy y pertenencia del UUID a la
  sección antes de servir bytes;
- los tests focales verifican media ajena, UUID inventado, disco privado,
  promoción, alt requerido y preview owner-only;
- el agente recibió 403 real en los recursos del CMS.

## 8. Riesgos de mantenimiento

El adaptador compartido está correctamente parametrizado por consumidor. No debe
reintroducirse un default de dimensiones en `SectionImageFields::make()`: eso
volvería a permitir que un nuevo tipo herede por accidente el mínimo del hero.

El partial `gallery` ya fue retirado en `cede517`, por lo que no queda código
muerto de ese tipo en las vistas versionadas.

## 9. Tests faltantes

No se identifican escenarios TB2B obligatorios faltantes. La prueba focal cubre
TB2B-1 a TB2B-8, incluida la privacidad del disco y la ruta owner-only, y la
suite completa confirma ausencia de regresiones.

## 10. Correcciones obligatorias

Ninguna. El hallazgo heredado M-B-1 está cerrado y no quedan bloqueantes de B.

## 11. Correcciones recomendadas

- Mantener `failOnWarning` y `failOnNotice` en CI.
- Mantener una captura o medición responsive por consumidor cuando se agreguen
  nuevos tipos con media.
- Tratar los avisos de Browserslist y Tailwind como higiene independiente, sin
  reabrir este lote.

## 12. Decisión explícita del gate

> **GATE LOTE B: APROBADO.** El Lote B cumple la suite focal, la suite
> acumulada, Composer, migración PostgreSQL, Pint, build, aislamiento por rol,
> privacidad de media y verificación DOM/HTTP pública. El Lote C queda
> habilitado.
