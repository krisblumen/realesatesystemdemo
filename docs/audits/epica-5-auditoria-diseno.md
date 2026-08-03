# Auditoría de diseño — Épica 5 — Leads

## Veredicto
**Aprobado con observaciones.** El diseño técnico está bien estructurado y sigue la arquitectura monolítica de New Hauz. Sin embargo, presenta acoplamientos prematuros con la Épica 3 (Zonas), ambigüedades en la lógica del Round-Robin y riesgos de seguridad importantes en la autorización de reasignación y sanitización de datos que deben subsanarse antes de iniciar la implementación.

## Hallazgos críticos
1. **Falta de restricción en la reasignación de Leads a nivel backend (Riesgo de Seguridad):**
   - *Detalle:* El diseño menciona que los agentes solo gestionan sus leads y que los owner/admin pueden reasignar. No obstante, no especifica que el backend deba validar y bloquear cualquier intento de modificación del campo `agent_id` por parte de un usuario con el rol de `agente` (en la policy o form request). Si el `LeadResource` expone el campo o permite su actualización, un agente malintencionado podría secuestrar leads modificando la petición HTTP.
2. **Acoplamiento prematuro con la Épica 3 en la migración de base de datos (Deuda Técnica):**
   - *Detalle:* Añadir el campo `zone_id` a la migración `create_leads_table` antes de que la Épica 3 esté terminada y mergeada viola el principio YAGNI y de autonomía de módulos. La estructura de la relación con zonas aún no está consolidada (podría ser polimórfica, N:M, etc.).

## Hallazgos medios
1. **Estrategia de Round-Robin indeterminada y poco performante:**
   - *Detalle:* La especificación plantea elegir en implementación entre "menor carga reciente" o "turno persistido". Evaluar la menor carga mediante consultas de agregación (`COUNT`) sobre la tabla de leads degrada el rendimiento a medida que la base de datos escala y genera problemas de concurrencia/empates.
2. **Inexistencia de un mecanismo de reconciliación para fallos de asignación:**
   - *Detalle:* El desacoplamiento vía evento `LeadCaptured` es correcto, pero si el listener asíncrono falla (caída del queue worker, error de BD temporal), el lead quedará huérfano (en estado `nuevo` y con `agent_id` nulo) de forma permanente, sin que ningún agente lo reciba.
3. **Implementación de Rate Limiting (Throttle) en Livewire:**
   - *Detalle:* No se especifica cómo se implementará el throttle en Livewire. Los componentes Livewire no se acceden por rutas normales de Laravel, sino por peticiones AJAX internas. Un throttle mal configurado a nivel de ruta global de Livewire afectaría a toda la aplicación.

## Hallazgos menores
1. **Borrado físico no deshabilitado explícitamente:**
   - *Detalle:* El recurso de Filament (`LeadResource`) incluye por defecto acciones de borrado físico (`ForceDeleteAction`) si no se configuran para deshabilitarlo. Se debe explicitar que los agentes y administradores no tengan permitido realizar borrado físico bajo ninguna circunstancia.
2. **Nomenclatura inconsistente entre el Prompt y los RFCs:**
   - *Detalle:* Aunque se ratifica el uso de `agent_id` (en vez de `assigned_user_id`) y los estados del prompt (`en_seguimiento`, `cerrado_ganado`, `cerrado_perdido`), esta decisión no está reflejada en los RFCs de origen. Se debe estandarizar y registrar formalmente para el Codex.

## Sobreingeniería detectada
1. **Pre-estructurar base de datos para Épica 3 (Zonas):**
   - Guardar espacio en la migración para `zone_id` añade complejidad innecesaria. Es preferible que la Épica 3 agregue sus propios campos en sus migraciones correspondientes cuando sea su momento.

## Riesgos de implementación
1. **Saturación del sistema de colas por fallos de correo:**
   - Si las notificaciones de correo fallan y se encolan en el mismo canal que otras tareas prioritarias del sistema sin reintentos limitados, se corre el riesgo de bloquear la cola de procesamiento.
2. **Dificultad de testeo unitario/integración del Round-Robin:**
   - Si el round-robin depende de la caché global o de registros cambiantes de BD, los tests podrían fallar debido a efectos secundarios no controlados (side-effects).

