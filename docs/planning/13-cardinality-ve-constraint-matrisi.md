# Konelsis Kurumsal Platform — Cardinality ve constraint matrisi

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. DDL üretimi bu belgeden yapılmaz; şemayı kullanıcı kendi kabuğunda kurar.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Kaynak:** Veri sözlüğü [06](06-veri-sozlugu-01-cekirdek-ve-personel.md)–[12](12-veri-sozlugu-07-workflow-audit-entegrasyon.md), ERD [05](05-veri-tabani-er-diyagramlari.md) §14–15  
**Hedef:** MySQL 8.4 LTS / InnoDB

Bu matris, sözlükte kolon düzeyinde verilen benzersizlik ve FK bilgisini fiziksel constraint kataloğuna dönüştürür. Sözlükte "UNIQUE", "→ tablo", "CHECK" ve "generated" olarak işaretlenen her kural burada isimlendirilmiş bir constraint'e karşılık gelir. Çelişki hâlinde sözlük kolon tanımı, bu matris ise constraint adı/sırası için bağlayıcıdır.

## 1. İsimlendirme ve kısaltma kaydı

### 1.1 Constraint ve indeks adları

| Tür | Kalıp | Örnek |
|---|---|---|
| Primary key | `pk_<tablo>` | `pk_employees` |
| Unique key | `uk_<tablo>_<kolonlar>` | `uk_employees_employee_no` |
| Foreign key | `fk_<tablo>_<kolon-kökü>` | `fk_employees_user` |
| Composite FK | `fk_<tablo>_<kolon-kökü>_agg` | `fk_project_focus_histories_workstream_agg` |
| Check | `ck_<tablo>_<kural>` | `ck_employees_left_after_joined` |
| Generated guard | kolon `<kural>_guard` + `uk_<tablo>_<kural>_guard` | `uk_position_assignments_primary_active_guard` |
| Secondary index | `ix_<tablo>_<kolonlar>` | `ix_report_assignments_status_due` |
| Fulltext | `ft_<tablo>_<kolonlar>` | `ft_documents_title` |
| Spatial | `sp_<tablo>_<kolon>` | — (DB-G7) |

MySQL'de `CHECK` ve foreign key adları şema genelinde benzersiz olmalıdır; bu yüzden her ad tablo adını içerir. Tanımlayıcı sınırı 64 karakterdir; aşan tablolar için 1.2 kısaltmaları kullanılır.

### 1.2 Uzun tablo adı kısaltmaları

| Tablo | Kısaltma |
|---|---|
| `functional_area_responsibilities` | `far` |
| `functional_area_role_definitions` | `fard` |
| `functional_area_role_capabilities` | `farc` |
| `functional_area_org_unit_bindings` | `faoub` |
| `functional_area_transition_events` | `fate` |
| `functional_area_transition_items` | `fati` |
| `personnel_private_profiles` | `epp` |
| `report_template_versions` | `rtv` |
| `report_submission_versions` | `rsv` |
| `report_schedule_targets` | `rst` |
| `notification_rule_versions` | `nrv` |
| `notification_rule_recipients` | `nrr` |
| `notification_delivery_attempts` | `nda` |
| `notification_acknowledgements` | `nack` |
| `business_alert_acknowledgements` | `baack` |
| `business_alert_resolutions` | `bares` |
| `document_template_versions` | `dtv` |
| `inbound_email_business_links` | `iebl` |
| `social_account_identifier_histories` | `saih` |
| `social_content_version_translations` | `scvt` |
| `social_publication_corrections` | `spc` |
| `social_publication_attempts` | `spa` |
| `social_reminder_policy_versions` | `srpv` |
| `social_reminder_policy_steps` | `srps` |
| `social_reminder_step_recipients` | `srsr` |
| `social_special_day_reminder_instances` | `ssdri` |
| `social_reminder_instance_recipients` | `srir` |
| `social_account_metric_observations` | `samo` |
| `social_publication_metric_observations` | `spmo` |
| `social_platform_metric_mappings` | `spmm` |
| `social_metric_ingestion_batches` | `smib` |
| `business_development_activity_participants` | `bdap` |
| `responsibility_matrix_items` | `rmi` |
| `operation_handoff_versions` | `ohv` |
| `department_handoff_versions` | `dhv` |
| `department_handoff_items` | `dhi` |
| `department_handoff_reviews` | `dhr` |
| `stage_requirement_definitions` | `srd` |
| `project_stage_requirements` | `psr` |
| `purchase_requisition_lines` | `prl` |
| `supplier_quote_versions` | `sqv` |
| `purchase_order_versions` | `pov` |
| `commissioning_package_tests` | `cpt` |
| `commissioning_package_assets` | `cpa` |
| `test_plan_step_definitions` | `tpsd` |
| `workflow_definition_versions` | `wdv` |
| `approval_policy_versions` | `apv` |
| `approval_request_steps` | `ars` |
| `integration_connection_capabilities` | `icc` |
| `external_analysis_requests` | `ear` |
| `external_analysis_artifacts` | `eaa` |
| `external_analysis_results` | `eares` |
| `external_analysis_result_references` | `earr` |
| `external_action_executions` | `eaex` |
| `service_capability_grant_actions` | `scga` |

## 2. Composite "aynı aggregate" anahtarları

Bir çocuğun parent ile aynı aggregate'e ait olması gereken yerlerde parent üzerinde `(agg_id, id)` unique key ve çocukta composite FK bulunur. CASCADE yoktur; hepsi `RESTRICT`.

| Parent unique key | Çocuk composite FK | Amaç |
|---|---|---|
| `report_template_versions(report_template_id, id)` | `report_templates(id, current_version_id)` | current version aynı kökten |
| `report_submission_versions(report_submission_id, id)` | `report_submissions(id, current_version_id)` | |
| `notification_rule_versions(notification_rule_id, id)` | `notification_rules(id, current_version_id)` | |
| `document_revisions(document_id, id)` | `documents(id, current_revision_id)`; `document_revisions(document_id, superseded_by_revision_id)` | |
| `message_versions(message_id, id)` | `messages(id, current_version_id)` | |
| `messages(conversation_id, id)` | `messages(conversation_id, reply_to_message_id)` | Reply aynı konuşmada |
| `social_content_versions(content_item_id, id)` | `social_content_items(id, current_version_id)`; `social_content_versions(content_item_id, superseded_by_version_id)` | |
| `social_kpi_formula_versions(social_kpi_id, id)` | `social_kpis(id, current_formula_version_id)` | |
| `tender_notice_versions(tender_notice_id, id)` | `tender_notices(id, current_version_id)` | |
| `proposal_versions(proposal_id, id)` | `proposals(id, current_version_id)` | |
| `contract_versions(contract_id, id)` | `contracts(id, current_version_id)` | |
| `operation_handoff_versions(operation_handoff_id, id)` | `operation_handoffs(id, accepted_version_id)` | Kabul aynı kökten |
| `business_cases(id, sequence_no)` | `business_codes(business_case_id, sequence_no)` | TKLF/PRJ sıra eşliği |
| `stage_template_versions(stage_template_id, id)` | `stage_templates(id, current_version_id)` | |
| `project_workstreams(project_id, id)` | `projects(id, primary_focus_workstream_id)`; `project_focus_histories(project_id, workstream_id)`; `department_handoffs(project_id, source_workstream_id)`; `department_handoffs(project_id, target_workstream_id)`; `purchase_requisitions(project_id, workstream_id)`; `project_tasks/project_issues/project_risks/delay_events/engineering_deliverables/work_plans/inspections(project_id, workstream_id)` | Aynı proje |
| `project_stage_instances(project_id, id)` | `department_handoffs(project_id, trigger_stage_instance_id)`; `project_tasks(project_id, stage_instance_id)` | |
| `work_packages(project_id, id)` | `project_tasks/method_statements/installed_quantities/site_photos/inspections/punch_items(project_id, work_package_id)` | |
| `wbs_nodes(project_id, id)` | `wbs_nodes(project_id, parent_id)`; `work_packages/milestones/project_tasks/installed_quantities(project_id, wbs_node_id)` | |
| `cbs_nodes(project_id, id)` | `cbs_nodes(project_id, parent_id)`; `commitments/actual_cost_references/commercial_exposures(project_id, cbs_node_id)` | |
| `department_handoff_versions(department_handoff_id, id)` | `department_handoffs(id, accepted_version_id)` | |
| `installed_assets(project_id, id)` | `installed_assets(project_id, parent_asset_id)`; `test_executions(project_id, installed_asset_id)` | |
| `supplier_quote_versions(supplier_quote_id, id)` | `supplier_quotes(id, current_version_id)` | |
| `purchase_order_versions(purchase_order_id, id)` | `purchase_orders(id, current_version_id)` | |
| `budget_versions(project_budget_id, id)` | `project_budgets(id, current_version_id)` | |
| `progress_claim_versions(progress_claim_id, id)` | `progress_claims(id, current_version_id)` | |
| `engineering_revisions(deliverable_id, id)` | `engineering_deliverables(id, current_revision_id)` | |
| `test_plan_versions(test_plan_template_id, id)` | `test_plan_templates(id, current_version_id)` | |
| `workflow_definition_versions(workflow_definition_id, id)` | `workflow_definitions(id, current_version_id)` | |
| `approval_policy_versions(approval_policy_id, id)` | `approval_policies(id, current_version_id)` | |
| `functional_area_role_definitions(functional_area_id, role_code)` | `functional_area_responsibilities(functional_area_id, role_code)` | Rol aynı alanda |

Not: Kök tablodaki `current_*_id` ile sürüm tablosundaki kök FK'sı karşılıklı FK oluşturur; MySQL'de bu döngü tablolar oluşturulduktan sonra `ALTER TABLE ... ADD CONSTRAINT` ile kurulur ([16 §4](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md)).

## 3. Generated guard kataloğu

Her guard `STORED` generated kolondur; koşul doğruyken kök kimliği veya `1`, değilse `NULL` üretir ve üzerinde UNIQUE index bulunur. MySQL unique index'te NULL değerler birbirine eşit sayılmaz.

| Tablo | Guard kolonu | İfade | Unique | Korunan kural |
|---|---|---|---|---|
| `business_calendars` | `default_guard` | `CASE WHEN is_default = 1 THEN legal_entity_id END` | `(default_guard)` | Legal entity başına tek varsayılan takvim |
| `org_unit_relations` | `hierarchy_active_guard` | `CASE WHEN relation_type = 'hierarchy' AND valid_until IS NULL THEN child_org_unit_id END` | `(hierarchy_active_guard)` | Tek aktif hiyerarşik parent |
| `position_assignments` | `primary_active_guard` | `CASE WHEN is_primary = 1 AND valid_until IS NULL THEN personnel_id END` | `(primary_active_guard)` | Tek aktif primary pozisyon |
| `reporting_relationships` | `line_active_guard` | `CASE WHEN relation_type = 'line' AND valid_until IS NULL THEN personnel_id END` | `(line_active_guard)` | Tek aktif doğrudan amir |
| `functional_area_responsibilities` | `active_owner_guard` | `CASE WHEN role_code = 'function_owner' AND is_primary = 1 AND valid_until IS NULL THEN functional_area_id END` | `(active_owner_guard)` | Tek aktif function owner |
| `functional_area_org_unit_bindings` | `active_owner_binding_guard` | `CASE WHEN binding_type = 'owner' AND valid_until IS NULL THEN functional_area_id END` | `(active_owner_binding_guard)` | Tek aktif owner birim |
| `staffing_options` | `selected_guard` | `CASE WHEN is_selected = 1 THEN workforce_request_id END` | `(selected_guard)` | Tek seçili seçenek |
| `report_metric_facts` | `current_guard` | `CASE WHEN is_current = 1 THEN 1 END` | `(report_assignment_id, report_question_id, row_key, current_guard)` | Güncel sürüm fact'i |
| `task_assignments` | `active_guard` | `CASE WHEN unassigned_at IS NULL THEN 1 END` | `(task_id, assignee_personnel_id, responsibility_type, active_guard)` | Aktif atama tekliği |
| `business_alert_resolutions` | `accepted_guard` | `CASE WHEN is_accepted = 1 THEN business_alert_id END` | `(accepted_guard)` | Tek kabul edilen çözüm |
| `document_revision_files` | `original_guard` | `CASE WHEN file_role = 'original' THEN document_revision_id END` | `(original_guard)` | Revizyon başına tek orijinal |
| `conversation_memberships` | `active_guard` | `CASE WHEN left_at IS NULL THEN 1 END` | `(conversation_id, personnel_id, active_guard)` | Tek aktif üyelik |
| `party_roles` | `active_guard` | `CASE WHEN valid_until IS NULL THEN 1 END` | `(party_id, role_code, active_guard)` | Rol başına tek aktif satır |
| `addresses` | `primary_guard` | `CASE WHEN is_primary = 1 THEN 1 END` | `(party_id, address_type, primary_guard)` | Tip başına tek primary |
| `proposals` | `selected_guard` | `CASE WHEN is_selected = 1 THEN business_case_id END` | `(selected_guard)` | Business case başına tek seçili teklif |
| `pricing_scenarios` | `selected_guard` | `CASE WHEN is_selected = 1 THEN estimate_version_id END` | `(selected_guard)` | Tek seçili senaryo |
| `project_focus_histories` | `open_guard` | `CASE WHEN ended_at IS NULL THEN project_id END` | `(open_guard)` | Tek açık primary focus |
| `social_account_connections` | `active_guard` | `CASE WHEN status = 'active' AND valid_until IS NULL THEN 1 END` | `(social_account_id, provider_code, active_guard)` | Hesap/provider başına tek aktif bağlantı |
| `workflow_instances` | `active_guard` | `CASE WHEN status IN ('running','waiting') THEN 1 END` | `(subject_type, subject_id, subject_revision_id, active_guard)` | Subject başına tek aktif workflow |
| `approval_requests` | `active_guard` | `CASE WHEN status IN ('pending','in_progress') THEN 1 END` | `(subject_type, subject_id, subject_revision_id, active_guard)` | Subject başına tek aktif onay isteği |
| `external_analysis_results` | `selected_guard` | `CASE WHEN is_selected = 1 THEN request_id END` | `(selected_guard)` | Request başına tek seçili sonuç |

