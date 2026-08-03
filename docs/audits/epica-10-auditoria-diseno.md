# Auditoría de diseño — Épica 10 Contratos de Intermediación

**Proyecto:** New Hauz — Plataforma Inmobiliaria  
**Fecha:** 2026-07-14  
**Auditor:** Codex  
**Documento auditado:** `docs/epicas/epica-10-contratos-intermediacion.md`  
**Rama:** `feature/epica-10-contratos-intermediacion`

## Evidencia verificada en código real

- `composer.json` / `composer.lock`: ya existen `barryvdh/laravel-dompdf ^3.1`, `endroid/qr-code ^6.0`, `spatie/laravel-medialibrary ^11.23`, `spatie/laravel-permission ^8.0`.
- `app/Models/User.php`: `phone`, `whatsapp`, `HasRoles`, `isActive()` existen.
- `database/seeders/PermissionSeeder.php`: matriz real de permisos usa Spatie y roles `owner`, `admin`, `agente`; actualmente tiene permisos base + Lonas.
- `config/media-library.php`: `disk_name` default = `env('MEDIA_DISK', 'public')`; `.env.example` usa `FILESYSTEM_DISK=public`; `config/filesystems.php` define `public` como URL `/storage` con visibilidad pública.
- `routes/console.php`: ya hay patrón real `Schedule::command(...)`.
- `php artisan route:list`: no hay rutas ni canal real de WhatsApp; solo aparece `properties.pdf.show` relacionado con PDF.
- `app/Models/Property.php`: existe catálogo `Property`; el diseño auditado no crea FK a `properties`.
- `php -r '... Str::plural("contrato_intermediacion") ...'`: Laravel pluraliza a `contrato_intermediacions`; por eso `foreignId('contrato_intermediacion_id')->constrained()` NO apuntaría a `contratos_intermediacion`.

---

## 1. Veredicto

❌ **Rechazado hasta corregir los hallazgos críticos.**

El diseño está bien orientado en lo macro: es aditivo, no acopla el contrato a `Property`, reconoce que WhatsApp real no existe, usa token opaco anti-IDOR, contempla consumo atómico del enlace y documenta la circularidad del hash en el sello. ESO está bien.

Pero hay tres problemas que no son cosméticos: migraciones FK que fallarían o apuntarían mal, media sensible que por defecto quedaría pública si el implementador sigue el snippet, y un flujo de creación de contrato que sugiere generar el folio después del insert aunque la columna `folio` es obligatoria/unique. Acá no se puede “confiar en que el dev lo entiende”: el diseño tiene que dejar el contrato técnico cerrado. Si no, después la implementación se rompe en lo obvio.

---

## 2. Hallazgos críticos

### C-1 — `constrained()` infiere la tabla equivocada para `contrato_intermediacion_id`

**Ubicación:** `docs/epicas/epica-10-contratos-intermediacion.md`, §6.2, §9.2 y §13 (`contrato_accesos`, `contrato_firma_evidencias`, `contrato_eventos`).

**Evidencia repo:** Laravel pluraliza `contrato_intermediacion` como `contrato_intermediacions`, pero la tabla diseñada es `contratos_intermediacion`.

**Impacto:** las migraciones pueden fallar o crear FKs contra una tabla inexistente. Es un blocker de Lote A/B/E.

**Corrección concreta:** reemplazar todos los:

```php
$table->foreignId('contrato_intermediacion_id')->constrained()->cascadeOnDelete();
```

por:

```php
$table->foreignId('contrato_intermediacion_id')
    ->constrained('contratos_intermediacion')
    ->cascadeOnDelete();
```

También revisar relaciones si se cambia el nombre de tabla para mantener consistencia.

### C-2 — Media sensible quedaría pública si se sigue el snippet actual

**Ubicación:** §5.4 `registerMediaCollections()`, §14.4 Identificación oficial.

**Evidencia repo:** `config/media-library.php` usa `MEDIA_DISK` con default `public`; `config/filesystems.php` sirve `public` por `/storage`. El snippet del modelo define:

