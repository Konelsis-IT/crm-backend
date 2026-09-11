# Konelsis Kurumsal Platform — Veri sözlüğü 6: Operasyon

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. 05 §16 madde 5–10 açık kararları kapanmadan bu bölüm uygulanmaz.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md)  
**ERD:** ERD-08 (proje, workstream, stage-gate, departman devri, proje kontrolü), ERD-09 (satın alma, lojistik/stok, finans), ERD-10 (mühendislik, saha, kalite/İSG, test/devreye alma, servis)

Operasyon tablolarında "aynı proje" garantisi gereken her çocuk FK, `(project_id, id)` composite unique üzerinden composite FK ile bağlanır ([13 §2](13-cardinality-ve-constraint-matrisi.md)).

## 1. Proje kökü ve workstream'ler (ERD-08 §10.1, 03 §11.1–11.2)

### 1.1 `projects` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases`; UNIQUE (business case başına tek proje) |
| `project_business_code_id` | fk | ✗ | → `business_codes` (`code_kind = project`); UNIQUE |
| `accepted_handoff_version_id` | fk | ✓ | → `operation_handoff_versions`; UNIQUE; `accepted` durumda olmalı. D-68: `origin = direct` (doğrudan oluşturma) projelerinde NULL |
| `origin` | status | ✗ | D-68: [`handoff`, `converted`, `direct`]; projenin nasıl doğduğu |
| `legacy_reference` | short | ✓ | D-68: eski sistemdeki proje numarası / dosya adı |
| `description` | text | ✓ | D-68 |
| `name` | name | ✗ | |
| `customer_party_id` | fk | ✗ | → `parties` |
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `project_manager_employee_id` | fk | ✗ | → `personnel` |
| `country_code` | country | ✗ | → `countries` |
| `timezone` | tz | ✗ | Saha yerel saati |
| `site_location` | short | ✓ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `contract_value_snapshot` | money | ✓ | Devir anındaki değer |
| `stage_template_version_id` | fk | ✗ | → `stage_template_versions`; uygulanan şablon sürümü |
| `criticality_profile` | status | ✗ | [`standard`, `critical`, `strategic`] |
| `status` | status | ✗ | [`opening`, `active`, `acceptance`, `warranty`, `closed`, `suspended`, `cancelled`] |
| `current_macro_gate_code` | code32 | ✓ | Son geçilen gate |
| `primary_focus_workstream_id` | fk | ✓ | Composite FK `(id, primary_focus_workstream_id)` → `project_workstreams(project_id, id)` |
| `cover_file_object_id` | fk | ✓ | → `file_objects` |
| `classification_id` | fk | ✗ | → `security_classifications` |
| `planned_start_on` | date | ✓ | |
| `planned_finish_on` | date | ✓ | `CHECK` tarih sırası |
| `actual_start_on` | date | ✓ | |
| `actual_finish_on` | date | ✓ | |
| `site_address_line1` | name | ✓ | D-68: tam saha adresi |
| `site_address_line2` | name | ✓ | D-68 |
| `site_district` | short | ✓ | D-68 |
| `site_city` | short | ✓ | D-68 |
| `site_postal_code` | ascii16 | ✓ | D-68 |
| `site_country_code` | country | ✓ | D-68: → `countries` |
| `site_latitude` | decimal(10,7) | ✓ | D-68: `CHECK` −90..90 |
| `site_longitude` | decimal(10,7) | ✓ | D-68: `CHECK` −180..180 |
| `site_note` | text | ✓ | D-68 |

### 1.2 `component_definitions` — R, sınıf P, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE; `GES`, `HES`, `RES`, `BESS`, `EMS`, `ENH`, `SUBSTATION`, `SCADA_DCS`, `PLC_RTU`, `CIVIL`, `MECHANICAL`, `AUTOMATION`, `FIBER_CCTV` (katalog D-20) |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `discipline` | code32 | ✗ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 1.3 `project_components` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `component_definition_id` | fk | ✗ | → `component_definitions`; UNIQUE `(project_id, component_definition_id)` |
| `scope_state` | status | ✗ | [`planned`, `in_scope`, `out_of_scope`, `completed`] |
| `capacity_value` | `DECIMAL(18,6)` | ✓ | Örn. MWp |
| `capacity_uom_id` | fk | ✓ | → `units_of_measure` |
| `note` | text | ✓ | |

### 1.4 `operation_group_definitions` — R, sınıf P, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE; [`PROJECT`, `PROCUREMENT`, `ACCOUNTING`, `SOFTWARE`, `LOGISTICS`, `FIELD`] |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `default_sort_order` | tint | ✗ | Varsayılan odak sırası |
| `status` | status | ✗ | [`active`, `inactive`] |

### 1.5 `project_workstreams` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE `(project_id, id)` |
| `group_definition_id` | fk | ✗ | → `operation_group_definitions`; UNIQUE `(project_id, group_definition_id)` |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`not_ready`, `ready`, `active`, `review`, `completed`, `blocked`, `waived`, `cancelled`] |
| `progress_pct` | pct | ✗ | |
| `planned_start_on` | date | ✓ | |
| `planned_finish_on` | date | ✓ | |
| `actual_start_on` | date | ✓ | |
| `actual_finish_on` | date | ✓ | |
| `blocked_at` | ts | ✓ | |
| `block_reason` | text | ✓ | `blocked` ise zorunlu |

### 1.6 `workstream_dependencies` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `predecessor_workstream_id` | fk | ✗ | → `project_workstreams` |
| `successor_workstream_id` | fk | ✗ | → `project_workstreams`; `CHECK (<> predecessor)`; UNIQUE çift |
| `dependency_type` | status | ✗ | [`FS`, `SS`, `FF`, `SF`] |
| `lag_days` | sint | ✗ | |
| `is_hard` | bool | ✗ | Hard bağımlılık bağlı işi bloke eder |
| `status` | status | ✗ | [`active`, `waived`] |
| `waived_by_personnel_id` | fk | ✓ | → `personnel` |
| `waiver_reason` | text | ✓ | |

Döngü kontrolü uygulama servisinde; aynı projeye aitlik servis kontrolü.

### 1.7 `project_focus_histories` — M (kapanınca immutable), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✗ | Composite FK `(project_id, workstream_id)` → `project_workstreams(project_id, id)` |
| `direction` | status | ✗ | [`initial`, `forward`, `backward`] |
| `started_at` | ts | ✗ | |
| `ended_at` | ts | ✓ | |
| `changed_by_personnel_id` | fk | ✗ | → `personnel` |
| `reason` | text | ✓ | `backward` için zorunlu |
| `open_guard` | fk (generated) | ✓ | `CASE WHEN ended_at IS NULL THEN project_id END`; UNIQUE (tek açık primary focus) |

### 1.8 `work_packages` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_workstream_id` | fk | ✗ | → `project_workstreams` |
| `project_id` | fk | ✗ | → `projects`; denormalize; UNIQUE `(project_id, id)` |
| `package_code` | code32 | ✗ | UNIQUE `(project_workstream_id, package_code)` |
| `name` | name | ✗ | |
| `description` | text | ✓ | |
| `wbs_node_id` | fk | ✓ | Composite FK aynı proje → `wbs_nodes` |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`planned`, `ready`, `active`, `completed`, `cancelled`] |
| `planned_start_on` | date | ✓ | |
| `planned_finish_on` | date | ✓ | |

### 1.9 `work_package_dependencies` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `predecessor_package_id` | fk | ✗ | → `work_packages` |
| `successor_package_id` | fk | ✗ | → `work_packages`; `CHECK (<> predecessor)`; UNIQUE çift |
| `dependency_type` | status | ✗ | [`FS`, `SS`, `FF`, `SF`] |
| `is_hard` | bool | ✗ | |

### 1.10 `project_photos` — M, sınıf I, +S1 +S2 (D-68)

Saha fotoğrafları; dosya DMS `file_objects` üzerinde durur, projede yalnız bağ ve açıklama tutulur.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `file_object_id` | fk | ✗ | → `file_objects`; UNIQUE `(project_id, file_object_id)` |
| `workstream_id` | fk | ✓ | Composite FK `(project_id, workstream_id)` → `project_workstreams(project_id, id)`; fotoğrafın ait olduğu adım |
| `caption` | name | ✓ | |
| `taken_on` | date | ✓ | |
| `sort_order` | int | ✗ | |
| `is_cover` | bool | ✗ | Proje başına tek kapak (servis kuralı); `projects.cover_file_object_id` ile senkron tutulur |

### 1.11 `project_supply_items` — M, sınıf C, +S1 +S2 (D-68)

Satın alma adımında açılan ürün / hizmet / yazılım kalemleri; lojistik adımında sevkiyat-teslim, saha adımında montaj durumu işlenir. B18 kataloğu gelene kadar serbest kod ve ad.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK → `project_workstreams(project_id, id)` |
| `item_kind` | status | ✗ | [`product`, `service`, `software`] |
| `item_code` | code64 | ✓ | Serbest kod (örn. PV-550) |
| `name` | name | ✗ | |
| `specification` | text | ✓ | |
| `quantity` | decimal(18,4) | ✗ | `CHECK > 0` |
| `uom_id` | fk | ✓ | → `units_of_measure` |
| `unit_cost` | money | ✓ | `CHECK ≥ 0` |
| `currency_code` | currency | ✗ | → `currencies`; boşsa projeden |
| `supplier_party_id` | fk | ✓ | → `parties` |
| `wbs_node_id` | fk | ✓ | Composite FK → `wbs_nodes(project_id, id)` |
| `needed_on`, `ordered_on`, `expected_delivery_on`, `delivered_on` | date | ✓ | `ordered_on` ve `delivered_on` durumdan otomatik damgalanır |
| `status` | status | ✗ | [`planned`, `requested`, `ordered`, `shipped`, `delivered`, `installed`, `cancelled`]; geçişler serbest |
| `note` | text | ✓ | |

