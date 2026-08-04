# Auditoría de diseño — RFC-062 — Control de Lonas Asignadas

## Veredicto
**Aprobado con observaciones.** La especificación del RFC-062 es sólida, introduce un flujo operativo bien modelado y respeta los contratos de las épicas previas (como la obtención de datos de contacto de `User`, y el `scopePublished` y `canonical` de `Property`). Sin embargo, presenta vacíos críticos en la lógica de negocio y en la seguridad (como condiciones de carrera en solicitudes, falta de políticas de Filament, y vulnerabilidades de carga de archivos en el componente Livewire) que deben corregirse antes de pasar a la fase de implementación.

---

## Hallazgos críticos

### 1. Condición de carrera y solicitudes concurrentes redundantes
- **Ubicación:** Sección 5.3 (`LonaEligibilityService`) y 5.7 (`LonaRequestResource`).
- **Impacto:** Alto. `canRequestMore` solo evalúa si existen `LonaUnit` en estado `pendiente_colocacion`. Como las unidades físicas no se crean hasta que la solicitud es aprobada (`LonaBatchApprovalService::grant`), un agente con 0 unidades pendientes puede enviar múltiples solicitudes redundantes de forma consecutiva o concurrente del mismo tipo (venta/renta). Esto sobrecarga al administrador y evade el control de reposición.
- **Corrección:** Modificar `LonaEligibilityService::canRequestMore()` para verificar y denegar la solicitud si ya existe una `LonaRequest` del mismo tipo en estado `pendiente` para ese agente.

### 2. Falta de Policy de autorización para `LonaBatchResource`
- **Ubicación:** Sección 5.7 y Árbol de archivos.
- **Impacto:** Alto. El RFC detalla la creación de `LonaUnitPolicy` y `LonaRequestPolicy`, pero omite `LonaBatchPolicy`. Filament resuelve la autorización de sus Resources utilizando las Policies de Laravel basadas en el modelo asociado. Sin esta Policy, el acceso a `LonaBatchResource` podría quedar completamente desprotegido o con un comportamiento inconsistente según la configuración global del panel.
- **Corrección:** Agregar `app/Policies/LonaBatchPolicy.php` al árbol de archivos de la especificación y al plan de implementación del Lote A, aplicando el gate estricto del permiso `lonas.manage`.

---

## Hallazgos medios

### 1. Imposibilidad de asociar propiedad o ubicación en la colocación (Lote genérico)
- **Ubicación:** Sección 5.2 (migración `lona_units`), 5.4 (`CapturePlacementEvidence`), y 5.6 (`AgentLonas`).
- **Impacto:** Medio/Alto. Si un lote de lonas se entrega de forma genérica (`property_id = null` en el lote), las unidades se crean sin propiedad asociada. Sin embargo, el componente Livewire de captura de evidencia de colocación solo recibe la foto (`photoData`), sin ofrecer campos para que el agente asocie la lona a un inmueble de su cartera o ingrese una dirección en `ubicacion_referencia`. Esto hace que los campos `property_id` y `ubicacion_referencia` en `LonaUnit` queden nulos de por vida, anulando la trazabilidad de dónde se colocó cada lona física.
- **Corrección:** Rediseñar `CapturePlacementEvidence` para que, en caso de que la unidad no tenga una propiedad pre-asignada, exponga un selector opcional de propiedades (filtrado a las propiedades publicadas del agente) y un campo de texto para `ubicacion_referencia`.

### 2. Validación débil y riesgo de denegación de servicio (OOM) en la carga de Base64
- **Ubicación:** Sección 5.4 (`CapturePlacementEvidence`).
- **Impacto:** Medio. Validar únicamente con `starts_with:data:image/` permite a un usuario malintencionado enviar payloads gigantescos (ej. 50MB o más) que agoten la memoria RAM del servidor al decodificar el Base64 en `addMediaFromBase64()`. Además, abre la puerta a subir tipos MIME no deseados o potencialmente peligrosos (como imágenes SVG con scripts de XSS inyectados).
- **Corrección:** Cambiar la regla de validación de `photoData` para limitar su tamaño en caracteres y restringir los formatos a tipos binarios comunes seguros:
  ```php
  'photoData' => ['required', 'string', 'starts_with:data:image/jpeg;base64,data:image/png;base64,', 'max:7000000']
  ```
  *(7M de caracteres equivale aproximadamente a un archivo binario de ~5MB).*

### 3. Falta de validación de rol y estado activo en la asignación de lotes
- **Ubicación:** Sección 5.5 (`LonaBatchApprovalService::grant`).
- **Impacto:** Medio. El servicio de asignación recibe un `User $agent` pero no valida que el usuario realmente posea el rol de `agente` y esté en estado `active`. Un administrador podría asignar por error un lote a un administrador, owner, o a un agente suspendido, lo cual afectaría la lógica del round-robin y la integridad de los datos.
- **Corrección:** Agregar una validación explícita en `LonaBatchApprovalService::grant()` que compruebe que el `$agent` esté activo y tenga el rol requerido antes de proceder a la inserción.

---

## Hallazgos menores

