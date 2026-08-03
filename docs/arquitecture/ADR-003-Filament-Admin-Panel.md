# ADR-003 - Filament como Panel Administrativo

## Estado
Aprobado

## Contexto
El proyecto requiere una administración rápida, segura y extensible para usuarios, agentes, inmuebles, zonas, leads y reportes.

## Decisión
Usar Filament v3 como panel administrativo.

## Justificación
Filament permite:

- CRUDs acelerados.
- Formularios robustos.
- Tablas filtrables.
- Dashboards.
- Widgets.
- Integración nativa con Laravel.
- Buen soporte para roles y políticas.

## Módulos Iniciales
- Usuarios
- Agentes
- Zonas
- Inmuebles
- Leads
- Proyectos
- Dashboard comercial

## Reglas
- Todo recurso administrativo debe vivir en Filament.
- Las acciones críticas deben protegerse con políticas.
- Owner conserva permisos máximos.
- Agente solo accede a sus propios datos.

## Consecuencias
El diseño administrativo prioriza velocidad, seguridad y trazabilidad.
