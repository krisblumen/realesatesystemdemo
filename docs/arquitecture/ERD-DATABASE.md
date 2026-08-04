# ERD DATABASE - New Hauz

## Estado
Versión preliminar

## Objetivo
Definir la estructura base de datos para la plataforma inmobiliaria New Hauz.

---

# Entidades Principales

## users

Representa usuarios del sistema: Owner, Admin y Agente.

Campos sugeridos:

- id
- name
- email
- password
- phone
- whatsapp
- avatar
- status
- zone_id
- created_at
- updated_at

Relaciones:

- Un usuario puede tener muchos inmuebles.
- Un usuario puede tener muchos leads asignados.
- Un usuario puede pertenecer a una zona.

---

## zones

Representa zonas comerciales de Querétaro.

Campos sugeridos:

- id
- name
- slug
- description
- municipality
- polygon
- center_point
- status
- created_at
- updated_at

Relaciones:

- Una zona tiene muchos agentes.
- Una zona tiene muchos inmuebles.

---

## properties

Representa inmuebles publicados.

Campos sugeridos:

- id
- user_id
- zone_id
- title
- slug
- description
- operation_type
- property_type
- price
- currency
- bedrooms
- bathrooms
- parking_spaces
- land_area
- construction_area
- address
- location
- status
- is_featured
- meta_title
- meta_description
- published_at
- created_at
- updated_at

Relaciones:

- Un inmueble pertenece a un agente.
- Un inmueble pertenece a una zona.
- Un inmueble tiene muchos leads.
- Un inmueble tiene muchas imágenes mediante Media Library.

---

## leads

Representa prospectos comerciales.

Campos sugeridos:

- id
- property_id
- assigned_user_id
- name
- email
- phone
- message
- source
- status
- created_at
- updated_at

Relaciones:

- Un lead puede pertenecer a un inmueble.
- Un lead puede estar asignado a un agente.

---

## projects

Representa proyectos de arquitectura, construcción o desarrollo.

Campos sugeridos:

- id
- title
- slug
- description
- project_type
- location
- status
- meta_title
- meta_description
- created_at
- updated_at

---

# Relaciones Clave

```text
users 1---N properties
users 1---N leads
zones 1---N properties
zones 1---N users
properties 1---N leads
projects N---N media
properties N---N media
```

---

# Consideraciones PostGIS

Campos geográficos:

- zones.polygon
- zones.center_point
- properties.location

Tipos esperados:

- geometry
- geography
- point
- polygon

---

# Notas
Este ERD debe evolucionar durante la Épica 1 y consolidarse antes de iniciar el desarrollo de inmuebles.
