# Manual de Lonas asignadas

Esta sección lista los lotes de lonas (publicidad física) que owner/admin le entregan a cada agente,
por tipo de operación (Venta o Renta).

## ¿Para qué sirve?

Lleva el control de cuántas lonas se le entregaron a cada agente y cuándo, para poder auditar el
inventario físico de lonas frente a las que efectivamente se colocan.

{{captura: lonas-asignadas/listado | Listado de lotes de lonas con cantidad, unidades y quién autorizó}}

## Cómo se usa

1. Para entregar un lote nuevo, elige el **Agente**, el **Tipo** de operación y la **Cantidad** — hay un
   tope máximo de lonas sin colocar por tipo y agente, que el sistema valida.

   {{captura: lonas-asignadas/form | Formulario para asignar un lote de lonas a un agente}}
2. Opcionalmente, elige un **Inmueble** (solo publicados) para que el código QR del PDF de la lona
   apunte al detalle público de ese inmueble.
3. Un lote entregado queda inmutable: no se edita ni se elimina, solo se puede descargar su **PDF** de
   diseño si tiene uno cargado.

## Campos importantes

- **Cantidad**: tope máximo configurado por tipo — si el agente ya tiene lonas sin colocar de ese tipo,
  el sistema solo te deja completar el cupo restante, no exceder el máximo.
- **Unidades**: cada lote genera unidades individuales de lona (ver también **Evidencias** y **Mis
  Lonas** del agente), que luego se marcan como colocadas con su propia foto de evidencia.
- **Autorizó**: quién generó el lote (owner/admin).

## Preguntas frecuentes

- **No puedo entregar más lonas a un agente** — está en el tope de lonas sin colocar por tipo; el
  agente necesita colocar las que tiene (con evidencia) para liberar cupo, o haz la entrega por menos
  cantidad.
- **¿Cómo veo si ya se colocaron las lonas de un lote?** — revisa **Evidencias**, donde aparecen todas
  las unidades ya colocadas con su foto y ubicación.
