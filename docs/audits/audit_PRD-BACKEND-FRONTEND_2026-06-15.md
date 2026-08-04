# Audit Report: PRD-BACKEND.md & PRD-FRONTEND.md

**Fecha:** 15 de Junio, 2026  
**Auditor:** Claude Code (Sonnet 4.6)  
**Estado:** Requiere Decisiones Arquitectónicas Urgentes  
**Archivos auditados:** `docs/PRD-BACKEND.md`, `docs/PRD-FRONTEND.md`  
**Referencia previa:** `docs/audits/audit_PRD-NEWHAUZ_2026-06-15.md`

---

## 1. Resumen Ejecutivo

Ambos PRDs especializados (Backend y Frontend) fueron generados como respuesta a la brecha crítica detectada en la auditoría anterior del PRD general. Sin embargo, los documentos **amplifican** el conflicto en lugar de resolverlo: definen un stack técnico (PostgreSQL/PostGIS + Filament + Sanctum en backend; Next.js + React en frontend) que es **completamente incompatible con el repositorio actual**, el cual es un monolito Laravel 13 con SQLite, Blade y Vanilla JS.

El repositorio no tiene instalado ningún paquete de los especificados en los PRDs. La brecha entre documentación e implementación es total.

**Veredicto: los PRDs son correctos como visión de producto, pero ninguno puede ejecutarse sobre el código actual sin una migración de infraestructura primero.**

---

## 2. Estado Real del Repositorio (Baseline)

Evidencia extraída de `composer.json`, `phpunit.xml`, estructura de archivos y `.env`:

| Capa | Estado Actual |
| :--- | :--- |
| Framework | Laravel 13.x, PHP 8.3+ ✅ |
| Base de datos | SQLite (`database/database.sqlite`) |
| ORM / migraciones | Solo 3 migraciones default: `users`, `cache`, `jobs` |
| Modelos de dominio | Solo `User.php` (skeleton) |
| Frontend | Blade + Vite 8 + Vanilla JS (sin React/Vue) |
| Autenticación | Standard Laravel (sin Sanctum) |
| Panel admin | Ninguno (sin Filament) |
| Roles | Ninguno (sin Spatie Permission) |
| API | Sin `routes/api.php`, sin controladores API |
| Tests | PHPUnit 12, in-memory SQLite, 2 tests placeholder |
| Packages extra | `laravel/tinker` únicamente |

---

## 3. Auditoría: PRD-BACKEND.md

### 3.1 Tabla de Alineación

| Requisito PRD | Estado Actual | Severidad |
| :--- | :--- | :--- |
| PostgreSQL + PostGIS | SQLite, sin driver PG en composer.json | 🔴 CRÍTICO |
| Laravel Sanctum | No instalado | 🔴 CRÍTICO |
| Filament v3 | No instalado | 🔴 CRÍTICO |
| Spatie/Permission (roles) | No instalado | 🔴 CRÍTICO |
| Modelos: Property, Zone, Lead | No existen | 🔴 CRÍTICO |
| Migraciones de dominio | No existen | 🔴 CRÍTICO |
| `routes/api.php` + 5 endpoints | No existe | 🔴 CRÍTICO |
| Slug automático + metadatos SEO | Sin implementar | 🟡 ALTO |
| Campo foto/WhatsApp en User | No está en migración users | 🟡 ALTO |
| `.env.example` refleja PG/Sanctum | No actualizado | 🟠 MEDIO |

### 3.2 Hallazgos Detallados

#### [BACKEND-01] 🔴 CRÍTICO — Base de Datos: SQLite vs PostgreSQL/PostGIS

