# ADR-004 - Estrategia Frontend con Blade + Livewire

## Estado
Aprobado

## Contexto
El sitio público requiere SEO, rendimiento, filtros dinámicos y formularios sin desacoplar el frontend.

## Decisión
Usar Blade para renderizado principal y Livewire v3 para interactividad.

## Justificación
Blade + Livewire permite:

- SEO nativo.
- Menos JavaScript manual.
- Formularios reactivos.
- Buscadores dinámicos.
- Filtros sin recarga completa.
- Integración directa con Eloquent.
- Un solo stack técnico.

## Componentes Prioritarios
- Buscador inmobiliario.
- Filtros de inmuebles.
- Formularios de leads.
- Botón WhatsApp con tracking.
- Cards de propiedades.
- Listados dinámicos.

## Reglas
- No introducir frameworks frontend desacoplados.
- Mantener componentes pequeños.
- Usar Livewire solo donde agregue valor.
- Blade debe manejar estructura y SEO.

## Consecuencias
La experiencia pública será rápida, controlada y fácil de mantener.