```php
$this->addMediaCollection('identificacion');
$this->addMediaCollection('documento-final')->singleFile();
```

sin `useDisk('local')` ni ruta autorizada obligatoria.

**Impacto:** identificación oficial, firma y PDF firmado contienen datos personales/sensibles. Si caen en el disco público, una URL directa puede saltarse la Policy. Esto viola el requerimiento “Owner only” para identificación y debilita todo el expediente.

**Corrección concreta:** convertir en regla obligatoria, no “considerar”:

```php
$this->addMediaCollection('identificacion')->useDisk('local');
$this->addMediaCollection('firma')->singleFile()->useDisk('local');
$this->addMediaCollection('documento-final')->singleFile()->useDisk('local');
```

Agregar un `ContratoMediaController`/acción autorizada que haga `Gate::authorize(...)` y streamee bytes desde disco privado. Tests: admin 403 para identificación, owner 200; URL pública `/storage/...` no debe existir para esas colecciones.

### C-3 — El folio se propone asignar en `afterCreate` aunque la columna es obligatoria y única

**Ubicación:** §5.3 migración `folio` y §7 “Al crear (`CreateContrato::handleRecordCreation` / `afterCreate`)”.

**Impacto:** si Filament intenta crear el registro sin `folio`, la migración diseñada (`$table->string('folio', 8)->unique()`) no permite `NULL`; si se marca nullable para sortearlo, se degrada el contrato de folio obligatorio y se abre una ventana de registros incompletos.

**Corrección concreta:** el folio debe generarse **antes del insert** dentro de un servicio transaccional de creación, no en `afterCreate`. El retry por colisión debe envolver el `ContratoIntermediacion::create(...)`, no sólo el `exists()` del generador:

```php
for ($i = 0; $i < 10; $i++) {
    try {
        return ContratoIntermediacion::create([
            ...$data,
            'folio' => $folios->generar(),
            'agente_id' => $actor->id,
        ]);
    } catch (QueryException $e) {
        if (! $this->esColisionFolio($e)) {
            throw $e;
        }
    }
}
```

El diseño debe declarar `ContratoCreacionService` o mover explícitamente esa responsabilidad al Resource sin `afterCreate`.

---

## 3. Hallazgos medios

### M-1 — La máquina de estados está bien listada, pero falta una API única de transición en el modelo/servicio

**Ubicación:** §5.2 enum `EstadoContrato`, §5.5 diagrama, §15 tests.

**Impacto:** el diseño dice “nunca por `update(['estado' => ...])` directo”, pero no define el método estable (`transicionarA`) en `ContratoIntermediacion` ni un `ContratoEstadoService`. Sin una API central, cada servicio/comando puede mutar estado y timestamps a mano.

**Corrección concreta:** declarar método único:

```php
public function transicionarA(EstadoContrato $destino, ?User $actor = null, array $meta = []): void
```

que valide `puedeTransicionarA`, actualice timestamp correspondiente, guarde evento y bloquee transiciones inválidas. Todos los servicios/comandos deben usarlo.

### M-2 — Consumo del token antes de validar payload puede quemar enlaces por errores de input

**Ubicación:** §9.3 `ContratoFirmaService::firmar()`.

**Impacto:** el snippet consume el token como paso 1 y recién después menciona validación de imagen/tamaño. Un POST con firma corrupta podría invalidar el enlace sin firmar ni rechazar. No es IDOR, pero sí una mala UX y vector de bloqueo si alguien obtiene el token.

**Corrección concreta:** validar formato/tamaño de firma y datos obligatorios antes de consumir el token; dentro de la transacción consumir token + lock contrato + transición final. Si se quiere máxima robustez, usar `lockForUpdate()` sobre acceso y contrato antes de marcar `usado_at`.

### M-3 — Acceso a identificación oficial queda bien planteado conceptualmente, pero falta contrato de ruta real

**Ubicación:** §12, §14.2, §14.4.