**PRD especifica:** PostgreSQL con extensión PostGIS para geolocalización avanzada (Zonas, coordenadas de propiedades).  
**Realidad:** El proyecto usa SQLite. El script `post-create-project-cmd` en `composer.json` hace `touch('database/database.sqlite')` explícitamente. `phpunit.xml` fuerza `:memory:` SQLite para todos los tests.  
**Impacto:** PostGIS no existe en SQLite. Toda la funcionalidad de geolocalización (búsqueda por zona, mapa, coordenadas de inmuebles) está estructuralmente bloqueada.  
**Acción requerida:**  
1. Instalar el driver `pdo_pgsql` en PHP.
2. Actualizar `config/database.php` y `.env.example` para PostgreSQL.
3. Revisar estrategia de tests: SQLite in-memory no puede testear queries PostGIS. Usar Docker + PostgreSQL para el entorno de tests, o definir dos perfiles de test.

#### [BACKEND-02] 🔴 CRÍTICO — Filament v3 No Instalado

**PRD especifica:** Panel administrativo completo con 5 recursos: `PropertiesResource`, `ZoneResource`, `LeadResource`, `UserResource`, Dashboard con widgets.  
**Realidad:** `composer.json` no incluye `filament/filament`. No hay directorio `/app/Filament/`. Sin Filament no existe ninguna ruta `/admin`.  
**Acción requerida:** `composer require filament/filament:"^3.0"` + configuración de panel + publicación de assets.

#### [BACKEND-03] 🔴 CRÍTICO — Sanctum No Instalado

**PRD especifica:** Sanctum para autenticación de la API consumida desde Next.js.  
**Realidad:** Solo `laravel/framework` y `laravel/tinker` en producción. Sin Sanctum no hay tokens de API, sin tokens no hay seguridad en ninguno de los 5 endpoints definidos.  
**Acción requerida:** `composer require laravel/sanctum` + `php artisan sanctum:install`.

#### [BACKEND-04] 🔴 CRÍTICO — Spatie/Permission No Instalado

**PRD especifica:** Roles: Owner, Admin, Agente. Reglas de negocio de visibilidad dependientes de roles (Sección 5.2).  
**Realidad:** Sin `spatie/laravel-permission` es imposible implementar la lógica de "agente solo edita sus inmuebles" vs "Admin/Owner edita todos".  
**Acción requerida:** `composer require spatie/laravel-permission` + migración de roles + seeder inicial.

#### [BACKEND-05] 🔴 CRÍTICO — Sin Modelos ni Migraciones de Dominio

**PRD especifica:** Modelos `Property` (con ~15 campos), `Zone`, `Lead`, extensión de `User` (foto, WhatsApp, zona).  
**Realidad:** Solo `User.php` con las columnas default de Laravel (id, name, email, password, timestamps). Sin migraciones para las tablas del dominio.  
**Acción requerida:** Crear migraciones y modelos en el orden correcto (respetando foreign keys):
1. `zones` → 2. `users` (extender) → 3. `properties` → 4. `leads`

#### [BACKEND-06] 🔴 CRÍTICO — Sin Endpoints API

**PRD especifica 5 endpoints:**
- `GET /api/properties` (con filtros)
- `GET /api/properties/{slug}`
- `GET /api/zones`
- `POST /api/leads`
- `GET /api/home-featured`

**Realidad:** No existe `routes/api.php`. Solo `routes/web.php` con la ruta de bienvenida. No hay ningún `ApiController`.  
**Acción requerida:** Crear `routes/api.php`, controladores bajo `app/Http/Controllers/Api/`, Resources de Eloquent para serialización JSON.

#### [BACKEND-07] 🟡 ALTO — Migración `users` Incompleta

**PRD especifica (Sección 2.2):** campos adicionales para agentes: foto, WhatsApp, teléfono, zona asignada.  
**Realidad:** La migración `0001_01_01_000000_create_users_table.php` solo tiene: id, name, email, password, remember_token, timestamps. Faltan todos los campos del agente.  
**Acción requerida:** Nueva migración `add_agent_fields_to_users_table` con los campos faltantes.

#### [BACKEND-08] 🟠 MEDIO — `.env.example` No Refleja el Stack Real

**Realidad:** `.env.example` usa `DB_CONNECTION=sqlite`. No hay variables para PostgreSQL (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), ni para Sanctum, ni para S3/R2.  
**Acción requerida:** Actualizar `.env.example` para reflejar el stack de producción.

