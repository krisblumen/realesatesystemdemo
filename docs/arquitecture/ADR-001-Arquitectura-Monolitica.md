# ADR-001 - Arquitectura Monolítica

## Estado
Aprobado

## Contexto
El proyecto New Hauz será desarrollado por dos arquitectos senior, Kristian y Edgar, con apoyo de IA y QA dedicado. Se requiere una arquitectura eficiente, mantenible y con baja fricción operativa.

## Decisión
Se adopta una arquitectura monolítica moderna basada en:

- Laravel 13.x
- Blade
- Livewire v3
- Filament v3
- PostgreSQL
- PostGIS

## Justificación
La arquitectura monolítica permite:

- Un solo repositorio.
- Un solo despliegue.
- Menor complejidad DevOps.
- Mayor velocidad de desarrollo.
- Mejor integración con Filament.
- SEO nativo con Blade.
- Menor superficie de fallos.

## Alternativas Evaluadas

### Headless Laravel + Next.js
Descartado por mayor complejidad operativa.

### Laravel + Inertia
Descartado por no ser necesario para el alcance inicial.

### Laravel + Blade + Livewire
Seleccionado por balance entre velocidad, control y mantenibilidad.

## Consecuencias
Todas las capas principales convivirán en el mismo proyecto Laravel.

## Impacto
El equipo debe evitar introducir React, Next.js o frontend desacoplado salvo decisión arquitectónica futura.