### 1.12 `project_team_members` — M, sınıf I, +S1 +S2 (D-68)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(project_id, personnel_id, team_role)` |
| `workstream_id` | fk | ✓ | Composite FK → `project_workstreams(project_id, id)` |
| `team_role` | status | ✗ | [`project_manager`, `site_manager`, `engineer`, `technician`, `procurement_officer`, `accountant`, `logistics_officer`, `software_engineer`, `qa_qc`, `hse_officer`, `other`] |
| `allocation_pct` | decimal(5,2) | ✗ | `CHECK 0 < x ≤ 100` |
| `assigned_from` | date | ✗ | |
| `assigned_until` | date | ✓ | `CHECK ≥ assigned_from`; `ended` durumunda damgalanır |
| `is_lead` | bool | ✗ | |
| `status` | status | ✗ | [`active`, `ended`] |
| `note` | text | ✓ | |

### 1.13 `focus_expectations` — R, sınıf P, +S1 +S2 (D-68)

Her odak adımının (operasyon grubunun) projeden beklediği veri. Çalışma alanı kontrol listesi, sekme rozetleri ve odak geçiş guard'ı bu tanımları okur (`App\Query\Project\ProjectStepReadiness`).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `group_definition_id` | fk | ✗ | → `operation_group_definitions`; UNIQUE `(group_definition_id, code)` |
| `code` | code32 | ✗ | |
| `name_tr`, `name_en` | name | ✗ | |
| `kind` | code32 | ✗ | Sayım kuralı: [`site_address`, `planned_dates`, `components`, `documents`, `drawings`, `photos`, `wbs`, `milestones`, `schedule_baseline`, `supply_items`, `supply_ordered`, `supply_delivered`, `cbs`, `exposures`, `team_members`, `work_packages`, `progress`, `software_items`, `automation_documents`] |
| `min_count` | int | ✗ | `CHECK ≥ 1`; sayım bu değere ulaşınca beklenti karşılanır |
| `is_mandatory` | bool | ✗ | Zorunlu beklentiler karşılanmadan ileri yönde odak değişimi (gerekçesiz) yapılamaz |
| `help_tr`, `help_en` | text | ✓ | |
| `sort_order` | int | ✗ | |
| `status` | status | ✗ | [`active`, `inactive`] |
## 2. Stage-gate şablonu ve proje snapshot'ı (ERD-08 §10.2, 03 §11.3)

### 2.1 `stage_templates` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `project_type` | code32 | ✗ | [`generic`, `ges`, `hes`, `res`, `bess`, `ems`, `enh`, `mixed`] |
| `current_version_id` | fk | ✓ | → `stage_template_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 2.2 `stage_template_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `stage_template_id` | fk | ✗ | → `stage_templates` |
| `version_no` | int | ✗ | UNIQUE `(stage_template_id, version_no)` |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `change_summary` | text | ✓ | |
| `definition_hash` | hash | ✓ | |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 2.3 `stage_nodes` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `stage_template_version_id` | fk | ✗ | → `stage_template_versions` |
| `stage_code` | code32 | ✗ | UNIQUE `(stage_template_version_id, stage_code)`; `G0`…`G9` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `sequence_no` | sint | ✗ | |
| `is_hard_gate` | bool | ✗ | |
| `owner_group_definition_id` | fk | ✓ | → `operation_group_definitions` |
| `approval_policy_id` | fk | ✓ | → `approval_policies`; instance açılışında yayımlı sürüm çözülür |
| `description` | text | ✓ | |

### 2.4 `stage_dependencies` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `predecessor_node_id` | fk | ✗ | → `stage_nodes` |
| `successor_node_id` | fk | ✗ | → `stage_nodes`; `CHECK (<> predecessor)`; UNIQUE çift |
| `is_hard` | bool | ✗ | |

### 2.5 `stage_requirement_definitions` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `stage_node_id` | fk | ✗ | → `stage_nodes` |
| `requirement_code` | code32 | ✗ | UNIQUE `(stage_node_id, requirement_code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `evidence_type` | status | ✗ | [`document`, `approval`, `checklist`, `measurement`, `external_check`, `handoff`] |
| `is_mandatory` | bool | ✗ | |
| `min_document_type_id` | fk | ✓ | → `document_types` |
| `description` | text | ✓ | |
| `sort_order` | sint | ✗ | |

### 2.6 `project_stage_instances` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE `(project_id, id)` |
| `stage_node_id` | fk | ✗ | → `stage_nodes`; UNIQUE `(project_id, stage_node_id)` |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`not_started`, `preparing`, `ready_for_review`, `approval_pending`, `passed`, `conditionally_passed`, `rejected`, `reopened`] |
| `entered_at` | ts | ✓ | |
| `ready_at` | ts | ✓ | |
| `passed_at` | ts | ✓ | |
| `condition_due_on` | date | ✓ | `conditionally_passed` için telafi tarihi |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

Gate durumu formdan düzenlenmez; hard dependency, mandatory requirement, exact evidence, waiver ve approval sonucu aynı transaction'da doğrulanır.

### 2.7 `project_stage_requirements` — M (snapshot alanları immutable), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_stage_instance_id` | fk | ✗ | → `project_stage_instances` |
| `requirement_definition_id` | fk | ✗ | → `stage_requirement_definitions` |
| `requirement_code_snapshot` | code32 | ✗ | UNIQUE `(project_stage_instance_id, requirement_code_snapshot)` |
| `name_snapshot_tr` | name | ✗ | |
| `name_snapshot_en` | name | ✗ | |
| `evidence_type_snapshot` | status | ✗ | |
| `is_mandatory_snapshot` | bool | ✗ | |
| `applicability` | status | ✗ | [`applicable`, `not_applicable`] |
| `owner_personnel_id` | fk | ✓ | → `personnel` |
| `due_at` | ts | ✓ | |
| `status` | status | ✗ | [`pending`, `submitted`, `accepted`, `rejected`, `waived`] |
| `outcome_note` | text | ✓ | |

### 2.8 `stage_evidence` — A, sınıf (dokümandan), +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_stage_requirement_id` | fk | ✗ | → `project_stage_requirements` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; UNIQUE `(project_stage_requirement_id, document_revision_id)` |
| `evidence_hash` | hash | ✗ | |
| `submitted_by_personnel_id` | fk | ✗ | → `personnel` |
| `submitted_at` | ts | ✗ | |
| `accepted_by_personnel_id` | fk | ✓ | → `personnel` |
| `accepted_at` | ts | ✓ | |

### 2.9 `stage_reviews` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_stage_instance_id` | fk | ✗ | → `project_stage_instances` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel` |
| `decision` | status | ✗ | [`passed`, `conditionally_passed`, `rejected`] |
| `reviewed_hash` | hash | ✗ | Kanıt setinin hash'i |
| `conditions` | text | ✓ | `conditionally_passed` için zorunlu |
| `comment` | text | ✓ | |
| `decided_at` | ts | ✗ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 2.10 `stage_waivers` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_stage_instance_id` | fk | ✗ | → `project_stage_instances` |
| `project_stage_requirement_id` | fk | ✓ | → `project_stage_requirements`; NULL = gate düzeyi |
| `approved_by_personnel_id` | fk | ✗ | → `personnel`; waiver yetkilisi (D-22) |
| `risk_owner_personnel_id` | fk | ✗ | → `personnel` |
| `reason` | text | ✗ | |
| `remediation_due_on` | date | ✗ | |
| `remediation_task_id` | fk | ✓ | → `tasks` |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `granted_at` | ts | ✗ | |

## 3. Departman devri, WBS/CBS ve proje kontrolü (ERD-08 §10.3)

### 3.1 `department_handoffs` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `source_workstream_id` | fk | ✗ | Composite FK aynı proje → `project_workstreams` |
| `target_workstream_id` | fk | ✗ | Composite FK aynı proje; `CHECK (<> source)` |
| `trigger_stage_instance_id` | fk | ✗ | Composite FK aynı proje → `project_stage_instances` |
| `status` | status | ✗ | [`preparing`, `in_review`, `accepted`, `rejected`, `cancelled`] |
| `sla_due_at` | ts | ✓ | |
| `accepted_version_id` | fk | ✓ | → `department_handoff_versions` (aynı kök) |
| `accepted_by_personnel_id` | fk | ✓ | → `personnel` |
| `accepted_at` | ts | ✓ | |

Kabul edilmeden hedef workstream aktif edilemez; aktivasyon aynı use-case içindedir.

### 3.2 `department_handoff_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `department_handoff_id` | fk | ✗ | → `department_handoffs` |
| `version_no` | int | ✗ | UNIQUE `(department_handoff_id, version_no)` |
| `manifest_snapshot` | json | ✗ | Teslimat manifesti; immutable |
| `snapshot_hash` | hash | ✗ | |
| `status` | status | ✗ | [`draft`, `submitted`, `accepted`, `rejected`, `superseded`] |
| `submitted_by_personnel_id` | fk | ✓ | → `personnel` |
| `submitted_at` | ts | ✓ | |

### 3.3 `department_handoff_items` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `handoff_version_id` | fk | ✗ | → `department_handoff_versions` |
| `item_code` | code32 | ✗ | UNIQUE `(handoff_version_id, item_code)` |
| `item_type` | status | ✗ | [`document`, `evidence`, `open_issue`, `checklist`, `material`, `quantity`] |
| `description` | text | ✗ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `completion_state` | status | ✗ | [`pending`, `complete`, `waived`, `not_applicable`] |
| `sort_order` | sint | ✗ | |

### 3.4 `department_handoff_reviews` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `handoff_version_id` | fk | ✗ | → `department_handoff_versions` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel` |
| `decision` | status | ✗ | [`accepted`, `rejected`, `returned`] |
| `comment` | text | ✓ | |
| `decided_at` | ts | ✗ | |

### 3.5 `wbs_nodes` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE `(project_id, id)` |
| `parent_id` | fk | ✓ | Composite FK aynı proje → `wbs_nodes`; `CHECK (<> id)` |
| `wbs_code` | code32 | ✗ | UNIQUE `(project_id, wbs_code)` |
| `name` | name | ✗ | |
| `level` | tint | ✗ | |
| `sort_order` | sint | ✗ | |
| `status` | status | ✗ | [`active`, `closed`] |