#### [BACKEND-09] 🟠 MEDIO — Regla SEO de Slug Automático Sin Implementar

**PRD especifica (Sección 5.3):** El backend debe generar automáticamente el slug único y los metadatos base a partir del título y zona.  
**Realidad:** No hay modelo `Property`, no hay Observer ni Trait de slug. No hay validación de unicidad de slug.  
**Acción requerida:** Implementar via `Str::slug()` + Observer en `PropertyObserver` al crear/actualizar, con validación de unicidad incrementando sufijo numérico.

---

## 4. Auditoría: PRD-FRONTEND.md

### 4.1 Tabla de Alineación

| Requisito PRD | Estado Actual | Severidad |
| :--- | :--- | :--- |
| Next.js App Router | Blade + Vanilla JS (arquitecturas incompatibles) | 🔴 CRÍTICO |
| React Server Components | No aplica (sin React) | 🔴 CRÍTICO |
| Componentes UI base (Nav, Footer, PropertyCard) | Solo `welcome.blade.php` | 🔴 CRÍTICO |
| Rutas: /properties, /vender, /preventas, etc. | Solo ruta `/` | 🔴 CRÍTICO |
| Google Maps API | Sin integración, sin API key | 🟡 ALTO |
| JSON-LD Schema.org | Sin implementar | 🟡 ALTO |
| Next.js Metadata API (SEO) | No aplica si se usa Blade | 🟡 ALTO |
| WhatsApp click tracking | Sin implementar | 🟠 MEDIO |
| Lighthouse > 90 en móviles | No medible (sin contenido) | 🟠 MEDIO |
| Paleta de colores definida | Sin tokens de diseño en Tailwind config | 🟠 MEDIO |

### 4.2 Hallazgos Detallados

#### [FRONTEND-01] 🔴 CRÍTICO — Conflicto Arquitectónico Fundamental: Next.js vs Blade

**PRD especifica:** Next.js con App Router, React Server Components, SSR.  
**Realidad:** El repositorio es un monolito Laravel con Blade templates y Vanilla JS. Vite 8 sirve assets estáticos (CSS/JS) para Blade.

Este es el hallazgo más crítico del proyecto. Son **dos enfoques arquitectónicos irreconciliables en el mismo repositorio**:

| Aspecto | Blade (actual) | Next.js (PRD) |
| :--- | :--- | :--- |
| Rendering | Server-side PHP (Blade) | SSR/SSG Node.js |
| Interactividad | Vanilla JS | React Components |
| Repositorio | 1 repo (monolito) | 2 repos o monorepo |
| Deploy | PHP + Nginx/Apache | Node.js + Vercel/Railway |
| API | Opcional | Obligatoria (Sanctum) |
| SEO | Blade nativo | Metadata API de Next.js |

**Opciones con tradeoffs:**

**Opción A — Headless (PRD como está):** Laravel API-only (sin Blade) + Next.js en repo separado.
- ✅ Máximo rendimiento frontend, SSG/ISR para SEO, ecosystem React.
- ❌ Dos repositorios, dos deploys, mayor complejidad DevOps, tiempo de setup más largo.

**Opción B — Monolito con Inertia.js:** Laravel + Inertia.js + React (mismo repo).
- ✅ Un solo repo, un deploy, auth de Laravel nativa, sin duplicar lógica de rutas.
- ❌ Menos flexibilidad de hosting para el frontend, SSR limitado.

**Opción C — Mantener Blade (cambiar el PRD):** Laravel + Blade + Livewire para reactividad.
- ✅ Menor complejidad, un ecosistema, testing unificado.
- ❌ Livewire no es React, el PRD-FRONTEND quedaría desactualizado, pérdida de Metadata API.

**DECISIÓN REQUERIDA antes de cualquier implementación.** Esta elección bloquea todo lo demás.

#### [FRONTEND-02] 🔴 CRÍTICO — Sin Estructura de Páginas ni Componentes

