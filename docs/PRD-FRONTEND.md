# PRD: Frontend & Experiencia de Usuario - New Hauz

**Proyecto:** Interfaz Pública New Hauz  
**Base Técnica:** Laravel Blade + Livewire v3 + Tailwind CSS v4  
**Estado:** Confirmado - Arquitectura Monolítica

---

## 1. Visión del Producto
Un sitio web de alto rendimiento y SEO nativo que utiliza el ecosistema de Laravel para ofrecer una experiencia interactiva sin la complejidad de un frontend desacoplado.

## 2. Tecnologías y Estándares
*   **Motor de Plantillas:** Blade para el esqueleto HTML y SEO.
*   **Reactividad:** Livewire v3 para filtros dinámicos, buscadores y carga infinita.
*   **Estilos:** Tailwind CSS v4 (Mobile-first).
*   **Componentes:** Uso de componentes anónimos de Blade para consistencia visual.
*   **Mapas:** Google Maps JS SDK integrado en componentes Livewire.

## 3. Estructura de Páginas

### 3.1 Inicio (Home)
*   **Componente Livewire:** Buscador rápido con autocompletado de zonas.
*   **Secciones:** Grid de propiedades destacadas con estados de carga (skeletons).

### 3.2 Sección Inmobiliaria
*   **Filtros Dinámicos:** Livewire para filtrar por precio, tipo y zona sin recargar la página.
*   **SEO:** URLs amigables (ej: `/inmuebles/casas-en-venta-juriquilla`).

### 3.3 Detalle de Propiedad
*   **Interactividad:** Formulario de contacto Livewire con validación en tiempo real.
*   **Sticky WhatsApp:** Botón persistente con tracking de clics (Evento JS).

## 4. Requerimientos UI/UX
*   **Diseño:** Limpio, profesional, tipografía legible.
*   **Performance:** Optimización de assets vía Vite. LCP < 2.5s.
*   **Feedback:** Estados de éxito/error claros en formularios de leads.

## 5. Plan de Implementación
1.  Definición de Layout base en Blade (`layouts/app.blade.php`).
2.  Instalación y configuración de Livewire v3.
3.  Desarrollo de componentes de UI atómicos (Buttons, Cards, Inputs).
4.  Implementación del buscador de propiedades reactivo.
5.  Optimización SEO (Meta tags dinámicos).

---
**Actualizado el:** 15 de Junio, 2026
