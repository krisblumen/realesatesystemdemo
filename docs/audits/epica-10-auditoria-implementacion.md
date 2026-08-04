# Auditoría de implementación — Épica 10 Contratos de Intermediación

- **Proyecto:** New Hauz — Plataforma Inmobiliaria
- **Fecha:** 2026-07-15
- **Auditor:** Codex
- **Rama auditada:** `feature/epica-10-contratos-intermediacion`
- **Documento base:** `docs/epicas/epica-10-contratos-intermediacion.md`
- **Auditoría de diseño revisada:** `docs/audits/epica-10-auditoria-diseno.md`

## 1. Veredicto

**Aprobado con observaciones menores.**

La implementación cumple los contratos críticos de Épica 10 contra PostgreSQL real: migraciones limpias, suite completa verde, folio único global respaldado por índice único, reintento ante colisión, token público de un solo uso, máquina de estados bloqueando transiciones inválidas, media privada autorizada por Policy, PDF final con hash SHA-256 verificable, retención sin borrado automático y auditoría de firma persistida.

La observación menor no bloquea merge: `ContratoRetencionService::confirmarEliminacion()` asume que el caller ya autorizó Owner. Hoy el único flujo de UI lo protege vía Policy, pero el contrato del servicio queda más frágil para usos futuros.

## Evidencia de ejecución viva

### Composer / dependencias

```bash
composer validate --strict && composer install --no-interaction --dry-run
```

Resultado:

```text
./composer.json is valid
Installing dependencies from lock file (including require-dev)
Verifying lock file contents can be installed on current platform.
Nothing to install, update or remove
```

Dependencias relevantes verificadas en `composer.json` + `composer.lock`:

- `barryvdh/laravel-dompdf: ^3.1`
- `endroid/qr-code: ^6.0`
- `spatie/laravel-medialibrary: ^11.23`
- `spatie/laravel-permission: ^8.0`

### Migración en PostgreSQL real

```bash
DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force
```

Resultado: migración limpia completa, incluyendo:

```text
2026_07_14_120000_create_contratos_intermediacion_table ........ DONE
2026_07_14_120100_create_contrato_eventos_table ................ DONE
2026_07_14_120200_create_contrato_accesos_table ................ DONE
2026_07_14_120300_create_contrato_firma_evidencias_table ....... DONE
```

### Suite completa

```bash
DB_DATABASE=inmo_test php artisan test
```

Resultado:

```json
{"tool":"phpunit","result":"passed","tests":470,"passed":470,"assertions":1719,"duration_ms":382942}
```

### Prueba viva específica de auditoría

Se ejecutó una prueba temporal contra el kernel Laravel + PostgreSQL real:

```bash
DB_DATABASE=inmo_test php artisan test /tmp/Epica10LiveAuditTest.php --env=testing --debug
```

Resultado:

```json
{"tool":"phpunit","result":"passed","tests":1,"passed":1,"assertions":36,"duration_ms":724}
```

Evidencia emitida por la prueba:

```json
{
  "folio_unique_index":"CREATE UNIQUE INDEX contratos_intermediacion_folio_unique ON public.contratos_intermediacion USING btree (folio)",
  "property_fk_count":0,
  "folio_collision_retry_result":"BBBB2345",
  "idor_invalid_token_status":410,
  "estado_post_firma":"firmado",
  "token_reuse_status":410,
  "invalid_transition_rejected":"Transición inválida: firmado → enviado",
  "identificacion_admin_status":403,
  "identificacion_owner_status":200,
  "firma_evidencia":{"ip":"203.0.113.10","user_agent":"AuditAgent/1.0","firmado_at_set":true,"firma_hash_len":64},
  "pdf_hash_matches":true,
  "job_expirar_estado":"expirado",
  "retencion_pendiente_sin_borrar":true,
  "retencion_confirmacion_soft_deleted":true,
  "notifications_checked":["enlace_on_demand","firmado_agente","retencion_owner"]
}
```

## 2. Hallazgos críticos

No se detectaron hallazgos críticos.

Controles críticos verificados:

- **Folio único global:** índice PostgreSQL real `contratos_intermediacion_folio_unique` sobre `folio`.
- **Colisión de folio:** inserción directa SQL con folio existente + generación por servicio reintentó y creó `BBBB2345`.
- **Sin FK a `properties`:** `property_fk_count = 0` en `information_schema`.
- **Token de un solo uso:** después de firmar, reutilizar el token devolvió HTTP `410`.
- **IDOR:** token inválido/ajeno devolvió HTTP `410` sin exponer cliente ni dirección privada.
- **Identificación oficial:** Admin recibió `403`; Owner recibió `200` vía ruta real `contratos.media`.
- **PDF/hash:** hash SHA-256 persistido coincide con el recalculado del archivo final.

## 3. Hallazgos medios

No se detectaron hallazgos medios.

## 4. Hallazgos menores

### Mn-IMP-1 — Autorización de retención vive fuera del servicio

