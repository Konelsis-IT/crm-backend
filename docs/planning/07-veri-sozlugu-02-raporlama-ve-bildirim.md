# Konelsis Kurumsal Platform — Veri sözlüğü 2: şablonlu raporlama, bildirim, kritik iş ve görev

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Bildirim yüzeyi Filament'in kendi bildirim arayüzüdür (D-46).  
**Sürüm:** 1.0 / 12 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md) (tip takma adları, S1/S2/S3 setleri, tablo sınıfları, desenler)  
**ERD:** ERD-03 (raporlama), ERD-04 (bildirim, kritik iş, görev)

> **D-86 notu (12 Eylül 2026):** Raporlama §1–3'teki veritabanı tabanlı şablon modeli (`report_templates` … `report_metric_facts`) **ertelendi**. Kullanıcı kararıyla rapor taslakları kodda tanımlanır (`App\Reports\Templates`); uygulanan şema üç tablodur — `reports`, `report_items`, `report_metrics` (16 §4 **B10A**, 02 M05A). §1–3 ileride takvim/assignment ve sürümlü şablon ihtiyacı doğarsa referans olarak kalır.

## 1. Şablon tanımı (ERD-03, 03 §6.1)

### 1.1 `report_templates` — M, sınıf I, +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE; örn. `DAILY_FIELD`, `WEEKLY_DEPT` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `kind` | status | ✗ | [`daily`, `weekly`, `monthly`, `custom_period`, `ad_hoc`, `event`] |
| `owner_org_unit_id` | fk | ✓ | → `org_units`; şablon sahibi birim |
| `allowed_scope` | status | ✗ | [`organization`, `org_unit`, `position`, `team`, `personnel`, `project`] |
| `current_version_id` | fk | ✓ | → `report_template_versions` (aynı kök; composite FK) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 1.2 `report_template_versions` — V, sınıf I, +S1 +S2

Yayımlanmış sürüm immutable'dır; açılmış dönem başladığı sürümde kalır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_template_id` | fk | ✗ | → `report_templates` |
| `version_no` | int | ✗ | UNIQUE `(report_template_id, version_no)` |
| `classification_id` | fk | ✗ | → `security_classifications`; rapor verisinin sınıfı |
| `status` | status | ✗ | [`draft`, `published`, `superseded`, `withdrawn`] |
| `effective_from` | date | ✓ | |
| `effective_until` | date | ✓ | `CHECK` tarih sırası |
| `external_check_mode` | status | ✗ | [`off`, `advisory`, `revision_required`]; şablon bazlı AI kontrol modu |
| `external_capability_code` | code | ✓ | → `external_capabilities.code`; `off` değilse zorunlu (`report.standard_control`) |
| `requires_pdf` | bool | ✗ | |
| `pdf_template_version_tr_id` | fk | ✓ | → `document_template_versions` (locale `tr`) |
| `pdf_template_version_en_id` | fk | ✓ | → `document_template_versions` (locale `en`) |
| `change_summary` | text | ✓ | |
| `definition_hash` | hash | ✓ | Yayımda bölüm/soru/seçenek tanımının hash'i |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 1.3 `report_sections` — V (sürüm çocuğu), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `template_version_id` | fk | ✗ | → `report_template_versions` (CASCADE yalnız `draft` sürümde; servis kuralı) |
| `section_key` | code32 | ✗ | UNIQUE `(template_version_id, section_key)` |
| `title_tr` | name | ✗ | |
| `title_en` | name | ✗ | |
| `help_tr` | text | ✓ | |
| `help_en` | text | ✓ | |
| `sort_order` | sint | ✗ | |
| `presentation_config` | json | ✓ | Section/Tab/Wizard yerleşimi; yalnız native bileşen anahtarları |

### 1.4 `report_questions` — V (sürüm çocuğu), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `template_version_id` | fk | ✗ | → `report_template_versions`; `field_key` benzersizliği için denormalize |
| `report_section_id` | fk | ✗ | → `report_sections` |
| `parent_question_id` | fk | ✓ | → `report_questions`; yalnız `repeated_group` altındaki alanlar |
| `field_key` | code32 | ✗ | UNIQUE `(template_version_id, field_key)` |
| `label_tr` | name | ✗ | |
| `label_en` | name | ✗ | |
| `help_tr` | text | ✓ | |
| `help_en` | text | ✓ | |
| `answer_type` | status | ✗ | [`short_text`, `long_text`, `rich_text`, `integer`, `decimal`, `money`, `percentage`, `boolean`, `date`, `datetime`, `single_choice`, `multi_choice`, `personnel`, `org_unit`, `project`, `document`, `repeated_group`, `callout`] |
| `is_required` | bool | ✗ | |
| `uom_id` | fk | ✓ | → `units_of_measure`; ölçü alanlarında |
| `currency_code` | currency | ✓ | → `currencies`; `money` tipinde |
| `is_kpi` | bool | ✗ | Metric fact projeksiyonuna girer |
| `kpi_code` | code | ✓ | `is_kpi = 1` ise zorunlu |
| `validation_config` | json | ✓ | min/max/regex/dosya allowlist; generated column ile indekslenmez |
| `presentation_config` | json | ✓ | Filament bileşen tercihi |
| `sort_order` | sint | ✗ | |

