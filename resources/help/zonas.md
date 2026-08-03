# Manual de Zonas

Una zona es el área geográfica de cobertura comercial de la inmobiliaria, compuesta por uno o más
códigos postales dentro de un municipio. Cada inmueble pertenece a una zona.

## ¿Para qué sirve?

Organiza el territorio en unidades manejables: sirve para asignar agentes por zona, filtrar inmuebles
y delimitar el área que un agente puede ver y ofrecer.

{{captura: zonas/listado | Listado de zonas con su estado y municipio}}

## Cómo se usa

1. Captura **Nombre** (el slug se autogenera, pero es editable) y elige **Estado** y **Municipio** —
   el municipio filtra qué códigos postales están disponibles.
2. Si no recuerdas el código postal, usa **Buscar colonia**: escribe el nombre y elige la colonia; su
   CP se agrega solo a "Códigos postales".
3. Elige uno o más **Códigos postales** — la zona se compone con los polígonos de todos los CP
   elegidos, y la **Descripción** (lista de colonias) se genera automáticamente, igual que la vista
   previa en el mapa.
4. Guarda con **Estatus** "Activa" para que la zona esté disponible al crear/editar inmuebles.
5. Con la zona ya creada, usa su pestaña **Agentes asignados** para dar de alta qué agentes trabajan
   esa zona: este es el único lugar donde se asigna la relación agente↔zona.

   {{captura: zonas/agentes | Pestaña Agentes asignados con el botón Asignar agente}}

Así se ve el formulario con una zona ya armada: los códigos postales quedan como etiquetas, la
descripción lista sus colonias, y el **mapa de abajo dibuja el polígono de cada CP** para que
confirmes de un vistazo que el área es la correcta.

{{captura: zonas/form | Formulario de zona con códigos postales, colonias y el mapa del área}}

## Campos importantes

- **Solo CP con geometría cargada**: no todos los códigos postales del municipio aparecen en el
  selector — solo los que ya tienen su polígono cargado en el catálogo geográfico (PostGIS).
- **Descripción**: es de solo lectura, se recalcula automáticamente al cambiar los códigos postales; no
  se edita a mano.
- **Estatus**: Activa/Inactiva. Una zona inactiva no aparece como opción al crear o editar inmuebles,
  pero los inmuebles ya asignados a ella no se ven afectados.
- **Papelera**: solo el rol `owner` puede eliminar y ver zonas eliminadas; una zona con inmuebles
  asociados no se puede eliminar (el sistema explica el motivo si lo intentas).

## Preguntas frecuentes

- **No encuentro el código postal que necesito** — puede que su geometría todavía no esté cargada en el
  catálogo; consulta con owner/admin para agregarla.
- **No puedo eliminar una zona** — revisa que no tenga inmuebles asociados; el sistema no permite
  eliminar zonas en uso.
- **Un agente no puede elegir esta zona al cargar un inmueble** — el agente solo ve, en el selector de
  zona del inmueble, las zonas que tiene asignadas (relación agente↔zona gestionada desde **Usuarios**).
