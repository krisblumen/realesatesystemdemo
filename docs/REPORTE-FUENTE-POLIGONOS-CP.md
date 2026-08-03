# Reporte: Fuente de polígonos de códigos postales (MX)

**Para:** Edgar
**Contexto:** Feature "Zonas por Código Postal" (Épica 3) — de dónde salen los polígonos de los CP.
**Estado:** Fuente confirmada y validada con datos reales.

---

## TL;DR

- **Fuente elegida:** [`open-mexico/mexico-geojson`](https://github.com/open-mexico/mexico-geojson) — GeoJSON por estado, licencia MIT, gratis y vivo.
- **Campo del código postal:** `d_codigo` (confirmado inspeccionando un archivo real).
- **Granularidad:** cada *feature* ya es un **código postal** (no colonia). En Querétaro: 518 features = 518 CP. La mayoría `Polygon`; **10 CP ya vienen como `MultiPolygon`** (zonas disjuntas) → por eso la tabla debe ser `MultiPolygon`.
- **Base tabular de apoyo:** padrón SEPOMEX en Excel (la que adjunto) — sirve para validar CP y enlazar `municipality_id`.

---

## La fuente

32 archivos GeoJSON, uno por estado, en la raíz del repo:

```
01-Ags.geojson, 02-Bc.geojson, ... , 32-Zac.geojson
```

Descarga directa (raw):

```
https://raw.githubusercontent.com/open-mexico/mexico-geojson/main/22-Qro.geojson
```

### Estructura real de cada feature

```json
{
  "type": "Feature",
  "properties": { "d_codigo": "76950" },
  "geometry": { "type": "Polygon", "coordinates": [ ... ] }
}
```

La **única** propiedad es `d_codigo` (el CP como string de 5 dígitos). No trae nombre de colonia → el dataset ya viene **agregado por código postal**.

---

## Implicación para el importador

- `CP_PROPERTY = 'd_codigo'` (esto resuelve el "PASO 0 bloqueante" del plan).
- Como cada feature suele ser 1 CP, la carga es casi directa. En Querétaro: 518 features = 518 CP únicos, **0 fragmentados**.
- **La geometría de entrada puede ser `Polygon` o `MultiPolygon`** (en QRO hay 508 `Polygon` y 10 `MultiPolygon`). El importador debe normalizar todo a `MultiPolygon` con `ST_Multi(...)`.
- **Salvaguarda:** en estados grandes (CDMX, Edomex) un mismo `d_codigo` podría aparecer en **varias** features (CP fragmentado). El importador debe **agrupar por `d_codigo` y unir** (`ST_Collect` → `MultiPolygon`). El diseño de `postal_code_areas` (columna `geometry(MultiPolygon,4326)` + upsert por `postal_code`) ya contempla esto.

### Flujo de carga sugerido

```
Para cada archivo NN-XXX.geojson:
  agrupar features por properties.d_codigo
  por cada CP:
    geom = ST_Multi(ST_SetSRID(ST_Collect(ST_GeomFromGeoJSON(...)), 4326))
    upsert en postal_code_areas (postal_code = d_codigo, polygon = geom)
    municipality_id = lookup best-effort por CP (usando el Excel/padrón SEPOMEX)
```

---

## El Excel (padrón SEPOMEX) — para qué sirve

El Excel adjunto (CP + estado + municipio + colonia) **no tiene geometría**, pero es el catálogo oficial de referencia:

- Validar qué CP existen y descartar ruido del GeoJSON.
- Enlazar cada CP con su `municipality_id` (el linkage `d_codigo` → municipio).

Geometría = open-mexico. Metadatos (estado/municipio/colonia) = Excel SEPOMEX. Se unen por el CP.

---

## Descartadas (para que no pierdas tiempo)

| Fuente | Por qué no |
|---|---|
| `inigoflores/ds-codigos-postales` | Es de **España**, no México. |
| `datos.gob.mx` (polígonos por CP, oficial) | Conceptualmente ideal, pero está en el portal "histórico" y no se pudo verificar que las descargas sigan vivas. Queda como plan B si se quiere provenance oficial. |

---

## Próximo paso

Implementar `geo:import-postal-codes` según el plan en
`openspec/changes/zonas-por-codigo-postal/` (rama `feature/epica-3-geografia-poligonos`),
usando `d_codigo` como campo de CP.
