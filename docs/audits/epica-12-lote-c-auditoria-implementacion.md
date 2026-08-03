# Auditoría de implementación — Épica 12, Lote C: Navegación, footer y CTAs

- **Proyecto:** New Hauz — CMS inmobiliario
- **Fecha:** 2026-07-22
- **Auditor:** Codex (modelo Sol), auditor independiente
- **Rama auditada:** `feature/epica-12-content-manager`
- **HEAD auditado:** `50a23f3 fix(epica-12): M-C1/Mn-C1 — footer malformado degrada sin 500`
- **Lote:** C — Navegación, footer y CTAs
- **Referencias:** `docs/epicas/epica-12-administrador-contenidos-frontend.md` §16.3, §16.10, §19.3; `docs/rfc/RFC-073-NAVEGACION-FOOTER-CTAS-FRONTEND.md`
- **Auditorías acumuladas:** Lotes A y B aprobados

## 1. Veredicto

## **APROBADO**

La corrección de M-C1 elimina el HTTP 500 ante estructuras malformadas de
footer, y Mn-C1 queda cerrado: el DTO de navegación materializa
`open_in_new_tab` y lo fuerza a `false` en v1. La verificación sobre PostgreSQL
real, la suite completa, el render HTTP, el DOM y los destinos adversariales
fueron satisfactorios.

## 2. Evidencia real

### 2.1 Verificaciones base obligatorias

| Verificación | Resultado | Evidencia |
|---|---:|---|
| Dependencias | ✅ | `composer validate --strict`: `./composer.json is valid`; `composer install --dry-run --no-interaction --prefer-dist`: lock sincronizado, sin cambios. |
| Migración | ✅ | `DB_DATABASE=inmo_test php artisan migrate:fresh --env=testing --force --seed`: ejecutó limpio contra PostgreSQL real, incluidas migraciones y seeders. |
| Tests focales | ✅ | 6 archivos del Lote C: **40 tests, 40 passed, 128 assertions**. |
| Suite acumulada | ✅ | `DB_DATABASE=inmo_test php artisan test --no-coverage`: **708 tests, 708 passed, 2.862 assertions**; `duration_ms=385803`. |
| Formato PHP | ✅ | `./vendor/bin/pint --test`: `passed`. |
| Build frontend | ✅ | `npm run build`: Vite 8 y build Filament/Tailwind 3 terminaron correctamente. El warning de Browserslist es informativo y no bloquea. |
| Higiene | ✅ | `git diff --check` limpio. `.atl/skill-registry.md` conserva un cambio previo no relacionado y no debe incluirse en el commit. |

### 2.2 Corrección M-C1 verificada en código

La corrección está en el commit `50a23f3`:

- `app/Services/Frontend/FrontendNavigationService.php:183-215` aplica
  `asList()` en cada nivel de `footer.columns`, columnas, `links` y enlaces.
- `app/Services/Frontend/FrontendNavigationService.php:242-251` convierte
  cualquier valor no-array en lista vacía, evitando iteraciones inseguras.
- `app/Services/Frontend/FrontendNavigationService.php:260-276` aplica el
  mismo principio a `social_links`.
- `tests/Feature/Frontend/FrontendFooterRenderTest.php:66-90` prueba mediante
  SQL directo cinco formas inválidas y exige HTTP 200 con footer presente.

### 2.3 Corrección Mn-C1 verificada en código y BD

- `app/Services/Frontend/FrontendNavigationService.php:113-123` devuelve el
  schema completo y fuerza `open_in_new_tab=false`.
- En PostgreSQL se persistió temporalmente `open_in_new_tab=true`. La BD lo
  conservó, pero el DTO real devolvió `"open_in_new_tab":false`.
- `tests/Feature/Frontend/FrontendNavigationServiceTest.php:113-128`
  verifica el schema y la normalización `true → false`.

### 2.4 Render HTTP/DOM real con configuración válida

Se cargó temporalmente en `inmo_test` una configuración adversarial con
navegación reordenada, un link deshabilitado, una key desconocida, una URL
persistida arbitraria en `home`, CTAs route/HTTPS y footer con link deshabilitado,
link `javascript:` y link externo HTTPS.

#### HTTP y DOM por curl

```text
GET http://127.0.0.1:8001/?audit=lotec-valid
HTTP/1.1 200 OK
GET http://127.0.0.1:8001/contacto?audit=lotec-valid
HTTP 200
```

La inspección de la respuesta observó:

```text
nav=2; footer=1
Hablemos C=2; Portada C=2; Cita C=2
Visible C=1; Externo C=1
Deshabilitado C ausente
Malicioso C ausente
wp-admin ausente
https://evil.example ausente
footer href="#" = 0
/contacto: aria-current="page" = 2
```

El footer externo real llegó como:

```html
<a href="https://example.com/c-footer"
   target="_blank" rel="noopener noreferrer">
```

#### Navegador y fuente compartida desktop/móvil

El navegador in-app confirmó:

```text
2 regiones <nav>
drawer #nh-mobile-menu: Hablemos C, Portada C, Cita C
role="dialog": 1
toggle aria-expanded="false": 1
links con aria-current="page" en la ruta activa: 2
footer configurable sin href="#": 0
href javascript:/data: en anchors: 0
```

El header desktop y el drawer móvil consumen la misma configuración de
`FrontendNavigationService`; el test focal y el DOM real confirman que el
renombrado aparece en ambas regiones. El navegador in-app no permitió cambiar
el viewport a una segunda dimensión móvil para una captura independiente; la
estructura responsive, accesibilidad del drawer y contenido compartido sí
quedaron verificados por DOM y test.