`business_codes.formatted_code` guard değil, görünen kod üreten STORED generated kolondur: `CONCAT(CASE code_kind WHEN 'offer' THEN 'TKLF-' WHEN 'project' THEN 'PRJ-' END, sequence_no)`; `uk_business_codes_formatted_code`.

## 4. CHECK kataloğu

### 4.1 Enum CHECK'leri

Sözlükte köşeli parantezle listelenen her `status`/enum kolonu için `ck_<tablo>_<kolon>_enum CHECK (<kolon> IN (...))` üretilir. Değer listesi PHP backed enum ile bire bir aynıdır; enum değişikliği yeni migration ve karar kaydı gerektirir.

### 4.2 Tarih ve sayı aralıkları

| Constraint | Kural |
|---|---|
| `ck_<t>_valid_range` | `valid_until IS NULL OR valid_until > valid_from` — bütün tarihçeli tablolar (06 §1.4) |
| `ck_<t>_date_order` | `ends_on >= starts_on` / `period_end > period_start` / `planned_finish_on >= planned_start_on` gibi sözlükte belirtilen sıralar |
| `ck_employees_left_after_joined` | `left_on IS NULL OR joined_on IS NULL OR left_on >= joined_on` |
| `ck_delegations_until_required` | `valid_until > valid_from` (NULL yasak) |
| `ck_position_assignments_allocation` | `allocation_pct > 0 AND allocation_pct <= 100` |
| `ck_<t>_pct_range` | `0 <= x <= 100` bütün `pct` kolonları |
| `ck_<t>_ratio_range` | `0 <= x <= 1` bütün `ratio` kolonları |
| `ck_<t>_quantity_positive` | `quantity > 0` (`purchase_requisition_lines`, `inventory_transactions`, `shipment_items`) |
| `ck_timesheet_lines_hours` | `hours > 0 AND hours <= 24` |
| `ck_report_periods_grace` | `grace_until >= due_at` |
| `ck_attendance_entries_order` | `check_out_at IS NULL OR check_in_at IS NULL OR check_out_at > check_in_at` |
| `ck_currencies_decimal_places` | `decimal_places <= 6` |
| `ck_units_of_measure_factor` | `to_base_factor IS NULL OR to_base_factor > 0` |
| `ck_positions_managerial_level` | `managerial_level <= 5` |
| `ck_positions_headcount` | `headcount >= 0` |
| `ck_business_calendar_weekdays_iso` | `iso_weekday BETWEEN 1 AND 7` (aynı kural `report_schedule_weekdays`) |
| `ck_special_day_definitions_fixed` | `recurrence_kind <> 'fixed_date' OR (fixed_month BETWEEN 1 AND 12 AND fixed_day BETWEEN 1 AND 31)` |

### 4.3 XOR / exact-one hedefler

| Constraint | İfade |
|---|---|
| `ck_far_exact_one_target` | `(personnel_id IS NOT NULL) + (team_id IS NOT NULL) + (position_id IS NOT NULL) = 1` |
| `ck_message_attachments_exact_one` | `(file_object_id IS NOT NULL) + (document_revision_id IS NOT NULL) = 1` |
| `ck_bdap_exact_one` | `(personnel_id IS NOT NULL) + (contact_party_id IS NOT NULL) = 1` |
| `ck_bd_activities_any_context` | `business_case_id IS NOT NULL OR party_id IS NOT NULL` |
| `ck_quality_evidence_exact_one` | Beş hedef kolonun `IS NOT NULL` toplamı `= 1` |
| `ck_eaa_exact_one_source` | `(document_revision_id IS NOT NULL) + (report_submission_version_id IS NOT NULL) = 1` |
| `ck_social_special_day_rules_scope` | `(occurrence_scope = 'calendar' AND special_day_calendar_id IS NOT NULL) OR (occurrence_scope = 'category' AND category IS NOT NULL AND special_day_calendar_id IS NOT NULL) OR (occurrence_scope = 'definition' AND definition_id IS NOT NULL)` |
| `ck_purchase_requisitions_project_kind` | `requisition_kind <> 'project' OR project_id IS NOT NULL` |
| `ck_notification_recipients_resolved_user` | `resolution_status <> 'resolved' OR personnel_id IS NOT NULL` |
| `ck_tasks_context_id` | `context_type = 'none' OR context_id IS NOT NULL` |
| `ck_social_publications_published_evidence` | `state <> 'published' OR (published_at IS NOT NULL AND (external_post_id IS NOT NULL OR evidence_document_revision_id IS NOT NULL))` |
| `ck_file_objects_derived` | `is_derived = 0 OR derived_from_file_object_id IS NOT NULL` |

### 4.4 Self-link yasağı

`ck_<t>_no_self_link CHECK (a <> b)` — `org_unit_relations(parent, child)`, `reporting_relationships(personnel, manager)`, `delegations(grantor, delegate)`, `contact_relationships(organization, contact)`, `task_dependencies`, `workstream_dependencies`, `work_package_dependencies`, `stage_dependencies`, `approved_equivalents`, `stock_transfers(source, target warehouse)`, `department_handoffs(source, target workstream)`; kendi kimliğine parent için `parent_id IS NULL OR parent_id <> id` — `units_of_measure`, `catalog_categories`, `wbs_nodes`, `cbs_nodes`, `estimate_lines`, `installed_assets`.

### 4.5 Tip–değer eşlemesi: `report_answers`

`ck_report_answers_single_value`: `(value_text IS NOT NULL) + (value_int IS NOT NULL) + (value_decimal IS NOT NULL) + (value_bool IS NOT NULL) + (value_date IS NOT NULL) + (value_datetime IS NOT NULL) + (value_ref_id IS NOT NULL) <= 1`.

Uygulama servisi tip bazında şu eşlemeyi uygular ve `answer_type_snapshot` ile denetler:

| `answer_type` | Dolu olabilecek kolon | Ek kural |
|---|---|---|
| `short_text`, `long_text`, `rich_text` | `value_text` | `rich_text` sanitize |
| `integer` | `value_int` | |
| `decimal` | `value_decimal` | `uom_id` opsiyonel |
| `money` | `value_decimal` | `currency_code` zorunlu |
| `percentage` | `value_decimal` | 0–100 |
| `boolean` | `value_bool` | |
| `date` | `value_date` | |
| `datetime` | `value_datetime` | UTC |
| `single_choice` | — | tam bir `report_answer_options` satırı |
| `multi_choice` | — | ≥ 1 `report_answer_options` satırı |
| `personnel`, `org_unit`, `project` | `value_ref_id` | Registry existence kontrolü |
| `document` | — | ≥ 1 `report_answer_documents` satırı |
| `repeated_group` | — | Çocuk soruların satırları `row_key` ile |
| `callout` | — | Cevap satırı oluşmaz |

### 4.6 Stok hareketi: `inventory_transactions`

`ck_inventory_transactions_bins`:

```text
(transaction_type IN ('receipt','return')            AND to_bin_id IS NOT NULL AND from_bin_id IS NULL)
OR (transaction_type IN ('issue_to_project','scrap')   AND from_bin_id IS NOT NULL AND to_bin_id IS NULL)
OR (transaction_type = 'transfer'                      AND from_bin_id IS NOT NULL AND to_bin_id IS NOT NULL AND from_bin_id <> to_bin_id)
OR (transaction_type = 'count_adjustment'              AND (from_bin_id IS NOT NULL) + (to_bin_id IS NOT NULL) = 1)
OR (transaction_type = 'reservation_fulfilment'        AND from_bin_id IS NOT NULL)
```

`ck_inventory_transactions_serial_qty`: `serialized_item_id IS NULL OR quantity = 1`. Lot/seri zorunluluğu `catalog_items.is_lot_tracked/is_serialized` üzerinden servis invariant'ıdır (MySQL CHECK başka tabloya bakamaz).

## 5. Cardinality ve FK matrisi

Sütunlar: kaynak kolon → hedef tablo; cardinality (ERD gösterimi, çocuk → parent); silme kuralı (varsayılan `RESTRICT`, `CASCADE` yalnız işaretli); not. FK indeksleri InnoDB tarafından zorunludur ve her FK için `ix_` oluşturulur.