### 3.6 `cbs_nodes` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE `(project_id, id)` |
| `parent_id` | fk | ✓ | Composite FK aynı proje; `CHECK (<> id)` |
| `cost_code` | code32 | ✗ | UNIQUE `(project_id, cost_code)` |
| `name` | name | ✗ | |
| `cost_category` | status | ✗ | [`material`, `labor`, `subcontract`, `logistics`, `engineering`, `commissioning`, `overhead`, `contingency`, `other`] |
| `status` | status | ✗ | [`active`, `closed`] |

### 3.7 `wbs_cbs_mappings` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `wbs_node_id` | fk | ✗ | → `wbs_nodes` |
| `cbs_node_id` | fk | ✗ | → `cbs_nodes`; UNIQUE `(wbs_node_id, cbs_node_id)` |
| `allocation_pct` | pct | ✗ | Aynı WBS için toplam ≤ 100 servis kontrolü |

### 3.8 `schedule_baselines` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `version_no` | int | ✗ | UNIQUE `(project_id, version_no)` |
| `name` | name | ✗ | |
| `source` | status | ✗ | [`manual`, `ms_project_import`] |
| `baseline_document_revision_id` | fk | ✓ | → `document_revisions` |
| `planned_start_on` | date | ✗ | |
| `planned_finish_on` | date | ✗ | `CHECK` tarih sırası |
| `status` | status | ✗ | [`draft`, `approved`, `superseded`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 3.9 `budget_baselines` — V, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `version_no` | int | ✗ | UNIQUE `(project_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_amount` | money | ✗ | |
| `budget_version_id` | fk | ✓ | → `budget_versions`; onaylanan bütçe sürümü |
| `status` | status | ✗ | [`draft`, `approved`, `superseded`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 3.10 `milestones` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `milestone_code` | code32 | ✗ | UNIQUE `(project_id, milestone_code)` |
| `name` | name | ✗ | |
| `milestone_kind` | status | ✗ | [`contractual`, `internal`, `payment`, `gate`] |
| `wbs_node_id` | fk | ✓ | Composite FK aynı proje |
| `contract_milestone_id` | fk | ✓ | → `contract_milestones` |
| `planned_at` | ts | ✗ | |
| `baseline_at` | ts | ✓ | |
| `forecast_at` | ts | ✓ | |
| `actual_at` | ts | ✓ | |
| `status` | status | ✗ | [`planned`, `at_risk`, `achieved`, `missed`, `cancelled`] |

### 3.11 `progress_snapshots` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `snapshot_at` | ts | ✗ | |
| `source` | status | ✗ | [`manual`, `report`, `computed`]; UNIQUE `(project_id, snapshot_at, source)` |
| `physical_progress_pct` | pct | ✗ | |
| `planned_progress_pct` | pct | ✓ | |
| `cost_progress_pct` | pct | ✓ | |
| `reported_by_personnel_id` | fk | ✗ | → `personnel` |
| `report_submission_version_id` | fk | ✓ | → `report_submission_versions` |

### 3.12 `project_tasks` — M, sınıf I, +S1 +S2

Genel `tasks` kaydının proje yapısına bağlanan uzantısı; ikinci bir görev kutusu değildir (D-21).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `task_id` | fk | ✗ | → `tasks`; UNIQUE (1:1) |
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `work_package_id` | fk | ✓ | Composite FK aynı proje |
| `wbs_node_id` | fk | ✓ | Composite FK aynı proje |
| `stage_instance_id` | fk | ✓ | Composite FK aynı proje |
| `planned_effort_hours` | `DECIMAL(8,2)` | ✓ | |

### 3.13 `project_issues` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `issue_no` | code32 | ✗ | UNIQUE `(project_id, issue_no)` |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `severity` | status | ✗ | [`low`, `medium`, `high`, `critical`] |
| `status` | status | ✗ | [`open`, `in_progress`, `resolved`, `closed`, `cancelled`] |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `raised_by_personnel_id` | fk | ✗ | → `personnel` |
| `raised_at` | ts | ✗ | |
| `due_at` | ts | ✓ | |
| `resolved_at` | ts | ✓ | |
| `resolution` | text | ✓ | |
| `source_type` | code32 | ✓ | Registry: `message`, `report_submission_version`, `inspection`, `delay_event` |
| `source_id` | fk | ✓ | |

### 3.14 `project_risks` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `risk_no` | code32 | ✗ | UNIQUE `(project_id, risk_no)` |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `category` | status | ✗ | [`technical`, `commercial`, `schedule`, `hse`, `supply`, `regulatory`, `financial`] |
| `probability` | ratio | ✗ | 0–1 |
| `impact` | tint | ✗ | 1–5 |
| `score` | `DECIMAL(9,6)` (STORED generated) | ✗ | `probability * impact` |
| `response_strategy` | status | ✗ | [`avoid`, `mitigate`, `transfer`, `accept`] |
| `mitigation_plan` | text | ✓ | |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`identified`, `assessed`, `mitigating`, `closed`, `materialized`] |
| `review_due_on` | date | ✓ | |

### 3.15 `delay_events` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `detected_at` | ts | ✗ | |
| `delay_days` | int | ✗ | `CHECK (delay_days >= 0)` |
| `cause_category` | status | ✗ | [`customer`, `supplier`, `internal`, `weather`, `regulatory`, `design`, `force_majeure`, `other`] |
| `description` | text | ✗ | |
| `is_excusable` | bool | ✓ | Sözleşme değerlendirmesi sonrası |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `reported_by_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`open`, `mitigating`, `absorbed`, `claimed`, `closed`] |

### 3.16 `recovery_actions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `delay_event_id` | fk | ✗ | → `delay_events` |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `description` | text | ✗ | |
| `expected_recovery_days` | sint | ✓ | |
| `due_at` | ts | ✗ | |
| `status` | status | ✗ | [`planned`, `in_progress`, `done`, `cancelled`] |
| `completed_at` | ts | ✓ | |

### 3.17 `project_changes` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `change_no` | code32 | ✗ | UNIQUE `(project_id, change_no)` |
| `change_type` | status | ✗ | [`scope`, `schedule`, `cost`, `technical`, `contract_variation`] |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `requested_at` | ts | ✗ | |
| `source_type` | code32 | ✓ | Registry: `technical_requirement`, `commercial_clarification`, `project_issue`, `ncr`, `engineering_deliverable` |
| `source_id` | fk | ✓ | |
| `impact_cost` | money | ✓ | |
| `currency_code` | currency | ✓ | |
| `impact_days` | sint | ✓ | |
| `affects_baseline` | bool | ✗ | |
| `status` | status | ✗ | [`draft`, `evaluating`, `approved`, `rejected`, `implemented`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_at` | ts | ✓ | |

### 3.18 `commercial_clarifications` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `clarification_no` | code32 | ✗ | UNIQUE `(project_id, clarification_no)` |
| `clarification_type` | status | ✗ | [`scope_interpretation`, `additional_work`, `price_adjustment`, `contract_qa`] |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `raised_by_personnel_id` | fk | ✗ | → `personnel` |
| `customer_contact_party_id` | fk | ✓ | → `parties` |
| `status` | status | ✗ | [`open`, `under_review`, `answered`, `closed`, `converted_to_change`] |
| `response` | text | ✓ | |
| `responded_at` | ts | ✓ | |
| `linked_change_id` | fk | ✓ | → `project_changes` |

### 3.19 `commercial_exposures` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `exposure_no` | code32 | ✗ | UNIQUE `(project_id, exposure_no)` |
| `cbs_node_id` | fk | ✓ | Composite FK aynı proje |
| `exposure_kind` | status | ✗ | [`loss`, `penalty`, `claim`, `unbilled_work`, `disputed_amount`] |
| `description` | text | ✗ | |
| `exposure_amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `probability` | ratio | ✓ | |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `source_delay_event_id` | fk | ✓ | → `delay_events` |
| `source_change_id` | fk | ✓ | → `project_changes` |
| `status` | status | ✗ | [`identified`, `assessed`, `recovering`, `recovered`, `written_off`, `closed`] |

### 3.20 `project_decisions` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `decision_no` | code32 | ✗ | UNIQUE `(project_id, decision_no)` |
| `decision_scope` | status | ✗ | [`gate`, `change`, `commercial`, `technical`, `focus`, `other`] |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `decided_at` | ts | ✗ | |
| `source_message_id` | fk | ✓ | → `messages` |
| `document_revision_id` | fk | ✓ | → `document_revisions` |

## 4. Satın alma (ERD-09 §11.1, 03 §12)

### 4.1 `catalog_categories` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `parent_id` | fk | ✓ | → `catalog_categories`; `CHECK (<> id)` |
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `level` | tint | ✗ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 4.2 `brands` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `name` | name | ✗ | |
| `normalized_name` | name | ✗ | UNIQUE |
| `manufacturer_party_id` | fk | ✓ | → `parties` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 4.3 `catalog_items` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `category_id` | fk | ✗ | → `catalog_categories` |
| `brand_id` | fk | ✓ | → `brands` |
| `item_code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `description` | text | ✓ | |
| `default_uom_id` | fk | ✗ | → `units_of_measure` |
| `technical_attributes` | json | ✓ | Teknik nitelik konfigürasyonu; filtrelenecek değerler generated column'a çıkarılır |
| `image_file_object_id` | fk | ✓ | → `file_objects` |
| `hs_code` | code32 | ✓ | Gümrük tarife |
| `is_serialized` | bool | ✗ | |
| `is_lot_tracked` | bool | ✗ | |
| `status` | status | ✗ | [`draft`, `active`, `inactive`, `obsolete`] |

### 4.4 `approved_equivalents` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `source_item_id` | fk | ✗ | → `catalog_items` |
| `equivalent_item_id` | fk | ✗ | → `catalog_items`; `CHECK (<> source)`; UNIQUE çift |
| `condition_note` | text | ✓ | |
| `status` | status | ✗ | [`proposed`, `approved`, `rejected`, `revoked`] |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 4.5 `purchase_requisitions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `requisition_no` | code32 | ✗ | UNIQUE |
| `requisition_kind` | status | ✗ | [`project`, `office`] |
| `project_id` | fk | ✓ | → `projects`; `project` türünde zorunlu (`CHECK`) |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `requesting_org_unit_id` | fk | ✗ | → `org_units` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `required_by` | date | ✗ | |
| `priority` | status | ✗ | [`low`, `normal`, `high`, `urgent`] |
| `justification` | text | ✓ | |
| `technical_spec_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`draft`, `submitted`, `technical_approved`, `budget_approved`, `approved`, `rejected`, `partially_ordered`, `ordered`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 4.6 `purchase_requisition_lines` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `requisition_id` | fk | ✗ | → `purchase_requisitions` |
| `line_no` | sint | ✗ | UNIQUE `(requisition_id, line_no)` |
| `catalog_item_id` | fk | ✓ | → `catalog_items`; NULL ise `description` zorunlu (yeni ürün) |
| `description` | name | ✗ | |
| `quantity` | qty | ✗ | `CHECK (quantity > 0)` |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `required_by` | date | ✗ | |
| `wbs_node_id` | fk | ✓ | `project` türünde zorunlu |
| `cbs_node_id` | fk | ✓ | `project` türünde zorunlu |
| `estimated_unit_cost` | money | ✓ | |
| `currency_code` | currency | ✓ | |
| `ordered_quantity` | qty | ✗ | Projeksiyon; servis günceller |
| `status` | status | ✗ | [`open`, `in_rfq`, `ordered`, `cancelled`] |

### 4.7 `supplier_rfqs` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `rfq_no` | code32 | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects` |
| `title` | name | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `issued_by_personnel_id` | fk | ✓ | → `personnel` |
| `issued_at` | ts | ✓ | |
| `due_at` | ts | ✓ | |
| `terms_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`draft`, `issued`, `closed`, `evaluated`, `awarded`, `cancelled`] |

### 4.8 `supplier_rfq_lines` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_rfq_id` | fk | ✗ | → `supplier_rfqs` |
| `line_no` | sint | ✗ | UNIQUE `(supplier_rfq_id, line_no)` |
| `requisition_line_id` | fk | ✗ | → `purchase_requisition_lines`; UNIQUE `(supplier_rfq_id, requisition_line_id)` |
| `requested_quantity` | qty | ✗ | Kısmi RFQ (05 §16 madde 7) |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `spec_note` | text | ✓ | |

### 4.9 `supplier_rfq_invitees` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_rfq_id` | fk | ✗ | → `supplier_rfqs` |
| `supplier_party_id` | fk | ✗ | → `parties`; UNIQUE `(supplier_rfq_id, supplier_party_id)` |
| `contact_party_id` | fk | ✓ | → `parties` |
| `invitation_status` | status | ✗ | [`invited`, `acknowledged`, `declined`, `quoted`, `no_response`] |
| `invited_at` | ts | ✓ | |
| `responded_at` | ts | ✓ | |

### 4.10 `supplier_quotes` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `rfq_invitee_id` | fk | ✗ | → `supplier_rfq_invitees`; UNIQUE (1:1) |
| `supplier_quote_ref` | short | ✓ | Tedarikçi referansı |
| `received_at` | ts | ✗ | |
| `current_version_id` | fk | ✓ | → `supplier_quote_versions` (aynı kök) |
| `status` | status | ✗ | [`received`, `under_evaluation`, `shortlisted`, `rejected`, `selected`, `expired`] |

### 4.11 `supplier_quote_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_quote_id` | fk | ✗ | → `supplier_quotes` |
| `version_no` | int | ✗ | UNIQUE `(supplier_quote_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `valid_until` | date | ✗ | |
| `payment_terms` | short | ✓ | |
| `delivery_terms` | short | ✓ | Incoterm |
| `lead_time_days` | sint | ✓ | |
| `total_amount` | money | ✓ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `received_at` | ts | ✗ | |
| `status` | status | ✗ | [`received`, `superseded`] |

### 4.12 `supplier_quote_lines` — V (sürüm çocuğu), sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `quote_version_id` | fk | ✗ | → `supplier_quote_versions` |
| `supplier_rfq_line_id` | fk | ✗ | → `supplier_rfq_lines`; UNIQUE `(quote_version_id, supplier_rfq_line_id)` |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `unit_price` | money | ✗ | |
| `line_total` | money (STORED generated) | ✗ | `quantity * unit_price` |
| `lead_time_days` | sint | ✓ | |
| `offered_brand` | name | ✓ | |
| `offered_catalog_item_id` | fk | ✓ | → `catalog_items` |
| `compliance_note` | text | ✓ | |

### 4.13 `bid_comparisons` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_rfq_id` | fk | ✗ | → `supplier_rfqs` |
| `version_no` | int | ✗ | UNIQUE `(supplier_rfq_id, version_no)` |
| `normalization_basis` | json | ✓ | Kur/lojistik normalizasyon snapshot'ı |
| `prepared_by_personnel_id` | fk | ✗ | → `personnel` |
| `prepared_at` | ts | ✗ | |
| `evaluation_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`draft`, `final`, `superseded`] |

### 4.14 `bid_comparison_lines` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `bid_comparison_id` | fk | ✗ | → `bid_comparisons` |
| `supplier_rfq_line_id` | fk | ✗ | → `supplier_rfq_lines` |
| `supplier_quote_line_id` | fk | ✗ | → `supplier_quote_lines`; UNIQUE `(bid_comparison_id, supplier_rfq_line_id, supplier_quote_line_id)` |
| `normalized_unit_price` | money | ✗ | |
| `normalized_total` | money | ✗ | |
| `technical_score` | `DECIMAL(5,2)` | ✓ | |
| `commercial_score` | `DECIMAL(5,2)` | ✓ | |
| `rank` | tint | ✓ | |
| `is_recommended` | bool | ✗ | |

### 4.15 `award_recommendations` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `bid_comparison_id` | fk | ✗ | → `bid_comparisons` |
| `selected_quote_version_id` | fk | ✗ | → `supplier_quote_versions` |
| `is_split_award` | bool | ✗ | Split award (05 §16 madde 7) |
| `total_amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `justification` | text | ✗ | |
| `recommended_by_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `superseded`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_at` | ts | ✓ | |

### 4.16 `purchase_orders` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `purchase_order_no` | code32 | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects` |
| `requesting_org_unit_id` | fk | ✗ | → `org_units` |
| `supplier_party_id` | fk | ✗ | → `parties` |
| `award_recommendation_id` | fk | ✓ | → `award_recommendations` |
| `currency_code` | currency | ✗ | → `currencies` |
| `current_version_id` | fk | ✓ | → `purchase_order_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `approved`, `issued`, `acknowledged`, `partially_delivered`, `delivered`, `closed`, `cancelled`] |
| `issued_at` | ts | ✓ | |
| `supplier_acknowledged_at` | ts | ✓ | |

### 4.17 `purchase_order_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `purchase_order_id` | fk | ✗ | → `purchase_orders` |
| `version_no` | int | ✗ | UNIQUE `(purchase_order_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_amount` | money | ✗ | |
| `payment_terms` | short | ✓ | |
| `delivery_terms` | short | ✓ | |
| `change_reason` | text | ✓ | 2. sürümden itibaren zorunlu |
| `document_revision_id` | fk | ✓ | → `document_revisions`; yayımlanan PO belgesi |
| `status` | status | ✗ | [`draft`, `approved`, `issued`, `superseded`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `issued_at` | ts | ✓ | |

### 4.18 `purchase_order_lines` — V (sürüm çocuğu; durum mutable), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `purchase_order_version_id` | fk | ✗ | → `purchase_order_versions` |
| `line_no` | sint | ✗ | UNIQUE `(purchase_order_version_id, line_no)` |
| `requisition_line_id` | fk | ✓ | → `purchase_requisition_lines` |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `description` | name | ✗ | |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `unit_price` | money | ✗ | |
| `line_total` | money (STORED generated) | ✗ | `quantity * unit_price` |
| `wbs_node_id` | fk | ✓ | → `wbs_nodes` |
| `cbs_node_id` | fk | ✓ | → `cbs_nodes` |
| `required_by` | date | ✗ | |
| `status` | status | ✗ | [`open`, `partially_received`, `received`, `cancelled`] |

### 4.19 `delivery_schedules` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `purchase_order_line_id` | fk | ✗ | → `purchase_order_lines` |
| `schedule_no` | tint | ✗ | UNIQUE `(purchase_order_line_id, schedule_no)` |
| `planned_delivery_on` | date | ✗ | |
| `quantity` | qty | ✗ | |
| `status` | status | ✗ | [`planned`, `shipped`, `delivered`, `cancelled`] |

## 5. Lojistik ve stok (ERD-09 §11.2)

### 5.1 `carriers` — M, sınıf I, +S1 +S2 (PK `party_id`)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | PRIMARY KEY; → `parties` |
| `carrier_code` | code32 | ✗ | UNIQUE |
| `carrier_kind` | status | ✗ | [`road`, `sea`, `air`, `rail`, `courier`, `multimodal`] |
| `status` | status | ✗ | [`active`, `inactive`] |

### 5.2 `shipments` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `shipment_no` | code32 | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects` |
| `carrier_party_id` | fk | ✓ | → `carriers` |
| `mode` | status | ✗ | [`road`, `sea`, `air`, `rail`, `courier`, `multimodal`] |
| `incoterm` | code32 | ✓ | |
| `origin_description` | name | ✗ | |
| `destination_description` | name | ✗ | |
| `destination_warehouse_id` | fk | ✓ | → `warehouses` |
| `etd_on` | date | ✓ | |
| `eta_on` | date | ✓ | |
| `actual_departure_at` | ts | ✓ | |
| `actual_arrival_at` | ts | ✓ | |
| `tracking_reference` | short | ✓ | |
| `responsible_employee_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`planned`, `booked`, `in_transit`, `at_customs`, `delivered`, `partially_delivered`, `damaged`, `cancelled`] |

### 5.3 `shipment_items` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `shipment_id` | fk | ✗ | → `shipments` |
| `delivery_schedule_id` | fk | ✗ | → `delivery_schedules`; UNIQUE `(shipment_id, delivery_schedule_id)` |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `package_reference` | short | ✓ | |

### 5.4 `customs_records` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `shipment_id` | fk | ✗ | → `shipments` |
| `declaration_no` | code | ✓ | UNIQUE (NULL hariç) |
| `customs_office` | short | ✓ | |
| `broker_party_id` | fk | ✓ | → `parties` |
| `declared_at` | ts | ✓ | |
| `cleared_at` | ts | ✓ | |
| `duty_amount` | money | ✓ | |
| `currency_code` | currency | ✓ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`pending`, `declared`, `inspection`, `cleared`, `held`, `rejected`] |

### 5.5 `warehouses` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name` | name | ✗ | |
| `warehouse_kind` | status | ✗ | [`central`, `site`, `transit`, `virtual_supplier`] |
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `project_id` | fk | ✓ | → `projects`; saha deposu |
| `address` | name | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`, `closed`] |

### 5.6 `warehouse_locations` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `warehouse_id` | fk | ✗ | → `warehouses` |
| `code` | code32 | ✗ | UNIQUE `(warehouse_id, code)` |
| `name` | name | ✗ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 5.7 `bins` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `warehouse_location_id` | fk | ✗ | → `warehouse_locations` |
| `warehouse_id` | fk | ✗ | → `warehouses`; denormalize (sayım/transfer kontrolü) |
| `code` | code32 | ✗ | UNIQUE `(warehouse_location_id, code)` |
| `bin_kind` | status | ✗ | [`shelf`, `floor`, `cage`, `yard`, `quarantine`] |
| `status` | status | ✗ | [`active`, `inactive`] |

### 5.8 `goods_receipts` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `receipt_no` | code32 | ✗ | UNIQUE |
| `shipment_id` | fk | ✓ | → `shipments` |
| `warehouse_id` | fk | ✗ | → `warehouses` |
| `supplier_party_id` | fk | ✓ | → `parties` |
| `delivery_note_reference` | short | ✓ | |
| `received_at` | ts | ✗ | |
| `received_by_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`draft`, `received`, `inspected`, `posted`, `rejected`] |
| `posted_at` | ts | ✓ | Stok hareketi üretildi |

### 5.9 `goods_receipt_lines` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `goods_receipt_id` | fk | ✗ | → `goods_receipts` |
| `line_no` | sint | ✗ | UNIQUE `(goods_receipt_id, line_no)` |
| `shipment_item_id` | fk | ✓ | → `shipment_items` |
| `purchase_order_line_id` | fk | ✓ | → `purchase_order_lines` |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `expected_quantity` | qty | ✓ | |
| `received_quantity` | qty | ✗ | |
| `accepted_quantity` | qty | ✓ | Muayene sonrası |
| `rejected_quantity` | qty | ✓ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `bin_id` | fk | ✓ | → `bins` |
| `stock_lot_id` | fk | ✓ | → `stock_lots` |
| `shortage_note` | text | ✓ | |
| `damage_note` | text | ✓ | |

### 5.10 `receipt_inspections` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `goods_receipt_line_id` | fk | ✗ | → `goods_receipt_lines` |
| `inspected_by_personnel_id` | fk | ✗ | → `personnel` |
| `inspected_at` | ts | ✗ | |
| `outcome` | status | ✗ | [`accepted`, `accepted_with_deviation`, `rejected`, `quarantined`] |
| `note` | text | ✓ | |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `ncr_id` | fk | ✓ | → `ncrs` |

### 5.11 `stock_lots` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `lot_no` | code | ✗ | UNIQUE `(catalog_item_id, lot_no)` |
| `supplier_party_id` | fk | ✓ | → `parties` |
| `received_on` | date | ✓ | |
| `expires_on` | date | ✓ | |
| `status` | status | ✗ | [`active`, `quarantined`, `consumed`, `expired`] |

### 5.12 `serialized_items` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `serial_no` | code | ✗ | UNIQUE `(catalog_item_id, serial_no)` |
| `stock_lot_id` | fk | ✓ | → `stock_lots` |
| `current_bin_id` | fk | ✓ | → `bins`; hareketlerden türetilen projeksiyon |
| `project_id` | fk | ✓ | → `projects` |
| `installed_asset_id` | fk | ✓ | → `installed_assets` |
| `status` | status | ✗ | [`in_stock`, `reserved`, `issued`, `installed`, `returned`, `scrapped`] |

### 5.13 `stock_reservations` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `warehouse_id` | fk | ✗ | → `warehouses` |
| `bin_id` | fk | ✓ | → `bins` |
| `reserved_quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `reserved_for_type` | code32 | ✗ | Registry: `work_package`, `purchase_requisition_line`, `project` |
| `reserved_for_id` | fk | ✗ | |
| `reserved_by_personnel_id` | fk | ✗ | → `personnel` |
| `reserved_at` | ts | ✗ | |
| `expires_at` | ts | ✓ | |
| `status` | status | ✗ | [`active`, `fulfilled`, `released`, `expired`] |

### 5.14 `inventory_transactions` — A, sınıf I, +S1

Stok doğruluk kaynağı; bakiye projeksiyondur.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `posting_sequence` | bigu | ✗ | `AUTO_INCREMENT` UNIQUE; projeksiyon sırası |
| `transaction_type` | status | ✗ | [`receipt`, `issue_to_project`, `transfer`, `count_adjustment`, `return`, `scrap`, `reservation_fulfilment`] |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `stock_lot_id` | fk | ✓ | → `stock_lots`; lot izlenen üründe zorunlu |
| `serialized_item_id` | fk | ✓ | → `serialized_items`; seri izlenen üründe zorunlu, `quantity = 1` |
| `from_bin_id` | fk | ✓ | → `bins` |
| `to_bin_id` | fk | ✓ | → `bins` |
| `project_id` | fk | ✓ | → `projects` |
| `quantity` | qty | ✗ | `CHECK (quantity > 0)` |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `unit_cost` | money | ✓ | |
| `currency_code` | currency | ✓ | |
| `source_type` | code32 | ✗ | Registry: `goods_receipt_line`, `stock_transfer_line`, `stock_count_line`, `work_package`, `stock_reservation`, `service_work_order` |
| `source_id` | fk | ✗ | |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `occurred_at` | ts | ✗ | |
| `posted_by_personnel_id` | fk | ✗ | → `personnel` |

`CHECK` hareket tipine göre: `receipt`/`return` → `to_bin` dolu, `from_bin` NULL; `issue_to_project`/`scrap` → `from_bin` dolu; `transfer` → ikisi dolu ve farklı; `count_adjustment` → tam biri dolu.

### 5.15 `stock_counts` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `warehouse_id` | fk | ✗ | → `warehouses` |
| `count_no` | code32 | ✗ | UNIQUE |
| `count_kind` | status | ✗ | [`full`, `cycle`, `spot`] |
| `planned_on` | date | ✗ | |
| `counted_at` | ts | ✓ | |
| `status` | status | ✗ | [`planned`, `counting`, `review`, `approved`, `posted`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |

### 5.16 `stock_count_lines` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `stock_count_id` | fk | ✗ | → `stock_counts` |
| `bin_id` | fk | ✗ | → `bins` |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `stock_lot_id` | fk | ✓ | → `stock_lots` |
| `expected_quantity` | qty | ✗ | |
| `counted_quantity` | qty | ✗ | |
| `variance_quantity` | qty (STORED generated) | ✗ | `counted_quantity - expected_quantity` |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `counted_by_personnel_id` | fk | ✗ | → `personnel` |
| `note` | text | ✓ | |

UNIQUE `(stock_count_id, bin_id, catalog_item_id, stock_lot_id)`.

### 5.17 `stock_transfers` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `transfer_no` | code32 | ✗ | UNIQUE |
| `source_warehouse_id` | fk | ✗ | → `warehouses` |
| `target_warehouse_id` | fk | ✗ | → `warehouses`; `CHECK (<> source)` |
| `project_id` | fk | ✓ | → `projects` |
| `shipment_id` | fk | ✓ | → `shipments` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`draft`, `approved`, `dispatched`, `received`, `posted`, `cancelled`] |
| `dispatched_at` | ts | ✓ | |
| `received_at` | ts | ✓ | |

### 5.18 `stock_transfer_lines` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `stock_transfer_id` | fk | ✗ | → `stock_transfers` |
| `line_no` | sint | ✗ | UNIQUE `(stock_transfer_id, line_no)` |
| `catalog_item_id` | fk | ✗ | → `catalog_items` |
| `stock_lot_id` | fk | ✓ | → `stock_lots` |
| `serialized_item_id` | fk | ✓ | → `serialized_items` |
| `source_bin_id` | fk | ✗ | → `bins` |
| `target_bin_id` | fk | ✗ | → `bins` |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `received_quantity` | qty | ✓ | |

## 6. Bütçe, üçlü eşleştirme ve ödeme (ERD-09 §11.3)

### 6.1 `project_budgets` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE (1:1) |
| `currency_code` | currency | ✗ | → `currencies` |
| `current_version_id` | fk | ✓ | → `budget_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `closed`] |

### 6.2 `budget_versions` — V, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_budget_id` | fk | ✗ | → `project_budgets` |
| `version_no` | int | ✗ | UNIQUE `(project_budget_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_amount` | money | ✗ | |
| `change_reason` | text | ✓ | |
| `status` | status | ✗ | [`draft`, `approved`, `superseded`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 6.3 `budget_lines` — V (sürüm çocuğu), sınıf R, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `budget_version_id` | fk | ✗ | → `budget_versions` |
| `cbs_node_id` | fk | ✗ | → `cbs_nodes`; UNIQUE `(budget_version_id, cbs_node_id)` |
| `amount` | money | ✗ | |
| `note` | text | ✓ | |

### 6.4 `commitments` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `cbs_node_id` | fk | ✗ | Composite FK aynı proje |
| `purchase_order_line_id` | fk | ✗ | → `purchase_order_lines`; UNIQUE `(purchase_order_line_id, cbs_node_id)` |
| `committed_amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `exchange_rate_snapshot` | json | ✓ | Kur kaynağı/tarihi |
| `committed_at` | ts | ✗ | |
| `status` | status | ✗ | [`open`, `partially_invoiced`, `invoiced`, `closed`, `cancelled`] |

### 6.5 `actual_cost_references` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `cbs_node_id` | fk | ✗ | Composite FK aynı proje |
| `external_system` | code32 | ✗ | `zirve` |
| `external_record_id` | short | ✗ | UNIQUE `(external_system, external_record_id)` |
| `amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `posted_on` | date | ✗ | |
| `payload_hash` | hash | ✗ | |
| `imported_at` | ts | ✗ | |

### 6.6 `supplier_invoices` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_party_id` | fk | ✗ | → `parties` |
| `invoice_no` | short | ✗ | |
| `invoice_date` | date | ✗ | UNIQUE `(supplier_party_id, invoice_no, invoice_date)` |
| `project_id` | fk | ✓ | → `projects` |
| `due_date` | date | ✓ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `net_amount` | money | ✗ | |
| `tax_amount` | money | ✗ | |
| `gross_amount` | money | ✗ | |
| `document_revision_id` | fk | ✗ | → `document_revisions`; fatura görüntüsü |
| `external_accounting_ref` | short | ✓ | Zirve kaydı |
| `status` | status | ✗ | [`received`, `matching`, `matched`, `disputed`, `approved_for_payment`, `paid`, `cancelled`] |

### 6.7 `invoice_lines` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `supplier_invoice_id` | fk | ✗ | → `supplier_invoices` |
| `line_no` | sint | ✗ | UNIQUE `(supplier_invoice_id, line_no)` |
| `description` | name | ✗ | |
| `cbs_node_id` | fk | ✓ | → `cbs_nodes` |
| `purchase_order_line_id` | fk | ✓ | → `purchase_order_lines` |
| `quantity` | qty | ✓ | |
| `uom_id` | fk | ✓ | → `units_of_measure` |
| `unit_price` | money | ✓ | |
| `line_total` | money | ✗ | |
| `tax_rate_pct` | pct | ✗ | |

### 6.8 `invoice_matches` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `invoice_line_id` | fk | ✗ | → `invoice_lines` |
| `purchase_order_line_id` | fk | ✗ | → `purchase_order_lines` |
| `goods_receipt_line_id` | fk | ✗ | → `goods_receipt_lines`; UNIQUE üçlü |
| `matched_quantity` | qty | ✗ | |
| `matched_amount` | money | ✗ | |
| `variance_amount` | money | ✓ | |
| `match_state` | status | ✗ | [`proposed`, `confirmed`, `rejected`] |
| `matched_by_personnel_id` | fk | ✗ | → `personnel` |
| `matched_at` | ts | ✗ | |

Toplamlar üç satırın miktar/tutarını aşamaz; eşzamanlı eşleştirmede üç kök deterministik sırayla kilitlenir.

### 6.9 `tax_obligations` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `project_id` | fk | ✓ | → `projects` |
| `supplier_invoice_id` | fk | ✓ | → `supplier_invoices` |
| `tax_type` | status | ✗ | [`vat`, `withholding`, `stamp`, `customs_duty`, `corporate`, `other`] |
| `period_code` | code32 | ✗ | |
| `due_on` | date | ✗ | |
| `amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `status` | status | ✗ | [`planned`, `due`, `paid`, `overdue`, `cancelled`] |
| `paid_at` | ts | ✓ | |
| `external_reference` | short | ✓ | |

### 6.10 `payment_requests` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_no` | code32 | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects` |
| `beneficiary_party_id` | fk | ✗ | → `parties` |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_amount` | money | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `requested_at` | ts | ✗ | |
| `planned_payment_on` | date | ✓ | |
| `status` | status | ✗ | [`draft`, `submitted`, `approved`, `rejected`, `scheduled`, `paid`, `cancelled`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 6.11 `payment_request_invoices` — M, sınıf R, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `payment_request_id` | fk | ✗ | → `payment_requests` |
| `supplier_invoice_id` | fk | ✗ | → `supplier_invoices`; UNIQUE `(payment_request_id, supplier_invoice_id)` |
| `requested_amount` | money | ✗ | |

### 6.12 `payments` — A, sınıf R, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `payment_request_id` | fk | ✗ | → `payment_requests` |
| `external_payment_id` | short | ✓ | UNIQUE (NULL hariç) |
| `paid_amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `paid_at` | ts | ✗ | |
| `payment_method` | status | ✗ | [`bank_transfer`, `check`, `cash`, `letter_of_credit`] |
| `bank_reference` | short | ✓ | |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `recorded_by_personnel_id` | fk | ✗ | → `personnel` |

### 6.13 `cash_flow_forecasts` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `period_start` | date | ✗ | |
| `period_end` | date | ✗ | `CHECK` tarih sırası |
| `forecast_version` | sint | ✗ | UNIQUE `(project_id, period_start, forecast_version)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `inflow_amount` | money | ✗ | |
| `outflow_amount` | money | ✗ | |
| `source` | status | ✗ | [`manual`, `computed`] |
| `prepared_by_personnel_id` | fk | ✗ | → `personnel` |

### 6.14 `progress_claims` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `claim_no` | code32 | ✗ | UNIQUE |
| `contract_version_id` | fk | ✓ | → `contract_versions` |
| `period_start` | date | ✗ | |
| `period_end` | date | ✗ | `CHECK` tarih sırası |
| `current_version_id` | fk | ✓ | → `progress_claim_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `submitted`, `certified`, `invoiced`, `paid`, `rejected`, `cancelled`] |

### 6.15 `progress_claim_versions` — V, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `progress_claim_id` | fk | ✗ | → `progress_claims` |
| `version_no` | int | ✗ | UNIQUE `(progress_claim_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `gross_amount` | money | ✗ | |
| `previous_certified_amount` | money | ✗ | |
| `retention_amount` | money | ✗ | |
| `net_claim_amount` | money | ✗ | |
| `certified_amount` | money | ✓ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`draft`, `submitted`, `certified`, `superseded`] |
| `submitted_at` | ts | ✓ | |
| `certified_at` | ts | ✓ | |

### 6.16 `retentions` — M, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `progress_claim_version_id` | fk | ✗ | → `progress_claim_versions` |
| `retained_amount` | money | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `release_due_on` | date | ✓ | |
| `released_amount` | money | ✓ | |
| `released_at` | ts | ✓ | |
| `status` | status | ✗ | [`held`, `partially_released`, `released`] |

## 7. Mühendislik ve saha yürütme (ERD-10 §12.1)

### 7.1 `engineering_deliverables` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✗ | Composite FK aynı proje |
| `deliverable_code` | code32 | ✗ | UNIQUE `(project_id, deliverable_code)` |
| `title` | name | ✗ | |
| `deliverable_type` | status | ✗ | [`drawing`, `calculation`, `specification`, `datasheet`, `single_line_diagram`, `plc_program`, `scada_config`, `as_built`, `manual`, `other`] |
| `discipline` | code32 | ✗ | |
| `responsible_employee_id` | fk | ✗ | → `personnel` |
| `planned_issue_on` | date | ✓ | |
| `current_revision_id` | fk | ✓ | → `engineering_revisions` (aynı kök) |
| `status` | status | ✗ | [`planned`, `in_progress`, `issued_for_review`, `approved`, `issued_for_construction`, `as_built`, `superseded`, `cancelled`] |

### 7.2 `engineering_revisions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `deliverable_id` | fk | ✗ | → `engineering_deliverables` |
| `revision_no` | int | ✗ | UNIQUE `(deliverable_id, revision_no)` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; exact DMS revizyonu |
| `purpose` | status | ✗ | `document_revisions.purpose` değerleri |
| `status` | status | ✗ | [`draft`, `review`, `approved`, `issued`, `superseded`] |
| `issued_at` | ts | ✓ | |

### 7.3 `technical_requirements` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `requirement_code` | code32 | ✗ | UNIQUE `(project_id, requirement_code)` |
| `source_document_revision_id` | fk | ✗ | → `document_revisions` |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `discipline` | code32 | ✗ | |
| `priority` | status | ✗ | [`must`, `should`, `could`] |
| `verification_method` | status | ✗ | [`inspection`, `test`, `analysis`, `demonstration`, `document`] |
| `status` | status | ✗ | [`open`, `verified`, `waived`, `failed`] |
| `linked_test_execution_id` | fk | ✓ | → `test_executions` |
| `verified_by_personnel_id` | fk | ✓ | → `personnel` |
| `verified_at` | ts | ✓ | |

### 7.4 `work_plans` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `plan_kind` | status | ✗ | [`execution_plan`, `hse_plan`, `quality_plan`, `commissioning_plan`, `logistics_plan`, `other`] |
| `effective_from` | date | ✓ | |
| `acknowledgement_required` | bool | ✗ | Kişi bazlı okundu/kabul (`document_acknowledgements`) |
| `status` | status | ✗ | [`draft`, `approved`, `distributed`, `superseded`] |

### 7.5 `procedures` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects`; NULL = şirket geneli |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `procedure_type` | status | ✗ | [`safety`, `quality`, `test`, `installation`, `environmental`, `emergency`, `other`] |
| `acknowledgement_required` | bool | ✗ | |
| `status` | status | ✗ | [`active`, `superseded`] |

### 7.6 `method_statements` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `work_package_id` | fk | ✓ | Composite FK aynı proje |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `risk_assessment_document_revision_id` | fk | ✓ | → `document_revisions` |
| `title` | name | ✗ | |
| `status` | status | ✗ | [`draft`, `review`, `approved`, `superseded`] |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 7.7 `crews` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `crew_code` | code32 | ✗ | UNIQUE `(project_id, crew_code)` |
| `name` | name | ✗ | |
| `crew_kind` | status | ✗ | [`electrical`, `mechanical`, `civil`, `automation`, `test`, `mixed`] |
| `supervisor_employee_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`forming`, `active`, `standby`, `disbanded`] |

### 7.8 `crew_memberships` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `crew_id` | fk | ✗ | → `crews` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `role_code` | code32 | ✗ | [`supervisor`, `lead`, `technician`, `helper`, `hse_officer`] |
| `certification_checked_at` | ts | ✓ | Zorunlu sertifika kontrolü |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 7.9 `equipment_usages` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `crew_id` | fk | ✓ | → `crews` |
| `equipment_reference` | short | ✗ | Varlık kodu veya kiralama referansı |
| `equipment_kind` | code32 | ✗ | |
| `operator_employee_id` | fk | ✓ | → `personnel` |
| `started_at` | ts | ✗ | |
| `ended_at` | ts | ✓ | `CHECK` tarih sırası |
| `hours` | `DECIMAL(8,2)` | ✓ | |
| `note` | text | ✓ | |

### 7.10 `installed_quantities` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `work_package_id` | fk | ✗ | Composite FK aynı proje |
| `wbs_node_id` | fk | ✓ | Composite FK aynı proje |
| `catalog_item_id` | fk | ✓ | → `catalog_items` |
| `progress_date` | date | ✗ | |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `reported_by_personnel_id` | fk | ✗ | → `personnel` |
| `report_submission_version_id` | fk | ✓ | → `report_submission_versions`; saha günlüğünden |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |

UNIQUE `(work_package_id, progress_date, catalog_item_id, uom_id, report_submission_version_id)`.

### 7.11 `site_photos` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `work_package_id` | fk | ✓ | Composite FK aynı proje |
| `file_object_id` | fk | ✗ | → `file_objects`; orijinal dosya |
| `captured_at` | ts | ✗ | |
| `captured_by_employee_id` | fk | ✗ | → `personnel` |
| `caption` | short | ✓ | |
| `location_note` | short | ✓ | |
| `latitude` | `DECIMAL(9,6)` | ✓ | Spatial index kararı DB-G7 (D-23) |
| `longitude` | `DECIMAL(9,6)` | ✓ | |
| `report_answer_id` | fk | ✓ | → `report_answers` |

## 8. Kalite, İSG, uygunsuzluk ve punch (ERD-10 §12.2)

### 8.1 `inspections` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `workstream_id` | fk | ✓ | Composite FK aynı proje |
| `work_package_id` | fk | ✓ | Composite FK aynı proje |
| `inspection_no` | code32 | ✗ | UNIQUE `(project_id, inspection_no)` |
| `inspection_type` | status | ✗ | [`incoming`, `in_process`, `final`, `hse_walk`, `third_party`, `customer_witness`] |
| `procedure_id` | fk | ✓ | → `procedures` |
| `inspected_at` | ts | ✗ | |
| `inspector_personnel_id` | fk | ✗ | → `personnel` |
| `outcome` | status | ✗ | [`pending`, `pass`, `pass_with_remarks`, `fail`] |
| `remarks` | text | ✓ | |
| `checklist_document_revision_id` | fk | ✓ | → `document_revisions` |

### 8.2 `ncrs` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `ncr_no` | code32 | ✗ | UNIQUE |
| `inspection_id` | fk | ✓ | → `inspections` |
| `title` | name | ✗ | |
| `description` | text | ✗ | |
| `severity` | status | ✗ | [`minor`, `major`, `critical`] |
| `category` | status | ✗ | [`material`, `workmanship`, `design`, `documentation`, `hse`, `supplier`] |
| `responsible_party_id` | fk | ✓ | → `parties`; tedarikçi/taşeron |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `raised_by_personnel_id` | fk | ✗ | → `personnel` |
| `raised_at` | ts | ✗ | |
| `root_cause` | text | ✓ | |
| `disposition` | status | ✓ | [`rework`, `repair`, `use_as_is`, `reject`, `scrap`] |
| `status` | status | ✗ | [`open`, `root_cause`, `corrective_action`, `verification`, `closed`, `cancelled`] |
| `closed_by_personnel_id` | fk | ✓ | → `personnel` |
| `closed_at` | ts | ✓ | |

### 8.3 `corrective_actions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `ncr_id` | fk | ✗ | → `ncrs` |
| `action_no` | sint | ✗ | UNIQUE `(ncr_id, action_no)` |
| `description` | text | ✗ | |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `due_at` | ts | ✗ | |
| `status` | status | ✗ | [`planned`, `in_progress`, `done`, `verified`, `cancelled`] |
| `completed_at` | ts | ✓ | |
| `verified_by_personnel_id` | fk | ✓ | → `personnel` |
| `verified_at` | ts | ✓ | |
| `effectiveness_note` | text | ✓ | |

### 8.4 `incidents` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `incident_no` | code32 | ✗ | UNIQUE |
| `incident_type` | status | ✗ | [`injury`, `near_miss`, `property_damage`, `environmental`, `fire`, `electrical`, `vehicle`, `security`, `other`] |
| `severity` | status | ✗ | [`low`, `medium`, `high`, `critical`] |
| `occurred_at` | ts | ✗ | |
| `timezone` | tz | ✗ | |
| `location_note` | short | ✓ | |
| `description` | text | ✗ | |
| `reported_by_personnel_id` | fk | ✗ | → `personnel` |
| `reported_at` | ts | ✗ | |
| `involved_employee_count` | tint | ✗ | Kişi kimlikleri restricted profilde tutulur |
| `lost_time_days` | sint | ✓ | |
| `authority_notified` | bool | ✗ | |
| `investigation_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`reported`, `investigating`, `actions_open`, `closed`] |
| `closed_at` | ts | ✓ | |

### 8.5 `permits` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `permit_type` | status | ✗ | [`work_permit`, `hot_work`, `electrical_isolation`, `work_at_height`, `confined_space`, `excavation`, `energization`, `authority_permit`] |
| `permit_no` | code | ✗ | UNIQUE `(project_id, permit_type, permit_no)` |
| `issued_by_personnel_id` | fk | ✗ | → `personnel` |
| `issued_to_crew_id` | fk | ✓ | → `crews` |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✗ | `CHECK (valid_until > valid_from)` |
| `conditions` | text | ✓ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`requested`, `issued`, `active`, `suspended`, `expired`, `closed`, `cancelled`] |

### 8.6 `punch_items` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `punch_no` | code32 | ✗ | UNIQUE `(project_id, punch_no)` |
| `inspection_id` | fk | ✓ | → `inspections` |
| `test_execution_id` | fk | ✓ | → `test_executions` |
| `work_package_id` | fk | ✓ | Composite FK aynı proje |
| `category` | status | ✗ | [`A`, `B`, `C`]; `A` enerjilendirme öncesi kapanmalı |
| `description` | text | ✗ | |
| `location_note` | short | ✓ | |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `raised_by_personnel_id` | fk | ✗ | → `personnel` |
| `raised_at` | ts | ✗ | |
| `due_at` | ts | ✓ | |
| `status` | status | ✗ | [`open`, `in_progress`, `ready_for_check`, `cleared`, `cancelled`] |
| `cleared_by_personnel_id` | fk | ✓ | → `personnel` |
| `cleared_at` | ts | ✓ | |
| `verification_document_revision_id` | fk | ✓ | → `document_revisions` |

### 8.7 `quality_evidence` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `inspection_id` | fk | ✓ | → `inspections` |
| `ncr_id` | fk | ✓ | → `ncrs` |
| `corrective_action_id` | fk | ✓ | → `corrective_actions` |
| `incident_id` | fk | ✓ | → `incidents` |
| `punch_item_id` | fk | ✓ | → `punch_items` |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `evidence_role` | status | ✗ | [`photo`, `report`, `certificate`, `checklist`, `other`] |

`CHECK` XOR: beş hedeften tam biri dolu.

## 9. Test, devreye alma, kabul ve servis (ERD-10 §12.3)

### 9.1 `test_plan_templates` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `test_family` | status | ✗ | [`fat`, `sat`, `pre_commissioning`, `commissioning`, `performance`, `other`]; kurumsal adlar D-24 |
| `component_definition_id` | fk | ✓ | → `component_definitions` |
| `current_version_id` | fk | ✓ | → `test_plan_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 9.2 `test_plan_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `test_plan_template_id` | fk | ✗ | → `test_plan_templates` |
| `version_no` | int | ✗ | UNIQUE `(test_plan_template_id, version_no)` |
| `procedure_document_revision_id` | fk | ✓ | → `document_revisions` |
| `definition_hash` | hash | ✓ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 9.3 `test_plan_step_definitions` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `test_plan_version_id` | fk | ✗ | → `test_plan_versions` |
| `step_code` | code32 | ✗ | UNIQUE `(test_plan_version_id, step_code)` |
| `sequence_no` | sint | ✗ | UNIQUE `(test_plan_version_id, sequence_no)` |
| `title_tr` | name | ✗ | |
| `title_en` | name | ✗ | |
| `instruction` | text | ✓ | |
| `witness_type` | status | ✗ | [`none`, `internal`, `customer_witness`, `hold_point`, `third_party`] |
| `acceptance_rule` | text | ✗ | |
| `measurement_uom_id` | fk | ✓ | → `units_of_measure` |
| `min_value` | `DECIMAL(24,8)` | ✓ | |
| `max_value` | `DECIMAL(24,8)` | ✓ | |
| `requires_evidence` | bool | ✗ | |
| `is_mandatory` | bool | ✗ | |

### 9.4 `test_equipment` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `asset_no` | code | ✗ | UNIQUE |
| `name` | name | ✗ | |
| `equipment_type` | code32 | ✗ | |
| `manufacturer` | name | ✓ | |
| `model` | short | ✓ | |
| `serial_no` | short | ✓ | |
| `owner_org_unit_id` | fk | ✓ | → `org_units` |
| `current_calibration_certificate_id` | fk | ✓ | → `calibration_certificates` |
| `status` | status | ✗ | [`active`, `calibration_due`, `out_of_service`, `retired`] |

### 9.5 `calibration_certificates` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `test_equipment_id` | fk | ✗ | → `test_equipment` |
| `certificate_no` | code | ✗ | UNIQUE `(test_equipment_id, certificate_no)` |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `calibrated_by` | name | ✗ | |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✗ | `CHECK (valid_until > valid_from)` |
| `status` | status | ✗ | [`valid`, `expired`, `revoked`] |

### 9.6 `installed_assets` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects`; UNIQUE `(project_id, id)` |
| `asset_code` | code | ✗ | UNIQUE |
| `name` | name | ✗ | |
| `component_definition_id` | fk | ✓ | → `component_definitions` |
| `catalog_item_id` | fk | ✓ | → `catalog_items` |
| `serialized_item_id` | fk | ✓ | → `serialized_items` |
| `serial_no` | short | ✓ | |
| `parent_asset_id` | fk | ✓ | Composite FK aynı proje; `CHECK (<> id)` |
| `location_note` | short | ✓ | |
| `installed_on` | date | ✓ | |
| `energized_on` | date | ✓ | |
| `customer_party_id` | fk | ✓ | → `parties`; devir sonrası sahibi |
| `status` | status | ✗ | [`planned`, `installed`, `tested`, `commissioned`, `in_service`, `under_warranty_claim`, `decommissioned`] |

### 9.7 `test_executions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `execution_no` | code32 | ✗ | UNIQUE `(project_id, execution_no)` |
| `test_plan_version_id` | fk | ✗ | → `test_plan_versions`; `published` olmalı |
| `installed_asset_id` | fk | ✓ | Composite FK aynı proje |
| `lead_engineer_employee_id` | fk | ✗ | → `personnel` |
| `witness_party_id` | fk | ✓ | → `parties` |
| `witness_name` | name | ✓ | |
| `retest_of_execution_id` | fk | ✓ | → `test_executions` |
| `started_at` | ts | ✓ | |
| `completed_at` | ts | ✓ | |
| `overall_outcome` | status | ✓ | [`pass`, `fail`, `conditional`] |
| `result_summary` | text | ✓ | |
| `status` | status | ✗ | [`planned`, `in_progress`, `passed`, `failed`, `aborted`, `superseded`] |
| `daily_report_submission_version_id` | fk | ✓ | → `report_submission_versions` |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |

### 9.8 `test_execution_steps` — M (snapshot alanları immutable), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `test_execution_id` | fk | ✗ | → `test_executions` |
| `step_definition_id` | fk | ✗ | → `test_plan_step_definitions`; UNIQUE `(test_execution_id, step_definition_id)` |
| `sequence_no_snapshot` | sint | ✗ | |
| `title_snapshot` | name | ✗ | |
| `acceptance_rule_snapshot` | text | ✗ | |
| `witness_type_snapshot` | status | ✗ | |
| `measured_value` | `DECIMAL(24,8)` | ✓ | |
| `measured_text` | short | ✓ | |
| `uom_id` | fk | ✓ | → `units_of_measure` |
| `outcome` | status | ✗ | [`pending`, `pass`, `fail`, `not_applicable`, `waived`] |
| `performed_by_personnel_id` | fk | ✓ | → `personnel` |
| `performed_at` | ts | ✓ | |
| `witnessed_by_personnel_id` | fk | ✓ | → `personnel` |
| `witness_name` | name | ✓ | |
| `hold_point_released_by_personnel_id` | fk | ✓ | → `personnel` |
| `hold_point_released_at` | ts | ✓ | |
| `remarks` | text | ✓ | |

### 9.9 `test_execution_equipment` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `test_execution_id` | fk | ✗ | → `test_executions` |
| `test_equipment_id` | fk | ✗ | → `test_equipment`; UNIQUE `(test_execution_id, test_equipment_id)` |
| `calibration_certificate_id` | fk | ✗ | → `calibration_certificates`; icra anında geçerli olmalı (servis) |

### 9.10 `test_evidence` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `execution_step_id` | fk | ✗ | → `test_execution_steps` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; UNIQUE `(execution_step_id, document_revision_id)` |
| `evidence_kind` | status | ✗ | [`measurement_sheet`, `photo`, `log_export`, `certificate`, `video`, `other`] |
| `evidence_hash` | hash | ✗ | |

### 9.11 `commissioning_packages` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `project_id` | fk | ✗ | → `projects` |
| `package_code` | code32 | ✗ | UNIQUE `(project_id, package_code)` |
| `name` | name | ✗ | |
| `package_kind` | status | ✗ | [`system`, `subsystem`, `area`] |
| `owner_employee_id` | fk | ✗ | → `personnel` |
| `planned_energization_on` | date | ✓ | |
| `actual_energization_at` | ts | ✓ | |
| `status` | status | ✗ | [`planned`, `in_progress`, `mechanical_complete`, `pre_commissioned`, `commissioned`, `accepted`, `closed`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 9.12 `commissioning_package_tests` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `package_id` | fk | ✗ | → `commissioning_packages` |
| `test_execution_id` | fk | ✗ | → `test_executions`; UNIQUE `(package_id, test_execution_id)` |
| `is_required` | bool | ✗ | |

### 9.13 `commissioning_package_assets` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `package_id` | fk | ✗ | → `commissioning_packages` |
| `installed_asset_id` | fk | ✗ | → `installed_assets`; UNIQUE `(package_id, installed_asset_id)` |

### 9.14 `acceptance_certificates` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `certificate_no` | code | ✗ | UNIQUE |
| `project_id` | fk | ✗ | → `projects` |
| `commissioning_package_id` | fk | ✓ | → `commissioning_packages` |
| `acceptance_type` | status | ✗ | [`mechanical_completion`, `first_energization`, `provisional_acceptance`, `final_acceptance`, `partial`] |
| `document_revision_id` | fk | ✗ | → `document_revisions`; imzalı sertifika |
| `accepted_by_party_id` | fk | ✗ | → `parties` |
| `customer_signatory_name` | name | ✓ | |
| `konelsis_signatory_personnel_id` | fk | ✗ | → `personnel` |
| `accepted_at` | ts | ✗ | |
| `open_punch_count_snapshot` | sint | ✗ | |
| `conditions` | text | ✓ | |

### 9.15 `warranties` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `installed_asset_id` | fk | ✗ | → `installed_assets` |
| `warranty_kind` | status | ✗ | [`supplier`, `konelsis_contractual`, `extended`] |
| `provider_party_id` | fk | ✓ | → `parties` |
| `starts_on` | date | ✗ | UNIQUE `(installed_asset_id, warranty_kind, starts_on)` |
| `ends_on` | date | ✗ | `CHECK (ends_on > starts_on)` |
| `coverage_note` | text | ✓ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`pending`, `active`, `expired`, `voided`] |

### 9.16 `service_requests` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_no` | code32 | ✗ | UNIQUE |
| `installed_asset_id` | fk | ✗ | → `installed_assets` |
| `project_id` | fk | ✗ | → `projects`; denormalize |
| `warranty_id` | fk | ✓ | → `warranties` |
| `request_type` | status | ✗ | [`breakdown`, `preventive`, `warranty_claim`, `inspection`, `upgrade`] |
| `priority` | status | ✗ | [`low`, `normal`, `high`, `critical`] |
| `reported_by_party_id` | fk | ✓ | → `parties` |
| `reported_by_personnel_id` | fk | ✗ | → `personnel` |
| `reported_at` | ts | ✗ | |
| `description` | text | ✗ | |
| `sla_due_at` | ts | ✓ | |
| `status` | status | ✗ | [`received`, `triaged`, `scheduled`, `in_progress`, `resolved`, `closed`, `cancelled`] |
| `resolved_at` | ts | ✓ | |

### 9.17 `work_orders` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `service_request_id` | fk | ✗ | → `service_requests` |
| `work_order_no` | code32 | ✗ | UNIQUE |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `crew_id` | fk | ✓ | → `crews` |
| `scheduled_start_at` | ts | ✓ | |
| `scheduled_end_at` | ts | ✓ | |
| `labor_hours` | `DECIMAL(8,2)` | ✓ | |
| `parts_note` | text | ✓ | Yedek parça hareketi `inventory_transactions` ile |
| `completion_report_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`planned`, `dispatched`, `in_progress`, `done`, `verified`, `cancelled`] |
| `completed_at` | ts | ✓ | |

### 9.18 `maintenance_plans` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `installed_asset_id` | fk | ✗ | → `installed_assets` |
| `plan_code` | code32 | ✗ | UNIQUE `(installed_asset_id, plan_code)` |
| `frequency_rule` | code32 | ✗ | ISO 8601 süre (`P6M`, `P1Y`) veya kurum kuralı |
| `procedure_id` | fk | ✓ | → `procedures` |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `next_due_on` | date | ✗ | |
| `last_done_on` | date | ✓ | |
| `generates_work_order` | bool | ✗ | |
| `status` | status | ✗ | [`active`, `paused`, `retired`] |

## 10. Bu bölümün DB-G5/DB-G8 kontrol listesi

- ERD-08/09/10 tabloları eşlendi; sözlük eklemeleri: `purchase_requisitions.requisition_kind` (ofis talebi), `project_tasks` = `tasks` uzantısı (D-21), `inventory_transactions.posting_sequence`, `site_photos.latitude/longitude` (D-23), `bins.warehouse_id` denormalizasyonu.
- Açık kararlar (05 §16 madde 5–10; 03 §19 madde 9–11): altı workstream'in başlangıç bağımlılıkları ve primary focus/waiver yetkilisi (D-22), proje tipi/component kataloğu ve G0–G9 şablonları (D-20), kısmi RFQ/split award/kısmi teslim/iade kuralları, UoM/lot/seri matrisi ve eksi stok politikası, bütçe/kur/Zirve eşlemesi, test aşamalarının kurumsal adları ve witness/hold point'leri (D-24), Zirve/MS Project/SCADA entegrasyon sözleşmeleri.
- Stok bakiyesi, WBS/CBS toplamları ve maliyet özetleri `_rm` projeksiyonlarıdır; sözlük kapsamında değildir.
