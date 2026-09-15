# Konelsis Kurumsal Platform — Migration üretim sırası ve DBA/DevOps teslim paketi

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. 7 Eylül 2026'da B03 (D-62), B05 (D-63), B06 (D-66) ve B16/B17 (D-67), 8 Eylül 2026'da B17A (D-68), 10 Eylül 2026'da B06A (D-75), B07 (D-76) ve B13A (D-80), 11 Eylül 2026'da B03A (D-81), B11A (D-82), B12A (D-83) ve B11B (D-84) yazıldı. Bu belge hiçbir şema komutu çalıştırmaz; dosyalar yazıldı, uygulanması kullanıcıdadır.  
**Sürüm:** 1.5 / 15 Eylül 2026 — B26 (personel unvanları, D-88) eklendi.  
**Kaynak:** [01 §3, §18–19](01-teknik-mimari-plani.md), [02 M01](02-modul-bazli-ilerleme-plani.md), [03 §2, §17–18](03-veri-tabani-tasarim-plani.md), sözlük 06–12, matris 13

## 1. Kapsam ve değişmez kurallar

- Kodlama ajanları (Codex, Claude Code) DB-G8 onayından sonra yalnız kullanıcının yetkilendirdiği kapsam için migration **dosyası** hazırlar; hiçbir zaman `migrate`, `migrate:*`, `db:wipe`, reset/refresh/rollback, raw DDL, seed veya test çalıştırmaz.
- Şema değişikliğini yalnız atanmış DBA/DevOps (D-40) dış süreçte, bu belgedeki paketle uygular ve teyit eder; teyit gelmeden özellik açılmaz.
- Üretim ana veritabanı MySQL 8.4 LTS/InnoDB'dur; SQLite yalnız mevcut yerel iskeletin geçici veritabanıdır; PostgreSQL hedef değildir.
- Production'da uygulama açılışında otomatik şema değişikliği çalışmaz; kod dağıtımı ile şema aktivasyonu feature flag ile ayrılır.
- Expand/contract: yeni kolon/tablo önce eklenir (expand), uygulama sürümü dağıtılır, eski yapı sonraki sürümde kaldırılır (contract). Destructive işlem yalnız contract adımında, DBA onayıyla ve backup/PITR doğrulandıktan sonra.

## 2. MySQL 8.4 ortam ve bağlantı standardı (DB-G7 önerisi)

| Konu | Öneri | Not |
|---|---|---|
| Sürüm | MySQL 8.4 LTS, exact patch DBA tarafından sabitlenir; dev/staging/production aynı minör aile | D-06 |
| Engine / row format | `InnoDB`, `ROW_FORMAT=DYNAMIC`, `innodb_file_per_table=ON` | |
| Charset / collation | `utf8mb4`; DB varsayılanı `utf8mb4_0900_ai_ci`; Türkçe ad sıralaması `utf8mb4_tr_0900_ai_ci`; kod/handle/normalize e-posta `utf8mb4_0900_as_cs` | D-07 test seti ile dondurulur |
| SQL mode | `STRICT_TRANS_TABLES, NO_ZERO_DATE, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION, ONLY_FULL_GROUP_BY` (8.4 varsayılanı korunur) | |
| Timezone | Sunucu ve bağlantı `time_zone = '+00:00'`; uygulama `config('app.timezone') = UTC` (mevcut) | |
| Kimlik doğrulama | `caching_sha2_password`; `mysql_native_password` 8.4'te varsayılan kapalı | |
| Bağlantı | TLS zorunlu; `max_connections` D-05 hacmine göre; Laravel PDO `PDO::ATTR_EMULATE_PREPARES = false` | |
| InnoDB | `innodb_buffer_pool_size` (RAM'in %60–70'i), `innodb_flush_log_at_trx_commit=1`, `innodb_lock_wait_timeout=10` (deadlock retry uygulama tarafında bounded), `innodb_print_all_deadlocks=ON` (staging) | |
| Binlog | `ROW` format, PITR için `expire_logs_days`/`binlog_expire_logs_seconds` ≥ 7 gün | Backup/PITR D-06 |
| Büyük paket | `max_allowed_packet` JSON snapshot'lar için ≥ 64 MB | |
| Yedek | Günlük full + binlog ile PITR; restore provası aylık; şifreli yedek | Kurum operasyon ekibi |

Laravel tarafı (yalnız belgeleme; dosya değiştirilmez): `config/database.php` `mysql` bağlantısı `charset => utf8mb4`, `collation => utf8mb4_0900_ai_ci`, `strict => true`, `modes` yukarıdaki liste; `.env` production'da `DB_CONNECTION=mysql`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`.

## 3. Kimlik tipi geçiş stratejisi (D-15)