### 5.1 Çekirdek, kimlik, organizasyon (06)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `organizations.default_currency_code` | `currencies` | }o--|| | RESTRICT | |
| `legal_entities.organization_id` | `organizations` | }o--|| | RESTRICT | |
| `legal_entities.country_code` / `.currency_code` | `countries` / `currencies` | }o--|| | RESTRICT | |
| `units_of_measure.base_unit_id` | `units_of_measure` | }o--o| | RESTRICT | self |
| `business_calendars.legal_entity_id` / `.country_code` | `legal_entities` / `countries` | }o--o| | RESTRICT | |
| `business_calendar_weekdays.business_calendar_id` | `business_calendars` | }|--|| | CASCADE | saf kompozisyon |
| `calendar_days.business_calendar_id` | `business_calendars` | }o--|| | RESTRICT | |
| `business_number_allocations.allocated_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `personnel.personnel_id` | `personnel` | |o--o| | RESTRICT | UNIQUE |
| `org_units.legal_entity_id` | `legal_entities` | }o--|| | RESTRICT | |
| `org_unit_relations.parent_org_unit_id` / `.child_org_unit_id` | `org_units` | }o--|| | RESTRICT | |
| `positions.org_unit_id` | `org_units` | }o--|| | RESTRICT | |
| `personnel.personnel_id` | `personnel` | |o--o| | RESTRICT | UNIQUE nullable |
| `personnel.photo_file_object_id` | `file_objects` | }o--o| | RESTRICT | |
| `personnel_private_profiles.personnel_id` | `personnel` | ||--o| | RESTRICT | PK=FK |
| `employments.personnel_id` / `.legal_entity_id` | `personnel` / `legal_entities` | }o--|| | RESTRICT | |
| `position_assignments.personnel_id` / `.position_id` | `personnel` / `positions` | }o--|| | RESTRICT | |
| `reporting_relationships.personnel_id` / `.manager_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `teams.owner_org_unit_id` | `org_units` | }o--o| | RESTRICT | |
| `teams.project_id` | `projects` | }o--o| | RESTRICT | dalga 3'te eklenir |
| `team_memberships.team_id` / `.personnel_id` | `teams` / `personnel` | }o--|| | RESTRICT | |
| `delegations.grantor_employee_id` / `.delegate_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `delegations.approved_by_personnel_id` / `.revoked_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `personnel_status_histories.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `functional_areas.organization_id` | `organizations` | }o--|| | RESTRICT | |
| `functional_areas.classification_id` | `security_classifications` | }o--|| | RESTRICT | |
| `functional_area_translations.functional_area_id` | `functional_areas` | }|--|| | CASCADE | |
| `functional_area_role_definitions.functional_area_id` | `functional_areas` | }o--|| | RESTRICT | |
| `functional_area_role_capabilities.role_definition_id` | `functional_area_role_definitions` | }o--|| | CASCADE | |
| `far.functional_area_id` | `functional_areas` | }o--|| | RESTRICT | |
| `far(functional_area_id, role_code)` | `fard(functional_area_id, role_code)` | }o--|| | RESTRICT | composite |
| `far.personnel_id` / `.team_id` / `.position_id` | `personnel` / `teams` / `positions` | }o--o| | RESTRICT | XOR |
| `faoub.functional_area_id` / `.org_unit_id` | `functional_areas` / `org_units` | }o--|| | RESTRICT | |
| `faoub.approved_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `fate.functional_area_id` | `functional_areas` | }o--|| | RESTRICT | |
| `fate.target_org_unit_id` | `org_units` | }o--o| | RESTRICT | |
| `fate.approved_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `fate.decision_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `fati.transition_event_id` | `fate` | }o--|| | RESTRICT | |
| `personnel_competencies.personnel_id` / `.competency_id` | `personnel` / `competencies` | }o--|| | RESTRICT | |
| `personnel_competencies.verified_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `personnel_competencies.evidence_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `personnel_certifications.personnel_id` / `.certification_id` | `personnel` / `certifications` | }o--|| | RESTRICT | |
| `personnel_certifications.document_revision_id` / `.verified_by_personnel_id` | `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `training_attendances.training_id` / `.personnel_id` | `trainings` / `personnel` | }o--|| | RESTRICT | |
| `training_attendances.certificate_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `leave_requests.personnel_id` / `.leave_type_id` | `personnel` / `leave_types` | }o--|| | RESTRICT | |
| `leave_requests.approval_request_id` / `.document_revision_id` | `approval_requests` / `document_revisions` | }o--o| | RESTRICT | |
| `attendance_entries.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `attendance_entries.project_id` | `projects` | }o--o| | RESTRICT | |
| `timesheets.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `timesheets.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `timesheet_lines.timesheet_id` | `timesheets` | }|--|| | RESTRICT | |
| `timesheet_lines.project_id` / `.wbs_node_id` | `projects` / `wbs_nodes` | }o--o| | RESTRICT | |
| `expense_claims.personnel_id` / `.currency_code` | `personnel` / `currencies` | }o--|| | RESTRICT | |
| `expense_claims.project_id` / `.approval_request_id` | `projects` / `approval_requests` | }o--o| | RESTRICT | |
| `expense_lines.expense_claim_id` | `expense_claims` | }|--|| | RESTRICT | |
| `expense_lines.receipt_document_revision_id` / `.cbs_node_id` | `document_revisions` / `cbs_nodes` | }o--o| | RESTRICT | |
| `workforce_requests.requesting_org_unit_id` / `.requested_by_employee_id` | `org_units` / `personnel` | }o--|| | RESTRICT | |
| `workforce_requests.position_id` / `.project_id` / `.approval_request_id` | `positions` / `projects` / `approval_requests` | }o--o| | RESTRICT | |
| `staffing_options.workforce_request_id` | `workforce_requests` | }o--|| | RESTRICT | |
| `staffing_options.personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `resource_assignments.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `resource_assignments.project_id` / `.workforce_request_id` | `projects` / `workforce_requests` | }o--o| | RESTRICT | |
| `job_requisitions.workforce_request_id` | `workforce_requests` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `job_requisitions.hiring_manager_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `job_requisitions.recruiter_employee_id` | `personnel` | }o--o| | RESTRICT | |
| `job_post_versions.job_requisition_id` | `job_requisitions` | }|--|| | RESTRICT | |
| `candidates.cv_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `applications.candidate_id` / `.job_requisition_id` | `candidates` / `job_requisitions` | }o--|| | RESTRICT | |
| `interviews.application_id` / `.interviewer_employee_id` | `applications` / `personnel` | }o--|| | RESTRICT | |
| `interviews.notes_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `performance_records.performance_cycle_id` / `.personnel_id` / `.reviewer_employee_id` | `performance_cycles` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `development_actions.performance_record_id` | `performance_records` | }o--|| | RESTRICT | |
| Bütün tablolarda S1/S2/S3 `*_by_personnel_id` | `personnel` | }o--|| | RESTRICT | tabloda tekrar edilmez |

### 5.2 Raporlama, bildirim, görev (07)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `report_templates.owner_org_unit_id` | `org_units` | }o--o| | RESTRICT | |
| `report_templates(id, current_version_id)` | `rtv(report_template_id, id)` | |o--o| | RESTRICT | composite; ALTER ile |
| `rtv.report_template_id` | `report_templates` | }|--|| | RESTRICT | |
| `rtv.classification_id` | `security_classifications` | }o--|| | RESTRICT | |
| `rtv.pdf_template_version_tr_id` / `_en_id` | `document_template_versions` | }o--o| | RESTRICT | |
| `rtv.published_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `report_sections.template_version_id` | `rtv` | }|--|| | RESTRICT | |
| `report_questions.template_version_id` / `.report_section_id` | `rtv` / `report_sections` | }|--|| | RESTRICT | |
| `report_questions.parent_question_id` | `report_questions` | }o--o| | RESTRICT | self |
| `report_questions.uom_id` / `.currency_code` | `units_of_measure` / `currencies` | }o--o| | RESTRICT | |
| `report_question_options.report_question_id` | `report_questions` | }o--|| | RESTRICT | |
| `report_workflow_bindings.template_version_id` | `rtv` | }o--|| | RESTRICT | |
| `report_workflow_bindings.approval_policy_version_id` / `.notification_rule_version_id` | `apv` / `nrv` | }o--o| | RESTRICT | |
| `report_schedules.report_template_id` | `report_templates` | }o--|| | RESTRICT | |
| `report_schedules.business_calendar_id` | `business_calendars` | }o--o| | RESTRICT | |
| `report_schedule_weekdays.report_schedule_id` | `report_schedules` | }o--|| | CASCADE | |
| `rst.report_schedule_id` | `report_schedules` | }|--|| | RESTRICT | |
| `report_periods.report_schedule_id` / `.template_version_id` | `report_schedules` / `rtv` | }o--|| | RESTRICT | |
| `report_assignments.report_period_id` / `.personnel_id` / `.reviewer_employee_id` | `report_periods` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `report_assignments.org_unit_snapshot_id` / `.position_snapshot_id` / `.waived_by_personnel_id` | `org_units` / `positions` / `personnel` | }o--o| | RESTRICT | |
| `report_submissions.report_assignment_id` | `report_assignments` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `report_submissions(id, current_version_id)` | `rsv(report_submission_id, id)` | |o--o| | RESTRICT | composite |
| `rsv.report_submission_id` | `report_submissions` | }o--|| | RESTRICT | |
| `rsv.submitted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `rsv.external_check_request_id` | `external_analysis_requests` | }o--o| | RESTRICT | |
| `report_answers.submission_version_id` / `.report_question_id` | `rsv` / `report_questions` | }o--|| | RESTRICT | |
| `report_answers.uom_id` | `units_of_measure` | }o--o| | RESTRICT | |
| `report_answer_options.report_answer_id` / `.question_option_id` | `report_answers` / `report_question_options` | }o--|| | RESTRICT | |
| `report_answer_documents.report_answer_id` / `.document_revision_id` | `report_answers` / `document_revisions` | }o--|| | RESTRICT | |
| `report_reviews.submission_version_id` / `.reviewer_personnel_id` | `rsv` / `personnel` | }o--|| | RESTRICT | |
| `report_reviews.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `report_status_transitions.report_submission_id` / `.personnel_id` | `report_submissions` / `personnel` | }o--|| | RESTRICT | |
| `report_status_transitions.submission_version_id` | `rsv` | }o--o| | RESTRICT | |
| `report_metric_facts.*` | `rsv`, `report_assignments`, `report_periods`, `report_questions`, `personnel` | }o--|| | RESTRICT | |
| `report_metric_facts.org_unit_id` / `.project_id` / `.uom_id` | `org_units` / `projects` / `units_of_measure` | }o--o| | RESTRICT | |
| `notification_rules(id, current_version_id)` | `nrv(notification_rule_id, id)` | |o--o| | RESTRICT | composite |
| `nrv.notification_rule_id` | `notification_rules` | }|--|| | RESTRICT | |
| `notification_rule_channels.rule_version_id` / `nrr.rule_version_id` | `nrv` | }|--|| | RESTRICT | |
| `notification_instances.rule_version_id` | `nrv` | }o--|| | RESTRICT | |
| `notification_recipients.notification_instance_id` | `notification_instances` | }|--|| | RESTRICT | |
| `notification_recipients.personnel_id` / `.personnel_id` | `personnel` / `personnel` | }o--o| | RESTRICT | |
| `nda.notification_recipient_id` | `notification_recipients` | }o--|| | RESTRICT | |
| `notification_receipts.notification_recipient_id` | `notification_recipients` | |o--|| | RESTRICT | UNIQUE |
| `nack.notification_recipient_id` | `notification_recipients` | |o--|| | RESTRICT | UNIQUE |
| `nack.acknowledged_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `notification_escalations.notification_instance_id` / `.to_recipient_id` | `notification_instances` / `notification_recipients` | }o--|| | RESTRICT | |
| `notification_escalations.from_recipient_id` | `notification_recipients` | }o--o| | RESTRICT | |
| `notification_preferences.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `tasks.owner_personnel_id` / `.requester_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `tasks.closed_by_personnel_id` / `.source_message_id` / `.external_action_request_id` | `personnel` / `messages` / `external_action_requests` | }o--o| | RESTRICT | |
| `task_assignments.task_id` / `.assignee_personnel_id` | `tasks` / `personnel` | }|--|| | RESTRICT | |
| `task_dependencies.predecessor_task_id` / `.successor_task_id` | `tasks` | }o--|| | RESTRICT | |
| `business_alerts.owner_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `baack.business_alert_id` / `.personnel_id` | `business_alerts` / `personnel` | }o--|| | RESTRICT | |
| `bares.business_alert_id` / `.resolved_by_personnel_id` | `business_alerts` / `personnel` | }o--|| | RESTRICT | |
| `bares.evidence_document_revision_id` / `.approved_by_personnel_id` | `document_revisions` / `personnel` | }o--o| | RESTRICT | |

### 5.3 DMS, iletişim, e-posta (08)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `file_objects.derived_from_file_object_id` | `file_objects` | }o--o| | RESTRICT | self |
| `file_objects.uploaded_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `file_objects.retention_policy_id` | `retention_policies` | }o--o| | RESTRICT | |
| `document_types.default_classification_id` / `.default_retention_policy_id` | `security_classifications` / `retention_policies` | }o--|| | RESTRICT | |
| `documents.document_type_id` / `.owner_personnel_id` / `.classification_id` / `.retention_policy_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `documents.owner_org_unit_id` / `.project_id` / `.functional_area_id` | `org_units` / `projects` / `functional_areas` | }o--o| | RESTRICT | |
| `documents(id, current_revision_id)` | `document_revisions(document_id, id)` | |o--o| | RESTRICT | composite |
| `document_revisions.document_id` | `documents` | }|--|| | RESTRICT | |
| `document_revisions.prepared_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `document_revisions.checked_by/approved_by_personnel_id` / `.approval_request_id` | `personnel` / `approval_requests` | }o--o| | RESTRICT | |
| `document_revisions(document_id, superseded_by_revision_id)` | `document_revisions(document_id, id)` | }o--o| | RESTRICT | composite self |
| `document_revision_files.document_revision_id` / `.file_object_id` | `document_revisions` / `file_objects` | }|--|| / }o--|| | RESTRICT | |
| `document_links.document_id` / `.linked_by_personnel_id` | `documents` / `personnel` | }o--|| | RESTRICT | |
| `document_links.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `document_reviews.document_revision_id` / `.reviewer_personnel_id` | `document_revisions` / `personnel` | }o--|| | RESTRICT | |
| `document_distributions.document_revision_id` / `.recipient_personnel_id` / `.distributed_by_personnel_id` | `document_revisions` / `personnel` | }o--|| | RESTRICT | |
| `document_distributions.transmittal_id` | `transmittals` | }o--o| | RESTRICT | |
| `document_acknowledgements.document_revision_id` / `.personnel_id` | `document_revisions` / `personnel` | }o--|| | RESTRICT | |
| `transmittals.project_id` / `.recipient_party_id` / `.issued_by_personnel_id` / `.cover_document_revision_id` | `projects` / `parties` / `personnel` / `document_revisions` | }o--o| | RESTRICT | |
| `transmittal_items.transmittal_id` / `.document_revision_id` | `transmittals` / `document_revisions` | }|--|| / }o--|| | RESTRICT | |
| `dtv.document_template_id` | `document_templates` | }|--|| | RESTRICT | |
| `generated_outputs.template_version_id` / `.personnel_id` | `dtv` / `personnel` | }o--|| | RESTRICT | |
| `generated_outputs.file_object_id` | `file_objects` | }o--o| | RESTRICT | |
| `legal_holds.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `legal_holds.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `legal_hold_documents.legal_hold_id` / `.document_id` / `.added_by_personnel_id` | `legal_holds` / `documents` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `legal_hold_documents.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `conversations.classification_id` | `security_classifications` | }o--|| | RESTRICT | |
| `conversation_memberships.conversation_id` / `.personnel_id` | `conversations` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `messages.conversation_id` / `.author_personnel_id` | `conversations` / `personnel` | }o--|| | RESTRICT | |
| `messages(conversation_id, reply_to_message_id)` | `messages(conversation_id, id)` | }o--o| | RESTRICT | composite self |
| `messages(id, current_version_id)` | `message_versions(message_id, id)` | |o--o| | RESTRICT | composite |
| `messages.redacted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `message_versions.message_id` | `messages` | }|--|| | RESTRICT | |
| `message_mentions.message_id` / `.mentioned_personnel_id` | `messages` / `personnel` | }o--|| | RESTRICT | |
| `message_attachments.message_id` | `messages` | }o--|| | RESTRICT | |
| `message_attachments.file_object_id` / `.document_revision_id` | `file_objects` / `document_revisions` | }o--o| | RESTRICT | XOR |
| `conversation_read_cursors.conversation_id` / `.personnel_id` | `conversations` / `personnel` | }o--|| | RESTRICT | |
| `message_business_links.message_id` | `messages` | }o--|| | RESTRICT | |
| `inbound_email_messages.mailbox_id` / `.raw_file_object_id` / `.classification_id` | `mailboxes` / `file_objects` / `security_classifications` | }o--|| | RESTRICT | |
| `inbound_email_recipients.inbound_email_message_id` | `inbound_email_messages` | }|--|| | RESTRICT | |
| `inbound_email_attachments.inbound_email_message_id` / `.file_object_id` | `inbound_email_messages` / `file_objects` | }o--|| | RESTRICT | |
| `inbound_email_attachments.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `iebl.inbound_email_message_id` / `.linked_by_personnel_id` | `inbound_email_messages` / `personnel` | }o--|| | RESTRICT | |
| `email_processing_attempts.inbound_email_message_id` | `inbound_email_messages` | }o--|| | RESTRICT | |

