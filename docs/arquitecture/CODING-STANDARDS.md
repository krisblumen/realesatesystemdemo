# CODING STANDARDS - New Hauz

## Objetivo
Definir reglas de codificación para mantener consistencia entre Kristian, Edgar e IA.

---

# PHP / Laravel

## Convenciones

- Usar PSR-12.
- Usar nombres descriptivos.
- Evitar lógica compleja en controladores.
- Usar Form Requests para validaciones.
- Usar Policies para permisos.
- Usar Services cuando la lógica supere el CRUD básico.

## Estructura

```text
app/
├── Models/
├── Policies/
├── Services/
├── Filament/
├── Livewire/
└── Http/
```

---

# Modelos

Reglas:

- Relaciones explícitas.
- Casts definidos.
- Fillable controlado.
- Scopes para filtros frecuentes.
- Observers para slugs y eventos automáticos.

---

# Filament

Reglas:

- Cada Resource debe tener validaciones.
- Tablas con filtros útiles.
- Acciones críticas con confirmación.
- Formularios ordenados por secciones.
- Acceso protegido por Policies.

---

# Livewire

Reglas:

- Componentes pequeños.
- Validación dentro del componente o Form Object.
- Evitar lógica pesada en render().
- Usar paginación cuando aplique.
- Mantener estado mínimo.

---

# Blade

Reglas:

- Layouts reutilizables.
- Componentes anónimos para botones, cards e inputs.
- SEO gestionado por stacks o secciones.
- Evitar duplicación de HTML.

---

# Base de Datos

Reglas:

- Migraciones reversibles.
- Índices en campos de búsqueda.
- Foreign keys obligatorias.
- Soft deletes cuando aplique.
- Campos geográficos en PostgreSQL/PostGIS.

---

# Git

Ramas:

```text
main
develop
feature/rfc-xxx-descripcion
fix/bug-xxx-descripcion
release/vx.x.x
```

Commits:

```text
feat:
fix:
docs:
test:
refactor:
chore:
```

---

# Definition of Done Técnico

Un RFC está listo cuando:

- Código implementado.
- Validaciones agregadas.
- Permisos protegidos.
- QA aprobado.
- Sin regresiones.
- Documentación actualizada.
