# TEST CASE CATALOG
## Proyecto NEW HAUZ

Versión: 1.0
QA Responsable: Sebastián

---

# OBJETIVO

Catálogo maestro de casos de prueba para validar funcionalidades, reglas de negocio, seguridad, experiencia de usuario y regresión del sistema New Hauz.

---

# ESTRUCTURA DE CASOS

Cada caso debe contener:

- ID
- Módulo
- RFC asociado
- Prioridad
- Precondiciones
- Pasos
- Resultado esperado
- Evidencia

---

# MÓDULO USUARIOS

## TC-USR-001

RFC:
RFC-011

Nombre:
Crear usuario Owner

Prioridad:
Crítica

Precondiciones:
Usuario autenticado como Owner.

Pasos:
1. Acceder a Usuarios.
2. Crear nuevo usuario.
3. Asignar rol Owner.
4. Guardar.

Resultado esperado:
Usuario creado correctamente.

---

## TC-USR-002

RFC:
RFC-013

Nombre:
Crear usuario Agente

Prioridad:
Crítica

Resultado esperado:
Agente creado correctamente.

---

## TC-USR-003

Nombre:
Suspender usuario

Prioridad:
Crítica

Resultado esperado:
Usuario suspendido y acceso bloqueado.

---

## TC-USR-004

Nombre:
Reactivar usuario

Resultado esperado:
Usuario puede iniciar sesión nuevamente.

---

# MÓDULO ROLES Y PERMISOS

## TC-ROL-001

Nombre:
Admin intenta crear Owner

Resultado esperado:
Acceso denegado.

---

## TC-ROL-002

Nombre:
Agente intenta acceder a administración

Resultado esperado:
Acceso denegado.

---

## TC-ROL-003

Nombre:
Owner consulta todos los usuarios

Resultado esperado:
Visualiza todos los registros.

---

# MÓDULO ZONAS

## TC-ZON-001

Nombre:
Crear zona

Resultado esperado:
Zona registrada.

---

## TC-ZON-002

Nombre:
Editar zona

Resultado esperado:
Zona actualizada.

---

## TC-ZON-003

Nombre:
Asignar agente a zona

Resultado esperado:
Asignación correcta.

---

## TC-ZON-004

Nombre:
Crear polígono geográfico

Resultado esperado:
Polígono almacenado.

---

# MÓDULO INMUEBLES

## TC-PROP-001

Nombre:
Crear inmueble

Resultado esperado:
Inmueble registrado.

---

## TC-PROP-002

Nombre:
Editar inmueble

Resultado esperado:
Cambios guardados.

---

## TC-PROP-003

Nombre:
Publicar inmueble

Resultado esperado:
Visible en frontend.

---

## TC-PROP-004

Nombre:
Pausar inmueble

Resultado esperado:
No visible en frontend.

---

## TC-PROP-005

Nombre:
Subir imagen principal

Resultado esperado:
Imagen almacenada.

---

## TC-PROP-006

Nombre:
Subir galería múltiple

Resultado esperado:
Galería visible.

---

## TC-PROP-007

Nombre:
Generar slug

Resultado esperado:
Slug único.

---

## TC-PROP-008

Nombre:
Guardar metadatos SEO

Resultado esperado:
SEO persistido.

---

# MÓDULO LEADS

## TC-LEAD-001

Nombre:
Formulario general

Resultado esperado:
Lead registrado.

---

## TC-LEAD-002

Nombre:
Formulario desde inmueble

Resultado esperado:
Lead asociado.

---

## TC-LEAD-003

Nombre:
Asignación automática

Resultado esperado:
Lead asignado.

---

## TC-LEAD-004

Nombre:
Notificación al agente

Resultado esperado:
Correo enviado.

---

# MÓDULO FRONTEND

## TC-FRONT-001

Nombre:
Visualizar Home

Resultado esperado:
Página visible.

---

## TC-FRONT-002

Nombre:
Visualizar Nosotros

Resultado esperado:
Página visible.

---

## TC-FRONT-003

Nombre:
Visualizar Servicios

Resultado esperado:
Página visible.

---

## TC-FRONT-004

Nombre:
Visualizar Proyectos

Resultado esperado:
Página visible.

---

## TC-FRONT-005

Nombre:
Visualizar Inmobiliaria

Resultado esperado:
Página visible.

---

## TC-FRONT-006

Nombre:
Visualizar Contacto

Resultado esperado:
Página visible.

---

# MÓDULO BUSCADOR

## TC-SRC-001

Nombre:
Filtro por operación

Resultado esperado:
Resultados correctos.

---

## TC-SRC-002

Nombre:
Filtro por zona

Resultado esperado:
Resultados correctos.

---

## TC-SRC-003

Nombre:
Filtro por precio

Resultado esperado:
Resultados correctos.

---

## TC-SRC-004

Nombre:
Filtros combinados

Resultado esperado:
Resultados consistentes.

---

# MÓDULO SEO

## TC-SEO-001

Nombre:
Validar title

Resultado esperado:
Title correcto.

---

## TC-SEO-002

Nombre:
Validar meta description

Resultado esperado:
Correcta.

---

## TC-SEO-003

Nombre:
Validar Open Graph

Resultado esperado:
Correcto.

---

## TC-SEO-004

Nombre:
Validar Schema.org

Resultado esperado:
Válido.

---

# MÓDULO WHATSAPP

## TC-WA-001

Nombre:
Abrir conversación

Resultado esperado:
WhatsApp abre correctamente.

---

## TC-WA-002

Nombre:
Tracking WhatsApp

Resultado esperado:
Evento registrado.

---

# MÓDULO CRM

## TC-CRM-001

Nombre:
Crear oportunidad

Resultado esperado:
Registro creado.

---

## TC-CRM-002

Nombre:
Cambiar etapa pipeline

Resultado esperado:
Etapa actualizada.

---

## TC-CRM-003

Nombre:
Consultar historial

Resultado esperado:
Información visible.

---

# MÓDULO REPORTES

## TC-REP-001

Nombre:
Exportar Excel

Resultado esperado:
Archivo generado.

---

## TC-REP-002

Nombre:
Exportar PDF

Resultado esperado:
Archivo generado.

---

# MÓDULO NOTIFICACIONES

## TC-NOT-001

Nombre:
Correo Lead

Resultado esperado:
Correo enviado.

---

## TC-NOT-002

Nombre:
Correo Usuario

Resultado esperado:
Correo enviado.

---

## TC-NOT-003

Nombre:
Notificación Interna

Resultado esperado:
Visible correctamente.

---

# EVIDENCIAS

Cada ejecución debe almacenar:

- Captura
- Video
- Fecha
- Responsable
- Resultado

---

# ESTADOS DE EJECUCIÓN

PASS
FAIL
BLOCKED
NOT RUN

---

Fin del documento.
