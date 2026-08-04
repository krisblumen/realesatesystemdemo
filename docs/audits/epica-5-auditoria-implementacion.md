# Auditoría de implementación — Épica 5 — Leads

## Veredicto
**Aprobado con correcciones.** La funcionalidad básica de la Épica 5 (Leads) está bien implementada, con desacoplamiento mediante eventos, transacciones robustas y cobertura completa de pruebas (100% verde). No obstante, existen hallazgos de seguridad y lógica de negocio importantes en la creación manual de leads por agentes, el manejo del Honeypot, el flujo del Rate Limiter en Livewire y la optimización de la base de datos que deben ser resueltos antes del merge a `develop`.

## Hallazgos críticos
1. **Pérdida de control y visibilidad en leads creados manualmente por Agentes (Lógica de Negocio):**
   - *Detalle:* El recurso `LeadResource` permite la creación manual de leads a usuarios con el permiso `leads.manage` (que incluye el rol de `agente`). Sin embargo, en el formulario de creación, el campo `agent_id` está oculto para los agentes (solo visible para owners/admins). Al no tener una pre-asignación automática para el creador del registro, el lead se guarda con `agent_id = null`. Como consecuencia, el lead entra en un limbo invisible para el agente creador (ya que su query de Filament solo muestra leads con su ID) y eventualmente la tarea de reconciliación lo asignará vía round-robin a *otro* agente diferente.
   - *Ruta afectada:* [CreateLead.php](file:///Volumes/MacStudio2/Develop/newhauz/app/Filament/Resources/LeadResource/Pages/CreateLead.php)
2. **Rate Limiter (Throttle) ineficaz ante peticiones de Livewire inválidas (Rendimiento/Seguridad):**
   - *Detalle:* En `LeadCaptureForm@submit`, la llamada a `RateLimiter::hit(...)` se realiza *después* de `$this->validate()`. Dado que la validación de Livewire lanza una `ValidationException` que interrumpe inmediatamente el flujo, cualquier petición que falle la validación (p. ej., formato de email incorrecto o honeypot completado) no incrementará el contador del limitador. Un bot de spam puede saturar el servidor enviando miles de peticiones mal formadas sin ser bloqueado por IP.
   - *Ruta afectada:* [LeadCaptureForm.php](file:///Volumes/MacStudio2/Develop/newhauz/app/Livewire/Leads/LeadCaptureForm.php)

## Hallazgos medios
1. **Falta de índices de base de datos para la agregación del Round-Robin (Rendimiento):**
   - *Detalle:* El método `leastLoadedAgent` ejecuta una consulta agrupada sobre la tabla de leads filtrando por `assigned_at >= $since`. Sin embargo, la migración de la tabla `leads` no añade un índice sobre `assigned_at` ni un índice compuesto `(agent_id, assigned_at)`. A medida que crezca la tabla, esta consulta agrupada sin índices provocará un escaneo completo (table/index scan) que degradará el rendimiento del sistema de asignaciones.
   - *Ruta afectada:* [2026_06_23_120000_create_leads_table.php](file:///Volumes/MacStudio2/Develop/newhauz/database/migrations/2026_06_23_120000_create_leads_table.php) y [LeadAssignmentService.php](file:///Volumes/MacStudio2/Develop/newhauz/app/Services/LeadAssignmentService.php)
2. **Honeypot ruidoso y fácil de eludir para bots inteligentes (Seguridad):**
   - *Detalle:* Al completar el campo trampa `company_website`, la validación de Livewire devuelve un error convencional de Laravel indicando que el campo "sitio web de empresa" está prohibido. Esto no solo le da información inmediata al bot para que modifique su script de envío, sino que expone un error de validación 422 en la UI.
   - *Ruta afectada:* [LeadCaptureForm.php](file:///Volumes/MacStudio2/Develop/newhauz/app/Livewire/Leads/LeadCaptureForm.php)

## Hallazgos menores
1. **Falta de logueo o alerta de leads en el limbo si no hay agentes activos:**
   - *Detalle:* Si un lead ingresa cuando no hay agentes activos, el sistema no produce ninguna alerta o log. Aunque el comando de reconciliación lo resolverá cuando un agente se active, sería conveniente emitir un log de advertencia en el canal del sistema para auditoría operacional.
   - *Ruta afectada:* [LeadAssignmentService.php](file:///Volumes/MacStudio2/Develop/newhauz/app/Services/LeadAssignmentService.php)

## Regresiones detectadas
- **Ninguna.** La suite completa de pruebas unitarias y de integración (`composer test`) pasó al 100% de forma exitosa (184 tests, 856 aserciones ejecutadas).

## Riesgos de seguridad
1. **Denegación de Servicio (DoS) por evasión de Throttle:**
   - Al no contar las peticiones fallidas en el rate limiter, un bot malicioso puede inundar de peticiones fallidas el endpoint de captura pública y agotar los sockets/recursos del servidor de base de datos.
2. **Identificación de Honeypot:**
   - Al recibir un error de validación formal en el campo honeypot, los atacantes pueden automatizar la detección del señuelo y saltarse la protección en minutos.

## Riesgos de mantenimiento
- **Degradación del query de Round-Robin:**
   - Sin índices compuestos para `(agent_id, assigned_at)`, el rendimiento del round-robin decaerá linealmente con el volumen de leads en base de datos.

## Tests faltantes
1. **Test de Auto-asignación en creación manual por Agente:**
   - Validar que al crear un lead manualmente en Filament con rol de agente, se auto-asigne el ID de ese agente y esté visible inmediatamente.
2. **Test de incremento de Throttle con peticiones inválidas:**
   - Validar que el rate limiter sume intentos (hit) incluso cuando el email sea inválido o el honeypot esté lleno.

## Correcciones obligatorias para Codex
1. **Pre-asignar lead al agente en creación manual:**
   - En `CreateLead.php`, implementar:
     ```php
     protected function mutateFormDataBeforeCreate(array $data): array
     {
         $user = auth()->user();
         if ($user && $user->hasRole('agente')) {
             $data['agent_id'] = $user->id;
             $data['assigned_at'] = now();
         }
         return $data;
     }
     ```
2. **Mover RateLimiter::hit al inicio del submit:**
   - En `LeadCaptureForm.php`, mover el `RateLimiter::hit($this->rateLimitKey(), 60)` justo antes de `$validated = $this->validate();`.
3. **Optimizar honeypot para descartar silenciosamente:**
   - Remover `'company_website' => ['prohibited']` de las reglas de validación en `LeadCaptureForm.php`. Al inicio del método `submit()`, validar:
     ```php
     if (! empty($this->company_website)) {
         $this->reset(['name', 'email', 'phone', 'message', 'company_website']);
         $this->submitted = true;
         return;
     }
     ```
4. **Agregar índices de rendimiento en base de datos:**
   - En la migración `create_leads_table.php`, agregar un índice compuesto para optimizar el round-robin:
     ```php
     $table->index(['agent_id', 'assigned_at']);
     ```

## Correcciones recomendadas
1. **Deshabilitar Force Delete en Filament:**
   - Configurar explícitamente el recurso `LeadResource` eliminando `ForceDeleteBulkAction` y `ForceDeleteAction` del esquema de la tabla de Filament para evitar cualquier riesgo de borrado físico si las políticas cambiaran en el futuro.

## Checklist final antes de merge
- [ ] Aplicar las correcciones del Honeypot y del Rate Limiter en `LeadCaptureForm`.
- [ ] Modificar `CreateLead` en Filament para la auto-asignación del agente creador.
- [ ] Añadir la migración correctora para indexar `assigned_at` junto con `agent_id` en la tabla de leads.
- [ ] Ejecutar el comando `composer test` y confirmar que todos los tests pasen tras los cambios aplicados.
