# Manual de Usuarios

Administra las cuentas del equipo que acceden al panel: quién existe, qué roles tiene, su estado y sus
zonas asignadas (si es agente).

## ¿Para qué sirve?

Es el control central de acceso: qué puede ver y hacer cada persona en el CMS depende de los roles que
se le asignen aquí.

{{captura: usuarios/listado | Listado de usuarios con roles, estados y zonas asignadas}}

## Cómo se usa

1. Al crear un usuario, captura **Nombre**, **Email** y los **Roles** — no se define contraseña al alta:
   se le envía un correo de invitación para que la elija.

   {{captura: usuarios/form | Formulario de alta de un usuario}}

2. Un usuario puede tener **más de un rol** (por ejemplo, `arquitectura` + `agente`).
3. Para editar, usa el ícono de lápiz de la fila; si necesitas resetear el acceso, deja el campo
   **Nueva contraseña** vacío para no tocarlo, o complétalo para forzar una contraseña específica.
4. Gestiona el ciclo de vida con las acciones: **Reenviar invitación** (si el usuario pendiente no
   activó su cuenta), **Suspender** (bloquea el login, requiere motivo) y **Reactivar**.
5. En la ficha del agente, la pestaña **Zonas asignadas** te muestra qué zonas tiene y te permite
   **quitarle** alguna. Ojo: **desde aquí no se asignan zonas nuevas** — eso se hace del otro lado,
   entrando a **Zonas**, abriendo la zona y usando **Asignar agente** en su pestaña "Agentes
   asignados".

   {{captura: usuarios/zonas | Pestaña Zonas asignadas en la ficha de un agente}}

## Campos importantes

- **Roles**: solo puedes asignar los roles que tu propio permiso te habilita — por ejemplo, solo el rol
  `owner` puede asignar el rol `owner` a otra persona.
- **Estado**: Pendiente de activación (invitado, no activó todavía) → Activo → Suspendido. Un usuario
  suspendido conserva sus roles pero no puede iniciar sesión.
- **Zonas asignadas**: solo aplica a agentes; determina qué zonas ve ese agente al elegir la ubicación
  de un inmueble.
- **Papelera**: solo el rol `owner` ve y restaura usuarios eliminados.

## Preguntas frecuentes

- **Un usuario invitado dice que no le llegó el correo** — usa **Reenviar invitación** desde su fila.
- **No puedo asignarle el rol owner a alguien** — solo un usuario con rol `owner` puede otorgar ese rol;
  si no lo tienes, pídele a un owner que lo haga.
- **Suspendí a un usuario por error** — usa **Reactivar** en su fila; recupera el acceso inmediatamente
  sin perder sus roles ni zonas asignadas.
