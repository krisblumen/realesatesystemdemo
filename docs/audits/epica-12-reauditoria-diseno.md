# Reauditoría de diseño — Épica 12: Administrador de Contenidos del Frontend

| Campo | Valor |
| --- | --- |
| Proyecto | New Hauz |
| Fecha | 2026-07-21 |
| Auditor | Codex (Sol), independiente |
| Rama | `feature/epica-12-content-manager` |
| Commit auditado | `44f8ec9` |
| Entradas | Épica 12, RFC-071→077, auditoría original, reconciliación §18 y Engram |

## 1. Veredicto

**APROBADO.** El único hallazgo pendiente, C-1, quedó cerrado: los inventarios activos de RFC-075 y RFC-077 ahora prescriben validación de media sin lock/recheck, y la regla residual de purga está marcada localmente como histórica. Los contratos activos de §16 y RFC-071→077 son implementables y coherentes.

**Balance final: 17 de 17 hallazgos resueltos. P4-A queda habilitado.**

## 2. Engram y evidencia verificada

Se consultaron `mem_context`, `mem_search` y la observación completa **#824**, que conserva el único bloqueante de la ronda anterior. La corrección se verificó directamente contra HEAD.

Evidencia inspeccionada:

- `git rev-parse --short HEAD` → `44f8ec9`; diff `46cbb65..44f8ec9`.
- RFC-075 `:424-440`; RFC-077 `:297-312,354-362`; Épica §18.16 `:1227-1252`.
- Búsqueda positiva de términos históricos y búsqueda filtrada de instrucciones activas.
- Vendor Filament `SpatieMediaLibraryFileUpload.php:125-128,247-256` y Spatie `FileAdder.php:645-651`, `MediaCollection.php:90-106`.
- Revisión integral de los 17 hallazgos contra §16 y RFC-071→077.

El cambio previo y ajeno en `.atl/skill-registry.md` no fue modificado.

## 3. Matriz hallazgo → evidencia → estado

| Hallazgo | Evidencia vigente | Estado |
| --- | --- | --- |
| **C-1** Fuente normativa | RFC-075 `:429-431` y RFC-077 `:303` ahora exigen validación sin lock/recheck; RFC-077 `:361` está marcado histórico. | **RESUELTO** |
| **C-2** Snapshot concurrente | Snapshot completo, revisión esperada y locks deterministas de entidad: épica `:380-395,702-720`. | **RESUELTO** |
| **C-3** Privacidad/ciclo de vida media | Draft privado, promoción recuperable y rutas destructivas neutralizadas: épica `:539-575`; comportamiento del vendor confirmado. | **RESUELTO** |
| **C-4** Singleton físico | `CHECK(singleton_key='default') + UNIQUE`: épica `:333-336`. | **RESUELTO** |
| **C-5** Owner-only desplegable | Rol + permiso, migración productiva, policies y 403: épica `:514-522`. | **RESUELTO** |
| **M-1** Registry incompleto | Registry completo y fuentes dinámicas separadas: épica `:402-470`. | **RESUELTO** |
| **M-2** Servicios fail-open | Elegibilidad fail-closed y validación server-side: épica `:629-649`. | **RESUELTO** |
| **M-3** Caché stale | Generación durable, bump atómico y protección contra refill stale: épica `:682-689`. | **RESUELTO** |
| **M-4** Tema/CSS divergente | Schema único, contraste y normalización defensiva: épica `:577-625`. | **RESUELTO** |
| **M-5** Backfill destructivo | `SeedInversionService` insert-if-missing y prueba repetible: épica `:651-656`. | **RESUELTO** |
| **M-6** Footer/CTA/SEO | CTA/footer tipados y SEO definido: épica `:724-728`. | **RESUELTO** |
| **M-7** Cobertura insuficiente | Matriz nominal completa, incluidas rutas reales de retención, reporte y scheduler: épica `:750-843`. | **RESUELTO** |
| **Mn-1** Copy CTA | Fallback “Agenda una cita”: épica `:678`. | **RESUELTO** |
| **Mn-2** FK/UNIQUE | `string(30)` e índices parciales sin UNIQUE global: épica `:370-376,474-500`. | **RESUELTO** |
| **Mn-3** `is_active` duplicado | Eliminado: épica `:365`; RFC-071 `:133-135`. | **RESUELTO** |
| **Mn-4** SVG | Prohibido en v1: épica `:541-544`. | **RESUELTO** |
| **N-1** Instalación productiva | Cinco páginas creadas por migración/acción no destructiva: épica `:503-512,792-793`. | **RESUELTO** |

## 4. Cierre del bloqueante P16

| Evidencia anterior | Corrección verificada | Estado |
| --- | --- | --- |
| RFC-075 inventariaba publisher y referencia media con locks/recheck | `:429-431` ahora indica página→secciones y validación de existencia/owner/colección, explícitamente sin lock ni recheck. | **CERRADO** |
| RFC-077 inventariaba `FrontendMediaReferenceService` con lock/recheck | `:303` usa el contrato de validación sin lock/recheck. | **CERRADO** |
| RFC-077 mantenía una regla de purga sin marca local | `:361` lleva `HISTÓRICO: fuera de alcance v1, §18.13`. | **CERRADO** |

La búsqueda filtrada documentada en §18.16 devuelve cinco resultados y ninguno es una instrucción destructiva activa: dos explican riesgos de rollback/índices huérfanos y tres delimitan expresamente el alcance excluido.

## 5. Observación no bloqueante

El control numérico de §18.16 quedó desactualizado por la propia sección autorreferencial: en el documento final se reproducen **44/14/23/8/1** coincidencias para épica/RFC-075/077/074/072, no **35/12/21/8/1**.

Esto es una imprecisión editorial, no una contradicción de implementación: el control continúa siendo positivo y la búsqueda filtrada reproduce los cinco resultados benignos declarados. Se recomienda sustituir los conteos fijos por “resultado mayor que cero” o actualizar las cifras, sin reabrir el gate.

## 6. Decisión explícita del gate

Los contratos activos son coherentes, las referencias históricas están delimitadas y no quedan bloqueantes técnicos ni documentales para iniciar P4-A.

> **GATE DE DISEÑO: APROBADO**