**Impacto:** el diseño dice que la UI usa Policy y que la Media Library se sirve tras Gate, pero no declara archivo/ruta/controlador en el árbol técnico. Para un dato sensible, eso tiene que estar en el alcance técnico y en QA, no implícito.

**Corrección concreta:** agregar al árbol:

- `Http/Controllers/ContratoMediaController.php`
- ruta admin privada para descargar/ver media del contrato.
- Policy methods separados: `viewDocumentoFinal`, `viewIdentificacion`, `viewFirma`.

### M-4 — Retención: la lista de eliminación pendiente existe como boolean, pero falta la acción Owner de confirmación

**Ubicación:** §11.3, §12, §13, §16 R-7.

**Impacto:** el job “no borra” está bien, pero no está diseñado el flujo final: quién confirma, cómo se audita, si borra físico o soft delete, y qué pasa con media sensible. El requerimiento pide confirmación del Owner; el diseño sólo dice que existirá.

**Corrección concreta:** agregar acción Filament explícita “Confirmar eliminación de expediente” solo Owner, con confirm modal, evento `eliminacion_confirmada`, y política clara: soft delete del contrato + eliminación/retención de media según legal. Si se borra media física, debe quedar auditado.

### M-5 — Verificación pública por folio puede permitir enumeración de contratos firmados

**Ubicación:** §10.3 `/verificar/{folio}`.

**Impacto:** aunque no expone PII, un atacante puede probar folios y saber si existen/están firmados y fecha de firma. El folio es de 8 caracteres, no secreto.

**Corrección concreta:** mantener cero PII, agregar rate limiting específico, respuestas uniformes para folio inexistente/no firmado cuando no se sube PDF, y test de no exposición. Si negocio necesita mostrar existencia por folio, documentar el riesgo como aceptado.

### M-6 — Identificación oficial pide anverso/reverso, pero el diseño usa una sola colección genérica

**Ubicación:** RFC-063 “Identificación oficial (anverso y reverso)” y diseño §5.4/§7.

**Impacto:** una colección genérica `identificacion` no garantiza que existan ambos lados ni que se puedan validar/etiquetar. El DoD puede pasar con un solo archivo.

**Corrección concreta:** especificar dos campos/colecciones (`identificacion-anverso`, `identificacion-reverso`) o una colección con custom properties obligatorias (`lado=anverso|reverso`) y validación de ambos lados antes de firmar.

---

## 4. Hallazgos menores

### Mn-1 — `esFinal()` no incluye `Expirado` ni `Rechazado`, pero el nombre puede confundir

**Ubicación:** §5.2 `EstadoContrato::esFinal()`.

**Impacto:** funcionalmente puede estar bien porque `Rechazado`/`Expirado` permiten reenvío, pero el nombre “final” puede inducir a errores en UI/acciones.

**Corrección:** renombrar a `bloqueaCambiosDeNegocio()` o documentar con comentario que “final” significa “sin reenvío”, no “estado terminal de flujo público”.

### Mn-2 — `registrarEvento()` depende de `request()` dentro del modelo

**Ubicación:** §5.4 `ContratoIntermediacion::registrarEvento()`.

**Impacto:** acopla modelo a HTTP. Los comandos de consola no tienen request real y pueden guardar IP/UA vacíos o incorrectos.

**Corrección:** aceptar IP/user-agent explícitos en `meta` o usar un `ContratoEventoService` que resuelva contexto HTTP/CLI.

### Mn-3 — `emitido_por` debería ser enum o validación cerrada

**Ubicación:** §6.2 `contrato_accesos.emitido_por`.

**Impacto:** string libre permite valores inconsistentes.

**Corrección:** enum `OrigenAccesoContrato` o check a nivel aplicación (`inicial|reenvio|qr_impreso` si aplica).

### Mn-4 — Falta declarar throttling del formulario público

**Ubicación:** §8 rutas públicas y §14.3 controles.

**Impacto:** tokens son robustos, pero endpoints públicos de firma/rechazo/verificación deben tener rate limiting.

**Corrección:** middleware `throttle` dedicado en rutas públicas y tests mínimos de abuso.

---