## Riesgos de seguridad
1. **Ataques de XSS almacenado (Stored XSS):**
   - El campo `message` y `name` capturados públicamente no tienen sanitización especificada. Al ser visualizados por administradores y agentes dentro del panel de Filament, podrían ejecutar scripts maliciosos si no se escapan/sanitizan en el backend.
2. **Inyección de `property_id` no válidos:**
   - En el formulario público por propiedad, se envía el ID de la propiedad de manera oculta. El sistema debe validar que la propiedad realmente exista, esté activa y tenga un agente asignado para evitar la creación de leads corruptos o huérfanos dirigidos a propiedades fantasma.

## Recomendaciones obligatorias
1. **Eliminar `zone_id`** de la migración `create_leads_table`. La Épica 3 se encargará de crear su propia migración para agregar este campo cuando se implemente.
2. **Asegurar la restricción de modificación del `agent_id` en la Policy:**
   - En `LeadPolicy@update`, denegar el cambio de `agent_id` si el usuario no tiene el rol de `owner` o `admin`.
3. **Definir estrategia de Round-Robin por Turno Persistido (determinista):**
   - Utilizar el agente con la asignación más antigua (`User::whereStatus('active')->whereHasRole('agente')->withMin('leads', 'assigned_at')->orderBy('leads_assigned_at_min', 'asc')` o un puntero en caché) para garantizar equidad sin penalizar el rendimiento con un `COUNT` masivo.
4. **Agregar sanitización de inputs:**
   - Aplicar desinfección HTML a los campos `name` y `message` del Lead antes de guardarlos (usando purificadores como HTMLPurifier o la sanitización nativa de Laravel/PHP).
5. **Crear comando Artisan de reconciliación:**
   - Implementar un comando `leads:reconcile` que se ejecute en el scheduler para buscar leads creados hace más de X minutos con `status = nuevo` y `agent_id IS NULL`, y forzar su asignación automática.
6. **Implementar Throttle específico en Livewire:**
   - Aplicar el rate limiting usando el helper `RateLimiter` de Laravel o el decorator `#[Locked]` de Livewire en el método de envío del componente Livewire público.

## Recomendaciones opcionales
1. **Bloquear Force Delete en Filament:**
   - Configurar el recurso `LeadResource` eliminando explícitamente `ForceDeleteAction` y `ForceDeleteBulkAction`.
2. **Configurar notificaciones con colas dedicadas:**
   - Enviar las notificaciones de leads asignados a una cola secundaria (`leads-notifications`) para evitar el bloqueo del hilo principal de trabajo.

## Preguntas abiertas
1. ¿El administrador o el owner deben recibir un correo resumen diario de leads no asignados en caso de que no haya agentes activos, o basta con que los vean en su bandeja de Filament?
2. ¿Se debe permitir que los agentes marquen los leads directamente como "cerrado_ganado" o "cerrado_perdido" sin pasar previamente por "en_seguimiento"?

## Checklist de corrección para Claude
- [ ] Remover el campo `zone_id` de la migración `create_leads_table`.
- [ ] Agregar la lógica de validación de propiedad activa en el Form Request / Componente Livewire de captura.
- [ ] Definir la validación estricta de reasignación de agentes en `LeadPolicy`.
- [ ] Implementar la sanitización de inputs públicos antes de persistir el modelo `Lead`.

## Checklist de implementación para Codex
- [ ] Crear la migración `create_leads_table` sin `zone_id`.
- [ ] Implementar los enums `LeadSource` y `LeadStatus` en PHP 8.3.
- [ ] Crear el componente Livewire público de contacto con Honeypot e implementar throttle por IP en el método del componente.
- [ ] Crear el servicio `LeadAssignmentService` con la estrategia de round-robin por último lead asignado.
- [ ] Crear el comando Artisan `leads:reconcile` y registrarlo en el scheduler para ejecutarse cada 10 minutos.
- [ ] Implementar `LeadPolicy` asegurando que los agentes solo vean sus registros (`getEloquentQuery` en Filament) y no puedan reasignar.