- **Archivo:** `app/Services/Contratos/ContratoRetencionService.php`
- **Evidencia:** el servicio recibe `User $owner`, pero no valida internamente `confirmarEliminacion`; la protección actual está en `ContratoIntermediacionPolicy::confirmarEliminacion()` y en la acción Filament.
- **Impacto:** bajo. No hay bypass actual detectado porque el único caller (`ContratoIntermediacionResource`) consulta Policy antes de mostrar/ejecutar la acción. Pero si otro caller reutiliza el servicio sin Policy, podría purgar media personal indebidamente.
- **Corrección recomendada:** agregar `Gate::authorize('confirmarEliminacion', $contrato)` dentro del servicio o documentar explícitamente que el servicio exige caller preautorizado.

## 5. Regresiones detectadas

No se detectaron regresiones funcionales.

Evidencia:

- Suite completa: `470 tests / 1719 assertions` verde.
- Cambios sobre migraciones existentes prohibidas: no se modificaron migraciones previas de `users`, `properties`, `zones` ni `media`; solo se agregaron migraciones nuevas de contratos.
- El contrato de intermediación permanece independiente del catálogo `Property`: datos del inmueble viven en columnas propias; no hay FK a `properties`.
- Roles/permisos se extendieron de forma aditiva en `PermissionSeeder` con `contratos.manage`, `contratos.cancel`, `contratos.ver-identificacion`.

## 6. Riesgos de seguridad

### IDOR del formulario público

**Estado:** controlado.

- Token inválido/ajeno → HTTP `410`.
- La vista inválida no mostró `Cliente Secreto Auditoría`, `Dirección Privada 999` ni `Domicilio Privado 123`.
- `/verificar/{folio}` no revela existencia/estatus sin PDF correcto; la prueba GET/POST no expuso PII.

### Acceso a identificaciones oficiales

**Estado:** controlado.

- Colecciones `identificacion-anverso`, `identificacion-reverso`, `firma`, `documento-final` usan disco privado `local`.
- Ruta única de media: `/admin/contratos/{contrato}/media/{coleccion}` con `auth` + Policy.
- Admin no Owner: `403` para identificación.
- Owner: `200`.

### “Un solo uso” real

**Estado:** controlado.

- Firma consumió token.
- Reutilizar el mismo token para rechazar devolvió HTTP `410`.
- Servicio usa `lockForUpdate()` + `consumir()` atómico.

### Firma y evidencia

**Estado:** controlado.

Persistidos correctamente:

- IP: `203.0.113.10`
- User-Agent: `AuditAgent/1.0`
- timestamp servidor: presente
- `firma_hash`: 64 caracteres SHA-256

## 7. Riesgos de mantenimiento

- La autorización de eliminación por retención depende del caller. Ver `Mn-IMP-1`.
- Hay bastante lógica de dominio invocada desde acciones Filament estáticas. No bloquea, pero conviene mantener services como frontera estable y no dejar que la UI acumule reglas nuevas.

## 8. Tests faltantes

No bloqueantes:

- Test explícito de que `ContratoRetencionService::confirmarEliminacion()` rechaza usuario no Owner si se decide mover la autorización dentro del servicio.
- Test de doble POST concurrente real contra el mismo token usando dos conexiones/procesos. La implementación usa lock + update atómico y hay cobertura de token usado, pero no una prueba de carrera multiproceso.
- Test de regresión que asegure que ninguna migración futura de contratos agrega FK a `properties`.

## 9. Correcciones obligatorias

Ninguna para merge.

## 10. Correcciones recomendadas

1. Blindar `ContratoRetencionService::confirmarEliminacion()` con `Gate::authorize('confirmarEliminacion', $contrato)` o documentar formalmente preautorización requerida.
2. Agregar una prueba de carrera multiproceso para doble firma/rechazo con el mismo token.
3. Agregar un test de esquema permanente para `property_fk_count = 0` contra `contratos_intermediacion`.

## 11. Checklist final antes de merge

- [x] `composer validate --strict` limpio.
- [x] `composer.lock` en sync; `composer install --dry-run` sin cambios.
- [x] `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force` limpio.
- [x] `DB_DATABASE=inmo_test php artisan test` verde: 470 tests, 1719 assertions.
- [x] Índice único global de folio confirmado en PostgreSQL.
- [x] Colisión de folio forzada y reintento confirmado.
- [x] Sin FK a `properties` confirmado por SQL.
- [x] Token público de un solo uso confirmado por HTTP.
- [x] Transición inválida `firmado → enviado` rechazada.
- [x] IDOR con token inválido confirmado sin fuga de PII.
- [x] Identificación oficial: Admin `403`, Owner `200`.
- [x] Firma: IP, user-agent, timestamp y hash persistidos.
- [x] PDF final: hash persistido coincide con SHA-256 recalculado.
- [x] Verificación pública: sin PII, confirma integridad solo con PDF correcto.
- [x] Jobs de expiración y retención ejecutados; retención marca pendiente sin borrar.
- [x] Confirmación Owner purga media personal y soft-deletea expediente; PDF/hash conservados.
- [x] Notificaciones clave verificadas sin duplicado observado en suite existente.
- [x] No se tocaron migraciones previas de User/Property/Zone/Media.