Mevcut iskelet: `personnel.id BIGINT AUTO_INCREMENT`, `sessions.user_id` `foreignId`, SQLite dev veritabanı (test verisi dışında iş verisi yok).

Öneri:

1. MySQL şeması sıfırdan bu paketle kurulur; SQLite dosyası ve mevcut `0001_01_01_*` migration'ları taşınmaz.
2. DB-G8 sonrası, kullanıcı yetkilendirmesiyle, `personnel`/`password_reset_tokens`/`sessions` baseline migration'ı AUTO_INCREMENT `BIGINT UNSIGNED` kimlikle **yeniden yazılır** (henüz üretim şeması olmadığı için expand/contract gerekmez). Bu yeniden yazım da bir migration dosyası değişikliğidir ve yalnız DBA tarafından uygulanır.
3. `cache`, `jobs`, `job_batches`, `failed_jobs` framework migration'ları korunur; production'da Redis kullanılacağı için `cache`/`jobs` tabloları yalnız fallback amaçlıdır.
4. Kimlikler veritabanı tarafından üretilen klasik `BIGINT UNSIGNED AUTO_INCREMENT` değerlerdir; uygulama tarafında ek bir dönüşüm veya değer nesnesi yoktur.
5. Kodlama ajanları mevcut SQLite dosyasını silmez, sıfırlamaz veya üzerinde migration çalıştırmaz.

## 4. Migration üretim sırası

Her batch tek bir Laravel migration dosyası grubudur (tablo başına bir dosya veya batch başına bir dosya D-39 ile birlikte kararlaştırılır). Batch içi tablo sırası FK bağımlılığına göredir; karşılıklı FK'lar batch sonunda `ALTER TABLE ... ADD CONSTRAINT` ile eklenir. Dalga eşlemesi [02](02-modul-bazli-ilerleme-plani.md) modüllerine göredir.