### 5.4 Sosyal medya (09)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `social_accounts.functional_area_id` / `.platform_id` | `functional_areas` / `social_platforms` | }o--|| | RESTRICT | |
| `social_accounts.content_owner_employee_id` / `.ownership_evidence_document_revision_id` / `.verified_by_personnel_id` | `personnel` / `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `saih.social_account_id` / `.verified_by_personnel_id` | `social_accounts` / `personnel` | }o--|| | RESTRICT | |
| `social_webhook_receipts.platform_id` | `social_platforms` | }o--|| | RESTRICT | |
| `social_campaigns.functional_area_id` / `.owner_employee_id` | `functional_areas` / `personnel` | }o--|| | RESTRICT | |
| `social_content_items.functional_area_id` / `.owner_employee_id` | `functional_areas` / `personnel` | }o--|| | RESTRICT | |
| `social_content_items.campaign_id` / `.special_day_occurrence_id` | `social_campaigns` / `special_day_occurrences` | }o--o| | RESTRICT | |
| `social_content_items(id, current_version_id)` | `social_content_versions(content_item_id, id)` | |o--o| | RESTRICT | composite |
| `social_content_versions.content_item_id` | `social_content_items` | }|--|| | RESTRICT | |
| `social_content_versions.submitted_by/approved_by_personnel_id` / `.approval_policy_version_id` / `.approval_request_id` | `personnel` / `apv` / `approval_requests` | }o--o| | RESTRICT | |
| `social_content_versions(content_item_id, superseded_by_version_id)` | `social_content_versions(content_item_id, id)` | }o--o| | RESTRICT | composite self |
| `scvt.content_version_id` | `social_content_versions` | }o--|| | RESTRICT | |
| `social_content_assets.content_version_id` / `.document_revision_id` | `social_content_versions` / `document_revisions` | }o--|| | RESTRICT | |
| `social_content_assets.consent_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `social_content_reviews.content_version_id` / `.reviewer_personnel_id` | `social_content_versions` / `personnel` | }o--|| | RESTRICT | |
| `social_content_reviews.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `social_content_targets.content_version_id` / `.social_account_id` | `social_content_versions` / `social_accounts` | }o--|| | RESTRICT | |
| `social_content_targets.published_publication_id` | `social_publications` | }o--o| | RESTRICT | ALTER ile (döngü) |
| `social_publications.content_target_id` / `.platform_id` / `.recorded_by_personnel_id` | `social_content_targets` / `social_platforms` / `personnel` | }o--|| | RESTRICT | |
| `social_publications.evidence_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `spa.social_publication_id` | `social_publications` | }o--|| | RESTRICT | |
| `spc.social_publication_id` / `.personnel_id` | `social_publications` / `personnel` | }o--|| | RESTRICT | |
| `spc.evidence_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `special_day_calendars.organization_id` / `.owner_employee_id` | `organizations` / `personnel` | }o--|| | RESTRICT | |
| `special_day_calendars.country_code` | `countries` | }o--o| | RESTRICT | |
| `special_day_definitions.calendar_id` | `special_day_calendars` | }o--|| | RESTRICT | |
| `special_day_translations.definition_id` | `special_day_definitions` | }|--|| | CASCADE | |
| `special_day_occurrences.definition_id` | `special_day_definitions` | }o--|| | RESTRICT | |
| `special_day_occurrences.verified_by_personnel_id` / `.supersedes_occurrence_id` | `personnel` / `special_day_occurrences` | }o--o| | RESTRICT | |
| `srpv.functional_area_id` | `functional_areas` | }o--|| | RESTRICT | |
| `srpv.business_calendar_id` / `.published_by_personnel_id` | `business_calendars` / `personnel` | }o--o| | RESTRICT | |
| `srps.policy_version_id` | `srpv` | }|--|| | RESTRICT | |
| `srsr.policy_step_id` | `srps` | }|--|| | RESTRICT | |
| `social_special_day_rules.functional_area_id` / `.policy_version_id` | `functional_areas` / `srpv` | }o--|| | RESTRICT | |
| `social_special_day_rules.special_day_calendar_id` / `.definition_id` / `.social_account_id` | `special_day_calendars` / `special_day_definitions` / `social_accounts` | }o--o| | RESTRICT | |
| `social_special_day_plans.occurrence_id` / `.functional_area_id` / `.policy_version_id` | `special_day_occurrences` / `functional_areas` / `srpv` | }o--|| | RESTRICT | |
| `social_special_day_plans.owner_employee_id` / `.content_item_id` / `.acknowledged_by/decision_by_personnel_id` | `personnel` / `social_content_items` / `personnel` | }o--o| | RESTRICT | |
| `ssdri.special_day_plan_id` / `.policy_step_id` | `social_special_day_plans` / `srps` | }o--|| | RESTRICT | |
| `srir.reminder_instance_id` / `.personnel_id` | `ssdri` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `srir.notification_instance_id` / `.task_id` | `notification_instances` / `tasks` | }o--o| | RESTRICT | |
| `spmm.platform_id` / `.metric_definition_id` | `social_platforms` / `social_metric_definitions` | }o--|| | RESTRICT | |
| `smib.social_account_id` / `.entered_by_personnel_id` | `social_accounts` / `personnel` | }o--|| | RESTRICT | |
| `smib.sync_run_id` / `.evidence_document_revision_id` | `social_sync_runs` / `document_revisions` | }o--o| | RESTRICT | |
| `samo.ingestion_batch_id` / `.social_account_id` / `.metric_definition_id` | `smib` / `social_accounts` / `social_metric_definitions` | }o--|| | RESTRICT | |
| `spmo.ingestion_batch_id` / `.social_publication_id` / `.metric_definition_id` | `smib` / `social_publications` / `social_metric_definitions` | }o--|| | RESTRICT | |
| `social_metric_sync_cursors.social_account_id` | `social_accounts` | }o--|| | RESTRICT | |
| `social_kpis.functional_area_id` | `functional_areas` | }o--|| | RESTRICT | |
| `social_kpis.owner_employee_id` | `personnel` | }o--o| | RESTRICT | |
| `social_kpis(id, current_formula_version_id)` | `social_kpi_formula_versions(social_kpi_id, id)` | |o--o| | RESTRICT | composite |
| `social_kpi_formula_versions.social_kpi_id` | `social_kpis` | }|--|| | RESTRICT | |
| `social_kpi_formula_inputs.formula_version_id` / `.metric_definition_id` | `social_kpi_formula_versions` / `social_metric_definitions` | }|--|| / }o--|| | RESTRICT | |

### 5.5 Party ve İş Alım (10)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `parties.country_code` / `.merged_into_party_id` | `countries` / `parties` | }o--o| | RESTRICT | |
| `party_roles.party_id` | `parties` | }o--|| | RESTRICT | |
| `party_roles.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `organization_profiles.party_id` / `person_profiles.party_id` | `parties` | ||--o| | RESTRICT | PK=FK; subtype servis kontrolü |
| `organization_profiles.group_parent_party_id` | `parties` | }o--o| | RESTRICT | |
| `addresses.party_id` / `.country_code` | `parties` / `countries` | }o--|| | RESTRICT | |
| `communication_points.party_id` | `parties` | }o--|| | RESTRICT | |
| `contact_relationships.organization_party_id` / `.contact_party_id` | `parties` | }o--|| | RESTRICT | |
| `party_licenses.party_id` / `party_certificates.party_id` | `parties` | }o--|| | RESTRICT | |
| `party_licenses.document_revision_id` / `party_certificates.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `party_annual_reviews.party_id` / `.reviewer_employee_id` | `parties` / `personnel` | }o--|| | RESTRICT | |
| `business_cases.sequence_no` | `business_number_allocations` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `business_cases.legal_entity_id` / `.primary_party_id` / `.country_code` / `.currency_code` / `.owner_employee_id` / `.classification_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `business_cases.proposal_owner_employee_id` | `personnel` | }o--o| | RESTRICT | |
| `business_codes(business_case_id, sequence_no)` | `business_cases(id, sequence_no)` | }|--|| | RESTRICT | composite |
| `business_codes.issued_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `business_codes.predecessor_code_id` | `business_codes` | }o--o| | RESTRICT | self |
| `opportunities.business_case_id` | `business_cases` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `opportunities.bid_decision_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `opportunity_stage_histories.opportunity_id` / `.changed_by_personnel_id` | `opportunities` / `personnel` | }o--|| | RESTRICT | |
| `business_development_activities.organizer_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `business_development_activities.business_case_id` / `.party_id` / `.follow_up_task_id` | `business_cases` / `parties` / `tasks` | }o--o| | RESTRICT | |
| `bdap.activity_id` | `business_development_activities` | }o--|| | CASCADE | |
| `bdap.personnel_id` / `.contact_party_id` | `personnel` / `parties` | }o--o| | RESTRICT | XOR |
| `tender_notices.business_case_id` / `.tender_source_id` | `business_cases` / `tender_sources` | }o--|| | RESTRICT | |
| `tender_notices.issuer_party_id` | `parties` | }o--o| | RESTRICT | |
| `tender_notices(id, current_version_id)` | `tender_notice_versions(tender_notice_id, id)` | |o--o| | RESTRICT | composite |
| `tender_notice_versions.tender_notice_id` / `.captured_by_personnel_id` | `tender_notices` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `tender_notice_versions.source_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `tender_requirements.tender_notice_version_id` | `tender_notice_versions` | }o--|| | RESTRICT | |
| `tender_requirements.evaluated_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `tender_deadlines.tender_notice_version_id` | `tender_notice_versions` | }o--|| | RESTRICT | |
| `proposals.business_case_id` / `.owner_employee_id` | `business_cases` / `personnel` | }o--|| | RESTRICT | |
| `proposals(id, current_version_id)` | `proposal_versions(proposal_id, id)` | |o--o| | RESTRICT | composite |
| `proposal_versions.proposal_id` / `.currency_code` / `.prepared_by_personnel_id` | `proposals` / `currencies` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `proposal_versions.project_group_opinion_document_revision_id` / `.approval_request_id` / `.approved_by_personnel_id` / `.submission_evidence_document_revision_id` | `document_revisions` / `approval_requests` / `personnel` / `document_revisions` | }o--o| | RESTRICT | |
| `proposal_documents.proposal_version_id` / `.document_revision_id` | `proposal_versions` / `document_revisions` | }o--|| | RESTRICT | |
| `compliance_items.proposal_version_id` | `proposal_versions` | }o--|| | RESTRICT | |
| `compliance_items.tender_requirement_id` | `tender_requirements` | }o--o| | RESTRICT | |
| `deviations.proposal_version_id` | `proposal_versions` | }o--|| | RESTRICT | |
| `deviations.compliance_item_id` | `compliance_items` | }o--o| | RESTRICT | |
| `brand_items.proposal_version_id` / `rmi.proposal_version_id` | `proposal_versions` | }o--|| | RESTRICT | |
| `brand_items.origin_country_code` | `countries` | }o--o| | RESTRICT | |
| `estimate_versions.proposal_version_id` / `.currency_code` / `.prepared_by_personnel_id` | `proposal_versions` / `currencies` / `personnel` | }o--|| | RESTRICT | |
| `estimate_versions.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `estimate_lines.estimate_version_id` / `.uom_id` | `estimate_versions` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `estimate_lines.parent_line_id` | `estimate_lines` | }o--o| | RESTRICT | self |
| `pricing_scenarios.estimate_version_id` | `estimate_versions` | }o--|| | RESTRICT | |
| `boq_items.estimate_version_id` / `.uom_id` | `estimate_versions` / `units_of_measure` | }o--|| | RESTRICT | |
| `boq_items.catalog_item_id` | `catalog_items` | }o--o| | RESTRICT | |
| `contracts.business_case_id` / `.customer_party_id` | `business_cases` / `parties` | }o--|| | RESTRICT | |
| `contracts(id, current_version_id)` | `contract_versions(contract_id, id)` | |o--o| | RESTRICT | composite |
| `contract_versions.contract_id` / `.currency_code` | `contracts` / `currencies` | }|--|| / }o--|| | RESTRICT | |
| `contract_versions.approval_request_id` / `.approved_by_personnel_id` | `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `contract_parties.contract_version_id` / `.party_id` | `contract_versions` / `parties` | }|--|| / }o--|| | RESTRICT | |
| `contract_documents.contract_version_id` / `.document_revision_id` | `contract_versions` / `document_revisions` | }o--|| | RESTRICT | |
| `contract_obligations.contract_version_id` | `contract_versions` | }o--|| | RESTRICT | |
| `contract_obligations.responsible_party_id` | `parties` | }o--o| | RESTRICT | |
| `contract_milestones.contract_version_id` | `contract_versions` | }o--|| | RESTRICT | |
| `operation_handoffs.business_case_id` | `business_cases` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `operation_handoffs.prepared_by_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `operation_handoffs(id, accepted_version_id)` | `ohv(operation_handoff_id, id)` | |o--o| | RESTRICT | composite |
| `operation_handoffs.accepted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `ohv.operation_handoff_id` / `.proposal_version_id` | `operation_handoffs` / `proposal_versions` | }|--|| / }o--|| | RESTRICT | |
| `ohv.contract_version_id` / `.manifest_document_revision_id` / `.submitted_by_personnel_id` | `contract_versions` / `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `handoff_items.handoff_version_id` | `ohv` | }|--|| | RESTRICT | |
| `handoff_items.document_revision_id` / `.waived_by_personnel_id` | `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `handoff_reviews.handoff_version_id` / `.reviewer_personnel_id` | `ohv` / `personnel` | }o--|| | RESTRICT | |
| `handoff_reviews.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |

