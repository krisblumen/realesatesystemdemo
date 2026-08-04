# Módulo de Leads

El módulo de leads captura prospectos públicos, los asigna a agentes, permite gestionarlos desde Filament y notifica al agente asignado. Consume los contratos existentes de `User`, `Property` y, cuando está disponible, `Zone`.

## Camino rápido

1. Captura pública: `/contacto` o componente `App\Livewire\Leads\LeadCaptureForm` embebido en una ficha de inmueble.
2. Persistencia: `Lead` nace con `status = nuevo`, `source` según origen y soft delete habilitado.
3. Asignación: `LeadCaptured` dispara `LeadAssignmentService` si `LEADS_AUTO_ASSIGNMENT_ENABLED=true`.
4. Gestión: `/admin/leads` usa `LeadResource` con scope backend por rol.
5. Notificación: `LeadAssigned` envía `LeadAssignedNotification` al agente por database + mail.

## Modelo

| Elemento | Decisión implementada |
| --- | --- |
| Modelo | `App\Models\Lead` con `SoftDeletes`. |
| Enums | `LeadSource`: `web`, `landing`, `inmueble`, `manual`, `telefono`. `LeadStatus`: `nuevo`, `contactado`, `en_seguimiento`, `cerrado_ganado`, `cerrado_perdido`. |
| Casts | `source` y `status` se castea a enums; `assigned_at` a datetime. |
| Helpers | `isNew()`, `isAssigned()`, `isClosed()`. |
| Scopes | `unassigned()`, `visibleTo(User)`, `byAgent()`, `byStatus()`. |
| Relaciones | `property()`, `agent()`, `zone()`. La relación con `Zone` está activa porque Épica 3 ya está presente en esta rama. |
| Auditoría | Reasignaciones registradas en `lead_assignment_logs`. |
| Notificaciones | Laravel `notifications` con `data` JSON para PostgreSQL. |

## Máquina de estados

| Desde | Hacia permitido |
| --- | --- |
| `nuevo` | `contactado` |
| `contactado` | `en_seguimiento`, `cerrado_ganado`, `cerrado_perdido` |
| `en_seguimiento` | `cerrado_ganado`, `cerrado_perdido` |
| `cerrado_ganado` / `cerrado_perdido` | Terminal: sin reapertura. |

El servicio `LeadStatusService` bloquea transiciones fuera de esta tabla.

## Captura pública y anti-spam

`LeadCaptureForm` acepta `name`, `email`, `phone`, `message`, `source` y `property_id` opcional.

| Control | Implementación |
| --- | --- |
| Validación base | `name` requerido, `email` válido, `phone` opcional con formato laxo, `message` limitado. |
| Inmueble | Si llega `property_id`, debe existir, estar `publicado` y tener `agent_id`. |
| Honeypot | Campo oculto `company_website`; si llega con valor, se rechaza. |
| Throttle | `RateLimiter` por IP dentro del método Livewire `submit()`. |
| Sanitización | `name` y `message` se guardan con HTML removido (`strip_tags` + `trim`). |
| Evento | Captura válida dispara `LeadCaptured($lead)`. |

## Estrategia de asignación

`LeadAssignmentService` es idempotente: si el lead ya tiene `agent_id`, no reasigna.

Prioridad:

1. Si el lead tiene `property` con agente activo y rol `agente`, se asigna a ese agente.
2. Si el lead tiene `zone` con agentes activos, se asigna al menos cargado de esa zona.
3. Si no, se asigna al agente activo menos cargado entre todos los usuarios con rol `agente`.

La carga se calcula sobre leads recientes (`LEADS_ASSIGNMENT_RECENT_DAYS`, default `30`) usando cantidad de asignaciones recientes, última asignación e ID como desempate determinista.

Si no hay agentes activos, el lead queda `nuevo` y sin agente. El comando `leads:reconcile` reintenta asignar leads nuevos sin agente; está agendado cada diez minutos.

## Notificaciones

`LeadAssignedNotification` implementa `ShouldQueue` y usa canales `database` y `mail`.

Contenido:

- Nombre del lead.
- Email y teléfono cuando aplica.
- Inmueble cuando aplica.
- Link al registro en `LeadResource`.

Filament habilita la campana con `->databaseNotifications()` en `AdminPanelProvider`, con polling cada `30s`. En producción, el envío no bloquea la captura si el worker de queue está activo.

## Autorización y scope

| Rol | Alcance |
| --- | --- |
| `owner` / `admin` | Ven todos los leads, pueden reasignar manualmente, borrar/restaurar soft-deleted. |
| `agente` | Sólo ve leads con `agent_id = auth()->id()`. No ve la acción de reasignación. |

El scope se aplica en `LeadResource::getEloquentQuery()` mediante `Lead::visibleTo($user)`, no como filtro visual. El acceso base requiere `leads.manage`.

## Integraciones

