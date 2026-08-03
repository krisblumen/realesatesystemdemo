# QA MASTER PLAN
## Proyecto NEW HAUZ

Versión: 1.0
Estado: Activo
QA Responsable: Sebastián

---

# 1. OBJETIVO

Definir la estrategia integral de aseguramiento de calidad para la plataforma New Hauz.

Objetivos:

- Garantizar calidad funcional.
- Detectar regresiones.
- Validar reglas de negocio.
- Validar permisos y seguridad.
- Validar experiencia de usuario.
- Validar rendimiento básico.
- Aprobar releases.

---

# 2. EQUIPO QA

## QA Lead

Sebastián

Responsabilidades:

- Diseñar casos de prueba.
- Ejecutar pruebas funcionales.
- Ejecutar regresión.
- Gestionar incidencias.
- Validar releases.
- Emitir aprobación QA.

---

# 3. ALCANCE QA

Módulos cubiertos:

- Usuarios
- Roles y permisos
- Zonas
- Inmuebles
- Leads
- Frontend público
- SEO
- Comercialización
- CRM
- Integraciones

---

# 4. TIPOS DE PRUEBAS

## Funcionales

Validar comportamiento esperado.

## Regresión

Validar que nuevas funcionalidades no rompan módulos existentes.

## Seguridad

Validar roles, permisos y accesos.

## UI/UX

Validar interfaz y experiencia de usuario.

## Responsive

Mobile
Tablet
Desktop

## Integración

Validar interacción entre módulos.

## UAT

Pruebas de aceptación de usuario.

---

# 5. MATRIZ DE COBERTURA

| Módulo | Funcional | Seguridad | Regresión |
|---------|------------|------------|------------|
| Usuarios | Sí | Sí | Sí |
| Roles | Sí | Sí | Sí |
| Zonas | Sí | Sí | Sí |
| Inmuebles | Sí | Sí | Sí |
| Leads | Sí | Sí | Sí |
| Frontend | Sí | No | Sí |
| SEO | Sí | No | Sí |
| CRM | Sí | Sí | Sí |

---

# 6. CRITERIOS DE APROBACIÓN

Un sprint es aprobado cuando:

- 100% pruebas críticas aprobadas.
- Sin errores bloqueantes.
- Sin errores críticos.
- Regresión aprobada.
- Evidencias documentadas.
- QA emite aprobación.

---

# 7. CLASIFICACIÓN DE ERRORES

## Bloqueante

Impide operar el sistema.

## Crítico

Afecta negocio, permisos o leads.

## Alto

Afecta operación importante.

## Medio

Afecta funcionalidad secundaria.

## Bajo

Problema visual o menor.

---

# 8. REGRESIÓN GLOBAL

Antes de cada release ejecutar:

- Login Owner
- Login Admin
- Login Agente
- Crear Usuario
- Crear Zona
- Crear Inmueble
- Publicar Inmueble
- Buscar Inmueble
- Crear Lead
- Validar Permisos
- Tracking WhatsApp
- SEO
- Responsive
- Exportaciones
- Notificaciones

---

# 9. EVIDENCIAS

Toda prueba debe contener:

- Captura de pantalla
- Video cuando aplique
- Resultado esperado
- Resultado obtenido
- Fecha
- Responsable

---

# 10. DEFINICIÓN DE DONE QA

Un RFC se considera aprobado cuando:

- Desarrollo terminado.
- Casos QA ejecutados.
- Regresión aprobada.
- Sin errores críticos.
- Evidencia almacenada.
- QA aprueba el RFC.

---

# 11. ENTREGABLES QA

- QA-MASTER-PLAN.md
- REGRESSION-SUITE.md
- MATRICES-QA.md
- Evidencias por sprint
- Reportes de defectos
- Actas de liberación

---

Fin del documento.
