# Lote 12.2-E — Evidencia de la verificación visual

**Fecha:** 2026-07-27 · **Commit auditado:** `eb81d42` · **Contrato:** `docs/epicas/epica-12-2-lotes-implementacion.md` §8

Se registran **mediciones**, no capturas: un número es reproducible y comparable entre corridas; una imagen no. Todo se obtuvo con el servidor de preview sobre la base de desarrollo, y el servidor se detuvo antes de correr la suite completa.

## Por qué existe este lote

Los lotes 12.2-A a 12.2-D cerraron con **1.000/1.000** pruebas verdes. Recorrer el panel en el navegador —con todo en verde— encontró un defecto que ninguna de esas pruebas podía encontrar. Esa es la razón de ser de este artefacto, y la conclusión que vale más que los números de abajo.

## Cómo reproducirlo

```bash
composer dev
```

Con sesión iniciada en el panel, sobre `/admin/frontend/paginas/{id}/edit`, abrir la sección y agregar una fila al repeater. Luego, en la consola:

```js
({
  altoDeFila: [...document.querySelectorAll('.fi-fo-repeater-item')]
    .map(f => Math.round(f.getBoundingClientRect().height)),
  ayuda: (() => {
    const e = [...document.querySelectorAll('*')]
      .filter(n => n.children.length === 0 && /mín\./.test(n.innerText))[0];
    if (!e) return null;
    const r = e.getBoundingClientRect();
    return {
      texto: e.innerText.trim(),
      ancho: Math.round(r.width),
      lineas: Math.round(r.height / parseFloat(getComputedStyle(e).lineHeight)),
    };
  })(),
  desbordeHorizontal: document.documentElement.scrollWidth > window.innerWidth,
})
```

## El defecto de criterio — TB2E-1/2/3

| | Antes | Después |
| --- | --- | --- |
| Mínimo en `hero` | 1200×675 (16:9) | 1200×675 — **sin cambios** |
| Mínimo en `feature_sequence` | 1200×675 (16:9) | 1200×675 — **sin cambios** |
| Mínimo en `team` | 1200×675 (16:9) | **600×600 (retrato o cuadrada)** |
| Foto de perfil 800×800 en `team` | **rechazada** | aceptada ✅ |
| Foto 300×300 en `team` | rechazada | **rechazada** ✅ |
| Foto 800×800 en `feature_sequence` | rechazada | **rechazada** ✅ |

Las tres últimas filas son pruebas, no observaciones: `test_a_square_portrait_is_accepted_for_a_team_member`, `test_a_photo_below_the_portrait_minimum_is_still_rejected` y `test_a_square_image_is_rejected_for_a_sequence_panel`, en `FrontendMediaSectionEditorTest`.

**Causa raíz cerrada:** `SectionImageFields::make()` declara `minWidth`, `minHeight` y `shape` como parámetros **obligatorios**. El defecto no fue elegir mal un número: fue que había un default y nadie lo miró al sumar el segundo y el tercer consumidor.

**Por qué la suite no lo veía:** cada prueba de media usaba `UploadedFile::fake()->image('x.png', 1600, 900)`. Una imagen que satisface cualquier mínimo apaisado no puede revelar que el mínimo está mal planteado.

## Densidad de las tarjetas — §8.3

| Métrica | Antes | Después | Contrato |
| --- | ---: | ---: | --- |
| Fila de `feature_sequence` | **585 px** | **441 px** | −25 % ✅ |
| Fila de `team` | **253 px** | **~215 px** | ✅ |
| Ayuda de la imagen | 3 líneas / 239 px | 1–2 líneas | ✅ |
| «Sin imagen» | texto suelto (107 px) | recuadro `aspect-ratio: 4/3` | Reserva su lugar: la fila no salta de alto al subir la primera foto ✅ |
| Ubicación del `alt` | al final de la tarjeta | junto a la imagen | Describe esa foto ✅ |
| Desborde horizontal | no | **no** | ✅ |

Dos cosas que se probaron y **no** funcionaron, para que no se reintenten:

- `panelLayout('compact')` de Filament **no** achica el dropzone vacío (76 px): sólo cambia cómo se presentan los archivos ya subidos.
- Con los spans sumando 13 sobre una grilla de 12, un campo cae de renglón y la fila **crece** en vez de compactarse. Los spans de cada fila suman exactamente 12.

## Lenguaje del editor — TB2E-4/5

| Métrica | Antes | Después |
| --- | --- | --- |
| Encabezado del modal | `Editar frontend section` | **`Ruta de inversión`** |
| Primeros campos | `section_key` y `type` deshabilitados | Visibilidad y orden, con su explicación |
| Columna de la tabla | `investment_path` | **`Ruta de inversión`**, clave interna como texto secundario |
| Aviso de borrador | ninguno | «Los cambios quedan en el borrador de la página hasta que la publiques.» |

Leído del DOM real del modal abierto:

```
Cerrar
Ruta de inversión
Los cambios quedan en el borrador de la página hasta que la publiques.
Visible en la página
Apagado, la sección deja de mostrarse sin perder su contenido.
Orden
En qué posición aparece dentro de la página.
Secuencia
```

Las 17 claves de `section_labels` cubren exactamente las 17 `section_key` distintas del registro —verificado por diferencia de conjuntos, sin faltantes ni sobrantes— y `test_every_canonical_section_has_a_human_name` falla si alguien agrega una sección sin etiquetarla.

## Listados dinámicos — sin regresión de 12.2-C

Se confirmó en pantalla que el formulario de `featured_properties` sigue ofreciendo **sólo** antetítulo, título y «cuántos mostrar», con el texto «Se muestran las propiedades marcadas como destacadas. Esta pantalla solo cambia el encabezado; el contenido se administra en su propia sección.» Ningún campo de ítems, ids ni consulta.

## Estado de la suite

| Momento | Resultado |
| --- | --- |
| Cerrado 12.2-D | 997/997 |
| Tras los mínimos por consumidor | 1.000/1.000 |
| Tras el lenguaje del editor | **1.002/1.002**, 4.367 aserciones |
| `./vendor/bin/pint --test` | limpio |

El servidor de preview se detuvo antes de cada corrida completa.