### 5.6 Operasyon (11)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `projects.business_case_id` | `business_cases` | |o--|| | RESTRICT | UNIQUE |
| `projects.project_business_code_id` | `business_codes` | |o--|| | RESTRICT | UNIQUE |
| `projects.accepted_handoff_version_id` | `ohv` | |o--|| | RESTRICT | UNIQUE |
| `projects.customer_party_id` / `.legal_entity_id` / `.project_manager_employee_id` / `.country_code` / `.currency_code` / `.stage_template_version_id` / `.classification_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `projects(id, primary_focus_workstream_id)` | `project_workstreams(project_id, id)` | |o--o| | RESTRICT | composite; ALTER ile |
| `projects.cover_file_object_id` | `file_objects` | }o--o| | RESTRICT | |
| `project_components.project_id` / `.component_definition_id` | `projects` / `component_definitions` | }o--|| | RESTRICT | |
| `project_components.capacity_uom_id` | `units_of_measure` | }o--o| | RESTRICT | |
| `project_workstreams.project_id` / `.group_definition_id` / `.owner_personnel_id` | `projects` / `operation_group_definitions` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `workstream_dependencies.predecessor/successor_workstream_id` | `project_workstreams` | }o--|| | RESTRICT | |
| `workstream_dependencies.waived_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `project_focus_histories.project_id` / `.changed_by_personnel_id` | `projects` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `project_focus_histories(project_id, workstream_id)` | `project_workstreams(project_id, id)` | }o--|| | RESTRICT | composite |
| `work_packages.project_workstream_id` / `.project_id` / `.owner_personnel_id` | `project_workstreams` / `projects` / `personnel` | }o--|| | RESTRICT | |
| `work_packages(project_id, wbs_node_id)` | `wbs_nodes(project_id, id)` | }o--o| | RESTRICT | composite |
| `work_package_dependencies.predecessor/successor_package_id` | `work_packages` | }o--|| | RESTRICT | |
| `stage_templates(id, current_version_id)` | `stage_template_versions(stage_template_id, id)` | |o--o| | RESTRICT | composite |
| `stage_template_versions.stage_template_id` | `stage_templates` | }|--|| | RESTRICT | |
| `stage_nodes.stage_template_version_id` | `stage_template_versions` | }|--|| | RESTRICT | |
| `stage_nodes.owner_group_definition_id` / `.approval_policy_id` | `operation_group_definitions` / `approval_policies` | }o--o| | RESTRICT | |
| `stage_dependencies.predecessor/successor_node_id` | `stage_nodes` | }o--|| | RESTRICT | |
| `srd.stage_node_id` | `stage_nodes` | }|--|| | RESTRICT | |
| `srd.min_document_type_id` | `document_types` | }o--o| | RESTRICT | |
| `project_stage_instances.project_id` / `.stage_node_id` / `.owner_personnel_id` | `projects` / `stage_nodes` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `project_stage_instances.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `psr.project_stage_instance_id` / `.requirement_definition_id` | `project_stage_instances` / `srd` | }|--|| / }o--|| | RESTRICT | |
| `psr.owner_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `stage_evidence.project_stage_requirement_id` / `.document_revision_id` / `.submitted_by_personnel_id` | `psr` / `document_revisions` / `personnel` | }o--|| | RESTRICT | |
| `stage_evidence.accepted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `stage_reviews.project_stage_instance_id` / `.reviewer_personnel_id` | `project_stage_instances` / `personnel` | }o--|| | RESTRICT | |
| `stage_reviews.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `stage_waivers.project_stage_instance_id` / `.approved_by_personnel_id` / `.risk_owner_personnel_id` | `project_stage_instances` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `stage_waivers.project_stage_requirement_id` / `.remediation_task_id` / `.approval_request_id` | `psr` / `tasks` / `approval_requests` | }o--o| | RESTRICT | |
| `department_handoffs.project_id` | `projects` | }o--|| | RESTRICT | |
| `department_handoffs(project_id, source/target_workstream_id)` | `project_workstreams(project_id, id)` | }o--|| | RESTRICT | composite ×2 |
| `department_handoffs(project_id, trigger_stage_instance_id)` | `project_stage_instances(project_id, id)` | }o--|| | RESTRICT | composite |
| `department_handoffs(id, accepted_version_id)` | `dhv(department_handoff_id, id)` | |o--o| | RESTRICT | composite |
| `department_handoffs.accepted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `dhv.department_handoff_id` | `department_handoffs` | }|--|| | RESTRICT | |
| `dhv.submitted_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `dhi.handoff_version_id` | `dhv` | }|--|| | RESTRICT | |
| `dhi.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `dhr.handoff_version_id` / `.reviewer_personnel_id` | `dhv` / `personnel` | }o--|| | RESTRICT | |
| `wbs_nodes.project_id` / `cbs_nodes.project_id` | `projects` | }|--|| | RESTRICT | |
| `wbs_nodes(project_id, parent_id)` / `cbs_nodes(project_id, parent_id)` | kendi tablosu `(project_id, id)` | }o--o| | RESTRICT | composite self |
| `wbs_cbs_mappings.wbs_node_id` / `.cbs_node_id` | `wbs_nodes` / `cbs_nodes` | }o--|| | RESTRICT | |
| `schedule_baselines.project_id` / `budget_baselines.project_id` | `projects` | }o--|| | RESTRICT | |
| `schedule_baselines.baseline_document_revision_id` / `.approval_request_id` / `.approved_by_personnel_id` | `document_revisions` / `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `budget_baselines.currency_code` | `currencies` | }o--|| | RESTRICT | |
| `budget_baselines.budget_version_id` / `.approval_request_id` / `.approved_by_personnel_id` | `budget_versions` / `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `milestones.project_id` | `projects` | }o--|| | RESTRICT | |
| `milestones(project_id, wbs_node_id)` | `wbs_nodes(project_id, id)` | }o--o| | RESTRICT | composite |
| `milestones.contract_milestone_id` | `contract_milestones` | }o--o| | RESTRICT | |
| `progress_snapshots.project_id` / `.reported_by_personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `progress_snapshots.report_submission_version_id` | `rsv` | }o--o| | RESTRICT | |
| `project_tasks.task_id` | `tasks` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `project_tasks.project_id` | `projects` | }o--|| | RESTRICT | |
| `project_tasks(project_id, workstream_id / work_package_id / wbs_node_id / stage_instance_id)` | ilgili `(project_id, id)` | }o--o| | RESTRICT | composite ×4 |
| `project_issues/project_risks/delay_events.project_id` | `projects` | }o--|| | RESTRICT | |
| `project_issues/project_risks/delay_events(project_id, workstream_id)` | `project_workstreams(project_id, id)` | }o--o| | RESTRICT | composite |
| `project_issues.owner/raised_by_personnel_id`, `project_risks.owner_personnel_id`, `delay_events.reported_by_personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `delay_events.evidence_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `recovery_actions.delay_event_id` / `.owner_personnel_id` | `delay_events` / `personnel` | }o--|| | RESTRICT | |
| `project_changes.project_id` / `.personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `project_changes.currency_code` / `.approval_request_id` | `currencies` / `approval_requests` | }o--o| | RESTRICT | |
| `commercial_clarifications.project_id` / `.raised_by_personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `commercial_clarifications.customer_contact_party_id` / `.linked_change_id` | `parties` / `project_changes` | }o--o| | RESTRICT | |
| `commercial_exposures.project_id` / `.currency_code` / `.owner_personnel_id` | `projects` / `currencies` / `personnel` | }o--|| | RESTRICT | |
| `commercial_exposures(project_id, cbs_node_id)` | `cbs_nodes(project_id, id)` | }o--o| | RESTRICT | composite |
| `commercial_exposures.source_delay_event_id` / `.source_change_id` | `delay_events` / `project_changes` | }o--o| | RESTRICT | |
| `project_decisions.project_id` / `.personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `project_decisions.source_message_id` / `.document_revision_id` | `messages` / `document_revisions` | }o--o| | RESTRICT | |
| `catalog_categories.parent_id` | `catalog_categories` | }o--o| | RESTRICT | self |
| `brands.manufacturer_party_id` | `parties` | }o--o| | RESTRICT | |
| `catalog_items.category_id` / `.default_uom_id` | `catalog_categories` / `units_of_measure` | }o--|| | RESTRICT | |
| `catalog_items.brand_id` / `.image_file_object_id` | `brands` / `file_objects` | }o--o| | RESTRICT | |
| `approved_equivalents.source_item_id` / `.equivalent_item_id` | `catalog_items` | }o--|| | RESTRICT | |
| `approved_equivalents.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `purchase_requisitions.requesting_org_unit_id` / `.personnel_id` | `org_units` / `personnel` | }o--|| | RESTRICT | |
| `purchase_requisitions.project_id` / `.technical_spec_document_revision_id` / `.approval_request_id` | `projects` / `document_revisions` / `approval_requests` | }o--o| | RESTRICT | |
| `purchase_requisitions(project_id, workstream_id)` | `project_workstreams(project_id, id)` | }o--o| | RESTRICT | composite |
| `prl.requisition_id` / `.uom_id` | `purchase_requisitions` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `prl.catalog_item_id` / `.wbs_node_id` / `.cbs_node_id` / `.currency_code` | `catalog_items` / `wbs_nodes` / `cbs_nodes` / `currencies` | }o--o| | RESTRICT | |
| `supplier_rfqs.currency_code` | `currencies` | }o--|| | RESTRICT | |
| `supplier_rfqs.project_id` / `.issued_by_personnel_id` / `.terms_document_revision_id` | `projects` / `personnel` / `document_revisions` | }o--o| | RESTRICT | |
| `supplier_rfq_lines.supplier_rfq_id` / `.requisition_line_id` / `.uom_id` | `supplier_rfqs` / `prl` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `supplier_rfq_invitees.supplier_rfq_id` / `.supplier_party_id` | `supplier_rfqs` / `parties` | }|--|| / }o--|| | RESTRICT | |
| `supplier_rfq_invitees.contact_party_id` | `parties` | }o--o| | RESTRICT | |
| `supplier_quotes.rfq_invitee_id` | `supplier_rfq_invitees` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `supplier_quotes(id, current_version_id)` | `sqv(supplier_quote_id, id)` | |o--o| | RESTRICT | composite |
| `sqv.supplier_quote_id` / `.currency_code` | `supplier_quotes` / `currencies` | }|--|| / }o--|| | RESTRICT | |
| `sqv.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `supplier_quote_lines.quote_version_id` / `.supplier_rfq_line_id` / `.uom_id` | `sqv` / `supplier_rfq_lines` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `supplier_quote_lines.offered_catalog_item_id` | `catalog_items` | }o--o| | RESTRICT | |
| `bid_comparisons.supplier_rfq_id` / `.prepared_by_personnel_id` | `supplier_rfqs` / `personnel` | }o--|| | RESTRICT | |
| `bid_comparisons.evaluation_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `bid_comparison_lines.bid_comparison_id` / `.supplier_rfq_line_id` / `.supplier_quote_line_id` | ilgili tablolar | }|--|| / }o--|| | RESTRICT | |
| `award_recommendations.bid_comparison_id` / `.selected_quote_version_id` / `.currency_code` / `.recommended_by_personnel_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `award_recommendations.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `purchase_orders.requesting_org_unit_id` / `.supplier_party_id` / `.currency_code` | `org_units` / `parties` / `currencies` | }o--|| | RESTRICT | |
| `purchase_orders.project_id` / `.award_recommendation_id` | `projects` / `award_recommendations` | }o--o| | RESTRICT | |
| `purchase_orders(id, current_version_id)` | `pov(purchase_order_id, id)` | |o--o| | RESTRICT | composite |
| `pov.purchase_order_id` / `.currency_code` | `purchase_orders` / `currencies` | }|--|| / }o--|| | RESTRICT | |
| `pov.document_revision_id` / `.approval_request_id` / `.approved_by_personnel_id` | `document_revisions` / `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `purchase_order_lines.purchase_order_version_id` / `.catalog_item_id` / `.uom_id` | `pov` / `catalog_items` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `purchase_order_lines.requisition_line_id` / `.wbs_node_id` / `.cbs_node_id` | `prl` / `wbs_nodes` / `cbs_nodes` | }o--o| | RESTRICT | |
| `delivery_schedules.purchase_order_line_id` | `purchase_order_lines` | }|--|| | RESTRICT | |
| `carriers.party_id` | `parties` | ||--o| | RESTRICT | PK=FK |
| `shipments.responsible_employee_id` | `personnel` | }o--|| | RESTRICT | |
| `shipments.project_id` / `.carrier_party_id` / `.destination_warehouse_id` | `projects` / `carriers` / `warehouses` | }o--o| | RESTRICT | |
| `shipment_items.shipment_id` / `.delivery_schedule_id` / `.uom_id` | `shipments` / `delivery_schedules` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `customs_records.shipment_id` | `shipments` | }o--|| | RESTRICT | |
| `customs_records.broker_party_id` / `.currency_code` / `.document_revision_id` | `parties` / `currencies` / `document_revisions` | }o--o| | RESTRICT | |
| `warehouses.legal_entity_id` | `legal_entities` | }o--|| | RESTRICT | |
| `warehouses.project_id` | `projects` | }o--o| | RESTRICT | |
| `warehouse_locations.warehouse_id` | `warehouses` | }|--|| | RESTRICT | |
| `bins.warehouse_location_id` / `.warehouse_id` | `warehouse_locations` / `warehouses` | }|--|| / }o--|| | RESTRICT | |
| `goods_receipts.warehouse_id` / `.received_by_personnel_id` | `warehouses` / `personnel` | }o--|| | RESTRICT | |
| `goods_receipts.shipment_id` / `.supplier_party_id` | `shipments` / `parties` | }o--o| | RESTRICT | |
| `goods_receipt_lines.goods_receipt_id` / `.catalog_item_id` / `.uom_id` | `goods_receipts` / `catalog_items` / `units_of_measure` | }|--|| / }o--|| | RESTRICT | |
| `goods_receipt_lines.shipment_item_id` / `.purchase_order_line_id` / `.bin_id` / `.stock_lot_id` | ilgili tablolar | }o--o| | RESTRICT | |
| `receipt_inspections.goods_receipt_line_id` / `.inspected_by_personnel_id` | `goods_receipt_lines` / `personnel` | }o--|| | RESTRICT | |
| `receipt_inspections.evidence_document_revision_id` / `.ncr_id` | `document_revisions` / `ncrs` | }o--o| | RESTRICT | |
| `stock_lots.catalog_item_id` | `catalog_items` | }o--|| | RESTRICT | |
| `stock_lots.supplier_party_id` | `parties` | }o--o| | RESTRICT | |
| `serialized_items.catalog_item_id` | `catalog_items` | }o--|| | RESTRICT | |
| `serialized_items.stock_lot_id` / `.current_bin_id` / `.project_id` / `.installed_asset_id` | ilgili tablolar | }o--o| | RESTRICT | |
| `stock_reservations.project_id` / `.catalog_item_id` / `.warehouse_id` / `.uom_id` / `.reserved_by_personnel_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `stock_reservations.bin_id` | `bins` | }o--o| | RESTRICT | |
| `inventory_transactions.catalog_item_id` / `.uom_id` / `.posted_by_personnel_id` | `catalog_items` / `units_of_measure` / `personnel` | }o--|| | RESTRICT | |
| `inventory_transactions.stock_lot_id` / `.serialized_item_id` / `.from_bin_id` / `.to_bin_id` / `.project_id` / `.currency_code` | ilgili tablolar | }o--o| | RESTRICT | |
| `stock_counts.warehouse_id` | `warehouses` | }o--|| | RESTRICT | |
| `stock_counts.approval_request_id` / `.approved_by_personnel_id` | `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `stock_count_lines.stock_count_id` / `.bin_id` / `.catalog_item_id` / `.uom_id` / `.counted_by_personnel_id` | ilgili tablolar | }|--|| / }o--|| | RESTRICT | |
| `stock_count_lines.stock_lot_id` | `stock_lots` | }o--o| | RESTRICT | |
| `stock_transfers.source_warehouse_id` / `.target_warehouse_id` / `.personnel_id` | `warehouses` / `warehouses` / `personnel` | }o--|| | RESTRICT | |
| `stock_transfers.project_id` / `.shipment_id` | `projects` / `shipments` | }o--o| | RESTRICT | |
| `stock_transfer_lines.stock_transfer_id` / `.catalog_item_id` / `.source_bin_id` / `.target_bin_id` / `.uom_id` | ilgili tablolar | }|--|| / }o--|| | RESTRICT | |
| `stock_transfer_lines.stock_lot_id` / `.serialized_item_id` | `stock_lots` / `serialized_items` | }o--o| | RESTRICT | |
| `project_budgets.project_id` | `projects` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `project_budgets.currency_code` | `currencies` | }o--|| | RESTRICT | |
| `project_budgets(id, current_version_id)` | `budget_versions(project_budget_id, id)` | |o--o| | RESTRICT | composite |
| `budget_versions.project_budget_id` / `.currency_code` | `project_budgets` / `currencies` | }|--|| / }o--|| | RESTRICT | |
| `budget_versions.approval_request_id` / `.approved_by_personnel_id` | `approval_requests` / `personnel` | }o--o| | RESTRICT | |
| `budget_lines.budget_version_id` / `.cbs_node_id` | `budget_versions` / `cbs_nodes` | }|--|| / }o--|| | RESTRICT | |
| `commitments.project_id` / `.purchase_order_line_id` / `.currency_code` | `projects` / `purchase_order_lines` / `currencies` | }o--|| | RESTRICT | |
| `commitments(project_id, cbs_node_id)` | `cbs_nodes(project_id, id)` | }o--|| | RESTRICT | composite |
| `actual_cost_references.project_id` / `.currency_code` | `projects` / `currencies` | }o--|| | RESTRICT | |
| `actual_cost_references(project_id, cbs_node_id)` | `cbs_nodes(project_id, id)` | }o--|| | RESTRICT | composite |
| `supplier_invoices.supplier_party_id` / `.currency_code` / `.document_revision_id` | `parties` / `currencies` / `document_revisions` | }o--|| | RESTRICT | |
| `supplier_invoices.project_id` | `projects` | }o--o| | RESTRICT | |
| `invoice_lines.supplier_invoice_id` | `supplier_invoices` | }|--|| | RESTRICT | |
| `invoice_lines.cbs_node_id` / `.purchase_order_line_id` / `.uom_id` | `cbs_nodes` / `purchase_order_lines` / `units_of_measure` | }o--o| | RESTRICT | |
| `invoice_matches.invoice_line_id` / `.purchase_order_line_id` / `.goods_receipt_line_id` / `.matched_by_personnel_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `tax_obligations.legal_entity_id` / `.currency_code` | `legal_entities` / `currencies` | }o--|| | RESTRICT | |
| `tax_obligations.project_id` / `.supplier_invoice_id` | `projects` / `supplier_invoices` | }o--o| | RESTRICT | |
| `payment_requests.beneficiary_party_id` / `.currency_code` / `.personnel_id` | `parties` / `currencies` / `personnel` | }o--|| | RESTRICT | |
| `payment_requests.project_id` / `.approval_request_id` | `projects` / `approval_requests` | }o--o| | RESTRICT | |
| `payment_request_invoices.payment_request_id` / `.supplier_invoice_id` | `payment_requests` / `supplier_invoices` | }|--|| / }o--|| | RESTRICT | |
| `payments.payment_request_id` / `.currency_code` / `.recorded_by_personnel_id` | `payment_requests` / `currencies` / `personnel` | }o--|| | RESTRICT | |
| `payments.evidence_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `cash_flow_forecasts.project_id` / `.currency_code` / `.prepared_by_personnel_id` | `projects` / `currencies` / `personnel` | }o--|| | RESTRICT | |
| `progress_claims.project_id` | `projects` | }o--|| | RESTRICT | |
| `progress_claims.contract_version_id` | `contract_versions` | }o--o| | RESTRICT | |
| `progress_claims(id, current_version_id)` | `progress_claim_versions(progress_claim_id, id)` | |o--o| | RESTRICT | composite |
| `progress_claim_versions.progress_claim_id` / `.currency_code` | `progress_claims` / `currencies` | }|--|| / }o--|| | RESTRICT | |
| `progress_claim_versions.document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `retentions.progress_claim_version_id` / `.currency_code` | `progress_claim_versions` / `currencies` | }o--|| | RESTRICT | |
| `engineering_deliverables.project_id` / `.responsible_employee_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `engineering_deliverables(project_id, workstream_id)` | `project_workstreams(project_id, id)` | }o--|| | RESTRICT | composite |
| `engineering_deliverables(id, current_revision_id)` | `engineering_revisions(deliverable_id, id)` | |o--o| | RESTRICT | composite |
| `engineering_revisions.deliverable_id` / `.document_revision_id` | `engineering_deliverables` / `document_revisions` | }|--|| / }o--|| | RESTRICT | |
| `technical_requirements.project_id` / `.source_document_revision_id` | `projects` / `document_revisions` | }o--|| | RESTRICT | |
| `technical_requirements.linked_test_execution_id` / `.verified_by_personnel_id` | `test_executions` / `personnel` | }o--o| | RESTRICT | |
| `work_plans.project_id` / `.document_revision_id` | `projects` / `document_revisions` | }o--|| | RESTRICT | |
| `work_plans(project_id, workstream_id)` | `project_workstreams(project_id, id)` | }o--o| | RESTRICT | composite |
| `procedures.document_revision_id` | `document_revisions` | }o--|| | RESTRICT | |
| `procedures.project_id` | `projects` | }o--o| | RESTRICT | |
| `method_statements.project_id` / `.document_revision_id` | `projects` / `document_revisions` | }o--|| | RESTRICT | |
| `method_statements(project_id, work_package_id)` | `work_packages(project_id, id)` | }o--o| | RESTRICT | composite |
| `method_statements.risk_assessment_document_revision_id` / `.approved_by_personnel_id` | `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `crews.project_id` / `.supervisor_employee_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `crew_memberships.crew_id` / `.personnel_id` | `crews` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `equipment_usages.project_id` | `projects` | }o--|| | RESTRICT | |
| `equipment_usages.crew_id` / `.operator_employee_id` | `crews` / `personnel` | }o--o| | RESTRICT | |
| `installed_quantities.project_id` / `.uom_id` / `.reported_by_personnel_id` | `projects` / `units_of_measure` / `personnel` | }o--|| | RESTRICT | |
| `installed_quantities(project_id, work_package_id)` | `work_packages(project_id, id)` | }o--|| | RESTRICT | composite |
| `installed_quantities(project_id, wbs_node_id)` | `wbs_nodes(project_id, id)` | }o--o| | RESTRICT | composite |
| `installed_quantities.catalog_item_id` / `.report_submission_version_id` / `.evidence_document_revision_id` | `catalog_items` / `rsv` / `document_revisions` | }o--o| | RESTRICT | |
| `site_photos.project_id` / `.file_object_id` / `.captured_by_employee_id` | `projects` / `file_objects` / `personnel` | }o--|| | RESTRICT | |
| `site_photos(project_id, work_package_id)` | `work_packages(project_id, id)` | }o--o| | RESTRICT | composite |
| `site_photos.report_answer_id` | `report_answers` | }o--o| | RESTRICT | |
| `inspections.project_id` / `.inspector_personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `inspections(project_id, workstream_id / work_package_id)` | ilgili `(project_id, id)` | }o--o| | RESTRICT | composite |
| `inspections.procedure_id` / `.checklist_document_revision_id` | `procedures` / `document_revisions` | }o--o| | RESTRICT | |
| `ncrs.project_id` / `.owner_personnel_id` / `.raised_by_personnel_id` | `projects` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `ncrs.inspection_id` / `.responsible_party_id` / `.closed_by_personnel_id` | `inspections` / `parties` / `personnel` | }o--o| | RESTRICT | |
| `corrective_actions.ncr_id` / `.owner_personnel_id` | `ncrs` / `personnel` | }|--|| / }o--|| | RESTRICT | |
| `corrective_actions.verified_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `incidents.project_id` / `.reported_by_personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `incidents.investigation_document_revision_id` | `document_revisions` | }o--o| | RESTRICT | |
| `permits.project_id` / `.issued_by_personnel_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `permits.issued_to_crew_id` / `.document_revision_id` | `crews` / `document_revisions` | }o--o| | RESTRICT | |
| `punch_items.project_id` / `.owner_personnel_id` / `.raised_by_personnel_id` | `projects` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `punch_items.inspection_id` / `.test_execution_id` / `.cleared_by_personnel_id` / `.verification_document_revision_id` | ilgili tablolar | }o--o| | RESTRICT | |
| `punch_items(project_id, work_package_id)` | `work_packages(project_id, id)` | }o--o| | RESTRICT | composite |
| `quality_evidence.document_revision_id` | `document_revisions` | }o--|| | RESTRICT | |
| `quality_evidence.inspection_id` / `.ncr_id` / `.corrective_action_id` / `.incident_id` / `.punch_item_id` | ilgili tablolar | }o--o| | RESTRICT | XOR |
| `test_plan_templates.component_definition_id` | `component_definitions` | }o--o| | RESTRICT | |
| `test_plan_templates(id, current_version_id)` | `test_plan_versions(test_plan_template_id, id)` | |o--o| | RESTRICT | composite |
| `test_plan_versions.test_plan_template_id` | `test_plan_templates` | }|--|| | RESTRICT | |
| `test_plan_versions.procedure_document_revision_id` / `.published_by_personnel_id` | `document_revisions` / `personnel` | }o--o| | RESTRICT | |
| `tpsd.test_plan_version_id` | `test_plan_versions` | }|--|| | RESTRICT | |
| `tpsd.measurement_uom_id` | `units_of_measure` | }o--o| | RESTRICT | |
| `test_equipment.owner_org_unit_id` / `.current_calibration_certificate_id` | `org_units` / `calibration_certificates` | }o--o| | RESTRICT | ALTER ile (döngü) |
| `calibration_certificates.test_equipment_id` / `.document_revision_id` | `test_equipment` / `document_revisions` | }o--|| | RESTRICT | |
| `installed_assets.project_id` | `projects` | }o--|| | RESTRICT | |
| `installed_assets(project_id, parent_asset_id)` | `installed_assets(project_id, id)` | }o--o| | RESTRICT | composite self |
| `installed_assets.component_definition_id` / `.catalog_item_id` / `.serialized_item_id` / `.customer_party_id` | ilgili tablolar | }o--o| | RESTRICT | |
| `test_executions.project_id` / `.test_plan_version_id` / `.lead_engineer_employee_id` | `projects` / `test_plan_versions` / `personnel` | }o--|| | RESTRICT | |
| `test_executions(project_id, installed_asset_id)` | `installed_assets(project_id, id)` | }o--o| | RESTRICT | composite |
| `test_executions.witness_party_id` / `.retest_of_execution_id` / `.daily_report_submission_version_id` / `.approval_request_id` / `.approved_by_personnel_id` | ilgili tablolar | }o--o| | RESTRICT | |
| `test_execution_steps.test_execution_id` / `.step_definition_id` | `test_executions` / `tpsd` | }|--|| / }o--|| | RESTRICT | |
| `test_execution_steps.uom_id` / `.performed_by/witnessed_by/hold_point_released_by_personnel_id` | `units_of_measure` / `personnel` | }o--o| | RESTRICT | |
| `test_execution_equipment.test_execution_id` / `.test_equipment_id` / `.calibration_certificate_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `test_evidence.execution_step_id` / `.document_revision_id` | `test_execution_steps` / `document_revisions` | }o--|| | RESTRICT | |
| `commissioning_packages.project_id` / `.owner_employee_id` | `projects` / `personnel` | }o--|| | RESTRICT | |
| `commissioning_packages.approval_request_id` | `approval_requests` | }o--o| | RESTRICT | |
| `cpt.package_id` / `.test_execution_id` | `commissioning_packages` / `test_executions` | }|--|| / }o--|| | RESTRICT | |
| `cpa.package_id` / `.installed_asset_id` | `commissioning_packages` / `installed_assets` | }|--|| / }o--|| | RESTRICT | |
| `acceptance_certificates.project_id` / `.document_revision_id` / `.accepted_by_party_id` / `.konelsis_signatory_personnel_id` | ilgili tablolar | }o--|| | RESTRICT | |
| `acceptance_certificates.commissioning_package_id` | `commissioning_packages` | }o--o| | RESTRICT | |
| `warranties.installed_asset_id` | `installed_assets` | }o--|| | RESTRICT | |
| `warranties.provider_party_id` / `.document_revision_id` | `parties` / `document_revisions` | }o--o| | RESTRICT | |
| `service_requests.installed_asset_id` / `.project_id` / `.reported_by_personnel_id` | `installed_assets` / `projects` / `personnel` | }o--|| | RESTRICT | |
| `service_requests.warranty_id` / `.reported_by_party_id` | `warranties` / `parties` | }o--o| | RESTRICT | |
| `work_orders.service_request_id` / `.owner_personnel_id` | `service_requests` / `personnel` | }o--|| | RESTRICT | |
| `work_orders.crew_id` / `.completion_report_document_revision_id` | `crews` / `document_revisions` | }o--o| | RESTRICT | |
| `maintenance_plans.installed_asset_id` / `.owner_personnel_id` | `installed_assets` / `personnel` | }o--|| | RESTRICT | |
| `maintenance_plans.procedure_id` | `procedures` | }o--o| | RESTRICT | |

