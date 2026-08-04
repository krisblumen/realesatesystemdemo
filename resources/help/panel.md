# Manual del Panel general

El Panel general (Dashboard) es la primera pantalla que ves al entrar. Resume el estado del negocio
con widgets agrupados por tema: inmuebles, leads, agentes, propietarios y zonas.

## ¿Para qué sirve?

Te da una foto rápida de cómo va la operación sin tener que entrar sección por sección: cuántos
inmuebles hay por estado, cómo evolucionan los leads, quién vende más, etc.

{{captura: panel/dashboard | Panel general con sus widgets agrupados por tema}}

## Cómo se usa

1. Los widgets están agrupados con encabezados de sección (Inmuebles, Leads, Agentes, Propietarios y
   comisiones, Zonas) — cada uno es un separador visual, no un enlace.
2. Owner/admin ven todos los widgets del panel global (métricas de todo el negocio).
3. El rol agente no ve estos widgets globales aquí: tiene su propia vista personal en **Mi Zona**, con
   sus zonas asignadas y su rendimiento propio, para no mezclar datos del equipo con los suyos.

## Campos importantes

- **Encabezados de sección**: los títulos como "Inmuebles" o "Leads" solo agrupan visualmente; no son
  clicables ni filtran nada por sí mismos.
- **Datos en tiempo real**: los widgets consultan la base de datos en cada carga del panel — no hay
  caché manual que limpiar si algo se ve desactualizado, solo recarga la página.

## Preguntas frecuentes

- **¿Por qué no veo los widgets del Panel general si soy agente?** — por diseño: el agente tiene su
  propio panel personal en **Mi Zona**, separado del panel global de owner/admin.
- **Un número no coincide con lo que veo en la sección de detalle** — revisa los filtros de fecha o rol
  activos en la sección de detalle; el widget del panel suele mostrar un total sin filtrar.
