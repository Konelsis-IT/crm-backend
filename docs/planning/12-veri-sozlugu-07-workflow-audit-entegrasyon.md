# Konelsis Kurumsal Platform — Veri sözlüğü 7: workflow, onay, Personel Hareketleri, entegrasyon, harici analiz ve registry

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Denetim kaydının adı **Personel Hareketleri**'dir (D-44); dış sistem erişim bilgileri açık tutulur (D-45).  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md)  
**ERD:** ERD-11 (workflow/onay, Personel Hareketleri, entegrasyon, harici analiz); kurallar [03 §13–14](03-veri-tabani-tasarim-plani.md), [01 §14–15](01-teknik-mimari-plani.md)

Bu bölümdeki tablolar AI uygulaması değildir. Prompt, model, RAG, embedding, vector, training data veya clone memory içermez; yalnız izinli capability, exact kaynak revizyonu, sonuç ve aksiyon audit metadata'sı tutulur.

## 1. Workflow (ERD-11 §13.1)

### 1.1 `workflow_definitions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `subject_type` | code32 | ✗ | Registry (§5) |
| `current_version_id` | fk | ✓ | → `workflow_definition_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 1.2 `workflow_definition_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workflow_definition_id` | fk | ✗ | → `workflow_definitions` |
| `version_no` | int | ✗ | UNIQUE `(workflow_definition_id, version_no)` |
| `change_summary` | text | ✓ | |
| `definition_hash` | hash | ✓ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 1.3 `workflow_steps` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workflow_definition_version_id` | fk | ✗ | → `workflow_definition_versions` |
| `step_code` | code32 | ✗ | UNIQUE `(workflow_definition_version_id, step_code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `sequence_no` | sint | ✗ | |
| `step_type` | status | ✗ | [`task`, `approval`, `notification`, `wait`, `system_action`, `parallel_group`] |
| `parallel_group_code` | code32 | ✓ | Paralel adımlar aynı grup kodunu taşır |
| `approval_policy_version_id` | fk | ✓ | → `approval_policy_versions`; `approval` tipinde zorunlu |
| `assignee_resolver` | status | ✓ | [`none`, `personnel`, `position`, `line_manager`, `org_unit_manager`, `project_role`, `functional_area_role`] |
| `resolver_target_id` | fk | ✓ | |
| `role_code` | code32 | ✓ | |
| `sla_minutes` | int | ✓ | |
| `on_reject_step_code` | code32 | ✓ | Ret/iade hedef adımı |
| `is_terminal` | bool | ✗ | |

### 1.4 `workflow_instances` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workflow_definition_version_id` | fk | ✗ | → `workflow_definition_versions`; başlangıç sürümüne sabit |
| `subject_type` | code32 | ✗ | Registry |
| `subject_id` | fk | ✗ | |
| `subject_revision_id` | fk | ✓ | Exact sürüm |
| `subject_hash` | hash | ✗ | |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `started_by_personnel_id` | fk | ✗ | → `personnel` |
| `started_at` | ts | ✗ | |
| `current_step_code` | code32 | ✓ | |
| `status` | status | ✗ | [`running`, `waiting`, `completed`, `rejected`, `cancelled`, `failed`] |
| `completed_at` | ts | ✓ | |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN status IN ('running','waiting') THEN 1 END`; UNIQUE `(subject_type, subject_id, subject_revision_id, active_guard)` |

### 1.5 `workflow_tasks` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `workflow_instance_id` | fk | ✗ | → `workflow_instances` |
| `workflow_step_id` | fk | ✗ | → `workflow_steps` |
| `activation_no` | sint | ✗ | Rework döngüsü; UNIQUE `(workflow_instance_id, workflow_step_id, activation_no)` |
| `assignee_personnel_id` | fk | ✓ | → `personnel` |
| `resolved_role_snapshot` | code32 | ✓ | |
| `task_id` | fk | ✓ | → `tasks`; görev kutusu yansıması |
| `status` | status | ✗ | [`pending`, `active`, `completed`, `rejected`, `skipped`, `cancelled`, `expired`] |
| `activated_at` | ts | ✓ | |
| `due_at` | ts | ✓ | |
| `completed_at` | ts | ✓ | |
| `completed_by_personnel_id` | fk | ✓ | → `personnel` |
| `outcome_code` | code32 | ✓ | |

## 2. Ortak onay altyapısı (ERD-11 §13.1, 03 §14)

Bu set teklif, sözleşme, devir, gate, departman devri, satın alma, ödeme, rapor, DMS, İK ve sosyal içerik tarafından ortak kullanılır; domain başına ikinci onay tablosu kurulmaz.

### 2.1 `approval_policies` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `subject_type` | code32 | ✗ | Registry |
| `current_version_id` | fk | ✓ | → `approval_policy_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 2.2 `approval_policy_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_policy_id` | fk | ✗ | → `approval_policies` |
| `version_no` | int | ✗ | UNIQUE `(approval_policy_id, version_no)` |
| `mode` | status | ✗ | [`sequential`, `parallel`, `quorum`] |
| `quorum_count` | tint | ✓ | `quorum` için |
| `requires_maker_checker` | bool | ✗ | Talep sahibi kendi son onayını veremez |
| `reapproval_on_change` | bool | ✗ | Subject hash değişirse yeniden onay |
| `applies_min_amount` | money | ✓ | Tutar eşiği |
| `applies_max_amount` | money | ✓ | |
| `currency_code` | currency | ✓ | → `currencies` |
| `risk_level` | code32 | ✓ | [`normal`, `high`, `crisis`] eşleşmesi |
| `sla_minutes` | int | ✓ | |
| `escalation_notification_rule_id` | fk | ✓ | → `notification_rules` |
| `definition_hash` | hash | ✓ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 2.3 `approval_steps` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_policy_version_id` | fk | ✗ | → `approval_policy_versions` |
| `step_code` | code32 | ✗ | UNIQUE `(approval_policy_version_id, step_code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `sequence_no` | sint | ✗ | |
| `resolver_type` | status | ✗ | [`personnel`, `position`, `line_manager`, `org_unit_manager`, `functional_manager`, `project_role`, `functional_area_role`, `executive`, `rbac_role`] |
| `resolver_target_id` | fk | ✓ | |
| `role_code` | code32 | ✓ | |
| `decision_rule` | status | ✗ | [`any_one`, `all`, `majority`] |
| `is_optional` | bool | ✗ | |
| `allows_delegation` | bool | ✗ | |
| `sla_minutes` | int | ✓ | |

### 2.4 `approval_requests` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_policy_version_id` | fk | ✗ | → `approval_policy_versions` |
| `workflow_task_id` | fk | ✓ | → `workflow_tasks` |
| `subject_type` | code32 | ✗ | Registry |
| `subject_id` | fk | ✗ | |
| `subject_revision_id` | fk | ✓ | Exact sürüm |
| `subject_hash` | hash | ✗ | Karar anında yeniden doğrulanır |
| `amount` | money | ✓ | Politika eşiği için |
| `currency_code` | currency | ✓ | → `currencies` |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `personnel_id` | fk | ✗ | → `personnel` |
| `requested_at` | ts | ✗ | |
| `current_step_sequence` | sint | ✗ | |
| `status` | status | ✗ | [`pending`, `in_progress`, `approved`, `rejected`, `cancelled`, `expired`, `invalidated`] |
| `decided_at` | ts | ✓ | |
| `invalidation_reason` | code32 | ✓ | [`hash_mismatch`, `subject_withdrawn`, `policy_superseded`] |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN status IN ('pending','in_progress') THEN 1 END`; UNIQUE `(subject_type, subject_id, subject_revision_id, active_guard)` |

### 2.5 `approval_request_steps` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_request_id` | fk | ✗ | → `approval_requests` |
| `approval_step_id` | fk | ✗ | → `approval_steps` |
| `sequence_no` | sint | ✗ | |
| `personnel_id` | fk | ✓ | → `personnel` |
| `resolved_role_snapshot` | code32 | ✗ | |
| `resolution_status` | status | ✗ | [`resolved`, `unresolved`] |
| `unresolved_reason` | code32 | ✓ | [`vacant_position`, `inactive_user`, `no_manager`, `no_role_holder`, `sod_conflict`] |
| `status` | status | ✗ | [`waiting`, `active`, `approved`, `rejected`, `skipped`, `expired`] |
| `activated_at` | ts | ✓ | |
| `due_at` | ts | ✓ | |
| `decided_at` | ts | ✓ | |

UNIQUE `(approval_request_id, approval_step_id, personnel_id)`.

### 2.6 `approval_decisions` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_request_step_id` | fk | ✗ | → `approval_request_steps` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `on_behalf_of_personnel_id` | fk | ✓ | → `personnel`; vekâletle karar |
| `decision` | status | ✗ | [`approved`, `rejected`, `returned`, `abstained`] |
| `comment` | text | ✓ | `rejected`/`returned` için zorunlu (servis) |
| `approved_subject_hash` | hash | ✗ | Karar anındaki exact hash |
| `channel` | status | ✗ | [`ui`, `api`, `external_service`] |
| `decided_at` | ts | ✗ | |

### 2.7 `delegation_snapshots` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `approval_decision_id` | fk | ✗ | → `approval_decisions`; UNIQUE (1:1) |
| `source_delegation_id` | fk | ✗ | → `delegations` |
| `grantor_personnel_id` | fk | ✗ | → `personnel` |
| `delegate_personnel_id` | fk | ✗ | → `personnel` |
| `scope_snapshot` | short | ✗ | |
| `valid_from_snapshot` | ts | ✗ | |
| `valid_until_snapshot` | ts | ✗ | |

## 3. Personel Hareketleri (ERD-11 §13.2)

Kullanıcı kararı D-47 (5 Eylül 2026): ayrı durum değişikliği tablosu (`status_changes`) ve giden kutusu tabloları (`outbox_messages`, `outbox_deliveries`) kaldırılmıştır. Olay ve durum geçmişinin tek kaynağı **Personel Hareketleri**'dir; ertelenen başlıklar [02 §11](02-modul-bazli-ilerleme-plani.md) listesindedir.

### 3.1 `personnel_activities` — **Personel Hareketleri** — A, sınıf C

Arayüzdeki adı kalıcı olarak **Personel Hareketleri**'dir (D-44). Hash zinciri kaldırılmıştır; IP adresi açık tutulur.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `id` | id | ✗ | |
| `personnel_id` | fk | ✓ | → `personnel`; işlemi yapan personel. Uygulamanın kendi yaptığı işte boş kalır ve arayüzde "Sistem" gösterilir (D-48). |
| `subject_type` | code32 | ✗ | Registry (`reference_types`) |
| `subject_id` | ascii64 | ✗ | İlgili kaydın anahtarı |
| `action_code` | code | ✗ | Aksiyon kataloğu (`personel.olusturuldu`, `personel.durum_degisti` …); arayüzde Türkçe cümleye çevrilir |
| `channel` | status | ✗ | [`panel`, `scheduled`, `integration`, `import`] |
| `changes` | json | ✓ | Alan bazlı önceki/yeni değer; parola, API anahtarı ve token değerleri maskelenir |
| `ip_address` | ascii45 | ✓ | |
| `occurred_at` | ts | ✗ | |

Tablo yalnız eklenir; güncelleme ve silme uygulama ve rol seviyesinde engellenir.

## 4. Entegrasyon altyapısı — **kaldırıldı** (D-56)

Kullanıcı kararı D-56 (5 Eylül 2026): entegrasyon arayüzde yönetilen bir özellik değildir, kod içinde yapılır. `integration_endpoints`, `integration_connections`, `integration_connection_capabilities`, `external_id_mappings`, `inbox_messages`, `sync_attempts` ve `sync_errors` tabloları ile bunlara ait ekranlar, modeller ve çeviriler projeden çıkarılmıştır.

Kullanıcı tek bir sistem kullandığını düşünür; arka planda başka bir projeye bağlanıldığını yalnız yazılımcı bilir. Bağlantı bilgisi gerektiğinde `.env` veya `config` üzerinden okunur. Ayrıntı: [02 §11 E-08](02-modul-bazli-ilerleme-plani.md).
## 5. Kontrollü genel referans registry'si (03 §14, 05 §15.7)

### 5.1 `reference_type_registry` — R, sınıf I, +S1 +S2

Polymorphic/general reference yalnız yatay altyapıda kullanılır; bilinmeyen `target_type` reddedilir. Migration'daki `CHECK` listeleri bu registry'nin onaylı sürümünden üretilir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `target_type` | code32 | ✗ | UNIQUE; tablo adının tekil kodu (`report_answer`, `social_content_version` …) |
| `table_name` | code | ✗ | Fiziksel tablo |
| `owning_domain` | code32 | ✗ | Bounded context |
| `min_classification_id` | fk | ✗ | → `security_classifications` |
| `existence_validator` | code | ✗ | Uygulama servis anahtarı |
| `registry_version` | sint | ✗ | |
| `status` | status | ✗ | [`active`, `retired`] |

### 5.2 `reference_type_usages` — R, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `target_type` | code32 | ✗ | → `reference_type_registry` |
| `usage_context` | status | ✗ | [`document_link`, `message_link`, `email_link`, `activity`, `workflow_subject`, `approval_subject`, `external_action_target`, `notification_subject`, `task_context`, `generated_output_source`, `transition_item`, `report_schedule_target`, `stock_reservation`, `inventory_source`, `project_change_source`]; UNIQUE `(target_type, usage_context)` |

Başlangıç registry'si: `personnel`, `org_unit`, `position`, `team`, `functional_area`, `functional_area_responsibility`, `report_assignment`, `report_submission`, `report_submission_version`, `report_answer`, `notification_instance`, `notification_recipient`, `business_alert`, `task`, `document`, `document_revision`, `message`, `conversation`, `inbound_email_message`, `social_account`, `social_content_item`, `social_content_version`, `social_content_target`, `social_publication`, `special_day_plan`, `party`, `business_case`, `tender_notice`, `proposal`, `proposal_version`, `contract_version`, `operation_handoff_version`, `project`, `project_workstream`, `project_stage_instance`, `department_handoff_version`, `work_package`, `purchase_requisition`, `purchase_requisition_line`, `purchase_order_version`, `award_recommendation`, `goods_receipt_line`, `stock_transfer_line`, `stock_count_line`, `stock_reservation`, `service_work_order`, `supplier_invoice`, `payment_request`, `budget_version`, `schedule_baseline`, `project_change`, `project_issue`, `project_decision`, `commercial_clarification`, `delay_event`, `technical_requirement`, `engineering_deliverable`, `inspection`, `ncr`, `incident`, `test_execution`, `commissioning_package`, `leave_request`, `expense_claim`, `timesheet`, `workforce_request`, `stock_count`, `external_analysis_request`, `external_action_request`.

## 6. Harici analiz servisi metadata sınırı (ERD-11 §13.3, 03 §13)

### 6.1 `external_capabilities` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE; ilk kayıt `report.standard_control` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `description` | text | ✓ | Model/prompt/provider bilgisi içermez |
| `max_input_classification_id` | fk | ✗ | → `security_classifications`; gönderilebilir en yüksek sınıf |
| `request_schema_version` | code32 | ✗ | |
| `result_schema_version` | code32 | ✗ | |
| `default_timeout_seconds` | int | ✗ | |
| `max_retries` | tint | ✗ | |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `status` | status | ✗ | [`draft`, `active`, `suspended`, `retired`] |

### 6.2 `external_capability_actions` — M, sınıf I, +S1 +S2

Capability'nin üretebileceği aksiyon kataloğu (sözlüğe eklendi).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `capability_id` | fk | ✗ | → `external_capabilities` |
| `action_code` | code32 | ✗ | [`mark_compliant`, `show_advisory_findings`, `revision_required`, `notify_employee`, `notify_manager`, `create_follow_up_task`, `mark_external_check_passed`]; UNIQUE `(capability_id, action_code)` |
| `is_enabled_default` | bool | ✗ | |
| `requires_human_approval` | bool | ✗ | |
| `status` | status | ✗ | [`active`, `disabled`] |

Final fiyat, ödeme, işe alma/çıkarma, İSG izni ve enerjilendirme gibi yüksek etkili komutlar bu katalogda yer almaz; eklenmesi ayrı açık karar gerektirir.

### 6.3 `service_capability_grants` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `capability_id` | fk | ✗ | → `external_capabilities` |
| `scope_type` | status | ✗ | [`organization`, `org_unit`, `report_template`, `functional_area`, `project`] |
| `scope_id` | fk | ✓ | `organization` dışında zorunlu |
| `scope_snapshot` | short | ✗ | |
| `granted_by_personnel_id` | fk | ✗ | → `personnel` |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `status` | status | ✗ | [`active`, `suspended`, `revoked`, `expired`] |

UNIQUE `(capability_id, scope_type, scope_id, valid_from)`.

### 6.4 `service_capability_grant_actions` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `grant_id` | fk | ✗ | → `service_capability_grants` |
| `action_code` | code32 | ✗ | `external_capability_actions.action_code`; UNIQUE `(grant_id, action_code)` |
| `is_allowed` | bool | ✗ | |
| `requires_human_approval` | bool | ✗ | Capability varsayılanını yalnız sıkılaştırabilir |

### 6.5 `external_analysis_requests` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `capability_id` | fk | ✗ | → `external_capabilities` |
| `grant_id` | fk | ✗ | → `service_capability_grants` |
| `requester_personnel_id` | fk | ✗ | → `personnel` |
| `subject_type` | code32 | ✗ | [`report_submission_version`, `document_revision`] |
| `subject_id` | fk | ✗ | |
| `report_template_version_id` | fk | ✓ | → `report_template_versions` |
| `external_check_mode_snapshot` | status | ✓ | [`advisory`, `revision_required`] |
| `locale` | locale | ✗ | |
| `source_set_hash` | hash | ✗ | Gönderilen artifact setinin hash'i |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `correlation_id` | fk | ✗ | |
| `callback_nonce` | key32 | ✓ | Replay koruması; UNIQUE (NULL hariç) |
| `external_job_id` | short | ✓ | |
| `status` | status | ✗ | [`draft`, `queued`, `sent`, `processing`, `succeeded`, `failed`, `retrying`, `expired`, `cancelled`] |
| `queued_at` | ts | ✓ | |
| `sent_at` | ts | ✓ | |
| `timeout_at` | ts | ✓ | |
| `completed_at` | ts | ✓ | |
| `safe_error_code` | code32 | ✓ | |

### 6.6 `external_analysis_artifacts` — M, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_id` | fk | ✗ | → `external_analysis_requests` |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `report_submission_version_id` | fk | ✓ | → `report_submission_versions` |
| `artifact_role` | status | ✗ | [`primary`, `reference`, `template_definition`] |
| `artifact_hash` | hash | ✗ | |
| `classification_snapshot_id` | fk | ✗ | → `security_classifications`; capability üst sınırını aşamaz |

`CHECK` XOR: document/report kaynaklarından tam biri dolu; UNIQUE `(request_id, document_revision_id, report_submission_version_id, artifact_role)`.

### 6.7 `external_analysis_attempts` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_id` | fk | ✗ | → `external_analysis_requests` |
| `attempt_no` | tint | ✗ | UNIQUE `(request_id, attempt_no)` |
| `started_at` | ts | ✗ | |
| `finished_at` | ts | ✓ | |
| `duration_ms` | int | ✓ | |
| `outcome` | status | ✗ | [`sent`, `accepted`, `rejected`, `timeout`, `failed`] |
| `http_status` | sint | ✓ | |
| `safe_error_code` | code32 | ✓ | |

### 6.8 `external_analysis_results` — M (alındıktan sonra immutable; `is_selected` hariç), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `request_id` | fk | ✗ | → `external_analysis_requests` |
| `result_no` | int | ✗ | UNIQUE `(request_id, result_no)` |
| `schema_version` | code32 | ✗ | |
| `external_run_reference` | short | ✓ | Harici run/version referansı |
| `result_code` | status | ✗ | [`compliant`, `advisory_findings`, `revision_required`, `inconclusive`, `error`] |
| `structured_result` | json | ✗ | Bulgular, uyarılar, önerilen aksiyonlar |
| `summary` | text | ✓ | |
| `provider_confidence` | ratio | ✓ | |
| `result_hash` | hash | ✗ | |
| `callback_signature_verified` | bool | ✗ | |
| `received_at` | ts | ✗ | |
| `is_selected` | bool | ✗ | |
| `selected_guard` | fk (generated) | ✓ | `CASE WHEN is_selected = 1 THEN request_id END`; UNIQUE |

### 6.9 `external_analysis_result_references` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `result_id` | fk | ✗ | → `external_analysis_results` |
| `artifact_id` | fk | ✗ | → `external_analysis_artifacts` |
| `locator_type` | status | ✗ | [`page`, `section`, `field_key`, `row_key`, `line`] |
| `locator_value` | short | ✗ | |
| `finding_code` | code32 | ✓ | |
| `severity` | status | ✓ | [`info`, `warning`, `error`] |
| `note` | text | ✓ | |

### 6.10 `external_action_requests` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `result_id` | fk | ✗ | → `external_analysis_results` |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `command_code` | code32 | ✗ | Grant allowlist'inde olmalı (servis) |
| `target_type` | code32 | ✗ | Registry |
| `target_id` | fk | ✗ | |
| `payload` | json | ✓ | Minimum alanlar |
| `payload_hash` | hash | ✗ | |
| `proposed_at` | ts | ✗ | |
| `status` | status | ✗ | [`proposed`, `awaiting_human_approval`, `approved`, `executing`, `executed`, `rejected`, `failed`, `expired`] |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `decision_reason` | text | ✓ | |

### 6.11 `external_action_executions` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `action_request_id` | fk | ✗ | → `external_action_requests`; UNIQUE (tek icra) |
| `audit_event_id` | fk | ✗ | → `personnel_activities` |
| `outcome` | status | ✗ | [`succeeded`, `failed`, `no_op`] |
| `resulting_type` | code32 | ✓ | Üretilen kayıt türü (`task`, `notification_instance`) |
| `resulting_id` | fk | ✓ | |
| `executed_at` | ts | ✗ | |
| `safe_error_code` | code32 | ✓ | |

## 7. Framework tabloları (F sınıfı)

Laravel 13 / Filament 5 standart tabloları korunur; yalnız kimlik tipi AUTO_INCREMENT `BIGINT UNSIGNED` ile hizalanır (D-15).

| Tablo | Not |
|---|---|
| `migrations` | Framework; değiştirilmez |
| `personnel` | Bölüm 06 §3.1'de iş kolonlarıyla birlikte tanımlı |
| `password_reset_tokens` | `email` PK; framework şeması |
| `sessions` | `user_id` → `BIGINT UNSIGNED` NULL; production'da Redis session kullanılırsa yalnız dev/staging |
| `cache`, `cache_locks` | Production'da Redis; tablolar dev/staging fallback |
| `jobs`, `job_batches`, `failed_jobs` | Production'da Redis queue; `failed_jobs` her ortamda tutulur |
| `notifications` | Laravel/Filament veritabanı bildirim kanalı; `id BIGINT UNSIGNED` framework varsayılanı korunur, `notifiable_type VARCHAR(255)` + `notifiable_id BIGINT UNSIGNED` (D-12) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Yalnız D-16 Spatie seçeneğinde; morph anahtar `BIGINT UNSIGNED` |
| `activity_log` | Yalnız Spatie Activitylog onaylanırsa; iş audit'i `personnel_activities`'te kalır |

## 8. Bu bölümün DB-G6/DB-G8 kontrol listesi

- ERD-11 tabloları eşlendi; eklenen tablolar: `external_capability_actions`, `service_capability_grant_actions`, `reference_type_registry`, `reference_type_usages`; eklenen kolonlar: `workflow_tasks.activation_no`, `approval_requests.active_guard`, `personnel_activities.changes` ve `personnel_activities.ip_address` (D-44).
- Açık kararlar (03 §19 madde 3, 6, 11; 05 §16 madde 11–13, 15): harici endpoint/auth/callback sözleşmesi (D-26), AI'ya gönderilebilir alan sınıfları, workflow paralel/quorum/vekâlet semantiği, bildirim resolver kuralları, Zirve/dosya sunucusu entegrasyon sözleşmeleri.
- Dış servis DB'ye doğrudan erişmez; runtime uygulama rolü dışında dış servis için DB hesabı açılmaz.