## 5. Sobreingeniería detectada

- **Cuatro modelos auxiliares + cinco servicios** es razonable para esta épica por sensibilidad legal, tokens, PDF y auditoría. No lo considero sobreingeniería.
- **`ContratoEvento` per-entidad** es correcto porque el repo no tiene auditoría global real; se verificaron tablas log por entidad (`lead_assignment_logs`, `user_status_logs`).
- **Riesgo de exceso:** `ContratoAcceso` histórico está justificado por reenvíos y un solo uso; no eliminar.
- **Sí hay una abstracción pendiente:** falta `ContratoCreacionService`. No es sobreingeniería: es necesario para folio + create + retry atómico.

---

## 6. Riesgos de implementación

1. **Migraciones fallidas por FKs mal inferidas** si no se corrige `constrained('contratos_intermediacion')`.
2. **Registros sin folio o create fallido** si se usa `afterCreate` en vez de generar folio antes del insert.
3. **Dompdf con firmas desde URL pública**: `getFirstMediaUrl('firma')` puede fallar en local o exponer media; preferir bytes/data URI desde disco privado.
4. **Eventos desde consola**: `request()->ip()` en modelo puede no comportarse bien en jobs.
5. **Reenvío desde `Rechazado`/`Expirado`** debe emitir token nuevo y registrar evento sin generar contrato nuevo; test obligatorio.
6. **PermissionSeederTest** debe actualizarse por 3 permisos nuevos; el repo tiene matriz exacta.

---

## 7. Riesgos de seguridad

### IDOR del formulario público

Diseño base: ✅ correcto. El acceso de llenado usa token opaco `Str::random(48)` y guarda sólo `sha256`. No usa folio como secreto.

Riesgo restante: `/verificar/{folio}` por folio puede enumerar existencia/estado. Mitigar con rate limiting y respuestas cuidadas.

### Acceso a identificaciones oficiales

Diseño actual: ⚠️ insuficiente en snippet. La Policy Owner-only está bien en intención, pero con Media Library default `public` una URL directa puede saltarse Filament/Policy.

Corrección obligatoria: disco privado + streaming autorizado + tests HTTP reales. No alcanza con ocultar UI.

### Condición de carrera “un solo uso”

Diseño base: ✅ bien orientado. `update where usado_at is null` + `lockForUpdate` cubre doble pestaña al firmar/rechazar.

Riesgo restante: validar payload antes de consumir para no quemar token con POST inválido.

---

## 8. Recomendaciones obligatorias

1. Corregir todos los `constrained()` de `contrato_intermediacion_id` a `constrained('contratos_intermediacion')`.
2. Definir `ContratoCreacionService` o flujo equivalente que genere folio antes del insert y capture colisiones `unique` alrededor del create completo.
3. Hacer privadas las colecciones sensibles (`identificacion`, `firma`, `documento-final`) y servirlas sólo por controlador autorizado.
4. Agregar ruta/controlador explícito para media de contratos con Policy real y tests admin/owner/agente.
5. Definir método/servicio único de transición de estados; prohibir mutaciones directas de `estado` en servicios/comandos.
6. Validar firma/datos antes de consumir token; mantener consumo atómico dentro de transacción.
7. Diseñar acción Owner para confirmación de eliminación tras `eliminacion_pendiente`.
8. Especificar cómo se exige anverso/reverso de identificación oficial.

---

## 9. Recomendaciones opcionales

1. Rate limit dedicado para `/contrato/{token}` y `/verificar/{folio}`.
2. Respuestas uniformes en `/verificar/{folio}` para reducir enumeración.
3. Enum para `emitido_por` en `contrato_accesos`.
4. Extraer `ContratoEventoService` para evitar `request()` dentro del modelo.
5. Usar data URI/bytes de firma para dompdf en lugar de URL pública.
6. Documentar explícitamente tamaño máximo y MIME de identificación oficial, además de la firma.

---

## 10. Evaluación de decisiones cerradas en EPICA-10