### 5.7 Workflow, audit, entegrasyon, harici analiz (12)

| Kaynak | Hedef | Card. | Silme | Not |
|---|---|---|---|---|
| `workflow_definitions(id, current_version_id)` | `wdv(workflow_definition_id, id)` | |o--o| | RESTRICT | composite |
| `wdv.workflow_definition_id` | `workflow_definitions` | }|--|| | RESTRICT | |
| `wdv.published_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `workflow_steps.workflow_definition_version_id` | `wdv` | }|--|| | RESTRICT | |
| `workflow_steps.approval_policy_version_id` | `apv` | }o--o| | RESTRICT | |
| `workflow_instances.workflow_definition_version_id` / `.started_by_personnel_id` | `wdv` / `personnel` | }o--|| | RESTRICT | |
| `workflow_tasks.workflow_instance_id` / `.workflow_step_id` | `workflow_instances` / `workflow_steps` | }|--|| / }o--|| | RESTRICT | |
| `workflow_tasks.assignee_personnel_id` / `.task_id` / `.completed_by_personnel_id` | `personnel` / `tasks` / `personnel` | }o--o| | RESTRICT | |
| `approval_policies(id, current_version_id)` | `apv(approval_policy_id, id)` | |o--o| | RESTRICT | composite |
| `apv.approval_policy_id` | `approval_policies` | }|--|| | RESTRICT | |
| `apv.currency_code` / `.escalation_notification_rule_id` / `.published_by_personnel_id` | `currencies` / `notification_rules` / `personnel` | }o--o| | RESTRICT | |
| `approval_steps.approval_policy_version_id` | `apv` | }|--|| | RESTRICT | |
| `approval_requests.approval_policy_version_id` / `.personnel_id` | `apv` / `personnel` | }o--|| | RESTRICT | |
| `approval_requests.workflow_task_id` / `.currency_code` | `workflow_tasks` / `currencies` | }o--o| | RESTRICT | |
| `ars.approval_request_id` / `.approval_step_id` | `approval_requests` / `approval_steps` | }|--|| / }o--|| | RESTRICT | |
| `ars.personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `approval_decisions.approval_request_step_id` / `.personnel_id` | `ars` / `personnel` | }o--|| | RESTRICT | |
| `approval_decisions.on_behalf_of_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `delegation_snapshots.approval_decision_id` | `approval_decisions` | |o--|| | RESTRICT | UNIQUE (1:1) |
| `delegation_snapshots.source_delegation_id` / `.grantor_personnel_id` / `.delegate_personnel_id` | `delegations` / `personnel` / `personnel` | }o--|| | RESTRICT | |
| `personnel_activities.personnel_id` | `personnel` | }o--|| | RESTRICT | |
| `icc.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `reference_type_registry.min_classification_id` | `security_classifications` | }o--|| | RESTRICT | |
| `reference_type_usages.target_type` | `reference_type_registry` | }|--|| | RESTRICT | |
| `external_capabilities.max_input_classification_id` | `security_classifications` | }o--|| | RESTRICT | |
| `external_capabilities.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `external_capability_actions.capability_id` | `external_capabilities` | }o--|| | RESTRICT | |
| `scga.grant_id` | `service_capability_grants` | }o--|| | RESTRICT | |
| `ear.capability_id` / `.grant_id` / `.requester_personnel_id` | `external_capabilities` / `service_capability_grants` / `personnel` | }o--|| | RESTRICT | |
| `ear.report_template_version_id` | `rtv` | }o--o| | RESTRICT | |
| `eaa.request_id` / `.classification_snapshot_id` | `ear` / `security_classifications` | }|--|| / }o--|| | RESTRICT | |
| `eaa.document_revision_id` / `.report_submission_version_id` | `document_revisions` / `rsv` | }o--o| | RESTRICT | XOR |
| `external_analysis_attempts.request_id` | `ear` | }o--|| | RESTRICT | |
| `eares.request_id` | `ear` | }o--|| | RESTRICT | |
| `earr.result_id` / `.artifact_id` | `eares` / `eaa` | }o--|| | RESTRICT | |
| `external_action_requests.result_id` | `eares` | }o--|| | RESTRICT | |
| `external_action_requests.approved_by_personnel_id` | `personnel` | }o--o| | RESTRICT | |
| `eaex.action_request_id` | `external_action_requests` | |o--|| | RESTRICT | UNIQUE (1:1) |

## 6. Sorguya özel composite indeks kataloğu

FK ve UNIQUE indeksleri dışında, planlanan Query Service sorguları için başlangıç indeksleri. Execution-plan eşikleri DB-G7'de doğrulanır.

| İndeks | Kolonlar | Sorgu |
|---|---|---|
| `ix_org_unit_relations_child_active` | `(child_org_unit_id, relation_type, valid_from, valid_until)` | Ağaç ve overlap |
| `ix_position_assignments_position_range` | `(position_id, valid_from, valid_until)` | Pozisyon doluluğu |
| `ix_position_assignments_employee_range` | `(personnel_id, valid_from, valid_until)` | Personel tarihçesi |
| `ix_reporting_relationships_manager_active` | `(manager_employee_id, relation_type, valid_until)` | Ast listesi/escalation |
| `ix_employments_employee_range` | `(personnel_id, valid_from, valid_until)` | Overlap |
| `ix_team_memberships_team_range` | `(team_id, valid_from, valid_until)` | |
| `ix_delegations_delegate_active` | `(delegate_employee_id, status, valid_from, valid_until)` | Vekâlet çözümü |
| `ix_far_area_role_range` | `(functional_area_id, role_code, valid_from, valid_until)` | Rol çözümü |
| `ix_employee_certifications_expiry` | `(valid_until, status)` | Expiry taraması |
| `ix_report_assignments_status_due` | `(status, due_at, reviewer_employee_id)` | Overdue/inceleme kutusu |
| `ix_report_assignments_employee_status` | `(personnel_id, status, due_at)` | Kişisel rapor kutusu |
| `ix_report_assignments_overdue_scan` | `(overdue_flagged_at, due_at)` | İdempotent overdue taraması |
| `ix_report_periods_schedule_status` | `(report_schedule_id, status, period_end)` | Dönem üretimi |
| `ix_report_submissions_status` | `(status, updated_at)` | İnceleme kuyruğu |
| `ix_report_metric_facts_kpi_period` | `(kpi_code, period_start, org_unit_id)` | KPI dashboard |
| `ix_notification_instances_subject` | `(subject_type, subject_id, occurred_at)` | Kayıt bildirim geçmişi |
| `ix_notification_instances_ack_due` | `(status, ack_due_at)` | Acknowledgment SLA taraması |
| `ix_notification_recipients_user_state` | `(personnel_id, resolution_status, created_at)` | Bildirim kutusu |
| `ix_notification_recipients_unresolved` | `(resolution_status, created_at)` | Operasyon kutusu |
| `ix_tasks_owner_status_due` | `(owner_personnel_id, status, due_at)` | Görev kutusu |
| `ix_tasks_context` | `(context_type, context_id)` | Bağlam görevleri |
| `ix_business_alerts_state_due` | `(state, severity, due_at)` | Kritik iş kutusu |
| `ix_documents_type_status` | `(document_type_id, status, updated_at)` | Doküman registeri |
| `ix_document_links_target` | `(target_type, target_id)` | İş nesnesi ekleri |
| `ix_document_acknowledgements_revision` | `(document_revision_id, acknowledgement_kind)` | Kabul takibi |
| `ix_file_objects_scan` | `(scan_status, uploaded_at)` | Tarama kuyruğu |
| `ix_generated_outputs_source` | `(source_type, source_id, locale, output_no)` | Çıktı geçmişi |
| `ix_messages_conversation_seq` | `(conversation_id, conversation_sequence)` | Kronolojik akış (UNIQUE ile aynı) |
| `ix_conversation_memberships_user_active` | `(personnel_id, left_at)` | Kullanıcı konuşmaları |
| `ix_inbound_email_messages_status` | `(mailbox_id, status, received_at)` | İşleme kuyruğu |
| `ix_social_content_items_area_lifecycle_due` | `(functional_area_id, lifecycle, due_at)` | İçerik kutusu |
| `ix_social_content_targets_account_planned` | `(social_account_id, status, planned_at_utc)` | Takvim ve yayın kuyruğu |
| `ix_social_content_targets_status_scheduled` | `(status, scheduled_at_utc)` | Publish worker |
| `ix_special_day_occurrences_date` | `(local_date, verification_state)` | Hazırlık kutusu |
| `ix_ssdri_trigger` | `(status, trigger_at_utc)` | Reminder scheduler |
| `ix_samo_account_metric_period` | `(social_account_id, metric_definition_id, period_start)` | Trend sorguları |
| `ix_spmo_publication_metric_period` | `(social_publication_id, metric_definition_id, period_start)` | |
| `ix_business_cases_segment_stage` | `(lifecycle_segment, acquisition_stage, updated_at)` | Pipeline |
| `ix_tender_deadlines_due` | `(due_at_utc, alert_generated_at)` | Teklif tarihi tetikleyicisi |
| `ix_parties_normalized_name` | `(normalized_name)` | Tekilleştirme |
| `ix_projects_status_manager` | `(status, project_manager_employee_id)` | Portföy |
| `ix_project_workstreams_status` | `(status, project_id)` | Workstream board |
| `ix_project_stage_instances_status` | `(status, project_id)` | Gate readiness |
| `ix_purchase_requisitions_status_required` | `(status, required_by)` | Satın alma kuyruğu |
| `ix_purchase_order_lines_required_status` | `(required_by, status)` | Teslimat takibi |
| `ix_delivery_schedules_planned` | `(planned_delivery_on, status)` | Teslimat eşiği |
| `ix_inventory_transactions_item_time` | `(catalog_item_id, occurred_at)` | Bakiye projeksiyonu |
| `ix_inventory_transactions_bin_item` | `(to_bin_id, catalog_item_id)`, `(from_bin_id, catalog_item_id)` | Bin bakiyesi |
| `ix_supplier_invoices_status_due` | `(status, due_date)` | Ödeme planı |
| `ix_tax_obligations_due` | `(status, due_on)` | Vergi/ödeme tetikleyicisi |
| `ix_punch_items_project_status_category` | `(project_id, status, category)` | Enerjilendirme öncesi kontrol |
| `ix_test_executions_project_status` | `(project_id, status)` | |
| `ix_maintenance_plans_next_due` | `(status, next_due_on)` | Bakım scheduler |
| `ix_warranties_ends` | `(status, ends_on)` | Garanti bitişi |
| `ix_approval_request_steps_personnel_status` | `(personnel_id, status, due_at)` | Onay kutusu |
| `ix_approval_requests_subject` | `(subject_type, subject_id, status)` | Kayıt onay geçmişi |
| `ix_workflow_tasks_assignee_status` | `(assignee_personnel_id, status, due_at)` | |
| `ix_personnel_activities_subject_time` | `(subject_type, subject_id, occurred_at)` | Audit görünümü |
| `ix_personnel_activities_actor_time` | `(personnel_id, occurred_at)` | Kullanıcı aktivitesi |
| `ix_personnel_activities_correlation` | `(correlation_id)` | İşlem izi |
| `ix_ear_status_timeout` | `(status, timeout_at)` | Harici istek zaman aşımı |
| `ix_external_action_requests_status` | `(status, proposed_at)` | İnsan onayı kuyruğu |

FULLTEXT adayları (DB-G7 kalite deneyine bağlı): `ft_documents_title (title)`, `ft_parties_display_name (display_name)`, `ft_catalog_items_name (name_tr, name_en)`, `ft_messages_search` yalnız `message_versions.content_snapshot` dışına çıkarılmış `search_text` generated/denormalize kolonda değerlendirilir.

## 7. Tarih aralığı kilit deseninin uygulandığı tablolar

03 §5.2 deseni (kök satır `SELECT ... FOR UPDATE` → indeksli overlap sorgusu → ekleme). Kilit sırası sabittir; deadlock bounded retry ile ele alınır.

| Tablo | Kilitlenen kök satır | Overlap kapsamı | İndeks |
|---|---|---|---|
| `org_unit_relations` | `org_units(child)` | child + relation_type | `ix_org_unit_relations_child_active` |
| `employments` | `personnel` | personnel | `ix_employments_employee_range` |
| `position_assignments` | `personnel` | personnel (primary) ve `positions` (headcount) | ilgili iki indeks |
| `reporting_relationships` | `personnel(personnel)` | personnel + relation_type + scope | `ix_reporting_relationships_manager_active` + personnel indeksi |
| `team_memberships` | `teams` | team + personnel | `ix_team_memberships_team_range` |
| `delegations` | `personnel(grantor)` | grantor + capability + scope | `ix_delegations_delegate_active` |
| `functional_area_responsibilities` | `functional_areas` | area + role + target | `ix_far_area_role_range` |
| `functional_area_org_unit_bindings` | `functional_areas` | area + binding_type | FK indeksi |
| `social_account_connections` | `social_accounts` | account + provider | guard |
| `crew_memberships` | `crews` | crew + personnel | FK indeksi |
| `resource_assignments` | `personnel` | personnel + project | FK indeksi |
| `party_roles` | `parties` | party + role | guard |
| `leave_requests` | `personnel` | personnel tarih aralığı (onaylı izinler) | `(personnel_id, starts_on, ends_on)` |

Concurrency kabul senaryosu: iki eşzamanlı atamadan yalnız biri başarılı olur.

## 8. Append-only ve immutability uygulaması

### 8.1 A sınıfı tablolar (runtime rolünde `UPDATE`/`DELETE` yok — DB-G7)

`business_number_allocations`, `personnel_status_histories`, `functional_area_transition_events`, `report_reviews`, `report_status_transitions`, `report_metric_facts`, `notification_delivery_attempts`, `notification_acknowledgements`, `notification_escalations`, `business_alert_acknowledgements`, `business_alert_resolutions`, `document_reviews`, `document_distributions`, `document_acknowledgements`, `legal_hold_documents`, `message_versions`, `inbound_email_recipients`, `email_processing_attempts`, `social_account_identifier_histories`, `social_connection_health_checks`, `social_content_reviews`, `social_publication_attempts`, `social_publication_corrections`, `social_account_metric_observations`, `social_publication_metric_observations`, `opportunity_stage_histories`, `handoff_reviews`, `stage_evidence`, `stage_reviews`, `stage_waivers`, `department_handoff_reviews`, `progress_snapshots`, `project_decisions`, `receipt_inspections`, `inventory_transactions`, `payments`, `installed_quantities`, `quality_evidence`, `test_evidence`, `acceptance_certificates`, `approval_decisions`, `delegation_snapshots`, `personnel_activities`, `external_analysis_attempts`, `external_analysis_result_references`, `external_action_executions`.

İstisna: `report_metric_facts.is_current` ve `business_codes.status` yalnız servis tarafından güncellenir; DB-G7'de bu iki kolon için ayrı rol/prosedür kararı verilir.

### 8.2 V sınıfı tablolarda yayım sonrası değişebilen kolonlar

| Tablo grubu | Yayım/onay durumu | Sonradan değişebilen kolonlar |
|---|---|---|
| `*_template_versions`, `*_policy_versions`, `*_definition_versions` | `published` | `status → superseded/withdrawn`, S2 |
| `report_submission_versions` | `submitted` | `status → superseded/withdrawn`, `external_check_request_id`, S2 |
| `document_revisions` | `approved/issued` | `status`, `issued_at`, `superseded_by_revision_id`, S2 |
| `social_content_versions` | `approved` | `status → superseded`, `superseded_by_version_id`, S2 |
| `proposal_versions`, `contract_versions`, `ohv`, `dhv`, `pov`, `budget_versions`, `progress_claim_versions`, `test_plan_versions`, `engineering_revisions`, `job_post_versions` | `approved/issued/published/executed` | yalnız yaşam döngüsü kolonları (`status`, `*_at`), S2 |
| `*_lines`, `*_items`, `*_documents` sürüm çocukları | Parent sürümü immutable olduğunda | Yalnız açıkça "durum mutable" işaretli kolonlar (`completion_state`, `status`, `approval_state`) |

Bu kural uygulama servisi invariant'ıdır; DB-G7'de trigger ile ikinci savunma hattı değerlendirilir (D-27).

## 9. DB-G8 kontrol listesi

- Her sözlük tablosu için PK, UK, FK, CHECK ve guard bu matriste adlandırılabilir durumdadır; DDL üretimi DB-G8 onayından sonra [16](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md) sırasıyla yapılır.
- Karşılıklı FK'lar (kök `current_*_id` ↔ sürüm kök FK'sı; `projects ↔ project_workstreams`; `social_content_targets ↔ social_publications`; `test_equipment ↔ calibration_certificates`) tablolar oluşturulduktan sonra ALTER ile eklenir.
- Açık kararlar: D-07 collation, D-23 spatial, D-27 trigger ikinci savunma, FULLTEXT kalite deneyi (DB-G7). D-25 hash zinciri D-44 ile kapatılmıştır.