**PRD especifica (Sección 3):** 4 secciones principales con al menos 8 páginas/rutas definidas.  
**Realidad:** Solo existe `resources/views/welcome.blade.php` (la página de bienvenida de Laravel). `routes/web.php` tiene una única ruta.  
**Acción requerida (post-decisión arquitectónica):** Crear la estructura de componentes y rutas según la opción elegida.

#### [FRONTEND-03] 🟡 ALTO — Google Maps API Sin Configurar

**PRD especifica (Sección 3.3):** Mostrar servicios cercanos o ubicación aproximada en la ficha de propiedad.  
**Realidad:** Sin `GOOGLE_MAPS_API_KEY` en `.env.example`, sin package JS de Maps (`@vis.gl/react-google-maps` si Next.js, o JS vanilla de Maps), sin componente de mapa.  
**Nota:** Google Maps requiere facturación activa y dominio autorizado. Debe planificarse en el budget del proyecto.

#### [FRONTEND-04] 🟡 ALTO — SEO Estructurado Sin Estrategia Definida

**PRD especifica (Sección 2, 5):** Metadata API de Next.js, JSON-LD Schema.org para inmuebles.  
**Observación crítica:** La `Metadata API` es **exclusiva de Next.js App Router**. Si la decisión arquitectónica es Blade, el SEO debe implementarse de forma diferente:
- JSON-LD via `@stack('scripts')` en Blade.
- Open Graph via meta tags en layout base.
- Sin equivalente de `generateMetadata()` en Blade.

El PRD confunde una herramienta específica de framework (Metadata API) con un requisito funcional (SEO). El requisito funcional es válido; la herramienta depende de la decisión arquitectónica.

#### [FRONTEND-05] 🟠 MEDIO — Sin Tokens de Diseño en Tailwind

**PRD especifica (Sección 4):** Paleta corporativa (negros, blancos, grises, acentos corporativos).  
**Realidad:** `resources/css/app.css` solo importa Tailwind sin customización. No hay `tailwind.config.js` con colores de la marca `new-hauz-*`.  
**Acción requerida:** Definir paleta en `tailwind.config.js` (o en CSS custom properties con `@theme` si Tailwind v4) antes de desarrollar cualquier componente.

#### [FRONTEND-06] 🟠 MEDIO — WhatsApp Tracking Sin Definición Técnica

**PRD especifica (Sección 5.1):** Tracking de clics de WhatsApp para medir efectividad por agente.  
**Observación:** El PRD no especifica el mecanismo técnico. Opciones posibles:
- Google Analytics 4 (GA4) con eventos custom `whatsapp_click`.
- Endpoint propio `POST /api/leads` que registre la acción antes de redirigir.
- Meta Pixel si se usa publicidad en Facebook/Instagram.

**Acción requerida:** Decidir el mecanismo de tracking y añadirlo como requisito técnico explícito en el PRD.

---

## 5. Inconsistencias Entre PRD-BACKEND y PRD-FRONTEND

| # | Inconsistencia | Impacto |
| :--- | :--- | :--- |
| 1 | **Auth dual no resuelta:** PRD-BACKEND define Sanctum para la API, pero Filament tiene su propia autenticación independiente. ¿El panel usa Sanctum o la auth nativa de Filament? | 🔴 Alto |
| 2 | **Tests PostGIS bloqueados:** `phpunit.xml` fuerza SQLite in-memory. Los tests de geolocalización nunca pasarán en ese entorno. | 🔴 Alto |
| 3 | **Repositorio único vs headless:** PRD-BACKEND implica Laravel como API-only (Sanctum, sin Blade). PRD-FRONTEND implica Next.js separado. El repositorio actual es monolito con Blade. Nadie resolvió la estructura de repos. | 🔴 Alto |
| 4 | **CLAUDE.md / GEMINI.md obsoletos:** Ambos archivos de contexto describen el stack actual (SQLite, Vanilla JS) que ya no corresponde al stack deseado. Agentes de IA futuros recibirán instrucciones incorrectas. | 🟡 Medio |
| 5 | **Plan de implementación no coordinado:** El plan del PRD-BACKEND (Sección 6) y el del PRD-FRONTEND (Sección 6) no tienen dependencias cruzadas definidas. ¿Cuál se ejecuta primero? | 🟠 Bajo |

