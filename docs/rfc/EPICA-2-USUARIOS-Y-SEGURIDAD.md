# EPICA-2-USUARIOS-Y-SEGURIDAD

## Proyecto
NEW HAUZ

## Épica
EPICA-2

## Responsable Principal
Edgar

## Participantes

### Arquitectura
- Edgar
- Kristian

### QA
- Sebastián

---

# Objetivo

Implementar la capa de autenticación, autorización y administración de usuarios que servirá como base para todos los módulos del sistema.

Esta épica establece:

- Gestión de usuarios
- Roles y permisos
- Seguridad de acceso
- Suspensión de usuarios
- Reactivación de usuarios
- Control de acceso por módulo

---

# Dependencias

## Requiere

- RFC-001 Laravel 13
- RFC-004 Filament
- RFC-006 Spatie Permission

---

# RFCs Incluidos

| RFC | Nombre |
|------|------|
| RFC-011 | Modelo Usuario |
| RFC-012 | Roles y Permisos |
| RFC-013 | CRUD Usuarios |
| RFC-014 | Suspensión y Reactivación |

---

# RFC-011 MODELO USUARIO

## Objetivo

Implementar la entidad User como núcleo de autenticación.

## Campos Iniciales

- id
- name
- email
- password
- phone
- whatsapp
- avatar
- status
- last_login_at
- created_at
- updated_at

## Relaciones

### User → Roles

Muchos a muchos.

### User → Properties

Uno a muchos.

### User → Leads

Uno a muchos.

## Criterios de Aceptación

- Modelo funcional.
- Migración ejecutada.
- Relaciones implementadas.

---

# RFC-012 ROLES Y PERMISOS

## Objetivo

Implementar control de acceso basado en Spatie Permission.

## Roles

### Owner

Control total del sistema.

### Admin

Operación administrativa.

### Agente

Gestión comercial limitada.

## Permisos Base

- users.view
- users.create
- users.update
- users.delete
- properties.manage
- leads.manage
- zones.manage

## Criterios de Aceptación

- Roles creados.
- Seeder funcional.
- Permisos asignados.

---

# RFC-013 CRUD USUARIOS

## Objetivo

Administrar usuarios desde Filament.

## Funcionalidades

### Crear usuario

Campos:
- Nombre
- Email
- Teléfono
- Rol

### Editar usuario

Actualizar información.

### Consultar usuario

Filtros y búsqueda.

### Eliminar usuario

Soft delete.

## Resource

UserResource

## Criterios de Aceptación

- CRUD completo.
- Validaciones operativas.
- Permisos respetados.

---

# RFC-014 SUSPENSIÓN Y REACTIVACIÓN

## Objetivo

Controlar acceso de usuarios mediante estados.

## Estados

### Activo

Acceso permitido.

### Suspendido

Acceso bloqueado.

## Reglas

- Un usuario suspendido no puede iniciar sesión.
- Owner no puede ser suspendido por Admin.
- Debe registrarse responsable y fecha.

## Auditoría

Registrar:

- Usuario afectado
- Responsable
- Fecha
- Motivo

## Criterios de Aceptación

- Suspensión funcional.
- Reactivación funcional.
- Login bloqueado correctamente.

---

# Casos QA

## QA-011

Crear usuario.

## QA-012

Asignar rol.

## QA-013

Editar usuario.

## QA-014

Suspender usuario.

## QA-015

Reactivar usuario.

## QA-016

Validar permisos.

## QA-017

Validar acceso bloqueado.

---

# Casos de Regresión

- Login Owner
- Login Admin
- Login Agente
- CRUD usuarios
- Roles
- Permisos
- Suspensión
- Reactivación

---

# Definition of Done

La épica se considera terminada cuando:

- RFC-011 aprobado.
- RFC-012 aprobado.
- RFC-013 aprobado.
- RFC-014 aprobado.
- QA aprobado.
- Regresión aprobada.
- Documentación actualizada.

---

# Estimación

Arquitectura: Edgar

Duración estimada:
1 Sprint

Complejidad:
Media

---

Estado:
Pendiente de implementación.
