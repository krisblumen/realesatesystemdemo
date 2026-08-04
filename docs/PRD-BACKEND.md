# PRD: Backend & Panel Administrativo - New Hauz

**Proyecto:** Plataforma Inmobiliaria Administrable New Hauz  
**Base Técnica:** Laravel 13.x + Filament + PostgreSQL + PostGIS + Livewire  
**Estado:** Confirmado - Arquitectura Monolítica

---

## 1. Arquitectura del Sistema
El sistema será un **Monolito Moderno** donde Laravel gestiona tanto la API interna como la renderización de vistas interactivas.

*   **Framework:** Laravel 13.x (PHP 8.3+)
*   **Base de Datos:** PostgreSQL con extensión PostGIS (Obligatorio para geolocalización).
*   **Panel Administrativo:** Filament v3 para módulos CRUD y operación interna.
*   **Motor de Vistas:** Blade + Livewire v3 para reactividad sin salir del ecosistema Laravel.
*   **Manejo de Imágenes:** Spatie Media Library para procesamiento y optimización (WebP/AVIF).

## 2. Modelos de Datos Requeridos

### 2.1 Inmuebles (Properties)
*   **Campos:** Título, slug, descripción, precio, tipo de operación, tipo de inmueble, características (habitaciones, baños, etc.).
*   **Geolocalización:** Punto geográfico (PostGIS).
*   **Estado:** Borrador, Publicado, Pausado, Vendido.

### 2.2 Usuarios y Agentes
*   **Roles:** Owner, Admin, Agente (vía Spatie Permission).
*   **Campos Agente:** Foto, WhatsApp, Teléfono, Zona asignada.

### 2.3 Zonas y Ubicaciones
*   **Zonas:** Catálogo de zonas comerciales en Querétaro.
*   **Asignación:** Relación uno a muchos con Agentes.

## 3. Módulos Administrativos (Filament)
*   `PropertiesResource`: Gestión de inmuebles con mapa interactivo.
*   `ZoneResource`: Definición de perímetros comerciales.
*   `LeadResource`: Captura y seguimiento de prospectos.

## 4. Reglas Críticas
1.  **Seguridad:** El registro de nuevos agentes es exclusivo del rol Owner.
2.  **SEO:** Slugs generados automáticamente y metadatos persistidos en DB.
3.  **Ambiente:** El entorno local debe usar Docker para asegurar compatibilidad con PostGIS.

---
**Actualizado el:** 15 de Junio, 2026
