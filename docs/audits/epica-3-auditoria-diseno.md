# Auditoría de diseño — Épica 3 — Catálogo Geográfico y Polígonos Automáticos

## Veredicto: Aprobado con observaciones

El diseño para la reestructuración geográfica y persistencia de polígonos comerciales es sólido y cumple con los estándares exigidos para el proyecto. Sin embargo, se identifican puntos de mejora en relación con la precisión espacial y la prevención de pérdida de datos.

## Hallazgos críticos

*   **Ninguno.** No se encontraron fallas graves de arquitectura que bloqueen el diseño general.

## Hallazgos medios

1.  **Cálculo de center_point con ST_Centroid en polígonos cóncavos:** El uso de `ST_Centroid` para derivar el centro geométrico del polígono presenta problemas cuando el polígono es cóncavo (por ejemplo, en forma de "U" o media luna), ya que el centroide calculado puede quedar fuera del límite físico de la zona. Esto afectaría negativamente la UX al colocar marcadores o etiquetas automáticas.
2.  **Riesgo de pérdida de datos al dropear 'municipality':** Eliminar de golpe la columna tipo string `municipality` de la tabla `zones` sin una migración previa de datos (script de transformación de string a `municipality_id` relacional) causará pérdida de información en entornos donde ya existan zonas registradas.

## Hallazgos menores

1.  **Falta de validación explícita de geocodificación en backend:** Aunque el mapa realiza la geocodificación y centrado de forma reactiva en la UI, el backend no valida si el polígono resultante se encuentra dentro de las coordenadas geográficas aproximadas del municipio o estado seleccionado.

## Sobreingeniería detectada

*   **Ninguna.** El diseño mantiene una separación limpia entre el transporte del GeoJSON en el formulario y la persistencia en PostGIS. La dependencia de `clickbar/laravel-magellan` se ha integrado como un cast del modelo, lo que resulta coherente con las convenciones modernas de Laravel.

## Riesgos de implementación

1.  **Actualización de datos históricos:** Si existen registros previos de zonas comerciales con el campo `municipality` lleno, es mandatorio ejecutar un script de mapeo para evitar perder la relación municipal al correr la migración en ambientes superiores.

## Riesgos de seguridad

*   **Bajo.** La API key de Google Maps se maneja a través de archivos de configuración y variables de entorno. Para producción, es obligatorio restringir la API key por HTTP referrers (dominios permitidos).

## Recomendaciones obligatorias

1.  **Cambiar ST_Centroid por ST_PointOnSurface:** Para asegurar que el punto central de la zona siempre caiga dentro de los límites del polígono (independientemente de si es cóncavo o convexo), se debe utilizar `ST_PointOnSurface(polygon)` en lugar de `ST_Centroid(polygon)`.
2.  **Asegurar idempotencia y transaccionalidad:** Mantener el importador bajo una transacción de base de datos (`DB::transaction`) y asegurar que los dumps utilicen `updateOrCreate` basados en el campo `source_id` para garantizar que la importación se pueda repetir sin duplicados.

## Recomendaciones opcionales

1.  **Restricción espacial futura:** Evaluar a mediano plazo una validación espacial tipo `ST_Within` para asegurar que el polígono dibujado por el usuario no se salga de los límites del municipio seleccionado.

## Preguntas abiertas

1.  ¿Se cuenta con zonas reales en producción que tengan el campo `municipality` string con información activa? Si es así, se debe coordinar un script de migración manual antes de desplegar el cambio de esquema.

## Checklists para Codex

- [x] Crear tablas `countries`, `states` y `municipalities` con compatibilidad PostgreSQL.
- [x] Configurar `nullOnDelete` en las llaves foráneas de `zones` hacia estados y municipios.
- [x] Instalar y validar la compatibilidad de `clickbar/laravel-magellan`.
- [x] Implementar geocodificación en frontend consumiendo la API de Google Maps de forma confinada.
- [x] Asegurar conversión limpia de GeoJSON a geometría PostGIS en el límite de la base de datos.
