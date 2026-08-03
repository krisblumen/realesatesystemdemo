# RFC-10 Límites de abuso y plazo de vida

> **FASE 2 — no se implementa todavía.** El demo arranca por invitación
> (`RFC-13`), y con invitación esto no hace falta. Queda escrito para cuando el
> demo se abra al público.

## Objetivo

Que el demo no se pueda usar para llenar el servidor de bases de datos, y que el
plazo de vida salga de lo que el servidor aguanta y no de una intuición.

## Épica

EPICA-DEMO. Lote F. Depende de RFC-01 y RFC-09.

Cierra el hallazgo M-2 de la auditoría de diseño.

## Responsable

Backend + Owner del producto.

## El número no se elige: se deriva

Cada inquilino es una base de datos completa. El techo real del demo es **cuántas
bases soporta la instancia de Postgres antes de degradarse**, y ese número hay
que medirlo en el VPS, no suponerlo.

La aritmética es simple y va en este orden:

```
inquilinos simultáneos ≈ registros por día × días de vida
```

Si el servidor sostiene 200 bases y el demo recibe 50 registros diarios, el plazo
no puede pasar de cuatro días. **No es una decisión de producto, es una
división.** Elegir el plazo primero y después descubrir el techo es cómo se llena
un disco un domingo.

## Alcance

- Medición del techo de bases del VPS, antes de abrir el demo.
- Plazo de vida configurable, derivado de esa medición.
- Límite de altas por origen y por ventana de tiempo.
- Un tope duro de inquilinos activos.

## Valores iniciales

Quedan **pendientes de la medición** del VPS. Hasta tenerla, el sistema arranca
con el tope duro puesto en un número conservador y una alerta al acercarse.

Lo que sí queda definido:

- El plazo de vida es un valor de configuración, no una constante en el código.
- El tope duro de inquilinos activos existe **siempre**, aunque el plazo sea
  corto. Es la última red: cuando se alcanza, el registro deja de aceptar altas
  con un mensaje honesto en vez de reventar.

## Límite por origen

Se guarda `origen_hash`: un hash con sal del origen de la petición. No la
dirección.

- Alcanza para limitar altas repetidas del mismo lugar.
- No permite reconstruir el origen ni cruzarlo con otra fuente.
- **La sal es fija y vive en configuración.** Si rota, los límites se pierden en
  silencio, que es peor que no tenerlos: nadie se entera. Queda escrito acá que
  no rota, y quién es su dueño.

El límite cuenta contra la fila del inquilino, que sobrevive al borrado (RFC-09).
Sin eso, bastaría esperar a que expire para volver a empezar.

## Qué pasa al llegar al límite

El registro responde con un mensaje que dice **qué pasó y cuándo se puede volver
a intentar**. No un error genérico: un visitante que quería probar el producto y
recibe "algo salió mal" no vuelve.

## Reglas

1. Ningún límite se aplica en el trabajo en cola. Se aplican en el registro,
   antes de encolar: encolar altas que van a fallar es acumular basura.
2. El tope duro se comprueba contra el conteo real de inquilinos activos, no
   contra un contador que se pueda desincronizar.
3. Los límites se pueden bajar sin desplegar.

## Definition of Done

- Medido y anotado en `docs/deployment/` cuántas bases sostiene el VPS.
- El plazo de vida está en configuración y su valor está justificado por esa
  medición.
- Un test verifica que el mismo origen no supera su límite, incluso después de
  que sus inquilinos anteriores se borraron.
- Un test verifica que al llegar al tope duro el registro responde con el mensaje
  correcto y no encola nada.
