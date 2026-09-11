# Konelsis Kurumsal Platform — Veri sözlüğü 4: Sosyal Medya

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Entegrasyon bağlantısı ve uygulama kodu ayrı yetkilendirme ister.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md)  
**ERD:** ERD-06 (hesap/bağlantı, içerik/özel gün/yayın, metrik/KPI); iş kuralları [04](04-sosyal-medya-ve-kurumsal-fonksiyon-plani.md) ve [03 §9](03-veri-tabani-tasarim-plani.md)

Bütün sosyal medya kök tabloları `functional_area_id` ile `social_media` alanına bağlanır; hiçbirinde zorunlu `project_id` yoktur. Platform bağlantısı arayüzde yönetilmez; kod içinde kurulur (D-56). Parola, token, recovery code veya client/webhook secret hiçbir kolonda tutulmaz.

## 1. Hesap ve bağlantı (ERD-06 §8.1)

### 1.1 `social_platforms` — R, sınıf P, +S1 +S2

Doğrulanmış platform kataloğu; capability varsayımı taşımaz.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code32 | ✗ | UNIQUE; `linkedin`, `instagram`, `x`, `youtube`, `facebook` … (D-18 listesi) |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `website_url` | url | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 1.2 `social_accounts` — M, sınıf C, +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `platform_id` | fk | ✗ | → `social_platforms` |
| `display_name` | name | ✗ | |
| `handle` | short | ✗ | Ham kullanıcı adı |
| `normalized_handle` | short | ✗ | UNIQUE `(platform_id, normalized_handle)`; lowercase, `@` ve boşluk temizlenmiş |
| `external_account_id` | short | ✓ | UNIQUE `(platform_id, external_account_id)`; NULL değerler benzersizlik dışında kalır |
| `public_url` | url | ✗ | Doğrulanmış profil URL'si |
| `url_hash` | hash | ✗ | UNIQUE |
| `locale` | locale | ✗ | Varsayılan içerik dili |
| `timezone` | tz | ✗ | |
| `audience_note` | text | ✓ | Hedef kitle/ülke |
| `mode` | status | ✗ | [`manual`, `connected`] |
| `status` | status | ✗ | [`draft`, `verifying`, `active`, `suspended`, `transferring`, `closed`] |
| `content_owner_employee_id` | fk | ✓ | → `personnel`; hesap içerik sorumlusu |
| `ownership_evidence_document_revision_id` | fk | ✓ | → `document_revisions`; kurumsal sahiplik kanıtı |
| `verified_by_personnel_id` | fk | ✓ | → `personnel` |
| `verified_at` | ts | ✓ | |
| `last_reviewed_at` | ts | ✓ | |
| `opened_on` | date | ✓ | |
| `closed_on` | date | ✓ | |

### 1.3 `social_account_identifier_histories` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `identifier_type` | status | ✗ | [`handle`, `external_account_id`, `public_url`] |
| `previous_value` | short | ✓ | |
| `new_value` | short | ✗ | |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | Sonraki değişimde servis kapatır |
| `verified_by_personnel_id` | fk | ✗ | → `personnel` |
| `verified_at` | ts | ✗ | |