---

## 6. Mapa de Deuda Técnica Acumulada

```
DEUDA TOTAL: ALTA
┌──────────────────────────────────────────────────────────────┐
│ Infraestructura                                         P0   │
│  ├── Decisión: headless vs monolito (BLOQUEANTE TODO)        │
│  ├── Migrar DB: SQLite → PostgreSQL + PostGIS                │
│  └── Actualizar phpunit.xml para tests con Postgres          │
│                                                              │
│ Backend (dependiente de Infraestructura)                P0   │
│  ├── composer require filament/filament:"^3"                 │
│  ├── composer require laravel/sanctum                        │
│  ├── composer require spatie/laravel-permission              │
│  ├── Migraciones: zones, properties, leads                   │
│  ├── Modelos: Property, Zone, Lead (con relaciones)          │
│  ├── routes/api.php + 5 endpoints con controladores          │
│  └── PropertyObserver para slug automático                   │
│                                                              │
│ Frontend (dependiente de decisión arquitectónica)       P1   │
│  ├── Si Next.js: crear repositorio separado                  │
│  ├── Si Blade: crear estructura de vistas y componentes      │
│  ├── Tokens de diseño en Tailwind (colores marca)            │
│  ├── Componentes base: Nav, Footer, PropertyCard             │
│  ├── Rutas de dominio: /properties, /vender, /preventas      │
│  └── Google Maps API key + componente de mapa                │
│                                                              │
│ Transversal                                             P2   │
│  ├── Actualizar CLAUDE.md y GEMINI.md con stack final        │
│  ├── Actualizar .env.example con todas las variables         │
│  ├── Definir mecanismo de WhatsApp tracking                  │
│  └── Estrategia de SEO según framework elegido              │
└──────────────────────────────────────────────────────────────┘
```

---

## 7. Recomendaciones Priorizadas

### P0 — Decisiones Bloqueantes (antes de escribir una línea de código)

1. **[P0-1] Resolver la arquitectura:** Elegir entre Headless (Laravel API + Next.js), Inertia.js, o Blade puro. Sin esta decisión ningún otro trabajo es válido.
2. **[P0-2] Migrar a PostgreSQL:** Instalar Docker con `postgres:16-alpine` + PostGIS para desarrollo local. Actualizar `config/database.php`, `.env.example` y crear un `docker-compose.yml` de desarrollo.
3. **[P0-3] Instalar stack base:** Una vez tomadas las decisiones anteriores, ejecutar en orden: Sanctum → Spatie Permission → Filament → configurar panel.

### P1 — Implementación de Dominio

4. **[P1-1] Crear migraciones en orden** respetando dependencias de foreign keys: zones → users (extend) → properties → leads.
5. **[P1-2] Crear modelos Eloquent** con relaciones, scopes y el observer de slug para `Property`.
6. **[P1-3] Exponer `routes/api.php`** con los 5 endpoints. Proteger con `auth:sanctum` donde corresponda.

### P2 — Frontend y Calidad

7. **[P2-1] Definir tokens de diseño** en Tailwind antes de cualquier componente.
8. **[P2-2] Actualizar CLAUDE.md y GEMINI.md** con el stack final una vez que [P0-1] esté resuelto.
9. **[P2-3] Definir estrategia de tracking** para WhatsApp y añadirla al PRD-FRONTEND como requisito técnico concreto.

---

## 8. Resumen de Severidades

| Severidad | Backend | Frontend | Total |
| :--- | :---: | :---: | :---: |
| 🔴 CRÍTICO | 6 | 2 | **8** |
| 🟡 ALTO | 1 | 2 | **3** |
| 🟠 MEDIO | 2 | 2 | **4** |
| ⚪ BAJO | 0 | 0 | **0** |
| **Total** | **9** | **6** | **15** |

---

**Fin del Audit**  
Generado el: 15 de Junio, 2026  
Próxima auditoría recomendada: después de resolver [P0-1] (decisión arquitectónica).