| Batch | Dalga/modül | Tablolar (sırayla) | Batch sonu ALTER |
|---|---|---|---|
| **B00** Framework | M01 | `migrations` (framework), `personnel` (kimlik baseline), `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `notifications` (framework; `notifiable_id BIGINT UNSIGNED`) | — |
| **B01** Referans | M01 | `countries`, `currencies`, `units_of_measure`, `security_classifications`, `retention_policies`, `organizations`, `legal_entities`, `business_calendars`, `business_calendar_weekdays`, `calendar_days` | `units_of_measure.base_unit_id` self FK |
| **B02** Yetkinlik | M01 | `competencies`, `personnel_competencies` | `*.created_by_personnel_id` → `personnel` (bootstrap NULL). `departments` D-62 ile kaldırıldı, yerini B03'teki `org_units` aldı. |
| **B03** Organizasyon/pozisyon | M02 | `org_units`, `org_unit_relations`, `positions`, `position_assignments`, `reporting_relationships` | D-62 ile 7 Eylül 2026'da teslim edildi. `personnel`, `personnel_private_profiles`, `employments`, `teams`, `team_memberships`, `delegations`, `personnel_status_histories` bu batch'te değil (personel zaten B00'da tek tablo — D-42; personnel_status_histories yerine personnel_activities — D-44; geri kalanı kapsam dışı, ayrı yetkilendirme ister). İki kasıtlı sadeleştirme: `org_units.manager_personnel_id` denormalize kolon (canonical'da yok); `org_unit_relations`/`reporting_relationships` ekranda düzenlenmez, servis katmanı "üst birim"/"doğrudan amir" seçimini otomatik yazar. |
| **B25** Organizasyon birimi ve görev geçmişi | M02 | `personnel_assignments` | D-61 ile kuruldu (o zaman departman+doğrudan amir birlikteydi); D-62 ile doğrudan amir kısmı B03'teki `reporting_relationships`'e taşındı, bu tablo yalnız org birimi+görev geçmişi için kaldı. |
| **B26** Personel unvanları | M02 | `personnel_titles`; `personnel.title_id` | D-88 ile 15 Eylül 2026'da yazıldı (`2026_09_15_100000_b26_create_personnel_titles.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B26` ekler.** "Görev" (`positions.title`/`personnel.job_title`, departmana bağlı) ile "unvan" (departmandan bağımsız, şirket genelinde tekrar eden kademe adı — Sorumlu, Müdür, Grup Müdürü…) kasıtlı olarak ayrıdır. Uygulanana kadar `title_id` boş kalır; seed dosyaları (`RealOrganizationSeeder`, `RealPersonnelSeeder`) sütun yoksa unvanı atlar. |
| **B04** Functional Area | M02/SM01 | `functional_areas`, `functional_area_translations`, `functional_area_role_definitions`, `functional_area_role_capabilities`, `functional_area_responsibilities`, `functional_area_org_unit_bindings`, `functional_area_transition_events`, `functional_area_transition_items` | `fate.decision_document_revision_id` B06 sonrası |
| **B05** RBAC (D-16) | M03 | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (spatie/laravel-permission) | D-63 ile 7 Eylül 2026'da teslim edildi; onay altyapısı (B07) bu turda değil, ayrı bir sonraki adım |
| **B06** DMS (tam kanonik tasarım) | M04 | `file_objects`, `document_types`, `documents`, `document_revisions`, `document_revision_files`, `document_links`, `document_reviews`, `document_distributions`, `document_acknowledgements`, `transmittals`, `transmittal_items`, `document_templates`, `document_template_versions`, `generated_outputs`, `legal_holds`, `legal_hold_documents` | D-66 ile 7 Eylül 2026'da teslim edildi (minimum çekirdek yerine 16 tablonun tamamı). `documents(id, current_revision_id)` composite'i `document_revisions` kurulduktan sonra ayrı `ALTER` ile çözüldü. `document_revisions.approval_request_id`, `document_reviews.approval_request_id` (M03/B07 onay motoru), `documents.project_id`/`functional_area_id`, `transmittals.project_id`/`recipient_party_id` (B04 tamamlanınca) hâlâ ertelenmiş FK. |
| **B06A** DMS yazma + paylaşım | M04 | `document_revisions.content_kind`, `document_revisions.body_html`; `document_shares` | D-75 ile 10 Eylül 2026'da yazıldı (`2026_09_10_100000_b06a_dms_authoring_and_sharing.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B06A` ekler.** Uygulanana kadar sistemde yazma ve paylaşım alanları gizli kalır; dosya yükleme bugün de çalışır. Şifre/yetki kolonları bilinçli olarak yok (02 §11 E-09). |
| **B13A** Sertifika geçerlilik tarihi | M07 | `certifications.valid_until` | D-80 ile 10 Eylül 2026'da yazıldı (`2026_09_10_100200_b13a_certifications_valid_until.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B13A` ekler.** Kullanıcı kararı: "Geçerlilik (ay)" girdisi tarih olur. Uygulanana kadar form/tablo eski ay alanını gösterir; uygulanınca tarih alanı gelir, `validity_months` kolonu kaldırılmaz (boş kalır). |
| **B03A** Pozisyon ↔ rol bağı | M02/M03 | `positions.role_id` (UK, FK → `roles`, ON DELETE SET NULL) | D-81 ile 11 Eylül 2026'da yazıldı (`2026_09_11_100000_b03a_positions_role_link.php`); **ön koşul B05; DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B03A` ekler, ardından `php artisan konelsis:roles:sync-positions` çalıştırır.** Her pozisyonun kendi Shield rolü (adı = pozisyon başlığı) `PositionRoleSync` ile üretilir; pozisyon sahibi rolü otomatik alır/kaybeder. Uygulanana kadar pozisyon rolü üretilmez. |
| **B11A** Bildirim, ilk dilim | M06 | `announcements`, `business_alerts` (07 §5.4 alt kümesi: `owner_personnel_id` NULL olabilir, ack/çözüm kolonları tablo içinde) | D-82 ile 11 Eylül 2026'da yazıldı (`2026_09_11_100100_b11a_announcements_and_business_alerts.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B11A` ekler.** Ön koşul B02, B03, B17. Teslimat kanalı Filament `notifications` (D-49); tam B11 kural motoru (`notification_rules`…`preferences`, `tasks`) ertelendi. Uygulanana kadar pano widget'ları, "Bildirim gönder" eylemi ve son tarih taraması pasif kalır. |
| **B12A** Kurum içi sohbet | M07 | `conversations`, `conversation_memberships` (+`pinned_at`), `messages` (`body`, `link_url`), `message_attachments`, `message_hides` (kanonik dışı ek), `conversation_read_cursors` | D-83 ile 11 Eylül 2026'da yazıldı (`2026_09_11_100200_b12a_create_chat_tables.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B12A` ekler.** Ön koşul B01, B02, B06. `message_versions`, `message_mentions`, `message_business_links` ve e-posta tabloları (08 §3) bu dilimde yok. Uygulanana kadar sağ alttaki sohbet düğmesi görünmez. |
| **B11B** Talepler | M06 | `work_requests` (07 §5.1 `tasks` talep dilimi: `target_kind` kişi/birim, `requester_org_unit_id`, ilgili kayıt FK'ları proje/müşteri/bileşen/teklif/iş dosyası/sözleşme/belge, `source_message_id`) | D-84 ile 11 Eylül 2026'da yazıldı (`2026_09_11_100300_b11b_create_work_requests.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B11B` ekler.** Ön koşul B02, B03, B06, B16, B17; `messages` (B12A) varsa `source_message_id` FK'sı da kurulur, yoksa kolon FK'sız kalır. `task_assignments`/`task_dependencies` yok (tek sorumlu). Uygulanana kadar "Talepler" menüsü ve sohbetteki "Talep aç" düğmesi görünmez. |
| **B10A** Raporlar (kod tanımlı taslak) | M05A | `reports`, `report_items`, `report_metrics` (07 §1–3 DB şablon modeli yerine; taslaklar `App\Reports\Templates`) | D-86 ile 12 Eylül 2026'da yazıldı (`2026_09_12_100000_b10a_create_reports.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B10A` ekler, ardından `ReferenceTypeRegistrySeeder` (report, report_item) ve `shield:generate` (Review / ViewConfidential / AuthorHrEvaluation izinleri) çalıştırır.** Ön koşul B00, B03, B16, B17. Uygulanana kadar Raporlar menüsü ve kayıt kartlarındaki Raporlar sekmesi gizlidir. |
| **B11D** Onaya tabi talep | M06 | `work_requests.requires_approval`, `approver_personnel_id`, `approval_request_id`; `work_requests.status` CHECK'ine `awaiting_approval`; `approval_steps.resolver_type` CHECK'ine `designated_approver` | D-87 ile 12 Eylül 2026'da yazıldı (`2026_09_12_100100_b11d_work_request_approval.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B11D` ekler, ardından `ApprovalPolicySeeder` (WORK_REQUEST_DESIGNATED) çalıştırır.** Ön koşul B11B, B07. Uygulanana kadar talep formunda "Onaya tabi talep" alanı görünmez ve tamamlama doğrudan kapatır. |
| **B11C** Personel kontrolü | M06A | `obligation_policies`, `obligation_policy_versions`, `obligation_policy_steps`, `personnel_obligations`, `obligation_assessments`, `obligation_escalations`, `obligation_reports`, `obligation_extensions`, `personnel_compliance_scores` (07 §5.7–5.15) | **Planlandı (11 Eylül 2026, D-85); henüz yazılmadı, uygulama ayrı yetkilendirme ister.** Ön koşul B00, B03, B06, B08, B11A. `external_analysis_requests` (B14) ve `approval_requests` (B07) varsa FK'lar kurulur, yoksa kolonlar FK'sız kalır. Uygulanana kadar "Personel Kontrolü" ve "Yükümlülük Politikaları" ekranları ile pano geri sayımı görünmez; kaynak modüller yükümlülük kaydı üretmez. |
| **B07** Onay motoru | M03 | `approval_policies`, `approval_policy_versions`, `approval_steps`, `approval_requests`, `approval_request_steps`, `approval_decisions`, `delegations`, `delegation_snapshots` (+ `document_revisions.approval_request_id`, `document_reviews.approval_request_id`) | D-76 ile 10 Eylül 2026'da yazıldı (`2026_09_10_100100_b07_create_approval_tables.php`); **DBA uygulayıp `KONELSIS_APPLIED_SCHEMA_BATCH`'e `B07` ekler.** `approval_policies.current_version_id` composite FK ALTER ile; `approval_requests.active_guard` generated. Bu batch'te olmayan (ertelenen): workflow tabloları `workflow_definitions`, `workflow_definition_versions`, `workflow_steps`, `workflow_instances`, `workflow_tasks` ve `approval_requests.workflow_task_id` FK'sı (tüketicisi yok); `approval_policy_versions.escalation_notification_rule_id` FK'sı (B11); teklif/sözleşme/devir/gate tablolarının `approval_request_id` kolonları (tüketici bağlanınca ALTER). |
| **B08** Personel Hareketleri ve registry | M01 | `reference_types`, `reference_type_usages`, `personnel_activities` | Giden kutusu ve ayrı durum değişikliği tablosu kaldırıldı (D-47) |
| **B10** Raporlama | M05 | `report_templates`, `report_template_versions`, `report_sections`, `report_questions`, `report_question_options`, `report_workflow_bindings`, `report_schedules`, `report_schedule_weekdays`, `report_schedule_targets`, `report_periods`, `report_assignments`, `report_submissions`, `report_submission_versions`, `report_answers`, `report_answer_options`, `report_answer_documents`, `report_reviews`, `report_status_transitions`, `report_metric_facts` | Kök composite FK'ları; `rsv.external_check_request_id` B14 sonrası |
| **B11** Bildirim/görev | M06 | `notification_rules`, `notification_rule_versions`, `notification_rule_channels`, `notification_rule_recipients`, `notification_instances`, `notification_recipients`, `notification_delivery_attempts`, `notification_receipts`, `notification_acknowledgements`, `notification_escalations`, `notification_preferences`, `tasks`, `task_assignments`, `task_dependencies`, `business_alerts`, `business_alert_acknowledgements`, `business_alert_resolutions` | `report_workflow_bindings.notification_rule_version_id`; `apv.escalation_notification_rule_id`; `tasks.source_message_id` B12, `tasks.external_action_request_id` B14 sonrası; `workflow_tasks.task_id` |
| **B12** İletişim/e-posta | M07/M09 | `conversations`, `conversation_memberships`, `messages`, `message_versions`, `message_mentions`, `message_attachments`, `conversation_read_cursors`, `message_business_links`, `mailboxes`, `inbound_email_messages`, `inbound_email_recipients`, `inbound_email_attachments`, `inbound_email_business_links`, `email_processing_attempts` | `messages` composite self/current FK'ları |
| **B13** İK uzantıları | M07 | `competencies`, `personnel_competencies`, `certifications`, `personnel_certifications`, `trainings`, `training_attendances`, `leave_types`, `leave_requests`, `attendance_entries`, `timesheets`, `timesheet_lines`, `expense_claims`, `expense_lines`, `workforce_requests`, `staffing_options`, `resource_assignments`, `job_requisitions`, `job_post_versions`, `candidates`, `applications`, `interviews`, `performance_cycles`, `performance_records`, `development_actions` | Proje/WBS/CBS FK'ları B17/B18 sonrası. `certifications`/`personnel_certifications`/`trainings`/`training_attendances` D-60 ile M02'de erkene alınıp ayrı bir migration dosyasıyla teslim edildi (`2026_09_07_100400_b13_create_personnel_development_tables.php`); geri kalan tablolar M07 geldiğinde ikinci bir B13 dosyasıyla tamamlanır. |
| **B14** Harici analiz | M06 | `external_capabilities`, `external_capability_actions`, `service_capability_grants`, `service_capability_grant_actions`, `external_analysis_requests`, `external_analysis_artifacts`, `external_analysis_attempts`, `external_analysis_results`, `external_analysis_result_references`, `external_action_requests`, `external_action_executions` | Bekleyen B10/B11 FK'ları |
| **B15** Sosyal medya | SM01/SM02 | `social_platforms`, `social_accounts`, `social_account_identifier_histories`, `social_account_connections`, `social_connection_health_checks`, `social_webhook_receipts`, `social_sync_runs`, `special_day_calendars`, `special_day_definitions`, `special_day_translations`, `special_day_occurrences`, `social_reminder_policy_versions`, `social_reminder_policy_steps`, `social_reminder_step_recipients`, `social_special_day_rules`, `social_campaigns`, `social_content_items`, `social_content_versions`, `social_content_version_translations`, `social_content_assets`, `social_content_reviews`, `social_content_targets`, `social_publications`, `social_publication_attempts`, `social_publication_corrections`, `social_special_day_plans`, `social_special_day_reminder_instances`, `social_reminder_instance_recipients`, `social_metric_definitions`, `social_platform_metric_mappings`, `social_metric_ingestion_batches`, `social_account_metric_observations`, `social_publication_metric_observations`, `social_metric_sync_cursors`, `social_kpis`, `social_kpi_formula_versions`, `social_kpi_formula_inputs` | `social_content_targets.published_publication_id`; kök composite FK'ları |
| **B16** Party/İş Alım | M08–M11 | `parties`, `party_roles`, `organization_profiles`, `person_profiles`, `addresses`, `communication_points`, `contact_relationships`, `party_licenses`, `party_certificates`, `party_annual_reviews`, `business_number_allocations`, `business_cases`, `business_codes`, `opportunities`, `opportunity_stage_histories`, `business_development_activities`, `business_development_activity_participants`, `tender_sources`, `tender_notices`, `tender_notice_versions`, `tender_requirements`, `tender_deadlines`, `proposals`, `proposal_versions`, `proposal_documents`, `compliance_items`, `deviations`, `brand_items`, `responsibility_matrix_items`, `estimate_versions`, `estimate_lines`, `pricing_scenarios`, `boq_items`, `contracts`, `contract_versions`, `contract_parties`, `contract_documents`, `contract_obligations`, `contract_milestones`, `operation_handoffs`, `operation_handoff_versions`, `handoff_items`, `handoff_reviews` | D-67 ile 7 Eylül 2026'da teslim edildi (`2026_09_07_100800_b16_...`; `business_number_allocations` zaten B01'de). `business_cases(id, sequence_no)` UK + `business_codes` composite FK; kök `current_version_id`/`accepted_version_id` composite FK'ları ALTER ile; `transmittals.recipient_party_id` eklendi. Ertelenmiş FK: `bd_activities.follow_up_task_id` (B11), `proposal_versions/contract_versions/handoff_reviews.approval_request_id` (B07), `boq_items.catalog_item_id` (B18). |
| **B17** Proje orkestrasyonu | M12 | `component_definitions`, `operation_group_definitions`, `stage_templates`, `stage_template_versions`, `stage_nodes`, `stage_dependencies`, `stage_requirement_definitions`, `projects`, `project_components`, `project_workstreams`, `workstream_dependencies`, `project_focus_histories`, `wbs_nodes`, `cbs_nodes`, `wbs_cbs_mappings`, `work_packages`, `work_package_dependencies`, `project_stage_instances`, `project_stage_requirements`, `stage_evidence`, `stage_reviews`, `stage_waivers`, `department_handoffs`, `department_handoff_versions`, `department_handoff_items`, `department_handoff_reviews`, `schedule_baselines`, `milestones`, `progress_snapshots`, `project_tasks`, `project_issues`, `project_risks`, `delay_events`, `recovery_actions`, `project_changes`, `commercial_clarifications`, `commercial_exposures`, `project_decisions` | D-67 ile 7 Eylül 2026'da teslim edildi (`2026_09_07_100900_b17_...`; `project_tasks` hariç — `tasks` B11 ile birlikte kurulur, D-21). `projects(id, primary_focus_workstream_id)`, `stage_templates` current sürüm ve `department_handoffs` accepted sürüm composite FK'ları ALTER ile; `documents.project_id`, `transmittals.project_id` eklendi. Ertelenmiş FK: `stage_nodes.approval_policy_id` ve `*.approval_request_id` (B07), `stage_waivers.remediation_task_id` (B11), `progress_snapshots.report_submission_version_id` (B10), `project_decisions.source_message_id` (B12); `teams.project_id`, `business_alerts.project_id`, B13 proje/WBS/CBS FK'ları ilgili batch'lerde. |
| **B17A** Proje çalışma alanı uzantısı | M12 | `projects` (+`origin`, `legacy_reference`, `description`, `site_*` adres/koordinat kolonları; `accepted_handoff_version_id` NULL olabilir), `focus_expectations`, `project_photos`, `project_supply_items`, `project_team_members` | D-68 ile 8 Eylül 2026'da teslim edildi (`2026_09_08_100000_b17a_...`). B17'den sonra uygulanır; `KONELSIS_APPLIED_SCHEMA_BATCH` içine `B17A` eklenmeden Projeler ve Satın Alma ekranları açılmaz. `project_photos.file_object_id` → B06 `file_objects`; `project_supply_items.uom_id`/`currency_code`/`supplier_party_id` → B01/B16; composite `(project_id, workstream_id)` ve `(project_id, wbs_node_id)` FK'ları "aynı proje" garantisi verir. |
| **B18** Satın alma | M13 | `catalog_categories`, `brands`, `catalog_items`, `approved_equivalents`, `purchase_requisitions`, `purchase_requisition_lines`, `supplier_rfqs`, `supplier_rfq_lines`, `supplier_rfq_invitees`, `supplier_quotes`, `supplier_quote_versions`, `supplier_quote_lines`, `bid_comparisons`, `bid_comparison_lines`, `award_recommendations`, `purchase_orders`, `purchase_order_versions`, `purchase_order_lines`, `delivery_schedules` | Kök composite FK'ları; `boq_items.catalog_item_id` |
| **B19** Lojistik/stok | M15 | `carriers`, `warehouses`, `warehouse_locations`, `bins`, `shipments`, `shipment_items`, `customs_records`, `goods_receipts`, `goods_receipt_lines`, `receipt_inspections`, `stock_lots`, `serialized_items`, `stock_reservations`, `inventory_transactions`, `stock_counts`, `stock_count_lines`, `stock_transfers`, `stock_transfer_lines` | `goods_receipt_lines.stock_lot_id`; `receipt_inspections.ncr_id` B21 sonrası; `serialized_items.installed_asset_id` B22 sonrası |
| **B20** Finans | M14 | `project_budgets`, `budget_versions`, `budget_lines`, `budget_baselines`, `commitments`, `actual_cost_references`, `supplier_invoices`, `invoice_lines`, `invoice_matches`, `tax_obligations`, `payment_requests`, `payment_request_invoices`, `payments`, `cash_flow_forecasts`, `progress_claims`, `progress_claim_versions`, `retentions` | Kök composite FK'ları |
| **B21** Mühendislik/saha/kalite | M16–M17 | `engineering_deliverables`, `engineering_revisions`, `technical_requirements`, `work_plans`, `procedures`, `method_statements`, `crews`, `crew_memberships`, `equipment_usages`, `installed_quantities`, `site_photos`, `inspections`, `ncrs`, `corrective_actions`, `incidents`, `permits`, `punch_items`, `quality_evidence` | `technical_requirements.linked_test_execution_id`, `punch_items.test_execution_id` B22 sonrası; `receipt_inspections.ncr_id` |
| **B22** Test/devreye alma/servis | M18–M19 | `test_plan_templates`, `test_plan_versions`, `test_plan_step_definitions`, `test_equipment`, `calibration_certificates`, `installed_assets`, `test_executions`, `test_execution_steps`, `test_execution_equipment`, `test_evidence`, `commissioning_packages`, `commissioning_package_tests`, `commissioning_package_assets`, `acceptance_certificates`, `warranties`, `service_requests`, `work_orders`, `maintenance_plans` | `test_equipment.current_calibration_certificate_id`; bekleyen B19/B21 FK'ları |
| **B23** Read model | M05+ | `_rm` view/projeksiyon tabloları (03 §16) | View'lar önce; fiziksel projeksiyon hacme göre |
| **B24** Seed (referans verisi) | M01 | `countries`, `currencies`, `units_of_measure`, `security_classifications`, `retention_policies` (pasif), `organizations`, `legal_entities` (doğrulanmış), ilk yönetici `personnel` kaydı, `functional_areas` (`social_media`) + rol tanımları, `operation_group_definitions`, `component_definitions`, `reference_type_registry`, `document_types`, `external_capabilities` (`report.standard_control`, pasif), `social_platforms` (doğrulanmış), başlangıç rolleri | Seed dosyaları da DBA tarafından uygulanır; kodlama ajanı çalıştırmaz |

R1 için yalnız B00–B14 (ve SM01 için B15) gerekir; B16–B22 R2/R3 kabul kapılarına göre ayrı DBA paketleriyle uygulanır. B13'teki proje FK'ları ve `teams.project_id` gibi ileri dalga referansları, ilgili tablo gelene kadar eklenmez (nullable kolon önce açılabilir).

## 5. Migration dosyası yazım sözleşmesi (DB-G8 sonrası)

Bu sözleşme yalnız yetkilendirilmiş implementation aşamasında geçerlidir; şimdi dosya üretilmez.

| Konu | Kural |
|---|---|
| Dosya adı | `YYYY_MM_DD_HHMMSS_bNN_<tablo|batch>.php`; batch kodu dosya adında |
| Kimlik | `$table->binary('id', 16, fixed: true)->primary()`; FK kolonları aynı tanım |
| Zaman | `$table->dateTime('created_at', 6)->useCurrent()`; `updated_at` `useCurrent()->useCurrentOnUpdate()` |
| Enum | `$table->string('status', 32)`; CHECK ayrı `DB::statement('ALTER TABLE ... ADD CONSTRAINT ck_... CHECK (...)')` |
| Generated | `->storedAs('CASE WHEN ... END')`; guard üzerinde `->unique('uk_...')` |
| Composite FK | `$table->foreign(['project_id', 'workstream_id'], 'fk_..._agg')->references(['project_id', 'id'])->on('project_workstreams')` |
| Karşılıklı FK | Batch sonunda ayrı migration; `down()` yalnız constraint kaldırır |
| Charset/collation | Tablo düzeyinde `$table->charset = 'utf8mb4'`; kolon düzeyi `->collation('utf8mb4_0900_as_cs')` yalnız D-07 listesi |
| `down()` | Kritik tablolarda `dropIfExists` yerine yalnız DBA prosedürüyle; destructive `down()` statik alarm üretir ([01 §19](01-teknik-mimari-plani.md)) |
| Veri taşıma | Migration içinde veri UPDATE/DELETE yok; veri düzeltmeleri ayrı DBA script paketiyle |
| Boyut | Tek migration tek amaç; JSON snapshot kolonları `json()`; `MEDIUMTEXT` için `mediumText()` |
| Statik kontrol | Her migration dosyasında `php -l`; destructive ifade taraması (`drop`, `truncate`, `delete`, `->change()` dikkat) |

## 6. Veritabanı rolleri ve yetkileri

| Rol | Yetki | Kullanım |
|---|---|---|
| `konelsis_deploy` | DDL (`CREATE/ALTER/DROP/INDEX/REFERENCES`), seed yazma | Yalnız DBA/DevOps; CI'da uygulama dağıtımıyla ayrı adım |
| `konelsis_app` | M/V/R tablolarda `SELECT/INSERT/UPDATE/DELETE`; A sınıfı tablolarda yalnız `SELECT/INSERT` (13 §8.1); DDL yok; `FILE`/`SUPER` yok | Web, scheduler ve queue worker'lar |
| `konelsis_projector` | `_rm` tablolarda yazma; kaynak tablolarda `SELECT` | Projection worker'ları (hacim gerektirince) |
| `konelsis_readonly` | `SELECT` (R sınıfı kolonlar view ile maskelenmiş) | Raporlama/analiz, Auditor sorguları |
| `konelsis_backup` | `SELECT`, `LOCK TABLES`, `RELOAD`, `REPLICATION CLIENT` | Yedekleme |
| Dış servis | **Hesap açılmaz** | Dış AI ve platformlar yalnız uygulama API'si üzerinden |

`report_metric_facts.is_current` ve `business_codes.status` güncellemeleri için `konelsis_app` rolüne kolon bazlı `UPDATE (is_current)` / `UPDATE (status)` yetkisi verilir; diğer A tabloları `UPDATE` alamaz.

## 7. DBA/DevOps teslim paketi ve kontrol listesi

Her batch veya şema değişikliği için teslim paketi (01 §18):

1. **Amaç ve kapsam:** batch kodu, etkilenen tablo/kolon/indeks, veri sınıfı (15 §2).
2. **Hacim ve kilit analizi:** beklenen satır sayısı (D-05), `ALTER` süresi/online DDL uygunluğu (`ALGORITHM=INPLACE, LOCK=NONE` mümkün mü), downtime.
3. **Backward compatibility:** expand/contract adımı, uygulama sürüm sırası, feature flag adı.
4. **Ön koşullar:** backup + PITR doğrulaması, staging'de aynı paketle prova, restore provası tarihi.
5. **Uygulama:** DBA'nın çalıştıracağı komut/araç (uygulama migration mekanizması veya DDL script) ve sırası; kodlama ajanı hiçbir komut çalıştırmaz.
6. **Doğrulama:** tablo/constraint/indeks varlığı (`information_schema`), CHECK/guard testleri (staging anonim veri), collation ve timezone kontrolü, overlap concurrency provası (13 §7), append-only rol testi.
7. **Roll-forward:** hata hâlinde geri alma yerine düzeltici migration; destructive rollback yalnız DBA kararı.
8. **Teyit:** DBA imzası, tarih, ortam; teyit sonrası feature flag açılır.
9. **Manuel kabul listesi:** ilgili dalganın [01 §19](01-teknik-mimari-plani.md) senaryoları (duplicate assignment yok, immutable sürüm, escalation doğru zincire, TR/EN PDF, dış AI kapalıyken manuel akış, tekrar callback ikinci sonuç üretmez, yetkisiz export engellenir).

## 8. Composer/CI güvenli hâle getirme önerisi (M01, D-39)

Mevcut `composer.json` `setup` script'i `artisan migrate --force`, `test` script'i `artisan test`, `post-create-project-cmd` ise `migrate --graceful` çalıştırabilir. Claude Code tarafında `.claude/settings.json` deny kuralları ve PreToolUse guard bu komutları engeller; Codex ve CI için aynı koruma Composer düzeyinde de sağlanmalıdır.

Onaya sunulan değişiklik (uygulanmadı):

- `scripts.setup`: migration adımı kaldırılır; yalnız `composer install`, `.env` kopyalama, `key:generate`, `npm install --ignore-scripts`, `npm run build` kalır. Şema kurulumu DBA sürecine bırakılır.
- `scripts.test`: kaldırılır veya yalnız "Konelsis policy: automated test suites are not executed by agents; see docs/ai/skills" mesajı basan bir `echo` olur.
- `post-create-project-cmd`: `migrate --graceful` adımı kaldırılır.
- `post-autoload-dump` (`package:discover`, `filament:upgrade`) ve `post-update-cmd` (`vendor:publish --tag=laravel-assets`) korunur; bunlar şema veya test çalıştırmaz.
- CI pipeline'ı yalnız `php -l`, `composer validate --strict`, `git diff --check`, yasak komut taraması ve raw SQL/DB facade taraması çalıştırır; `pint`, test suite ve migration adımı CI'da yer almaz.
- `.env.example` içinde `DB_CONNECTION=sqlite` yalnız yerel iskelet için kalır; production `.env` şablonu MySQL/Redis ile ayrı tutulur.

Bu değişiklikler kullanıcı onayı ve DB-G8 sonrası M01 kapsamında uygulanır; bu belge yalnız öneriyi kaydeder.

## 9. Bu turda yapılan ve yapılmayan doğrulamalar

Yapılan:

- Planlama belgeleri, mevcut iskelet dosyaları (`composer.json`, panel provider, middleware, migration, test dosyaları) ve safety hook/deny kuralları statik olarak incelendi; sürümler `composer.lock` üzerinden okundu (Laravel 13.30.1, Filament 5.7.8, Livewire 4.4.3; PHP CLI 8.5.10).
- Sözlük, matris, durum makinesi ve olay kataloğu birbirine karşı elle tutarlılık kontrolünden geçirildi; tespitler 06 §1.5 ve 17 karar defterine yazıldı.

Yapılmayan (politika gereği):

- Laravel Pint çalıştırılmadı.
- Laravel/PHP test paketi çalıştırılmadı.
- Hiçbir Artisan migration/migrate:* komutu, veritabanı reset/refresh/wipe/flush/drop/truncate işlemi veya raw DDL çalıştırılmadı.
- Composer/npm bağımlılığı kurulmadı; plugin kurulmadı; uygulama kodu, migration dosyası, Blade/JS/CSS oluşturulmadı veya değiştirilmedi.