### 1.4 `social_account_connections` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `provider_code` | code32 | ✗ | Endpoint provider snapshot'ı (guard için) |
| `status` | status | ✗ | [`pending`, `active`, `expired`, `revoked`, `failed`] |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN status = 'active' AND valid_until IS NULL THEN 1 END`; UNIQUE `(social_account_id, provider_code, active_guard)` |

### 1.5 `social_connection_health_checks` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `checked_at` | ts | ✗ | |
| `outcome` | status | ✗ | [`ok`, `degraded`, `auth_expiring`, `auth_expired`, `revoked`, `rate_limited`, `failed`] |
| `latency_ms` | int | ✓ | |
| `safe_error_code` | code32 | ✓ | Credential/payload içermez |

### 1.6 `social_webhook_receipts` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `platform_id` | fk | ✗ | → `social_platforms` |
| `provider_event_id` | short | ✗ | UNIQUE `(platform_id, provider_event_id)`; replay ikinci işlem üretmez |
| `event_kind` | code32 | ✗ | |
| `payload_hash` | hash | ✗ | |
| `payload_reference` | `VARCHAR(512) ascii` | ✓ | Object storage anahtarı; kısa retention |
| `signature_verified` | bool | ✗ | |
| `received_at` | ts | ✗ | |
| `processed_at` | ts | ✓ | |
| `status` | status | ✗ | [`received`, `verified`, `processed`, `ignored`, `rejected`, `failed`] |
| `safe_error_code` | code32 | ✓ | |

### 1.7 `social_sync_runs` — M (tamamlanınca immutable), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `sync_kind` | status | ✗ | [`profile`, `account_metrics`, `publication_metrics`, `reconciliation`] |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `window_start` | ts | ✗ | |
| `window_end` | ts | ✗ | `CHECK (window_end > window_start)` |
| `cursor_in` | `VARCHAR(1024)` | ✓ | |
| `cursor_out` | `VARCHAR(1024)` | ✓ | |
| `started_at` | ts | ✗ | |
| `completed_at` | ts | ✓ | |
| `outcome` | status | ✗ | [`running`, `succeeded`, `partial`, `failed`, `skipped`] |
| `items_ingested` | int | ✗ | |
| `safe_error_code` | code32 | ✓ | |

## 2. Kampanya, içerik, onay ve yayın (ERD-06 §8.2, 03 §9.2)

### 2.1 `social_campaigns` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `code` | code | ✗ | UNIQUE `(functional_area_id, code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `purpose` | text | ✓ | |
| `owner_employee_id` | fk | ✗ | → `personnel` |
| `starts_on` | date | ✓ | |
| `ends_on` | date | ✓ | `CHECK` tarih sırası |
| `status` | status | ✗ | [`planned`, `active`, `completed`, `cancelled`] |

### 2.2 `social_content_items` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `item_no` | code32 | ✗ | UNIQUE |
| `working_title` | name | ✗ | |
| `campaign_id` | fk | ✓ | → `social_campaigns` |
| `special_day_occurrence_id` | fk | ✓ | → `special_day_occurrences` |
| `kind` | status | ✗ | [`corporate_announcement`, `project_reference`, `technical_insight`, `sector_event`, `employer_brand`, `recruitment`, `sponsorship_csr`, `special_day`, `crisis_correction`, `other`] |
| `risk_level` | status | ✗ | [`normal`, `high`, `crisis`]; onay rotası seçimi |
| `owner_employee_id` | fk | ✗ | → `personnel` |
| `priority` | status | ✗ | [`low`, `normal`, `high`, `urgent`] |
| `due_at` | ts | ✓ | |
| `lifecycle` | status | ✗ | [`idea`, `planned`, `in_production`, `completed`, `archived`, `cancelled`] |
| `current_version_id` | fk | ✓ | → `social_content_versions` (aynı kök) |
| `public_disclosure_reference` | short | ✓ | Proje/müşteri kamuya açıklama onay referansı (`project_reference` türünde zorunlu) |

### 2.3 `social_content_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_item_id` | fk | ✗ | → `social_content_items` |
| `version_no` | int | ✗ | UNIQUE `(content_item_id, version_no)` |
| `status` | status | ✗ | [`draft`, `submitted`, `changes_requested`, `approval_pending`, `approved`, `rejected`, `superseded`] |
| `brief` | text | ✓ | |
| `objective` | text | ✓ | |
| `brand_notes` | text | ✓ | |
| `checklist_snapshot` | json | ✓ | Marka/hukuk/gizlilik/telif/kamuya açıklama checklist sonucu; gönderimde dondurulur |
| `content_hash` | hash | ✓ | Gönderimde çeviri+varlık setinden hesaplanır; onay sonrası değişmez |
| `submitted_by_personnel_id` | fk | ✓ | → `personnel` |
| `submitted_at` | ts | ✓ | |
| `approval_policy_version_id` | fk | ✓ | → `approval_policy_versions`; uygulanan policy snapshot'ı |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `superseded_by_version_id` | fk | ✓ | → `social_content_versions` (aynı item) |

### 2.4 `social_content_version_translations` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_version_id` | fk | ✗ | → `social_content_versions` |
| `locale` | locale | ✗ | UNIQUE `(content_version_id, locale)` |
| `title` | name | ✗ | |
| `caption_body` | longtext | ✗ | Sanitize edilmiş |
| `cta` | short | ✓ | |
| `alt_text` | text | ✓ | Görsel erişilebilirlik metni |
| `hashtags` | text | ✓ | |
| `link_url` | url | ✓ | |
| `translation_hash` | hash | ✗ | |

### 2.5 `social_content_assets` — V (sürüm çocuğu), sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_version_id` | fk | ✗ | → `social_content_versions` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; mutable dosya yoluna değil exact revizyona bağ |
| `usage_role` | status | ✗ | [`primary_image`, `gallery_image`, `video`, `thumbnail`, `document`, `cover`] |
| `sort_order` | sint | ✗ | |
| `rights_reference` | short | ✓ | Telif/kullanım hakkı kaydı |
| `consent_document_revision_id` | fk | ✓ | → `document_revisions`; kişi rızası |

UNIQUE `(content_version_id, document_revision_id, usage_role)`.

### 2.6 `social_content_reviews` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_version_id` | fk | ✗ | → `social_content_versions` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel` |
| `review_type` | status | ✗ | [`language`, `brand`, `legal`, `privacy`, `rights`, `final`] |
| `decision` | status | ✗ | [`approved`, `changes_requested`, `rejected`] |
| `reviewed_hash` | hash | ✗ | İncelenen exact hash |
| `comment` | text | ✓ | |
| `decided_at` | ts | ✗ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 2.7 `social_content_targets` — M, sınıf C, +S1 +S2

Hedef, exact onaylı sürüm + hesaptır; onaysız sürüm hedeflenemez (servis transaction'ı).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_version_id` | fk | ✗ | → `social_content_versions` |
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `locale` | locale | ✗ | Hedef dil; ilgili çeviri satırı zorunlu |
| `planned_local_date` | date | ✗ | |
| `planned_local_time` | time | ✗ | |
| `timezone_snapshot` | tz | ✗ | Hesap timezone snapshot'ı |
| `planned_at_utc` | ts | ✗ | UNIQUE `(content_version_id, social_account_id, locale, planned_at_utc)` |
| `scheduled_at_utc` | ts | ✓ | Kuyruğa verilen an |
| `status` | status | ✗ | [`planned`, `scheduled`, `queued`, `publishing`, `published`, `failed`, `retrying`, `manual_action_required`, `cancelled`] |
| `published_publication_id` | fk | ✓ | → `social_publications`; başarılı yayın |
| `cancel_reason` | short | ✓ | |

### 2.8 `social_publications` — M (published sonrası immutable), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `content_target_id` | fk | ✗ | → `social_content_targets` |
| `platform_id` | fk | ✗ | → `social_platforms`; snapshot |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `source` | status | ✗ | [`manual`, `api`] |
| `content_hash_snapshot` | hash | ✗ | Onaylı sürüm hash'ine eşit olmalı (servis) |
| `payload_hash` | hash | ✓ | Gönderilen payload |
| `external_post_id` | short | ✓ | UNIQUE `(platform_id, external_post_id)` |
| `external_post_url` | url | ✓ | |
| `published_at` | ts | ✓ | `state = published` ise zorunlu |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions`; manuel yayın kanıtı (`manual` için zorunlu) |
| `state` | status | ✗ | [`requested`, `in_progress`, `published`, `failed`, `cancelled`, `removed`, `corrected`] |
| `recorded_by_personnel_id` | fk | ✗ | → `personnel` |
| `recorded_at` | ts | ✗ | |

`CHECK`: `state = 'published'` ise `published_at IS NOT NULL` ve (`external_post_id IS NOT NULL` veya `evidence_document_revision_id IS NOT NULL`).

### 2.9 `social_publication_attempts` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_publication_id` | fk | ✗ | → `social_publications` |
| `attempt_no` | tint | ✗ | UNIQUE `(social_publication_id, attempt_no)` |
| `provider_request_id` | short | ✓ | |
| `started_at` | ts | ✗ | |
| `duration_ms` | int | ✓ | |
| `outcome` | status | ✗ | [`succeeded`, `failed`, `timeout`, `rate_limited`, `rejected`] |
| `safe_error_code` | code32 | ✓ | |
| `next_attempt_at` | ts | ✓ | |

### 2.10 `social_publication_corrections` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_publication_id` | fk | ✗ | → `social_publications` |
| `correction_action` | status | ✗ | [`removed`, `corrected`, `reposted`] |
| `reason` | text | ✗ | |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions` |
| `external_reference` | short | ✓ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `occurred_at` | ts | ✗ | |

## 3. Özel gün ve T−4/T−3 modeli (ERD-06 §8.2, 03 §9.3)

### 3.1 `special_day_calendars` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `organization_id` | fk | ✗ | → `organizations` |
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `country_code` | country | ✓ | → `countries` |
| `region_code` | code32 | ✓ | |
| `timezone` | tz | ✗ | |
| `owner_employee_id` | fk | ✗ | → `personnel`; yıllık doğrulama sahibi |
| `source_reference` | short | ✓ | |
| `last_verified_year` | sint | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 3.2 `special_day_definitions` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `calendar_id` | fk | ✗ | → `special_day_calendars` |
| `code` | code | ✗ | UNIQUE `(calendar_id, code)` |
| `category` | status | ✗ | [`national_official`, `religious`, `sector_energy_environment`, `corporate_anniversary`, `sponsorship_community`, `hr_employer_brand`, `international`] |
| `recurrence_kind` | status | ✗ | [`fixed_date`, `movable_verified`, `rule_based`, `single`] |
| `fixed_month` | tint | ✓ | `fixed_date` için |
| `fixed_day` | tint | ✓ | `fixed_date` için |
| `priority` | status | ✗ | [`low`, `normal`, `high`] |
| `default_content_kind` | status | ✓ | `social_content_items.kind` değerleri |
| `source_reference` | short | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | `CHECK` tarih sırası |

### 3.3 `special_day_translations` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `definition_id` | fk | ✗ | → `special_day_definitions` (CASCADE) |
| `locale` | locale | ✗ | UNIQUE `(definition_id, locale)` |
| `name` | name | ✗ | |
| `description` | text | ✓ | |

### 3.4 `special_day_occurrences` — M (doğrulanınca tarih immutable; değişiklik yeni satır), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `definition_id` | fk | ✗ | → `special_day_definitions` |
| `occurrence_year` | sint | ✗ | |
| `local_date` | date | ✗ | UNIQUE `(definition_id, occurrence_year, local_date)` |
| `local_time` | time | ✓ | |
| `timezone` | tz | ✗ | |
| `occurs_at_utc` | ts | ✗ | Yerel tarih/saat + timezone'dan servis hesabı |
| `source_kind` | status | ✗ | [`auto_rule`, `manual`, `official_decision`] |
| `source_reference` | short | ✓ | |
| `verification_state` | status | ✗ | [`unverified`, `verified`, `superseded`, `cancelled`] |
| `verified_by_personnel_id` | fk | ✓ | → `personnel` |
| `verified_at` | ts | ✓ | |
| `supersedes_occurrence_id` | fk | ✓ | → `special_day_occurrences` |
| `note` | text | ✓ | |

### 3.5 `social_reminder_policy_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `version_no` | int | ✗ | UNIQUE `(functional_area_id, version_no)` |
| `name` | name | ✗ | |
| `day_basis` | status | ✗ | [`calendar_day`, `business_day`] |
| `business_calendar_id` | fk | ✓ | → `business_calendars`; `business_day` ise zorunlu |
| `send_time` | time | ✗ | Yerel gönderim saati |
| `timezone` | tz | ✗ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `valid_from` | date | ✗ | |
| `valid_until` | date | ✓ | |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 3.6 `social_reminder_policy_steps` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `policy_version_id` | fk | ✗ | → `social_reminder_policy_versions` |
| `step_code` | code32 | ✗ | UNIQUE `(policy_version_id, step_code)`; `T_MINUS_4`, `T_MINUS_3`, `T_MINUS_1` |
| `offset_days` | sint | ✗ | Negatif: −4, −3, −1 |
| `severity` | status | ✗ | [`info`, `warning`, `critical`] |
| `readiness_predicate` | status | ✗ | [`always`, `owner_missing_or_below_drafting`, `not_approved_or_scheduled`] |
| `requires_acknowledgement` | bool | ✗ | |
| `creates_task` | bool | ✗ | |
| `escalation_level` | tint | ✗ | |
| `is_optional` | bool | ✗ | `T_MINUS_1` yalnız açıkça etkinse |
| `sort_order` | sint | ✗ | |

### 3.7 `social_reminder_step_recipients` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `policy_step_id` | fk | ✗ | → `social_reminder_policy_steps` |
| `resolver_type` | status | ✗ | [`functional_area_role`, `personnel`, `position`, `line_manager_of_owner`, `escalation_target`] |
| `functional_role_code` | code32 | ✓ | `functional_area_role` için |
| `target_id` | fk | ✓ | `personnel`/`position` için |
| `escalation_order` | tint | ✗ | |

UNIQUE `(policy_step_id, resolver_type, functional_role_code, target_id)`.

### 3.8 `social_special_day_rules` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `policy_version_id` | fk | ✗ | → `social_reminder_policy_versions` |
| `occurrence_scope` | status | ✗ | [`calendar`, `category`, `definition`] |
| `special_day_calendar_id` | fk | ✓ | → `special_day_calendars`; `calendar`/`category` scope'unda zorunlu |
| `category` | status | ✓ | `category` scope'unda zorunlu |
| `definition_id` | fk | ✓ | → `special_day_definitions`; `definition` scope'unda zorunlu |
| `relevance` | status | ✗ | [`required`, `optional`, `excluded`] |
| `social_account_id` | fk | ✓ | → `social_accounts`; hesap kısıtı |
| `locale` | locale | ✓ | Dil kısıtı |
| `status` | status | ✗ | [`active`, `inactive`] |

Scope tipi ile dolu hedef uyumu `CHECK` ile korunur.

### 3.9 `social_special_day_plans` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `occurrence_id` | fk | ✗ | → `special_day_occurrences` |
| `functional_area_id` | fk | ✗ | → `functional_areas`; UNIQUE `(occurrence_id, functional_area_id)` |
| `policy_version_id` | fk | ✗ | → `social_reminder_policy_versions`; uygulanan policy snapshot'ı |
| `owner_employee_id` | fk | ✓ | → `personnel` |
| `content_item_id` | fk | ✓ | → `social_content_items` |
| `readiness_status` | status | ✗ | [`unplanned`, `owner_assigned`, `drafting`, `review_pending`, `approved`, `scheduled`, `published`, `not_applicable`, `missed`, `cancelled`] |
| `readiness_changed_at` | ts | ✗ | |
| `is_late_entry` | bool | ✗ | Occurrence T−4'ten geç girildi |
| `acknowledged_at` | ts | ✓ | Sorumluluğu alma |
| `acknowledged_by_personnel_id` | fk | ✓ | → `personnel` |
| `decision_by_personnel_id` | fk | ✓ | → `personnel`; `not_applicable`/`cancelled` kararı |
| `decision_reason` | text | ✓ | |

### 3.10 `social_special_day_reminder_instances` — M (tetiklenince immutable), sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `special_day_plan_id` | fk | ✗ | → `social_special_day_plans` |
| `policy_step_id` | fk | ✗ | → `social_reminder_policy_steps`; UNIQUE `(special_day_plan_id, policy_step_id)` |
| `trigger_at_utc` | ts | ✗ | |
| `dedupe_key` | key32 | ✗ | UNIQUE; `sha256(occurrence + policy_version + step)` |
| `status` | status | ✗ | [`scheduled`, `triggered`, `skipped_predicate`, `superseded`, `cancelled`, `failed`] |
| `predicate_snapshot` | json | ✓ | Tetik anındaki readiness/owner durumu |
| `triggered_at` | ts | ✓ | |
| `skip_reason` | code32 | ✓ | |

### 3.11 `social_reminder_instance_recipients` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `reminder_instance_id` | fk | ✗ | → `social_special_day_reminder_instances` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(reminder_instance_id, personnel_id)` |
| `role_snapshot` | code32 | ✗ | |
| `resolver_source` | code32 | ✗ | |
| `notification_instance_id` | fk | ✓ | → `notification_instances` |
| `task_id` | fk | ✓ | → `tasks` |
| `delivery_state` | status | ✗ | [`pending`, `delivered`, `failed`, `unresolved`] |
| `acknowledged_at` | ts | ✓ | |

## 4. Metrik ve KPI (ERD-06 §8.3, 03 §9.4)

### 4.1 `social_metric_definitions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `canonical_code` | code | ✗ | UNIQUE `(canonical_code, version_no)` |
| `version_no` | int | ✗ | |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `subject_type` | status | ✗ | [`account`, `publication`] |
| `unit` | code32 | ✗ | [`count`, `percent`, `seconds`, `ratio`] |
| `value_type` | status | ✗ | [`integer`, `decimal`, `ratio`] |
| `aggregation_semantics` | status | ✗ | [`snapshot_total`, `period_sum`, `period_average`, `period_max`, `delta`] |
| `description` | text | ✓ | |
| `status` | status | ✗ | [`draft`, `active`, `deprecated`] |
| `effective_from` | date | ✗ | |
| `effective_until` | date | ✓ | |

### 4.2 `social_platform_metric_mappings` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `platform_id` | fk | ✗ | → `social_platforms` |
| `metric_definition_id` | fk | ✗ | → `social_metric_definitions` |
| `api_version` | code32 | ✗ | |
| `external_metric_key` | short | ✗ | |
| `confidence` | status | ✗ | [`exact`, `approximate`] |
| `transform_note` | text | ✓ | |
| `valid_from` | date | ✗ | UNIQUE `(platform_id, api_version, external_metric_key, valid_from)` |
| `valid_until` | date | ✓ | |

### 4.3 `social_metric_ingestion_batches` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `source` | status | ✗ | [`api`, `manual`] |
| `source_confidence` | status | ✗ | [`verified_api`, `manual_evidenced`, `manual_unevidenced`] |
| `source_dedupe_key` | key32 | ✗ | UNIQUE |
| `provider_request_id` | short | ✓ | |
| `sync_run_id` | fk | ✓ | → `social_sync_runs` |
| `window_start` | ts | ✗ | |
| `window_end` | ts | ✗ | `CHECK (window_end > window_start)` |
| `fetched_at` | ts | ✗ | |
| `entered_by_personnel_id` | fk | ✗ | → `personnel`; manuelde kullanıcı, API'de servis |
| `evidence_document_revision_id` | fk | ✓ | → `document_revisions`; manuel kanıt |
| `payload_hash` | hash | ✓ | |
| `payload_reference` | `VARCHAR(512) ascii` | ✓ | Token/PII temizlenmiş raw payload; kısa retention |
| `outcome` | status | ✗ | [`received`, `validated`, `applied`, `rejected`, `failed`] |
| `safe_error_code` | code32 | ✓ | |

### 4.4 `social_account_metric_observations` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `ingestion_batch_id` | fk | ✗ | → `social_metric_ingestion_batches` |
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `metric_definition_id` | fk | ✗ | → `social_metric_definitions` |
| `period_start` | ts | ✗ | |
| `period_end` | ts | ✗ | |
| `timezone` | tz | ✗ | |
| `observed_at` | ts | ✗ | |
| `metric_value` | `DECIMAL(24,8)` | ✗ | |
| `source_revision` | int | ✗ | Aynı dönem için sağlayıcı düzeltmesi |

UNIQUE `(social_account_id, metric_definition_id, period_start, period_end, source_revision)`.

### 4.5 `social_publication_metric_observations` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `ingestion_batch_id` | fk | ✗ | → `social_metric_ingestion_batches` |
| `social_publication_id` | fk | ✗ | → `social_publications` |
| `metric_definition_id` | fk | ✗ | → `social_metric_definitions` |
| `period_start` | ts | ✗ | |
| `period_end` | ts | ✗ | |
| `timezone` | tz | ✗ | |
| `observed_at` | ts | ✗ | |
| `metric_value` | `DECIMAL(24,8)` | ✗ | |
| `source_revision` | int | ✗ | |

UNIQUE `(social_publication_id, metric_definition_id, period_start, period_end, source_revision)`.

### 4.6 `social_metric_sync_cursors` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_account_id` | fk | ✗ | → `social_accounts` |
| `provider_code` | code32 | ✗ | |
| `capability_code` | code32 | ✗ | UNIQUE `(social_account_id, provider_code, capability_code)` |
| `cursor_value` | `VARCHAR(1024)` | ✓ | Secret/payload içermez |
| `last_success_at` | ts | ✓ | |

### 4.7 `social_kpis` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `functional_area_id` | fk | ✗ | → `functional_areas` |
| `code` | code | ✗ | UNIQUE `(functional_area_id, code)` |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `subject_type` | status | ✗ | [`account`, `publication`, `content_item`, `functional_area`] |
| `unit` | code32 | ✗ | |
| `owner_employee_id` | fk | ✓ | → `personnel` |
| `current_formula_version_id` | fk | ✓ | → `social_kpi_formula_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `active`, `retired`] |

### 4.8 `social_kpi_formula_versions` — V, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `social_kpi_id` | fk | ✗ | → `social_kpis` |
| `version_no` | int | ✗ | UNIQUE `(social_kpi_id, version_no)` |
| `formula_definition` | text | ✗ | Deklaratif ifade (alias'lar üzerinden); çalıştırılabilir kod değildir |
| `description` | text | ✓ | |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `effective_from` | date | ✗ | |
| `effective_until` | date | ✓ | |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 4.9 `social_kpi_formula_inputs` — V (sürüm çocuğu), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `formula_version_id` | fk | ✗ | → `social_kpi_formula_versions` |
| `metric_definition_id` | fk | ✗ | → `social_metric_definitions` |
| `input_alias` | code32 | ✗ | UNIQUE `(formula_version_id, input_alias)` |
| `aggregation` | status | ✗ | [`sum`, `avg`, `last`, `first`, `max`, `min`, `delta`] |
| `window_kind` | status | ✗ | [`period`, `trailing_days`, `snapshot`] |
| `window_days` | sint | ✓ | `trailing_days` için |

## 5. Bu bölümün DB-GSM/DB-G8 kontrol listesi

- ERD-06 tabloları eşlendi; eklenen kolonlar: `social_content_items.risk_level`, `social_account_connections.provider_code` (guard için), `special_day_occurrences.occurs_at_utc`, `social_special_day_plans.policy_version_id`.
- Açık kararlar (04 §14, 03 §19 madde 15–22): resmî platform/hesap listesi (D-18), RACI ve rol üyeleri, içerik türü/marka/yasak konular, onay rotaları ve SLA'lar, TR/EN zorunluluk matrisi, özel gün kataloğu ve T−4/T−3 saatleri (D-19), KPI sözlüğü, platform metrik/publish beklentisi, DMS varlık/telif kanıtı ve retention.
- Bu bölüm platform API bağlantısı kurmaz; SM02 kabiliyetleri ayrı entegrasyon kabul kapısına tabidir.
