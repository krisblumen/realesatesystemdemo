# REGRESSION SUITE
## Proyecto NEW HAUZ

Versión: 1.0
QA Responsable: Sebastián

---

# OBJETIVO

Garantizar que cada nuevo RFC, Sprint o Release no afecte funcionalidades previamente aprobadas.

La presente suite debe ejecutarse:

- Antes de cada Release.
- Antes de cada Merge a main.
- Después de correcciones críticas.
- Antes de liberaciones de producción.

---

# MÓDULO 1 - AUTENTICACIÓN

## RG-AUTH-001
Login Owner

Resultado esperado:
Acceso exitoso.

## RG-AUTH-002
Login Admin

Resultado esperado:
Acceso exitoso.

## RG-AUTH-003
Login Agente

Resultado esperado:
Acceso exitoso.

## RG-AUTH-004
Usuario suspendido

Resultado esperado:
Acceso bloqueado.

## RG-AUTH-005
Recuperación de contraseña

Resultado esperado:
Correo enviado correctamente.

---

# MÓDULO 2 - ROLES Y PERMISOS

## RG-ROLE-001
Owner crea usuario

Resultado esperado:
Usuario creado.

## RG-ROLE-002
Admin intenta crear Owner

Resultado esperado:
Operación denegada.

## RG-ROLE-003
Agente intenta acceder a administración

Resultado esperado:
Acceso restringido.

## RG-ROLE-004
Suspensión de usuario

Resultado esperado:
Usuario suspendido correctamente.

---

# MÓDULO 3 - ZONAS

## RG-ZONE-001
Crear zona

Resultado esperado:
Zona registrada.

## RG-ZONE-002
Editar zona

Resultado esperado:
Zona actualizada.

## RG-ZONE-003
Asignar agente

Resultado esperado:
Relación correcta.

## RG-ZONE-004
Consulta geográfica

Resultado esperado:
Resultados válidos.

---

# MÓDULO 4 - INMUEBLES

## RG-PROP-001
Crear inmueble

Resultado esperado:
Inmueble registrado.

## RG-PROP-002
Editar inmueble

Resultado esperado:
Cambios guardados.

## RG-PROP-003
Publicar inmueble

Resultado esperado:
Visible en frontend.

## RG-PROP-004
Pausar inmueble

Resultado esperado:
Oculto en frontend.

## RG-PROP-005
Subir galería

Resultado esperado:
Imágenes visibles.

## RG-PROP-006
SEO Slug

Resultado esperado:
Slug único.

## RG-PROP-007
Metadatos SEO

Resultado esperado:
Metadatos correctos.

---

# MÓDULO 5 - LEADS

## RG-LEAD-001
Formulario general

Resultado esperado:
Lead guardado.

## RG-LEAD-002
Formulario de inmueble

Resultado esperado:
Lead asociado al inmueble.

## RG-LEAD-003
Asignación automática

Resultado esperado:
Lead asignado al agente.

## RG-LEAD-004
Notificación

Resultado esperado:
Correo enviado.

---

# MÓDULO 6 - FRONTEND

## RG-FRONT-001
Home

Resultado esperado:
Carga correctamente.

## RG-FRONT-002
Nosotros

Resultado esperado:
Carga correctamente.

## RG-FRONT-003
Servicios

Resultado esperado:
Carga correctamente.

## RG-FRONT-004
Proyectos

Resultado esperado:
Carga correctamente.

## RG-FRONT-005
Inmobiliaria

Resultado esperado:
Carga correctamente.

## RG-FRONT-006
Contacto

Resultado esperado:
Carga correctamente.

---

# MÓDULO 7 - BUSCADOR

## RG-SEARCH-001
Filtro por operación

Resultado esperado:
Resultados correctos.

## RG-SEARCH-002
Filtro por zona

Resultado esperado:
Resultados correctos.

## RG-SEARCH-003
Filtro por precio

Resultado esperado:
Resultados correctos.

## RG-SEARCH-004
Combinación de filtros

Resultado esperado:
Resultados consistentes.

---

# MÓDULO 8 - RESPONSIVE

## RG-RESP-001
Mobile

Resultado esperado:
Diseño correcto.

## RG-RESP-002
Tablet

Resultado esperado:
Diseño correcto.

## RG-RESP-003
Desktop

Resultado esperado:
Diseño correcto.

---

# MÓDULO 9 - SEO

## RG-SEO-001
Title

Resultado esperado:
Correcto.

## RG-SEO-002
Meta Description

Resultado esperado:
Correcta.

## RG-SEO-003
Canonical

Resultado esperado:
Correcta.

## RG-SEO-004
Schema.org

Resultado esperado:
Válido.

## RG-SEO-005
Open Graph

Resultado esperado:
Correcto.

---

# MÓDULO 10 - WHATSAPP

## RG-WA-001
Botón WhatsApp

Resultado esperado:
Abre conversación.

## RG-WA-002
Tracking

Resultado esperado:
Evento registrado.

---

# MÓDULO 11 - CRM

## RG-CRM-001
Crear oportunidad

Resultado esperado:
Registro creado.

## RG-CRM-002
Mover pipeline

Resultado esperado:
Estado actualizado.

## RG-CRM-003
Historial

Resultado esperado:
Visible correctamente.

---

# MÓDULO 12 - REPORTES

## RG-REP-001
Exportar Excel

Resultado esperado:
Archivo generado.

## RG-REP-002
Exportar PDF

Resultado esperado:
Archivo generado.

---

# MÓDULO 13 - NOTIFICACIONES

## RG-NOT-001
Correo Lead

Resultado esperado:
Enviado.

## RG-NOT-002
Correo Usuario

Resultado esperado:
Enviado.

## RG-NOT-003
Notificación Interna

Resultado esperado:
Visible.

---

# CHECKLIST DE LIBERACIÓN

Antes de producción:

[ ] Regresión completa aprobada
[ ] Sin errores bloqueantes
[ ] Sin errores críticos
[ ] Evidencias almacenadas
[ ] QA aprobado
[ ] Arquitectos aprobaron release

---

# RESULTADO FINAL

PASS = Release Aprobado

FAIL = Release Rechazado

---

Fin del documento.