### 1. Ausencia de límite máximo en el tamaño de lote (cantidad)
- **Ubicación:** Sección 5.2 y 5.7.
- **Impacto:** Bajo. Si no se limita el campo `cantidad` al crear un lote, un error de digitación (ej. ingresar 5000 en lugar de 5) provocará la inserción masiva de miles de unidades en la base de datos, ralentizando el sistema.
- **Corrección:** Añadir una validación en Filament y en el servicio que limite la cantidad de lonas por lote a un máximo razonable (ej. `max:50`).

---

## Sobreingeniería
- **Modelo de 3 tablas:** **No hay sobreingeniería.** La separación entre solicitudes (`LonaRequest`), lotes autorizados (`LonaBatch`) y unidades físicas individuales (`LonaUnit`) está plenamente justificada. Esto permite registrar solicitudes rechazadas (sin crear lotes ni unidades) y mantener la justificación unitaria de cada lona, clave para la auditoría operacional.

---

## Riesgos de implementación
- **Dependencia de la plantilla base de la lona (Diseño Gráfico):** Al no contar con la plantilla final, la visualización del PDF puede desfasarse. La mitigación propuesta de utilizar un placeholder con dimensiones equivalentes es la correcta y reduce el acoplamiento.

---

## Riesgos de seguridad
- **Seguridad en la captura ("Sólo Cámara"):** Se confirma que el uso de `<video>` y `<canvas>` elimina de forma efectiva el selector de archivos nativo del navegador, mitigando la falsificación trivial. Se acepta el riesgo residual de inyección de feed mediante cámaras virtuales (OBS, virtual cams, etc.) debido a las limitaciones tecnológicas del sandbox del navegador.

---

## Recomendaciones obligatorias
1. **Verificar solicitudes pendientes:** En `LonaEligibilityService`, comprobar que el agente no tenga solicitudes en estado `pendiente` del mismo tipo antes de permitir una nueva.
2. **Crear `LonaBatchPolicy`:** Incorporar la política para el recurso de lotes y asociarla al modelo en Filament.
3. **Selector de Propiedad en la Evidencia:** Exponer campos en la interfaz de Livewire para asociar la propiedad (`property_id` o `ubicacion_referencia`) si la lona fue entregada genéricamente.
4. **Validación estricta de Base64:** Acotar el string base64 en tamaño y formatos MIME específicos (JPEG/PNG).

## Recomendaciones opcionales
1. **Deshabilitar Force Delete:** En `LonaUnitPolicy` y `LonaRequestPolicy`, retornar `false` en `forceDelete` de forma explícita, manteniendo únicamente el soft delete para trazabilidad histórica.

---

## Evaluación de Decisiones Diferidas (CD-1 a CD-6)
- **CD-1 (Épica de registro):** *De negocio.* Recomendamos anexarla a la Épica 7 (Comercialización) ya que la colocación de lonas físicas es una acción publicitaria directa para la venta/renta de inmuebles.
- **CD-2 (Confirmar endroid/qr-code):** **Cerrada técnicamente.** Se confirma que `endroid/qr-code` es una dependencia liviana, compatible con dompdf mediante data URIs, y no genera conflictos en el entorno.
- **CD-3 (Georreferenciación GPS):** *De negocio.* Si bien es deseable, no incluirla en esta fase reduce la fricción de uso en dispositivos con GPS inestable.
- **CD-4 (Rango de IDs QA):** *De proceso.* Se resolverá al momento de integrar en el plan de pruebas principal.
- **CD-5 (Cantidad máxima de lonas):** **Cerrada técnicamente.** Se recomienda fijar el límite en un máximo de `50` unidades por lote.
- **CD-6 (Tamaño físico real de la lona):** *De negocio/diseño.* Se resolverá junto con el asset final de la plantilla (R-1).

---

## Preguntas abiertas
1. ¿El PDF de la lona debe generarse nuevamente si el agente reasocia la `LonaUnit` a una propiedad diferente al momento de colocarla en el campo, o el diseño de la lona física genérica no lleva QR en absoluto?

---

## Checklist de corrección para Claude
- [ ] Incorporar `LonaBatchPolicy` al árbol de archivos y a las secciones correspondientes del diseño técnico.
- [ ] Modificar `LonaEligibilityService::canRequestMore()` para incluir el chequeo de solicitudes en estado `pendiente`.
- [ ] Actualizar el diseño del componente Livewire `CapturePlacementEvidence` para permitir ingresar `property_id` u `ubicacion_referencia` cuando la lona pertenezca a un lote genérico.
- [ ] Refinar la regla de validación de `photoData` en `CapturePlacementEvidence`.
- [ ] Agregar validación de rol activo en `LonaBatchApprovalService::grant()`.

---

## Checklist de implementación para Codex
- [ ] Crear la migración `create_lona_batches_table` con el índice compuesto de rendimiento.
- [ ] Implementar los modelos con sus relaciones y traits de Media Library.
- [ ] Crear las tres Policies correspondientes (`LonaBatchPolicy`, `LonaUnitPolicy`, `LonaRequestPolicy`).
- [ ] Configurar el componente Livewire con captura mediante canvas sin inputs de tipo archivo.
- [ ] Integrar `endroid/qr-code` y comprobar la renderización en el PDF generado por dompdf.
