# AI DEVELOPMENT PLAYBOOK - New Hauz

## Objetivo
Definir cómo Kristian y Edgar utilizarán IA para acelerar el desarrollo sin perder control arquitectónico.

---

# Principios

1. La IA propone; el arquitecto decide.
2. Ningún código generado por IA se integra sin revisión.
3. Todo prompt debe estar asociado a un RFC.
4. Todo cambio debe pasar QA.
5. La documentación debe actualizarse junto con el código.

---

# Formato de Prompt Técnico

```text
Actúa como arquitecto senior Laravel.
Proyecto: New Hauz.
Stack: Laravel 13 + Filament + Livewire + PostgreSQL + PostGIS.
RFC: RFC-XXX.
Objetivo:
Restricciones:
Entregables:
Criterios de aceptación:
Genera:
```

---

# Flujo de Trabajo con IA

1. Leer RFC.
2. Definir alcance.
3. Generar prompt.
4. Revisar respuesta IA.
5. Ajustar código.
6. Ejecutar pruebas.
7. Documentar cambios.
8. Solicitar QA.

---

# Reglas de Seguridad

La IA no debe:

- Inventar credenciales.
- Eliminar reglas de negocio.
- Omitir validaciones.
- Saltarse permisos.
- Modificar arquitectura aprobada.
- Introducir dependencias innecesarias.

---

# Checklist Antes de Merge

- Código revisado por arquitecto.
- Prompt asociado documentado.
- Tests ejecutados.
- QA aprobado.
- Sin errores críticos.
- Documentación actualizada.

---

# Responsabilidades

## Kristian
- UX/UI.
- Frontend.
- Zonas.
- Inmuebles.
- SEO.
- Comercialización.

## Edgar
- Infraestructura.
- Seguridad.
- Usuarios.
- Leads.
- CRM.
- Integraciones.

## Sebastián
- QA.
- Regresión.
- UAT.
- Evidencias.

---

# Buenas Prácticas

- Pedir código por archivos específicos.
- Evitar prompts gigantes.
- Pedir explicación técnica breve.
- Pedir pruebas junto con implementación.
- Pedir migraciones y rollback.
- Revisar naming y estándares.
