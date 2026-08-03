# ADR-002 - PostgreSQL + PostGIS

## Estado
Aprobado

## Contexto
New Hauz operará inmuebles en Querétaro y requiere búsquedas por zona, coordenadas, polígonos comerciales y ubicación aproximada.

## Decisión
Usar PostgreSQL 16 como motor principal de base de datos y PostGIS como extensión geoespacial.

## Justificación
PostgreSQL + PostGIS permite:

- Consultas geográficas.
- Polígonos de zonas comerciales.
- Búsqueda por cercanía.
- Escalabilidad relacional.
- Integridad de datos.
- Compatibilidad con Laravel.

## Uso Inicial
- Zonas comerciales.
- Ubicación de inmuebles.
- Asignación geográfica de agentes.
- Filtros por zona.

## Reglas Técnicas
- No usar SQLite para entornos formales.
- Staging y producción deben usar PostgreSQL.
- Docker local debe incluir imagen PostGIS.

## Consecuencias
Los tests de geolocalización deben ejecutarse contra PostgreSQL/PostGIS.