| Decisión | Evaluación |
| --- | --- |
| Firma electrónica simple in-house, sin NOM-151 | ✅ No reabrir. Técnica y legalmente está documentada como firma simple con evidencia. |
| Sin OTP adicional | ✅ No reabrir. Token opaco de 48 chars + hash + un solo uso es suficiente para fase 1. |
| Inmueble dentro del contrato, sin FK a Property | ✅ No reabrir. Diseño cumple aditividad real. |
| Exclusividad y vigencia variables | ✅ No reabrir. |
| Solo firma cliente; inmobiliaria valida con sello | ✅ No reabrir. Ajustar sólo la circularidad del hash, ya detectada por el diseño. |
| Una sola plantilla con clausulado dinámico | ✅ No reabrir. Requiere tests por combinación. |
| Identificación oficial como evidencia adjunta | ✅ No reabrir, pero exigir disco privado/ruta autorizada. |
| Email + WhatsApp simultáneo | ⚠️ Reinterpretación técnica correcta: no hay WhatsApp API real. Fase 1 debe decir “email automático + wa.me asistido”. No reabrir negocio, pero corregir wording de RFC-069/EPICA para no prometer API inexistente. |
| Reenvío conserva folio | ✅ No reabrir. |
| Retención 2 años + Owner confirma borrado | ✅ No reabrir. Falta diseñar acción de confirmación. |
| Roles: generan Agente/Admin/Owner; cancelan Admin/Owner; ID solo Owner | ✅ No reabrir. Ver nota: EPICA proceso menciona agente puede cancelar, pero la sección de decisiones y diseño final restringen a Admin/Owner; dejarlo explícito. |
| Sello SVG + mini QR | ✅ No reabrir. Hash del PDF final en BD, no impreso dentro del PDF. |

---

## 11. Preguntas abiertas

1. ¿El PDF final (`documento-final`) debe poder verlo el agente siempre, o sólo si es propio? El diseño sugiere agente propios/admin/owner, pero la media route debe fijarlo.
2. ¿La identificación oficial requiere ambos lados obligatorios para firmar, o puede omitirse si el contrato ya tiene evidencia previa? Definir regla exacta.
3. ¿La verificación por folio debe confirmar explícitamente “folio inexistente” o responder de forma uniforme para evitar enumeración?
4. ¿La eliminación confirmada por Owner borra físicamente media o sólo soft-deletea el expediente y conserva blobs por retención legal adicional?
5. ¿El wa.me asistido cuenta como cumplimiento funcional de “WhatsApp simultáneo” para QA, o QA debe validar sólo generación del enlace?

---

## 12. Checklist de corrección para Claude (Prompt 3)

- [ ] Corregir FKs: `constrained('contratos_intermediacion')` en accesos, evidencias y eventos.
- [ ] Reemplazar `afterCreate` por creación transaccional con folio pre-insert y retry de `QueryException` unique.
- [ ] Agregar `ContratoCreacionService` al árbol técnico o declarar explícitamente la responsabilidad en `CreateContrato::handleRecordCreation`.
- [ ] Hacer `identificacion`, `firma` y `documento-final` colecciones privadas (`useDisk('local')`).
- [ ] Agregar controlador/rutas de descarga/ver media con `Gate::authorize` y tests 403/200 por rol.
- [ ] Definir método único `transicionarA()` o `ContratoEstadoService` y actualizar snippets para usarlo.
- [ ] Validar firma y datos antes de consumir token; mantener consumo atómico en transacción.
- [ ] Diseñar acción Owner “Confirmar eliminación” para contratos con `eliminacion_pendiente = true`.
- [ ] Especificar anverso/reverso de identificación oficial y validaciones MIME/tamaño.
- [ ] Agregar rate limiting a rutas públicas de contrato/verificación.
- [ ] Ajustar wording de WhatsApp: `wa.me` asistido en fase 1; WhatsApp Business API diferido.
- [ ] Agregar tests QA-151→QA-180 incluyendo los críticos: FK/migración, media privada, doble pestaña, IDOR, transición inválida, retención no borra y Owner-only real para identificación.
