# RFC de la Épica DEMO

RFC del demo público multi-inquilino. Van en carpeta propia porque este es un
producto distinto del que se copió el código: la numeración arranca de cero y no
continúa la de `docs/rfc/`.

Documentos de referencia:

- `docs/rfc/EPICA-DEMO-MULTI-INQUILINO.md` — la épica a nivel producto.
- `docs/epicas/epica-demo-multi-inquilino.md` — el diseño técnico consolidado.
- `docs/audits/epica-demo-auditoria-diseno.md` — la auditoría de diseño.

## Índice

| RFC | Título | Lote | Cierra |
|---|---|---|---|
| 01 | [Base central y modelo de inquilino](RFC-01-BASE-CENTRAL-Y-MODELO-DE-INQUILINO.md) | A | — |
| 02 | [Caso base de pruebas con inquilinos](RFC-02-CASO-BASE-DE-PRUEBAS-CON-INQUILINOS.md) | A | C-9, crítico C-3 |
| 03 | [Colas ancladas a la central](RFC-03-COLAS-ANCLADAS-A-LA-CENTRAL.md) | A | C-4 |
| 04 | [Plantilla versionada](RFC-04-PLANTILLA-VERSIONADA.md) | B | C-6, medio M-5 |
| 05 | [Alta de inquilino](RFC-05-ALTA-DE-INQUILINO.md) | C | C-5, C-8, críticos C-1 y C-2 |
| 06 | [Resolución de inquilino por subdominio](RFC-06-RESOLUCION-DE-INQUILINO-POR-SUBDOMINIO.md) | D | C-1 del diseño, menor Mn-1 |
| 07 | [Aislamiento de caché](RFC-07-AISLAMIENTO-DE-CACHE.md) | E | C-2 del diseño |
| 08 | [Aislamiento de archivos](RFC-08-AISLAMIENTO-DE-ARCHIVOS.md) | E | C-3 del diseño |
| 09 | [Expiración y borrado](RFC-09-EXPIRACION-Y-BORRADO.md) | F | C-7, medio M-1 |
| 10 | [Límites de abuso y plazo de vida](RFC-10-LIMITES-DE-ABUSO-Y-PLAZO-DE-VIDA.md) | F | medio M-2, menor Mn-2 |
| 11 | [Registro público y entrega de acceso](RFC-11-REGISTRO-PUBLICO-Y-ENTREGA-DE-ACCESO.md) | G | medio M-3 |
| 12 | [Padrón del operador](RFC-12-PADRON-DEL-OPERADOR.md) | transversal | medio M-4 |

## Orden de lectura

Si vas a implementar, el orden es el de los lotes: 01, 02, 03 → 04 → 05 → 06 →
07, 08 → 09, 10 → 11. El 12 entra cuando haya inquilinos que mirar.

**RFC-02 no se saltea.** Es el andamiaje de pruebas, y los otros ocho contratos
de la épica se verifican con esa pieza. Sin ella todo lo que sigue se implementa
a ciegas.

## Lo que sigue abierto

- El menor **Mn-3** de la auditoría (`template_version` se guarda y nadie la
  usa): RFC-12 le da uso — se muestra en el padrón. Queda cerrado por esa vía.
- Los números de RFC-10 dependen de medir el VPS.
- Falta la auditoría de diseño con contexto fresco.
