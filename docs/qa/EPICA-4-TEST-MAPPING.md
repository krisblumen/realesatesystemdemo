# Matriz de pruebas — Épica 4

Esta matriz vincula los criterios QA-026→QA-051 con pruebas automatizadas ejecutables sobre PostgreSQL. Los nombres corresponden a métodos PHPUnit; la suite completa se ejecuta con `php artisan test`.

| Caso | Cobertura automatizada |
| --- | --- |
| QA-026 | `PropertyResourceTest::test_owner_can_create_property_with_agent_zone_and_features` |
| QA-027 | `PropertyResourceTest::test_agent_payload_forces_self_and_rejects_foreign_zone` |
| QA-028 | `PropertySlugTest::test_slug_is_generated_from_zone_type_and_title`, `test_slug_collision_includes_soft_deleted_properties` |
| QA-029 | `PropertyStatusServiceTest::test_publish_requires_cover_image` |
| QA-030 | `PropertyStatusServiceTest::test_publish_requires_a_current_active_zone_with_polygon` |
| QA-031 | `PropertyStatusServiceTest::test_valid_property_can_publish_pause_and_republish` |
| QA-032 | `PropertyStatusServiceTest::test_valid_property_can_publish_pause_and_republish` |
| QA-033 | `PropertyStatusServiceTest::test_sold_and_rented_states_require_matching_operation` |
| QA-034 | `PropertyStatusServiceTest::test_sold_and_rented_states_require_matching_operation` |
| QA-035 | `PropertyGalleryTest::test_gallery_accepts_multiple_images_and_persists_order` |
| QA-036 | `PropertyGalleryTest::test_cover_collection_keeps_one_file_and_generates_conversions` |
| QA-037 | `PropertyFeaturesTest::test_property_can_attach_and_detach_catalog_features` |
| QA-038 | `PropertySeoTest::test_seo_helpers_use_property_fallbacks`, `test_open_graph_image_contract_prefers_cover_then_gallery` |
| QA-039 | `PropertyPolicyTest::test_agent_cannot_manage_another_agents_property_even_in_a_shared_zone` |
| QA-040 | `Epica123RegressionTest` y suites existentes de `UserResourceTest`/`ZoneResourceTest` |
| QA-041 | `PropertyResourceTest::test_agent_payload_forces_self_and_rejects_foreign_zone` |
| QA-042 | `PropertyResourceTest::test_agent_payload_forces_self_and_rejects_foreign_zone` |
| QA-043 | `PropertyCoreTest::test_status_and_slug_are_not_mass_assignable` |
| QA-044 | `PropertyPolicyTest::test_agent_cannot_manage_another_agents_property_even_in_a_shared_zone` |
| QA-045 | `PropertyPublicationTest::test_published_property_cannot_be_reassigned_to_invalid_zone`, `test_published_property_cannot_delete_its_last_cover`, `test_published_property_can_replace_cover` |
| QA-046 | `PropertyPublicationTest::test_soft_deleting_zone_pauses_published_properties` |
| QA-047 | `PropertyPublicationTest::test_inactivating_zone_pauses_published_properties` |
| QA-048 | `PropertyScopesTest::test_visible_to_honors_agent_precedence_and_unassigned_zone_access` y `PropertyPolicyTest` |
| QA-049 | `PropertyResourceTest::test_owner_can_edit_soft_delete_and_restore_as_draft`, `test_edit_page_restore_also_degrades_published_property_to_draft`, `PropertyStatusServiceTest::test_terminal_states_can_reopen_only_to_draft` |
| QA-050 | `PropertySlugTest::test_generator_excludes_the_current_property_when_regenerating`, `PropertySlugConcurrencyTest::test_persist_retries_when_another_insert_wins_the_slug_race` |
| QA-051 | `PropertyCoreTest::test_database_rejects_non_positive_price`, `test_database_rejects_negative_metrics` |

La verificación manual complementaria cubre el render y la interacción del CRUD en `/admin/properties`; no sustituye las pruebas de Policy, persistencia ni reglas de estado.
