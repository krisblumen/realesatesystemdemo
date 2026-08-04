# Épica 12 — Revisión integral pre-diseño

**Proyecto:** New Hauz — Plataforma Inmobiliaria
**Fecha:** 2026-07-20
**Revisor:** Codex (modelo Sol)
**Alcance:** documento general, RFC-071→077, prompt multimodelo y contratos reales consumidos
**Veredicto:** 🔴 **BLOQUEADA PARA DISEÑO TÉCNICO hasta cerrar los bloqueantes B-1→B-6**

---

## 1. Resumen ejecutivo

La división funcional de la Épica 12 es viable y conserva correctamente la restricción owner-only, el enfoque aditivo y la exclusión de un page builder libre. Sin embargo, el diseño técnico no debe comenzar dando por resueltos contratos que aún están abiertos: publicación, singleton, autoridad sobre servicios, backfill productivo, fallbacks y orden de la capa de render/caché.

La implementación también queda sujeta a auditoría bloqueante por lote. No se permite implementar A→G y auditar al final.

## 2. Evidencia verificada en código real

| Evidencia | Confirmación |
| --- | --- |
| `app/Filament/Resources/ServiceTypeResource.php:100-112` | `owner` y `admin` pueden consultar y editar `ServiceType`. |
| `app/Livewire/Leads/LeadCaptureForm.php:102-120` | `ServiceType.active` controla la aceptación server-side del servicio en leads. |
| `database/migrations/2026_06_29_190245_add_service_type_fk_to_leads_table.php:15-28` | El repositorio confirma que `migrate` no ejecuta seeders y resuelve catálogos requeridos mediante migración idempotente. |
| `vite.config.js:14-21` | El build sólo registra Montserrat e Inter; Poppins no está disponible actualmente. |
| `phpunit.xml:26`, `config/cache.php:18` | Tests usan caché `array`; producción usa `database` por defecto. |
| `config/media-library.php:36` | Media Library usa el disco `public` por defecto; un draft con media requiere aislamiento explícito. |
| `routes/web.php` y vistas públicas Blade | El contenido actual es mixto: datos reales más contenido institucional hardcodeado. |

## 3. Bloqueantes antes del diseño

| ID | Hallazgo | Impacto | Corrección que P1 debe cerrar |
| --- | --- | --- | --- |
| B-1 | El flujo original concentraba la auditoría después de A→G. | Los defectos se acumulan y contaminan lotes dependientes. | Mantener siete ciclos implementación→auditoría→corrección→reauditoría, más auditoría integral final. |
| B-2 | RFC-077 no cierra qué entidades usan borrador/publicado ni cómo se publica. | Afecta esquemas, media, caché, preview y consistencia de RFC-071→075. | Definir estrategia por entidad, transacción, concurrencia y snapshot/estado publicado antes de fijar tablas. |
| B-3 | `FrontendSetting` se propone como singleton sin garantía de BD. | Dos procesos podrían crear configuraciones activas distintas. | Definir clave única estable, acceso idempotente, prohibición de delete/forceDelete y test de concurrencia. |
| B-4 | `ServiceType.active` es operativo y hoy también lo controla `admin`. | Choca con el área editorial owner-only y puede cambiar la recepción de leads. | Separar y documentar autoridad operativa vs. editorial; no alterar silenciosamente los permisos previos. |
| B-5 | “Inversión inmobiliaria” sólo aparece como decisión de implementación/seeder. | Producción no recibe seeders durante `migrate`; puede persistir el drift. | Cerrar código, estado inicial, `allow_leads` y backfill productivo idempotente. |
| B-6 | El kernel de render/fallback/caché aparece hasta el lote F. | A→E quedarían acoplados a contratos temporales que luego habría que rehacer. | Introducir la interfaz mínima de lectura, publicación, fallback e invalidación desde A; reservar F para integración/endurecimiento. |

## 4. Hallazgos altos

1. **Fuente duplicada de CTAs:** RFC-071 y RFC-073 reparten campos equivalentes. RFC-073 debe ser la única autoridad de navegación/footer/CTAs.
2. **Fallback ambiguo:** falta distinguir “sin inicializar” de “deshabilitado deliberadamente”; un fallback no debe revivir contenido apagado.
3. **Media incompleta:** deben cerrarse MIME, tamaño, dimensiones, SVG, `alt_text`, colecciones y aislamiento de media de borrador.
4. **Schemas incompletos en RFC-075:** la allowlist no contiene todos los tipos usados por las páginas propuestas.
5. **Restricciones faltantes:** deben declararse uniques de páginas/secciones, orden y reglas de eliminación/`forceDelete`.

## 5. Hallazgos medios

1. Poppins figura como opción, pero no está en el build; debe eliminarse o incorporarse explícitamente.
2. SEO necesita precedencia, canonical, sitemap, JSON-LD e indexación de páginas deshabilitadas.
3. La invalidación debe probarse con `CACHE_STORE=database`, no sólo con el store `array` de PHPUnit.
4. Navegación móvil requiere criterios verificables de teclado, foco, Escape, `aria-expanded` y reducción de movimiento.
5. Falta una estrategia de backfill/cutover que preserve el frontend actual antes de retirar el hardcode Blade.

## 6. Cambios requeridos por artefacto

| Artefacto | Cierre esperado durante P1/P3 |
| --- | --- |
| Épica general | Decisiones transversales, orden A→G y seguimiento de gates. |
| RFC-071 | Singleton real, policy owner-only, no delete, contrato media y eliminación de CTAs duplicados. |
| RFC-072 | Allowlist de fuentes real, mapping seguro CSS, publicación y matriz de contraste. |
| RFC-073 | Fuente única de CTAs, rutas por nombre allowlisted, privacidad y accesibilidad móvil. |
| RFC-074 | Autoridad owner/admin, inversión, backfill y regla única de elegibilidad render/lead. |
| RFC-075 | Allowlist/schemas completos, uniques, alt text, SEO y migración desde hardcode. |
| RFC-076 | Kernel desde A, invalidación `afterCommit`, keys versionadas y prueba con caché database. |
| RFC-077 | Estrategia de publicación por entidad, transacción, concurrencia, media draft y auditoría. |
| Prompt multimodelo | Auditoría y gate obligatorios después de cada lote. |

## 7. Gate para continuar

P1 puede comenzar únicamente para **cerrar documentalmente** B-2→B-6 y los hallazgos asociados. La implementación permanece bloqueada hasta completar:

```text
P1 diseño → P2 auditoría → P3 corrección → P3R reauditoría
→ GATE DE DISEÑO: APROBADO
```

Después, cada lote sigue su propio ciclo. Sólo `APROBADO` habilita el siguiente; `APROBADO CON OBSERVACIONES`, `CORRECCIONES REQUERIDAS` o `RECHAZADO` mantienen el gate cerrado.

## 8. Decisiones abiertas obligatorias

- Estrategia draft/publicado por entidad.
- Autoridad de `admin` sobre `ServiceType.active` frente al control editorial owner-only.
- Estado inicial y aceptación de leads para inversión inmobiliaria.
- Garantía física del singleton.
- Semántica fallback vs. desactivación intencional.
- Política de SVG y media draft.
- Fuente única de CTAs.
- Alcance del aviso de privacidad.
- Retirar o cargar Poppins.
- Backfill y cutover del contenido hardcodeado.

## 9. Conclusión

No falta un RFC funcional adicional. Lo que falta es cerrar los contratos transversales anteriores dentro del diseño técnico consolidado. Con esos cierres y el esquema de auditorías por fase, la Épica 12 puede avanzar sin construir sobre supuestos ambiguos.