### 2.5 Destinos inseguros verificados en vivo

Se cambió temporalmente el CTA primario y un link de footer a destinos
`data:text/html,<script>...`. La respuesta pública fue HTTP 200 y mostró:

```text
data:text/html ausente
javascript: ausente
label malicioso ausente
CTA fallback «Agenda una cita» presente
```

La configuración válida también ejercitó `javascript:` y la suite cubre
`file:`, `vbscript:`, HTTP no seguro, rutas relativas y rutas internas fuera de
la allowlist. No se observó bypass de `CtaResolver`.

### 2.6 M-C1 reproducido después de la corrección

Se persistió directamente en PostgreSQL el payload que había provocado el
rechazo anterior:

```json
{
  "columns": [
    {"title": "Bad C", "links": "malformed-links"}
  ],
  "legal_text": "© Malformed C"
}
```

La petición real respondió:

```text
GET http://127.0.0.1:8001/?audit=lotec-malformed-links
HTTP/1.1 200 OK
footer presente: sí
legal_text conservado: sí
columna malformada renderizada: no
ErrorException/foreach(): ausente
```

El mismo caso había producido HTTP 500 en `FrontendNavigationService.php:177`
antes de `50a23f3`; ahora degrada sin excepción.

### 2.7 Limpieza posterior

Al finalizar se restauraron en `inmo_test` `navigation`, `footer`, CTAs y
`social_links` a `null`; se detuvo el servidor temporal y no quedaron datos
adversariales persistidos.

## 3. Hallazgos críticos

Ninguno.

No se detectaron XSS, IDOR, bypass de allowlist, navegación a destinos
arbitrarios ni una ruta pública que fallara con datos válidos o malformados del
alcance auditado.

## 4. Hallazgos medios

Ninguno abierto.

### M-C1 — Cerrado: footer malformado provocaba HTTP 500

- **Cierre:** `50a23f3`, `app/Services/Frontend/FrontendNavigationService.php:183-215,242-251`.
- **Evidencia:** payload exacto enviado por SQL directo → HTTP 200; el bloque
  inválido se descarta y el texto legal se conserva.
- **Cobertura:** `FrontendFooterRenderTest` prueba cinco formas inválidas y
  espera render público exitoso.

## 5. Hallazgos menores

Ninguno abierto.

### Mn-C1 — Cerrado: schema de `open_in_new_tab`

- **Cierre:** `50a23f3`, `FrontendSettingsPage.php` y
  `FrontendNavigationService.php:113-123`.
- **Evidencia:** valor `true` persistido en PostgreSQL → DTO real con
  `open_in_new_tab=false`.
- **Cobertura:** `FrontendNavigationServiceTest` exige el schema completo y la
  normalización `true → false`.

## 6. Regresiones

- No se detectaron regresiones en Lotes A/B: migración, suite acumulada, Pint,
  build y render público quedaron verdes.
- Fallbacks de navegación, CTA primario y footer siguen disponibles sin
  configuración, cubiertos por tests focales.
- No se modificaron migraciones ni modelos existentes de User, Property,
  Project, Zone, ServiceType o Media.
- El diff del Lote C no contiene cambios en esos archivos protegidos.

## 7. Riesgos de seguridad

- **XSS/URL injection:** `javascript:`, `data:`, `file:`, `vbscript:`, HTTP no
  seguro y targets fuera de allowlist no llegaron al HTML.
- **Links externos:** el link HTTPS observado usa `target="_blank"` y
  `rel="noopener noreferrer"`.
- **Disponibilidad:** el riesgo M-C1 quedó cerrado con guards anidados en el
  boundary de render; una estructura inválida ahora se descarta sin excepción.
- **HTML libre:** no se observó HTML configurable en navegación, footer o CTA.

## 8. Riesgos de mantenimiento

1. Todo nuevo consumidor de destinos debe continuar usando `CtaResolver`; no
   debe resolver URLs directamente desde JSON.
2. Si se amplía el schema de navegación, debe conservarse la normalización del
   boundary y actualizar el test de schema exacto.
3. La captura visual móvil con viewport real debe incorporarse cuando el
   entorno de navegador permita redimensionamiento automatizado. Esto no deja
   un bloqueo funcional: DOM, accesibilidad y tests están cubiertos.

## 9. Tests faltantes

- No quedan tests obligatorios faltantes para el gate del Lote C.
- Recomendado: captura automatizada con viewport móvil real cuando la
  infraestructura lo soporte.
- Recomendado: mantener casos adicionales de JSON legacy en cualquier futura
  importación de configuración.

## 10. Correcciones obligatorias

Ninguna. M-C1 y Mn-C1 están cerrados y verificados en vivo.

## 11. Correcciones recomendadas

1. Mantener `asList()` como boundary único para todos los niveles de payload
   persistido.
2. Mantener el corte de los `href="#"` hardcodeados legacy para el Lote F,
   sin reabrir ni mezclar el alcance de C.
3. Añadir una captura responsive automatizada cuando el navegador de QA admita
   viewport móvil controlable.

## 12. Decisión explícita del gate

La corrección obligatoria fue implementada, probada con SQL directo y
confirmada mediante HTTP real. La navegación, footer, CTAs, allowlist,
fallbacks y drawer móvil no presentan correcciones obligatorias abiertas.

> **GATE LOTE C: APROBADO**

El Lote D queda habilitado.
