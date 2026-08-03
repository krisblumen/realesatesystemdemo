# RFC de la Épica DEMO

RFC del demo multi-inquilino. Van en carpeta propia porque este es un producto
distinto del que se copió el código: la numeración arranca de cero y no continúa
la de `docs/rfc/`.

Documentos de referencia:

- `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md` — la épica a nivel producto.
- `docs/epicas/epica-demo-multi-inquilino.md` — el diseño técnico consolidado.
- `docs/audits/epica-demo-auditoria-diseno.md` — la auditoría de diseño.

## Dos fases

El demo **arranca por invitación**, no abierto al público. Eso separa los RFC en
dos grupos, y la línea que los separa no es el tamaño del trabajo sino esto:

> **Lo estructural va ahora. Lo aditivo va cuando haga falta.**

El aislamiento —caché con prefijo, rutas de archivo con inquilino, resolución
antes de la sesión, trabajos que no heredan conexión— es estructural. Agregarlo
después obliga a recorrer cada clave de caché, cada ruta de medios y cada trabajo
que existan para entonces. Y **con dos inquilinos ya se pisan**: no es una
protección que dependa del volumen.

El registro público, los límites de abuso y la cola del alta son aditivos.
Existen porque cualquiera puede entrar. Con invitación, la invitación es el
límite.

## Índice

### Fase 1 — por invitación

| RFC | Título | Lote | Cierra |
|---|---|---|---|
| 01 | [Base central y modelo de inquilino](RFC-01-BASE-CENTRAL-Y-MODELO-DE-INQUILINO.md) | A | — |
| 02 | [Caso base de pruebas con inquilinos](RFC-02-CASO-BASE-DE-PRUEBAS-CON-INQUILINOS.md) | A | C-9, crítico C-3 |
| 03 | [Colas ancladas a la central](RFC-03-COLAS-ANCLADAS-A-LA-CENTRAL.md) | A | C-4 |
| 04 | [Plantilla versionada](RFC-04-PLANTILLA-VERSIONADA.md) | B | C-6, medio M-5 |
| 05 | [Alta de inquilino](RFC-05-ALTA-DE-INQUILINO.md) | C | C-5, C-8, críticos C-1 y C-2 |
| 13 | [Invitación](RFC-13-INVITACION.md) | C | reemplaza a 11 en fase 1 |
| 06 | [Resolución de inquilino por subdominio](RFC-06-RESOLUCION-DE-INQUILINO-POR-SUBDOMINIO.md) | D | C-1 del diseño, menor Mn-1 |
| 07 | [Aislamiento de caché](RFC-07-AISLAMIENTO-DE-CACHE.md) | E | C-2 del diseño |
| 08 | [Aislamiento de archivos](RFC-08-AISLAMIENTO-DE-ARCHIVOS.md) | E | C-3 del diseño |
| 14 | [Entorno cerrado](RFC-14-ENTORNO-CERRADO.md) | D | — |
| 09 | [Expiración y borrado](RFC-09-EXPIRACION-Y-BORRADO.md) | F | C-7, medio M-1 |
| 12 | [Padrón del operador](RFC-12-PADRON-DEL-OPERADOR.md) | transversal | medio M-4 |

### Fase 2 — cuando el demo se abra

| RFC | Título | Cierra |
|---|---|---|
| 10 | [Límites de abuso y plazo de vida](RFC-10-LIMITES-DE-ABUSO-Y-PLAZO-DE-VIDA.md) | medio M-2, menor Mn-2 |
| 11 | [Registro público y entrega de acceso](RFC-11-REGISTRO-PUBLICO-Y-ENTREGA-DE-ACCESO.md) | medio M-3 |

## Orden de lectura

Para implementar fase 1: 01, 02, 03 → 04 → 05, 13 → 06, 14 → 07, 08 → 09. El 12
entra cuando haya inquilinos que mirar.

**RFC-02 no se saltea.** Es el andamiaje de pruebas, y los otros contratos de la
épica se verifican con esa pieza. Sin ella todo lo que sigue se implementa a
ciegas.

## Qué cambió al pasar a invitación

- **RFC-11 sale de fase 1** y lo reemplaza RFC-13: un comando de consola que
  invita, aprovisiona e imprime el acceso. Desaparece el correo como punto de
  falla, que era el hallazgo M-3.
- **RFC-10 sale de fase 1** completo. La invitación es el límite.
- **`origen_hash` queda vacío** (RFC-01). La columna existe para no migrar
  después, pero no se llena: guardar un dato personal que nadie usa es guardarlo
  por nada.
- **La cola del alta deja de ser necesaria**, pero RFC-03 se mantiene: el sistema
  ya tiene trabajos en segundo plano y cualquiera que toque datos de un inquilino
  tiene el mismo problema de conexión heredada.
- **El cerrojo se mantiene en versión mínima** (RFC-05 sección 2). Son pocas
  líneas y convierten un error confuso de Postgres en un mensaje claro.
- **La validación del `slug` no se toca.** Baja de gravedad, no desaparece: sigue
  siendo interpolación de un identificador en DDL ejecutada por un rol que puede
  borrar bases.

## Lo que sigue abierto

- Los números de RFC-10 dependen de medir el VPS. En fase 1 el plazo lo fija
  quien invita (RFC-13).
- Falta la auditoría de diseño con contexto fresco.
- ~~A revisar: si hace falta el certificado comodín.~~ **Resuelto**: el VPS ya
  existe y la frontera sigue siendo el subdominio, así que el comodín se emite
  con certbot y validación DNS-01. Se evaluó un solo host con el inquilino en la
  sesión y se descartó: ahorra el comodín una vez y apoya la frontera en el orden
  de middleware para siempre.