### 1.5 `report_question_options` — V (sürüm çocuğu), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_question_id` | fk | ✗ | → `report_questions` |
| `option_key` | code32 | ✗ | UNIQUE `(report_question_id, option_key)` |
| `label_tr` | name | ✗ | |
| `label_en` | name | ✗ | |
| `sort_order` | sint | ✗ | |
| `is_active` | bool | ✗ | |

### 1.6 `report_workflow_bindings` — V (sürüm çocuğu), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `template_version_id` | fk | ✗ | → `report_template_versions` |
| `binding_kind` | status | ✗ | [`review`, `escalation`, `reminder`]; UNIQUE `(template_version_id, binding_kind)` |
| `approval_policy_version_id` | fk | ✓ | → `approval_policy_versions`; `review` için |
| `notification_rule_version_id` | fk | ✓ | → `notification_rule_versions`; `escalation`/`reminder` için |
| `reviewer_resolver` | status | ✓ | [`line_manager`, `functional_manager`, `org_unit_manager`, `position`, `personnel`]; `review` için |
| `reviewer_target_id` | fk | ✓ | `position`/`personnel` resolver hedefi |

## 2. Takvim ve beklenen rapor (03 §6.2)

### 2.1 `report_schedules` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_template_id` | fk | ✗ | → `report_templates` |
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `frequency` | status | ✗ | [`daily`, `weekly`, `monthly`, `custom`, `event`] |
| `timezone` | tz | ✗ | Varsayılan `Europe/Istanbul` |
| `business_calendar_id` | fk | ✓ | → `business_calendars`; `day_basis = business_day` ise zorunlu |
| `day_basis` | status | ✗ | [`calendar_day`, `business_day`] |
| `month_day` | tint | ✓ | `monthly` için 1–28 |
| `opens_at_time` | time | ✗ | Dönem başlangıcında yerel açılış saati |
| `due_time` | time | ✗ | Yerel teslim saati |
| `due_day_offset` | sint | ✗ | Dönem sonuna göre gün ofseti (0 = aynı gün) |
| `grace_minutes` | int | ✗ | `CHECK (grace_minutes >= 0)` |
| `event_type` | code | ✓ | `frequency = event` ise tetikleyici olay kodu |
| `resolver_config` | json | ✓ | Hedef çözümleme seçenekleri (alt birim dahil vb.) |
| `status` | status | ✗ | [`active`, `paused`, `retired`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 2.2 `report_schedule_weekdays` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_schedule_id` | fk | ✗ | → `report_schedules` (CASCADE) |
| `iso_weekday` | tint | ✗ | 1–7; UNIQUE `(report_schedule_id, iso_weekday)` |

### 2.3 `report_schedule_targets` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_schedule_id` | fk | ✗ | → `report_schedules` |
| `target_type` | status | ✗ | [`organization`, `org_unit`, `position`, `team`, `personnel`] |
| `target_id` | fk | ✗ | Registry'ye göre existence doğrulaması servis katmanında |
| `include_descendants` | bool | ✗ | `org_unit` için alt birimler |
| `is_excluded` | bool | ✗ | Hariç tutma satırı |

UNIQUE `(report_schedule_id, target_type, target_id, is_excluded)`.

### 2.4 `report_periods` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_schedule_id` | fk | ✗ | → `report_schedules` |
| `template_version_id` | fk | ✗ | → `report_template_versions`; dönem açılışındaki yayımlı sürüm snapshot'ı |
| `period_start` | ts | ✗ | UNIQUE `(report_schedule_id, period_start)` |
| `period_end` | ts | ✗ | `CHECK (period_end > period_start)` |
| `local_period_start` | date | ✗ | |
| `local_period_end` | date | ✗ | |
| `timezone` | tz | ✗ | Snapshot |
| `opens_at` | ts | ✗ | |
| `due_at` | ts | ✗ | |
| `grace_until` | ts | ✗ | `CHECK (grace_until >= due_at)` |
| `status` | status | ✗ | [`scheduled`, `open`, `closed`, `cancelled`] |
| `generated_at` | ts | ✗ | Scheduler üretim anı |

### 2.5 `report_assignments` — M, sınıf I, +S1 +S2

Gönderenin rapor yükümlülüğü; hiç gönderilmese de satır vardır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_period_id` | fk | ✗ | → `report_periods` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(report_period_id, personnel_id)` |
| `org_unit_snapshot_id` | fk | ✓ | → `org_units`; atama anındaki birim |
| `position_snapshot_id` | fk | ✓ | → `positions` |
| `reviewer_employee_id` | fk | ✗ | → `personnel`; atama anındaki inceleyen snapshot'ı |
| `due_at` | ts | ✗ | Dönemden kopyalanır; kişiye özel istisna değiştirebilir |
| `grace_until` | ts | ✗ | |
| `status` | status | ✗ | [`planned`, `open`, `waived`, `closed`] |
| `waived_by_personnel_id` | fk | ✓ | → `personnel` |
| `waived_at` | ts | ✓ | |
| `waiver_reason` | text | ✓ | `status = waived` ise zorunlu |
| `overdue_flagged_at` | ts | ✓ | `ReportBecameOverdue` bir kez üretildi işareti |
| `escalated_at` | ts | ✓ | |
| `idempotency_key` | key32 | ✗ | UNIQUE; `sha256(schedule_id + period_start + personnel_id)` |

## 3. Submission ve tipli cevaplar (03 §6.3)

### 3.1 `report_submissions` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_assignment_id` | fk | ✗ | → `report_assignments`; UNIQUE (1:1) |
| `current_version_id` | fk | ✓ | → `report_submission_versions` (aynı kök; composite FK) |
| `status` | status | ✗ | [`not_started`, `draft`, `ready_for_check`, `external_check_pending`, `submitted`, `under_review`, `approved`, `locked`, `revision_required`, `rejected`] |
| `revision_count` | sint | ✗ | |
| `first_submitted_at` | ts | ✓ | |
| `last_submitted_at` | ts | ✓ | |
| `approved_at` | ts | ✓ | |
| `locked_at` | ts | ✓ | |

`due_soon`, `overdue`, `escalated` durum değildir; assignment zaman alanlarından türetilir.

### 3.2 `report_submission_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_submission_id` | fk | ✗ | → `report_submissions` |
| `version_no` | int | ✗ | UNIQUE `(report_submission_id, version_no)` |
| `status` | status | ✗ | [`draft`, `submitted`, `superseded`, `withdrawn`] |
| `change_summary` | text | ✓ | 2. sürümden itibaren zorunlu (servis) |
| `submitted_by_personnel_id` | fk | ✓ | → `personnel` |
| `submitted_at` | ts | ✓ | |
| `answer_set_hash` | hash | ✓ | Gönderimde hesaplanır; sonradan değişmez |
| `external_check_request_id` | fk | ✓ | → `external_analysis_requests` |

### 3.3 `report_answers` — M (sürüm `submitted` olana kadar), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `submission_version_id` | fk | ✗ | → `report_submission_versions` |
| `report_question_id` | fk | ✗ | → `report_questions` |
| `row_key` | code32 | ✗ | Tekrarlı grup satırı; varsayılan `0`; UNIQUE `(submission_version_id, report_question_id, row_key)` |
| `answer_type_snapshot` | status | ✗ | Soru tipi snapshot'ı |
| `value_text` | text | ✓ | short/long/rich (sanitize edilmiş) |
| `value_int` | `BIGINT` | ✓ | integer |
| `value_decimal` | `DECIMAL(24,8)` | ✓ | decimal/money/percentage |
| `value_bool` | bool | ✓ | boolean |
| `value_date` | date | ✓ | date |
| `value_datetime` | ts | ✓ | datetime |
| `value_ref_id` | fk | ✓ | personnel/org_unit/project referansı |
| `currency_code` | currency | ✓ | money |
| `uom_id` | fk | ✓ | → `units_of_measure` |

`CHECK`: değer kolonlarından en fazla biri dolu; seçenek ve belge cevapları çocuk tablolarda. Tip–kolon eşlemesi servis ve 13 §4'te.

### 3.4 `report_answer_options` — M, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_answer_id` | fk | ✗ | → `report_answers` (CASCADE yalnız `draft` sürümde) |
| `question_option_id` | fk | ✗ | → `report_question_options`; UNIQUE `(report_answer_id, question_option_id)` |

### 3.5 `report_answer_documents` — M, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_answer_id` | fk | ✗ | → `report_answers` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; UNIQUE `(report_answer_id, document_revision_id)` |
| `evidence_role` | status | ✗ | [`evidence`, `attachment`, `photo`] |

### 3.6 `report_reviews` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `submission_version_id` | fk | ✗ | → `report_submission_versions` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel` |
| `decision` | status | ✗ | [`approved`, `revision_required`, `rejected`] |
| `comment` | text | ✓ | `revision_required`/`rejected` için zorunlu (servis) |
| `decided_at` | ts | ✗ | |
| `approval_request_id` | fk | ✓ | → `approval_requests`; policy'li şablonlarda |

### 3.7 `report_status_transitions` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_submission_id` | fk | ✗ | → `report_submissions` |
| `submission_version_id` | fk | ✓ | → `report_submission_versions` |
| `from_status` | status | ✓ | |
| `to_status` | status | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `reason` | text | ✓ | |
| `occurred_at` | ts | ✗ | |

Durum geçmişi Personel Hareketleri'nde tutulur; ayrı geçiş tablosu yoktur (D-47).

### 3.8 `report_metric_facts` — P/A, sınıf C, +S1

KPI işaretli cevapların dönemsel normalize projeksiyonu; projector yazar.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `submission_version_id` | fk | ✗ | → `report_submission_versions` |
| `report_assignment_id` | fk | ✗ | → `report_assignments` |
| `report_period_id` | fk | ✗ | → `report_periods` |
| `report_question_id` | fk | ✗ | → `report_questions` |
| `row_key` | code32 | ✗ | UNIQUE `(submission_version_id, report_question_id, row_key)` |
| `kpi_code` | code | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `org_unit_id` | fk | ✓ | → `org_units` |
| `project_id` | fk | ✓ | → `projects` |
| `metric_value` | `DECIMAL(24,8)` | ✗ | |
| `uom_id` | fk | ✓ | → `units_of_measure` |
| `currency_code` | currency | ✓ | |
| `period_start` | ts | ✗ | |
| `period_end` | ts | ✗ | |
| `is_current` | bool | ✗ | Assignment başına güncel sürüm |
| `current_guard` | tint (generated) | ✓ | `CASE WHEN is_current = 1 THEN 1 END`; UNIQUE `(report_assignment_id, report_question_id, row_key, current_guard)` |
| `projected_at` | ts | ✗ | |

## 4. Bildirim modeli (ERD-04, 03 §7.1)

Framework tablosu `notifications` Laravel/Filament veritabanı bildirim kanalına ayrılmıştır (N-01, D-12). İş bildirimi kökü `notification_instances`'tır; in-app teslim kaydı `notification_delivery_attempts.channel_reference` üzerinden framework satırına bağlanır.

### 4.1 `notification_rules` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `event_type` | code | ✗ | [14](14-durum-makineleri-ve-olay-katalogu.md) olay kataloğu kodu |
| `current_version_id` | fk | ✓ | → `notification_rule_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 4.2 `notification_rule_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_rule_id` | fk | ✗ | → `notification_rules` |
| `version_no` | int | ✗ | UNIQUE `(notification_rule_id, version_no)` |
| `severity` | status | ✗ | [`info`, `warning`, `critical`] |
| `scope_type` | status | ✗ | [`organization`, `org_unit`, `project`, `functional_area`] |
| `title_key` | code | ✗ | Çeviri anahtarı |
| `body_key` | code | ✗ | Çeviri anahtarı; hassas veri yerine deep link |
| `dedupe_policy` | status | ✗ | [`per_event`, `per_subject_window`, `per_subject_once`] |
| `dedupe_window_minutes` | int | ✓ | `per_subject_window` için |
| `requires_acknowledgement` | bool | ✗ | `critical` için zorunlu 1 |
| `ack_sla_minutes` | int | ✓ | |
| `reminder_interval_minutes` | int | ✓ | |
| `max_reminders` | tint | ✗ | |
| `escalation_after_minutes` | int | ✓ | |
| `max_escalation_level` | tint | ✗ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 4.3 `notification_rule_channels` — V (sürüm çocuğu), sınıf I, +S1

Kanal listesi çocuk tablodur (JSON/SET yerine).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `rule_version_id` | fk | ✗ | → `notification_rule_versions` |
| `channel` | status | ✗ | [`in_app`, `email`]; UNIQUE `(rule_version_id, channel)` |
| `is_mandatory` | bool | ✗ | Kullanıcı tercihi kapatamaz |

### 4.4 `notification_rule_recipients` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `rule_version_id` | fk | ✗ | → `notification_rule_versions` |
| `resolver_type` | status | ✗ | [`subject_employee`, `personnel`, `user`, `position`, `line_manager`, `functional_manager`, `org_unit_manager`, `org_unit_members`, `project_role`, `functional_area_role`, `escalation_chain`] |
| `role_code` | code32 | ✓ | `project_role`/`functional_area_role` için |
| `target_id` | fk | ✓ | `personnel`/`user`/`position` için |
| `escalation_level` | tint | ✗ | 0 = birincil alıcı |
| `sort_order` | sint | ✗ | |

UNIQUE `(rule_version_id, resolver_type, role_code, target_id, escalation_level)`.

### 4.5 `notification_instances` — M, sınıf I, +S1 +S2

ERD adı `notifications` (D-12).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `rule_version_id` | fk | ✗ | → `notification_rule_versions` |
| `event_type` | code | ✗ | Snapshot |
| `subject_type` | code32 | ✗ | Registry |
| `subject_id` | fk | ✗ | |
| `dedupe_key` | key32 | ✗ | UNIQUE |
| `severity` | status | ✗ | Snapshot |
| `title_key` | code | ✗ | |
| `body_params` | json | ✓ | Hassas veri içermez |
| `deep_link` | url | ✗ | Yetkili kayda link |
| `occurred_at` | ts | ✗ | |
| `requires_acknowledgement` | bool | ✗ | |
| `ack_due_at` | ts | ✓ | |
| `escalation_level` | tint | ✗ | Güncel seviye |
| `status` | status | ✗ | [`open`, `acknowledged`, `resolved`, `expired`, `cancelled`] |
| `closed_at` | ts | ✓ | |

### 4.6 `notification_recipients` — M, sınıf I, +S1 +S2

Olay anında çözülen alıcı snapshot'ı; çözülemeyen alıcı satırı kaybolmaz.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_instance_id` | fk | ✗ | → `notification_instances` |
| `personnel_id` | fk | ✓ | → `personnel`; `resolution_status = resolved` ise zorunlu (`CHECK`) |
| `personnel_id` | fk | ✓ | → `personnel` |
| `resolved_role` | code32 | ✗ | Örn. `line_manager`, `function_owner` |
| `resolver_type` | status | ✗ | `notification_rule_recipients.resolver_type` değerleri |
| `escalation_level` | tint | ✗ | |
| `resolution_status` | status | ✗ | [`resolved`, `unresolved`] |
| `unresolved_reason` | code32 | ✓ | [`vacant_position`, `inactive_user`, `no_manager`, `no_role_holder`] |
| `resolved_at` | ts | ✗ | |

UNIQUE `(notification_instance_id, personnel_id, escalation_level)` (NULL `personnel_id` satırları hariç tutulmaz; çözülmemiş satır için `resolver_type`+`role_code` bazlı servis dedupe).

### 4.7 `notification_delivery_attempts` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_recipient_id` | fk | ✗ | → `notification_recipients` |
| `channel` | status | ✗ | [`in_app`, `email`] |
| `attempt_no` | tint | ✗ | UNIQUE `(notification_recipient_id, channel, attempt_no)` |
| `outcome` | status | ✗ | [`queued`, `delivered`, `failed`, `bounced`, `skipped_preference`] |
| `channel_reference` | short | ✓ | Framework `notifications.id` veya mail message-id |
| `attempted_at` | ts | ✗ | |
| `safe_error_code` | code32 | ✓ | |

### 4.8 `notification_receipts` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_recipient_id` | fk | ✗ | → `notification_recipients`; UNIQUE (1:1) |
| `first_read_at` | ts | ✓ | |
| `last_read_at` | ts | ✓ | |
| `read_channel` | status | ✓ | [`in_app`, `email`] |

### 4.9 `notification_acknowledgements` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_recipient_id` | fk | ✗ | → `notification_recipients`; UNIQUE (1:1) |
| `acknowledged_by_personnel_id` | fk | ✗ | → `personnel`; vekil olabilir |
| `acknowledged_at` | ts | ✗ | |
| `comment` | text | ✓ | |

### 4.10 `notification_escalations` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `notification_instance_id` | fk | ✗ | → `notification_instances` |
| `level` | tint | ✗ | |
| `from_recipient_id` | fk | ✓ | → `notification_recipients` |
| `to_recipient_id` | fk | ✗ | → `notification_recipients`; UNIQUE `(notification_instance_id, level, to_recipient_id)` |
| `reason` | status | ✗ | [`ack_overdue`, `unresolved_recipient`, `manual`, `policy_step`] |
| `escalated_at` | ts | ✗ | |

### 4.11 `notification_preferences` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel` |
| `channel` | status | ✗ | [`in_app`, `email`] |
| `event_category` | code32 | ✓ | NULL = tüm olaylar |
| `enabled` | bool | ✗ | `is_mandatory` kanalı geçersiz kılamaz |

UNIQUE `(personnel_id, channel, event_category)`.

## 5. Görev ve kritik iş (ERD-04, 03 §7.2)

### 5.1 `tasks` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `task_no` | code32 | ✗ | UNIQUE; görünen numara |
| `title` | name | ✗ | |
| `description` | text | ✓ | Sanitize |
| `task_kind` | status | ✗ | [`general`, `follow_up`, `critical`, `approval_follow_up`, `external_action`, `transition_transfer`] |
| `context_type` | code32 | ✗ | Registry; [`none`, `functional_area`, `project`, `report_assignment`, `notification_instance`, `business_alert`, `message`, `document`, `social_content_item`, `special_day_plan`, `functional_area_transition_event`] |
| `context_id` | fk | ✓ | `context_type <> none` ise zorunlu |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `requester_personnel_id` | fk | ✗ | → `personnel` |
| `priority` | status | ✗ | [`low`, `normal`, `high`, `critical`] |
| `status` | status | ✗ | [`open`, `in_progress`, `blocked`, `done`, `cancelled`] |
| `due_at` | ts | ✓ | |
| `started_at` | ts | ✓ | |
| `completed_at` | ts | ✓ | |
| `closed_by_personnel_id` | fk | ✓ | → `personnel` |
| `source_message_id` | fk | ✓ | → `messages` |
| `external_action_request_id` | fk | ✓ | → `external_action_requests` |
| `idempotency_key` | key32 | ✓ | UNIQUE; sistem üretimli görevlerde zorunlu |

### 5.2 `task_assignments` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `task_id` | fk | ✗ | → `tasks` |
| `assignee_personnel_id` | fk | ✗ | → `personnel` |
| `responsibility_type` | status | ✗ | [`responsible`, `accountable`, `consulted`, `informed`] |
| `assigned_at` | ts | ✗ | |
| `unassigned_at` | ts | ✓ | |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN unassigned_at IS NULL THEN 1 END`; UNIQUE `(task_id, assignee_personnel_id, responsibility_type, active_guard)` |

### 5.3 `task_dependencies` — M, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `predecessor_task_id` | fk | ✗ | → `tasks` |
| `successor_task_id` | fk | ✗ | → `tasks`; `CHECK (<> predecessor)`; UNIQUE çift |
| `is_hard` | bool | ✗ | |

### 5.4 `business_alerts` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `alert_no` | code32 | ✗ | UNIQUE |
| `trigger_code` | code | ✗ | Kritik tetikleyici kataloğu (14 §4) |
| `subject_type` | code32 | ✗ | Registry |
| `subject_id` | fk | ✗ | |
| `project_id` | fk | ✓ | → `projects` |
| `functional_area_id` | fk | ✓ | → `functional_areas` |
| `org_unit_id` | fk | ✓ | → `org_units` |
| `severity` | status | ✗ | [`warning`, `high`, `critical`] |
| `impact` | status | ✗ | [`low`, `medium`, `high`] |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `title_key` | code | ✗ | |
| `params` | json | ✓ | Hassas veri yok |
| `due_at` | ts | ✓ | |
| `state` | status | ✗ | [`open`, `acknowledged`, `in_resolution`, `resolved`, `closed`, `cancelled`] |
| `opened_at` | ts | ✗ | |
| `closed_at` | ts | ✓ | |
| `dedupe_key` | key32 | ✗ | UNIQUE |

### 5.5 `business_alert_acknowledgements` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_alert_id` | fk | ✗ | → `business_alerts` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(business_alert_id, personnel_id)` |
| `acknowledged_at` | ts | ✗ | |
| `comment` | text | ✓ | |

### 5.6 `business_alert_resolutions` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_alert_id` | fk | ✗ | → `business_alerts` |
| `resolved_by_personnel_id` | fk | ✗ | → `personnel` |
| `resolution_summary` | text | ✗ | |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `resolved_at` | ts | ✗ | |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `is_accepted` | bool | ✗ | |
| `accepted_guard` | fk (generated) | ✓ | `CASE WHEN is_accepted = 1 THEN business_alert_id END`; UNIQUE (tek kabul edilen çözüm) |

### 5.7 `obligation_policies` — M, sınıf I, +S1 +S2 (D-85, B11C)

Personel kontrolü ([02 M06A](02-modul-bazli-ilerleme-plani.md)): konu türü / birim / önceliğe göre eşleşen kademeli uyarı politikası. Eşleşme sırası: konu türü + birim + öncelik → konu türü + birim → konu türü → varsayılan (üçü de NULL).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE (ör. `OBL-DEFAULT`, `OBL-REPORT`, `OBL-TASK-CRITICAL`) |
| `name_tr`, `name_en` | name | ✗ | |
| `subject_type` | code32 | ✓ | Registry; NULL = tüm konu türleri |
| `org_unit_id` | fk | ✓ | → `org_units`; NULL = tüm birimler |
| `priority` | status | ✓ | [`low`, `normal`, `high`, `critical`]; NULL = hepsi |
| `current_version_id` | fk | ✓ | → `obligation_policy_versions` (composite ALTER) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 5.8 `obligation_policy_versions` — V, sınıf I, +S1 +S2 (D-85)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `obligation_policy_id` | fk | ✗ | → `obligation_policies`; UNIQUE `(obligation_policy_id, version_no)` |
| `version_no` | int | ✗ | |
| `remind_before_minutes` | int | ✓ | Seviye 0: son tarihten önce hatırlatma (ör. 1440) |
| `grace_minutes` | int | ✗ | Son tarihten uyarıya kadar tolerans |
| `countdown_minutes_default` | int | ✗ | Seviye 2 geri sayım varsayılanı (kullanıcı örneği: 300 = 5 saat) |
| `countdown_minutes_min`, `countdown_minutes_max` | int | ✗ | AI'nin seçebileceği aralık; `CHECK min ≤ default ≤ max` |
| `ai_adjustment_enabled` | bool | ✗ | Kapalıysa yalnız kural varsayılanları |
| `tone_min`, `tone_max` | status | ✗ | [`soft`, `firm`, `harsh`]; AI bu aralığın dışına çıkamaz |
| `manager_report_mode` | status | ✗ | [`immediate`, `daily_digest`] |
| `extension_policy` | status | ✗ | [`none`, `manager_approval`, `self_declare`] |
| `max_extensions` | tint | ✗ | |
| `definition_hash` | hash | ✗ | Adımlarla birlikte |
| `published_at` | ts | ✓ | |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |

### 5.9 `obligation_policy_steps` — V (sürüm çocuğu), sınıf I, +S1 (D-85)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `obligation_policy_version_id` | fk | ✗ | → `obligation_policy_versions`; UNIQUE `(obligation_policy_version_id, level)` |
| `level` | tint | ✗ | 0 hatırlatma, 1 uyarı, 2 geri sayım, 3 yönetici raporu, 4 üst yönetim; atlanamaz |
| `trigger_kind` | status | ✗ | [`before_due`, `at_due`, `after_grace`, `countdown_expired`, `after_previous`] |
| `offset_minutes` | int | ✗ | Tetikleyiciye göre kayma |
| `recipient_resolver` | status | ✗ | [`self`, `line_manager`, `org_unit_manager`, `executive`, `custom_personnel`] |
| `custom_personnel_id` | fk | ✓ | → `personnel`; yalnız `custom_personnel` |
| `tone` | status | ✗ | [`soft`, `firm`, `harsh`]; AI sürümün `tone_min..max` aralığında değiştirebilir |
| `channels` | json | ✗ | `in_app` zorunlu (D-49); `email` isteğe bağlı |
| `requires_ack` | bool | ✗ | |
| `repeat_every_minutes` | int | ✓ | Teyit gelmezse tekrar; NULL = tek sefer |
| `produces_report` | bool | ✗ | `obligation_reports` üretir (seviye 3–4) |

### 5.10 `personnel_obligations` — M, sınıf I, +S1 +S2 (D-85)

Bir personelin belirli tarihe kadar yapması gereken iş; kaynak modül açar, kaynak olay kapatır. Konu türünden bağımsızdır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `obligation_no` | code32 | ✗ | UNIQUE; görünen numara (`YKM-000001`) |
| `subject_type` | code32 | ✗ | Registry; [`report_assignment`, `task`, `work_request`, `approval_request_step`, `project_stage_requirement`, `document_distribution`, `business_alert`, `personnel_certification`, `focus_expectation`, `manual`] |
| `subject_id` | fk | ✓ | `subject_type <> manual` ise zorunlu |
| `personnel_id` | fk | ✗ | → `personnel`; sorumlu |
| `assigned_by_personnel_id` | fk | ✓ | → `personnel`; `manual` yükümlülükte |
| `org_unit_id` | fk | ✓ | → `org_units`; kayıt anı snapshot'ı |
| `manager_personnel_id` | fk | ✓ | → `personnel`; her uyarıda yeniden çözümlenen amir snapshot'ı (07 §6 kuralı) |
| `policy_version_id` | fk | ✗ | → `obligation_policy_versions` |
| `title_key` | code | ✗ | |
| `params` | json | ✓ | Hassas veri yok |
| `priority` | status | ✗ | [`low`, `normal`, `high`, `critical`]; kaynaktan |
| `importance_weight` | tint | ✗ | 1–5; kaynak modül + politika |
| `due_at` | ts | ✗ | |
| `effective_due_at` | ts | ✗ | Uzatmalarla güncellenir; `CHECK ≥ due_at` |
| `state` | status | ✗ | [`pending`, `reminded`, `warned`, `countdown`, `escalated`, `fulfilled`, `waived`, `cancelled`] (SM-OBL) |
| `current_level` | tint | ✗ | Son uygulanan adım |
| `countdown_ends_at` | ts | ✓ | Seviye 2'de dolu |
| `fulfilled_at` | ts | ✓ | |
| `fulfilled_source` | status | ✓ | [`source_event`, `manual_manager`, `waiver`] |
| `last_assessment_id` | fk | ✓ | → `obligation_assessments` |
| `dedupe_key` | key32 | ✗ | UNIQUE |
| `active_guard` | fk (generated) | ✓ | `CASE WHEN state IN (pending, reminded, warned, countdown, escalated) THEN 1 END`; UNIQUE `(subject_type, subject_id, personnel_id, active_guard)` — aynı konuya ikinci açık yükümlülük yok |

### 5.11 `obligation_assessments` — A (immutable), sınıf I, +S1 (D-85)

Motorun her turdaki değerlendirmesi; AI ya da kural. AI yalnız politika sınırları içinde seçim yapar.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_obligation_id` | fk | ✗ | → `personnel_obligations` |
| `assessed_at` | ts | ✗ | |
| `source` | status | ✗ | [`ai`, `rule_fallback`] |
| `external_analysis_request_id` | fk | ✓ | → `external_analysis_requests` (12 §6.5); B14 yoksa NULL |
| `elapsed_overdue_minutes` | int | ✗ | |
| `urgency_score`, `importance_score`, `compliance_score` | tint | ✗ | 0–100 |
| `recommended_level` | tint | ✗ | Politika adımlarından biri; mevcut seviyeden en çok bir ileri |
| `recommended_tone` | status | ✗ | [`soft`, `firm`, `harsh`]; sürüm aralığında |
| `countdown_minutes` | int | ✓ | Sürümün min–max aralığında |
| `message_tr` | text | ✗ | Kişiye/yöneticiye gidecek üretilmiş metin; sanitize |
| `rationale` | text | ✓ | Kısa gerekçe; hassas veri yok |
| `applied` | bool | ✗ | Motor uyguladı mı (sınır dışı öneri `false` + kural varsayılanı) |

### 5.12 `obligation_escalations` — A, sınıf I, +S1 (D-85)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_obligation_id` | fk | ✗ | → `personnel_obligations` |
| `level` | tint | ✗ | |
| `occurrence_no` | tint | ✗ | Tekrarlar; UNIQUE `(personnel_obligation_id, level, recipient_personnel_id, occurrence_no)` |
| `recipient_personnel_id` | fk | ✗ | → `personnel` |
| `recipient_role` | status | ✗ | [`self`, `line_manager`, `org_unit_manager`, `executive`, `custom`] |
| `tone` | status | ✗ | Uygulanan ton |
| `assessment_id` | fk | ✓ | → `obligation_assessments` |
| `notification_id` | uuid | ✓ | Filament zil bildirimi (`notifications`) |
| `business_alert_id` | fk | ✓ | → `business_alerts`; seviye ≥ 3 |
| `sent_at` | ts | ✗ | |
| `countdown_ends_at` | ts | ✓ | Seviye 2 |
| `acknowledged_at` | ts | ✓ | |

### 5.13 `obligation_reports` — M (teslimden sonra gövde immutable), sınıf C, +S1 +S2 (D-85)

Yöneticiye giden rapor; yalnız yükümlülük olgularını içerir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `report_no` | code32 | ✗ | UNIQUE |
| `personnel_id` | fk | ✗ | → `personnel`; hakkında |
| `manager_personnel_id` | fk | ✗ | → `personnel`; alıcı |
| `report_kind` | status | ✗ | [`single`, `daily_digest`, `escalation_summary`] |
| `severity` | status | ✗ | [`firm`, `harsh`] |
| `period_start`, `period_end` | ts | ✓ | Özetlerde |
| `body_html` | text | ✗ | AI/kural üretimi; sanitize |
| `obligation_ids` | json | ✗ | Kapsanan yükümlülükler |
| `document_revision_id` | fk | ✓ | → `document_revisions`; DMS'e arşivlenen PDF |
| `delivered_at` | ts | ✗ | |
| `read_at`, `acknowledged_at` | ts | ✓ | |
| `manager_note` | text | ✓ | Yöneticinin yanıtı |
| `manager_decision` | status | ✓ | [`noted`, `waived`, `extended`, `hr_follow_up`]; yaptırım kararı insana aittir |

### 5.14 `obligation_extensions` — M, sınıf I, +S1 +S2 (D-85)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_obligation_id` | fk | ✗ | → `personnel_obligations` |
| `requested_by_personnel_id` | fk | ✗ | → `personnel` |
| `reason` | text | ✗ | Gerekçe zorunlu; AI'ye girdi |
| `requested_until` | ts | ✗ | `CHECK > effective_due_at` |
| `status` | status | ✗ | [`requested`, `approved`, `rejected`, `withdrawn`] |
| `approval_request_id` | fk | ✓ | → `approval_requests` (B07); `extension_policy = manager_approval` |
| `decided_by_personnel_id` | fk | ✓ | → `personnel` |
| `decided_at` | ts | ✓ | |

### 5.15 `personnel_compliance_scores` — P (okuma modeli, B23), sınıf I (D-85)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(personnel_id, period_start)` |
| `period_start`, `period_end` | date | ✗ | Aylık |
| `obligations_total`, `fulfilled_on_time`, `fulfilled_late`, `escalated_count`, `manager_reports_count` | int | ✗ | |
| `avg_delay_minutes` | int | ✗ | |
| `score` | tint | ✗ | 0–100; AI ton seçiminde girdi; personel kartı yan kutusunda görünür |
| `computed_at` | ts | ✗ | |

## 6. Bu bölümün DB-G8 kontrol listesi

- ERD-03 ve ERD-04 tabloları eşlendi; eklenen tablo: `notification_rule_channels`; yeniden adlandırılan tablo: `notifications → notification_instances` (D-12).
- Açık kararlar: D-12, gerçek pilot şablonlar ve escalation süreleri (03 §19 madde 2–3; [17](17-db-g8-onay-paketi-ve-karar-defteri.md) §4 taslak şablonlar).
- Cevap tipi–değer kolonu eşleme tablosu ve `report_answers` CHECK tanımı [13 §4](13-cardinality-ve-constraint-matrisi.md) belgesindedir.