| Módulo | Uso |
| --- | --- |
| `Property` | Captura por inmueble y prioridad de asignación por `property.agent_id`. |
| `User` | Agente asignado (`agent_id`) y notifiable para database/mail. |
| `Zone` | Asignación por zona cuando el lead incluye `zone_id`. |
| Filament | `LeadResource` para gestión interna y campana de notificaciones. |
| Laravel Queue | Notificaciones encoladas; `.env.example` usa `QUEUE_CONNECTION=database`. |

## Mapa QA → tests

| QA | Criterio | Tests |
| --- | --- | --- |
| QA-041 | Lead persiste con `status = nuevo` por defecto. | `LeadCoreTest::test_lead_defaults_and_enum_casts_are_available` |
| QA-042 | Enums source/status y soft delete/restore. | `LeadCoreTest::test_lead_defaults_and_enum_casts_are_available`, `LeadCoreTest::test_lead_uses_soft_deletes`, `LeadResourceTest::test_owner_can_filter_search_change_status_delete_and_restore_leads` |
| QA-043 | Relaciones property/agent; lead sin property/agent válido. | `LeadCoreTest::test_lead_relationships_resolve_property_agent_and_deferred_zone_contract`, `LeadCoreTest::test_lead_defaults_and_enum_casts_are_available` |
| QA-044 | Form público crea lead y dispara `LeadCaptured`. | `PublicLeadCaptureTest::test_public_form_creates_a_new_lead_and_dispatches_event` |
| QA-045 | Form por inmueble precarga/rechaza `property_id` inválido. | `PublicLeadCaptureTest::test_public_form_creates_a_new_lead_and_dispatches_event`, `PublicLeadCaptureTest::test_property_lead_rejects_missing_unpublished_or_unassigned_property` |
| QA-046 | Honeypot, throttle y sanitización anti-XSS. | `PublicLeadCaptureTest::test_honeypot_blocks_spam_payloads`, `PublicLeadCaptureTest::test_rate_limiter_blocks_repeated_submissions_from_same_ip`, `PublicLeadCaptureTest::test_public_form_sanitizes_name_and_message_before_persisting` |
| QA-047 | Rechaza email inválido / nombre vacío. | `PublicLeadCaptureTest::test_public_form_validates_required_fields_and_email` |
| QA-048 | LeadResource lista, filtra, busca, cambia status y soft delete/restore. | `LeadResourceTest::test_owner_can_filter_search_change_status_delete_and_restore_leads`, `LeadCoreTest::test_status_machine_blocks_invalid_shortcuts_and_terminal_reopens`, `LeadCoreTest::test_status_machine_allows_expected_progression_and_terminal_states_are_closed` |
| QA-049 | Inmueble con agente asigna a ese agente. | `LeadAssignmentServiceTest::test_property_agent_has_assignment_priority` |
| QA-050 | Round-robin determinista justo. | `LeadAssignmentServiceTest::test_round_robin_uses_active_agents_with_fewer_recent_leads` |
| QA-051 | Asignación idempotente. | `LeadAssignmentServiceTest::test_assignment_is_idempotent_when_lead_already_has_agent` |
| QA-052 | Sin agentes queda sin agente; reconcile asigna luego. | `LeadAssignmentServiceTest::test_without_active_agents_lead_remains_unassigned_without_error`, `LeadAssignmentServiceTest::test_reconcile_command_assigns_pending_unassigned_leads_when_agents_become_available` |
| QA-053 | Agente recibe database + mail. | `LeadAssignedNotificationTest::test_notification_uses_database_and_mail_channels_with_lead_context`, `LeadAssignedNotificationTest::test_lead_assigned_event_notifies_the_assigned_agent` |
| QA-054 | Campana Filament/database notification. | `LeadAssignedNotificationTest::test_notification_is_queueable_and_creates_database_payload_for_filament_bell`, `LeadAssignedNotificationTest::test_lead_resource_url_is_available_for_notification_action` |
| QA-055 | Scope y permisos por rol. | `LeadResourceTest::test_roles_with_permission_can_access_leads_index`, `LeadResourceTest::test_agent_only_sees_own_leads_while_owner_sees_all`, `LeadResourceTest::test_agent_cannot_access_another_agents_lead_edit_page`, `LeadResourceTest::test_owner_can_manually_reassign_a_lead_and_audit_the_change`, `LeadResourceTest::test_agent_cannot_see_manual_reassign_action` |

## Regresión cubierta

- Épica 2: roles `owner`, `admin`, `agente`, permiso `leads.manage`, `UserStatus::Active`, `Notifiable`.
- Épica 3: `Zone` y pivote `agent_zone` consumidos para asignación por zona cuando están presentes.
- Épica 4: `Property.agent_id`, `Property.status`, relación con agente y validación de ficha publicada.
- Suite completa: `composer test` cubre las pruebas de auth, zonas, propietarios, propiedades, dashboard y Filament existentes.

## Comandos de verificación

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Nota: al momento de este cierre, el análisis focalizado del módulo Leads pasa. El análisis PHPStan completo puede fallar por deuda previa fuera de Leads documentada en la salida de CI/local.
