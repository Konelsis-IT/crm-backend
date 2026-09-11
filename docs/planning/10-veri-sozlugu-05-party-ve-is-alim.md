# Konelsis Kurumsal Platform — Veri sözlüğü 5: Party, business case, ihale, teklif, sözleşme ve Operasyona devir

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. 05 §16 madde 1–4 açık kararları kapanmadan bu bölüm uygulanmaz.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md)  
**ERD:** ERD-07 (party/business case/ihale, teklif/sözleşme/handoff); kurallar [03 §10](03-veri-tabani-tasarim-plani.md)

## 1. Party ana verisi (03 §10.1)

### 1.1 `parties` — M, sınıf I (kişi satırları C), +S1 +S2 +S3

Customer, supplier, subcontractor, partner, employer ve investor tek Party'nin tarihçeli rolleridir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_no` | code32 | ✗ | UNIQUE |
| `party_kind` | status | ✗ | [`organization`, `person`] |
| `display_name` | name | ✗ | |
| `normalized_name` | name | ✗ | Tekilleştirme için normalize; indeksli |
| `country_code` | country | ✓ | → `countries` |
| `default_locale` | locale | ✗ | |
| `duplicate_check_hash` | key32 | ✓ | Ad + ülke + vergi no normalize hash'i; servis uyarısı |
| `status` | status | ✗ | [`prospect`, `active`, `inactive`, `blocked`, `merged`] |
| `merged_into_party_id` | fk | ✓ | → `parties`; `status = merged` ise zorunlu |

### 1.2 `party_roles` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | → `parties` |
| `role_code` | status | ✗ | [`customer`, `supplier`, `subcontractor`, `partner`, `employer`, `investor`, `consultant`, `carrier`, `authority`] |
| `status` | status | ✗ | [`active`, `suspended`, `ended`] |
| `approved_by_personnel_id` | fk | ✓ | → `personnel`; tedarikçi onayı vb. |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN valid_until IS NULL THEN 1 END`; UNIQUE `(party_id, role_code, active_guard)` |

### 1.3 `organization_profiles` — M, sınıf I, +S1 +S2 (PK `party_id`)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | PRIMARY KEY; → `parties` (`party_kind = organization`) |
| `legal_name` | name | ✗ | |
| `trade_name` | name | ✓ | |
| `registration_no` | code | ✓ | |
| `tax_office` | short | ✓ | |
| `tax_number` | code32 | ✓ | UNIQUE `(tax_number)` NULL hariç; ülke bazlı format servis kontrolü |
| `founded_year` | sint | ✓ | |
| `website_url` | url | ✓ | |
| `sector_code` | code32 | ✓ | |
| `personnel_band` | code32 | ✓ | |
| `group_parent_party_id` | fk | ✓ | → `parties` |
| `is_public_company` | bool | ✗ | |

### 1.4 `person_profiles` — M, sınıf C, +S1 +S2 (PK `party_id`)

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | PRIMARY KEY; → `parties` (`party_kind = person`) |
| `given_name` | name | ✗ | |
| `family_name` | name | ✗ | |
| `title` | short | ✓ | |
| `job_title` | name | ✓ | |
| `preferred_locale` | locale | ✗ | |
| `consent_status` | status | ✗ | [`pending`, `granted`, `withdrawn`]; KVKK iletişim rızası |
| `consent_at` | ts | ✓ | |

### 1.5 `addresses` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | → `parties` |
| `address_type` | status | ✗ | [`registered`, `billing`, `shipping`, `site`, `office`, `other`] |
| `line1` | name | ✗ | |
| `line2` | name | ✓ | |
| `district` | short | ✓ | |
| `city` | short | ✗ | |
| `postal_code` | code32 | ✓ | |
| `country_code` | country | ✗ | → `countries` |
| `is_primary` | bool | ✗ | Tip başına tek primary: generated guard `(party_id, address_type)` |
| `status` | status | ✗ | [`active`, `inactive`] |

### 1.6 `communication_points` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | → `parties` |
| `channel_type` | status | ✗ | [`email`, `phone`, `mobile`, `fax`, `website`, `linkedin`, `other`] |
| `value` | short | ✗ | |
| `normalized_value` | short | ✗ | UNIQUE `(party_id, channel_type, normalized_value)` |
| `purpose` | code32 | ✓ | |
| `is_primary` | bool | ✗ | |
| `status` | status | ✗ | [`active`, `inactive`, `bounced`] |

### 1.7 `contact_relationships` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `organization_party_id` | fk | ✗ | → `parties` (organization) |
| `contact_party_id` | fk | ✗ | → `parties` (person); `CHECK (<> organization_party_id)` |
| `relationship_role` | status | ✗ | [`decision_maker`, `technical_contact`, `procurement_contact`, `finance_contact`, `executive`, `other`] |
| `department_note` | short | ✓ | |
| `is_primary` | bool | ✗ | |
| `valid_from` | ts | ✗ | |
| `valid_until` | ts | ✓ | `CHECK` tarih sırası |

### 1.8 `party_licenses` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | → `parties` |
| `license_type` | code32 | ✗ | |
| `license_no` | code | ✗ | UNIQUE `(party_id, license_type, license_no)` |
| `issuer` | name | ✓ | |
| `issued_on` | date | ✓ | |
| `valid_until` | date | ✓ | Expiry tarama indeksi |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`valid`, `expiring`, `expired`, `revoked`] |

### 1.9 `party_certificates` — M, sınıf I, +S1 +S2

`party_licenses` ile aynı kolon şeması; `license_type/license_no` yerine `certificate_type/certificate_no`.

### 1.10 `party_annual_reviews` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `party_id` | fk | ✗ | → `parties` |
| `review_year` | sint | ✗ | UNIQUE `(party_id, review_year)` |
| `reviewer_employee_id` | fk | ✗ | → `personnel` |
| `outcome` | status | ✗ | [`pending`, `approved`, `conditional`, `rejected`] |
| `score` | `DECIMAL(5,2)` | ✓ | |
| `summary` | text | ✓ | |
| `reviewed_at` | ts | ✓ | |
| `next_review_on` | date | ✓ | |

## 2. Business case ve kod soy zinciri (03 §10.2)

### 2.1 `business_cases` — M, sınıf C, +S1 +S2

Tek ticari iş kökü. `(id, sequence_no)` üzerinde composite UNIQUE bulunur.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `sequence_no` | bigu | ✗ | UNIQUE; → `business_number_allocations` |
| `legal_entity_id` | fk | ✗ | → `legal_entities` |
| `primary_party_id` | fk | ✗ | → `parties`; aktif `customer` rolü servis kontrolü |
| `title` | name | ✗ | |
| `short_description` | text | ✓ | |
| `country_code` | country | ✗ | → `countries`; iş ülkesi |
| `currency_code` | currency | ✗ | → `currencies` |
| `project_type_code` | code32 | ✓ | GES/HES/RES/BESS/EMS/ENH/karma (katalog D-20) |
| `source_kind` | status | ✗ | [`email`, `manual`, `tender_source`, `referral`, `existing_customer`] |
| `criticality` | status | ✗ | [`normal`, `critical`] |
| `lifecycle_segment` | status | ✗ | [`acquisition`, `operation`] |
| `acquisition_stage` | status | ✗ | [`business_development`, `offer_preparation`, `offer_review`, `submitted`, `negotiation`, `won`, `handover_preparing`, `handover_review`, `handover_accepted`, `lost`, `cancelled`]; operasyona geçince son değer korunur |
| `outcome` | status | ✗ | [`open`, `won`, `lost`, `cancelled`] |
| `outcome_reason_code` | code32 | ✓ | |
| `outcome_at` | ts | ✓ | |
| `owner_employee_id` | fk | ✗ | → `personnel`; İş Geliştirme sahibi |
| `proposal_owner_employee_id` | fk | ✓ | → `personnel`; Teklif sahibi |
| `estimated_value` | money | ✓ | |
| `classification_id` | fk | ✗ | → `security_classifications` |

### 2.2 `business_codes` — A (status hariç), sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | Composite FK `(business_case_id, sequence_no)` → `business_cases(id, sequence_no)` |
| `sequence_no` | bigu | ✗ | AUTO_INCREMENT değildir |
| `code_kind` | status | ✗ | [`offer`, `project`]; UNIQUE `(business_case_id, code_kind)` |
| `formatted_code` | `VARCHAR(24)` (STORED generated) | ✗ | `CONCAT(CASE code_kind WHEN 'offer' THEN 'TKLF-' WHEN 'project' THEN 'PRJ-' END, sequence_no)`; UNIQUE |
| `issued_at` | ts | ✗ | |
| `issued_by_personnel_id` | fk | ✗ | → `personnel` |
| `predecessor_code_id` | fk | ✓ | → `business_codes`; `PRJ-n` için `TKLF-n` |
| `status` | status | ✗ | [`active`, `historical`]; devirde `offer` kodu `historical` olur |

### 2.3 `opportunities` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases`; UNIQUE (1:1) |
| `stage` | status | ✗ | [`identified`, `qualified`, `bid_decision_pending`, `bid`, `no_bid`, `converted_to_proposal`, `dropped`] |
| `probability_pct` | pct | ✗ | `CHECK (0..100)` |
| `expected_value` | money | ✓ | |
| `expected_decision_on` | date | ✓ | |
| `market_code` | code32 | ✓ | Ülke/pazar segmenti |
| `bid_decision` | status | ✗ | [`pending`, `bid`, `no_bid`] |
| `bid_decision_by_personnel_id` | fk | ✓ | → `personnel` |
| `bid_decision_at` | ts | ✓ | |
| `bid_decision_reason` | text | ✓ | |
| `competitor_note` | text | ✓ | |
| `handoff_checklist_completed_at` | ts | ✓ | İş Geliştirme kapanış checklist'i |

### 2.4 `opportunity_stage_histories` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `opportunity_id` | fk | ✗ | → `opportunities` |
| `from_stage` | status | ✓ | |
| `to_stage` | status | ✗ | |
| `changed_by_personnel_id` | fk | ✗ | → `personnel` |
| `changed_at` | ts | ✗ | |
| `reason` | text | ✓ | |

### 2.5 `business_development_activities` — M, sınıf C, +S1 +S2

Toplantı, ziyaret, arama ve etkinlik kaydı. Business case olmadan party düzeyinde de açılabilir.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✓ | → `business_cases` |
| `party_id` | fk | ✓ | → `parties`; `CHECK` en az biri dolu |
| `activity_type` | status | ✗ | [`meeting`, `visit`, `call`, `email`, `event`, `site_survey`, `other`] |
| `subject` | name | ✗ | |
| `occurred_at` | ts | ✗ | |
| `timezone` | tz | ✗ | |
| `location` | short | ✓ | |
| `organizer_employee_id` | fk | ✗ | → `personnel` |
| `outcome_summary` | text | ✓ | |
| `next_action` | text | ✓ | |
| `next_action_due_at` | ts | ✓ | |
| `follow_up_task_id` | fk | ✓ | → `tasks` |

### 2.6 `business_development_activity_participants` — M, sınıf C, +S1

Çok değerli katılımcı listesi (sözlüğe eklendi).

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `activity_id` | fk | ✗ | → `business_development_activities` (CASCADE) |
| `personnel_id` | fk | ✓ | → `personnel` |
| `contact_party_id` | fk | ✓ | → `parties` (person) |
| `participation_role` | status | ✗ | [`host`, `attendee`, `presenter`] |

`CHECK` XOR personnel/contact; UNIQUE `(activity_id, personnel_id, contact_party_id)`.

### 2.7 `tender_sources` — R, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE; `EKAP`, `WORLD_BANK`, `ILLER_BANKASI` … |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `source_type` | status | ✗ | [`public_procurement`, `international_finance`, `provincial_bank`, `private_invitation`, `marketplace`, `other`] |
| `base_url` | url | ✓ | |
| `access_mode` | status | ✗ | [`manual`, `email`, `api`]; scraping ayrı kurum onayı gerektirir |
| `scraping_allowed` | bool | ✗ | Kaynak kullanım şartı + kurum onayı olmadan 0 |
| `terms_reference` | short | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 2.8 `tender_notices` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases` |
| `tender_source_id` | fk | ✗ | → `tender_sources` |
| `external_notice_id` | short | ✓ | UNIQUE `(tender_source_id, external_notice_id)` |
| `title` | name | ✗ | |
| `issuer_party_id` | fk | ✓ | → `parties` |
| `notice_url` | url | ✓ | |
| `captured_at` | ts | ✗ | |
| `current_version_id` | fk | ✓ | → `tender_notice_versions` (aynı kök) |
| `status` | status | ✗ | [`captured`, `screening`, `pursuing`, `not_pursued`, `submitted`, `awarded`, `lost`, `cancelled`] |

### 2.9 `tender_notice_versions` — V, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `tender_notice_id` | fk | ✗ | → `tender_notices` |
| `version_no` | int | ✗ | UNIQUE `(tender_notice_id, version_no)` |
| `published_on` | date | ✓ | |
| `source_hash` | hash | ✗ | Kaynak içerik hash'i; aynı hash yeni sürüm üretmez |
| `source_document_revision_id` | fk | ✓ | → `document_revisions`; ilan dosyası |
| `summary` | text | ✓ | |
| `captured_by_personnel_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`current`, `superseded`] |

### 2.10 `tender_requirements` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `tender_notice_version_id` | fk | ✗ | → `tender_notice_versions` |
| `requirement_code` | code32 | ✗ | UNIQUE `(tender_notice_version_id, requirement_code)` |
| `requirement_type` | status | ✗ | [`eligibility`, `technical`, `financial`, `document`, `legal`, `experience`] |
| `description` | text | ✗ | |
| `is_mandatory` | bool | ✗ | |
| `compliance_state` | status | ✗ | [`unknown`, `met`, `partially_met`, `not_met`, `waived`]; ön uygunluk değerlendirmesi |
| `evaluated_by_personnel_id` | fk | ✓ | → `personnel` |
| `sort_order` | sint | ✗ | |

### 2.11 `tender_deadlines` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `tender_notice_version_id` | fk | ✗ | → `tender_notice_versions` |
| `deadline_type` | status | ✗ | [`clarification`, `site_visit`, `submission`, `opening`, `bond_validity`, `award`]; UNIQUE `(tender_notice_version_id, deadline_type)` |
| `local_due_date` | date | ✗ | |
| `local_due_time` | time | ✓ | |
| `timezone` | tz | ✗ | |
| `due_at_utc` | ts | ✗ | Kritik tetikleyici kaynağı |
| `alert_generated_at` | ts | ✓ | İdempotent uyarı işareti |

## 3. Teklif (03 §10.3)

### 3.1 `proposals` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases` |
| `proposal_no` | code | ✗ | UNIQUE; `TKLF-n` + alternatif soneki |
| `title` | name | ✗ | |
| `owner_employee_id` | fk | ✗ | → `personnel` |
| `current_version_id` | fk | ✓ | → `proposal_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `in_review`, `approved`, `submitted`, `negotiation`, `accepted`, `rejected`, `withdrawn`, `superseded`] |
| `is_selected` | bool | ✗ | Business case için seçili teklif |
| `selected_guard` | fk (generated) | ✓ | `CASE WHEN is_selected = 1 THEN business_case_id END`; UNIQUE (05 §16 madde 2) |

### 3.2 `proposal_versions` — V, sınıf C (fiyat/marj R), +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_id` | fk | ✗ | → `proposals` |
| `version_no` | int | ✗ | UNIQUE `(proposal_id, version_no)` |
| `locale` | locale | ✗ | |
| `status` | status | ✗ | [`draft`, `review`, `approved`, `submitted`, `superseded`, `withdrawn`] |
| `version_hash` | hash | ✓ | Onayda hesaplanır; sonradan değişmez |
| `currency_code` | currency | ✗ | → `currencies` |
| `total_price` | money | ✓ | |
| `margin_pct` | pct | ✓ | Restricted görünürlük |
| `validity_until` | date | ✓ | |
| `is_critical_route` | bool | ✗ | Proje Grubu görüşü zorunlu |
| `project_group_opinion_document_revision_id` | fk | ✓ | → `document_revisions`; kritik rotada zorunlu |
| `summary` | text | ✓ | |
| `prepared_by_personnel_id` | fk | ✗ | → `personnel` |
| `approval_request_id` | fk | ✓ | → `approval_requests`; final fiyat/yayımlama onayı |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `submitted_at` | ts | ✓ | Müşteriye gönderim |
| `submitted_channel` | status | ✓ | [`email`, `portal`, `hand_delivered`, `courier`] |
| `submission_evidence_document_revision_id` | fk | ✓ | → `document_revisions`; gönderim kanıtı |

### 3.3 `proposal_documents` — V (sürüm çocuğu), sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `document_role` | status | ✗ | [`technical_offer`, `commercial_offer`, `spec_compliance`, `brand_list`, `responsibility_matrix`, `schedule`, `site_survey`, `supplier_quote`, `kmz`, `photo`, `other`] |
| `sort_order` | sint | ✗ | |

UNIQUE `(proposal_version_id, document_revision_id, document_role)`.

### 3.4 `compliance_items` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `tender_requirement_id` | fk | ✓ | → `tender_requirements` |
| `requirement_code` | code32 | ✗ | UNIQUE `(proposal_version_id, requirement_code)` |
| `description` | text | ✗ | |
| `compliance_state` | status | ✗ | [`comply`, `partial`, `deviate`, `not_applicable`] |
| `note` | text | ✓ | |
| `sort_order` | sint | ✗ | |

### 3.5 `deviations` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `compliance_item_id` | fk | ✓ | → `compliance_items` |
| `deviation_type` | status | ✗ | [`technical`, `commercial`, `schedule`, `legal`] |
| `description` | text | ✗ | |
| `justification` | text | ✓ | |
| `status` | status | ✗ | [`proposed`, `accepted_by_customer`, `rejected_by_customer`, `withdrawn`] |
| `sort_order` | sint | ✗ | |

### 3.6 `brand_items` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `item_code` | code32 | ✗ | UNIQUE `(proposal_version_id, item_code)` |
| `item_description` | name | ✗ | |
| `proposed_brand` | name | ✗ | |
| `alternative_brand` | name | ✓ | |
| `origin_country_code` | country | ✓ | → `countries` |
| `approval_state` | status | ✗ | [`proposed`, `pending`, `customer_approved`, `customer_rejected`] |
| `sort_order` | sint | ✗ | |

### 3.7 `responsibility_matrix_items` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `scope_code` | code32 | ✗ | UNIQUE `(proposal_version_id, scope_code)` |
| `scope_description` | name | ✗ | |
| `responsible_party_role` | status | ✗ | [`konelsis`, `customer`, `subcontractor`, `supplier`, `shared`] |
| `note` | text | ✓ | |
| `sort_order` | sint | ✗ | |

### 3.8 `estimate_versions` — V, sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `proposal_version_id` | fk | ✗ | → `proposal_versions` |
| `version_no` | int | ✗ | UNIQUE `(proposal_version_id, version_no)` |
| `currency_code` | currency | ✗ | → `currencies` |
| `exchange_rate_snapshot` | json | ✓ | Kur kaynağı/tarihi; immutable snapshot |
| `total_cost` | money | ✓ | Satır toplamı |
| `total_price` | money | ✓ | |
| `target_margin_pct` | pct | ✓ | |
| `status` | status | ✗ | [`draft`, `reviewed`, `approved`, `superseded`] |
| `prepared_by_personnel_id` | fk | ✗ | → `personnel` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `notes` | text | ✓ | |

### 3.9 `estimate_lines` — V (sürüm çocuğu), sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `estimate_version_id` | fk | ✗ | → `estimate_versions` |
| `parent_line_id` | fk | ✓ | → `estimate_lines`; `CHECK (<> id)` |
| `line_code` | code32 | ✗ | UNIQUE `(estimate_version_id, line_code)` |
| `cost_type` | status | ✗ | [`material`, `labor`, `subcontract`, `logistics`, `engineering`, `commissioning`, `overhead`, `contingency`, `other`] |
| `description` | name | ✗ | |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `unit_cost` | money | ✗ | |
| `unit_price` | money | ✓ | |
| `line_total_cost` | money (STORED generated) | ✗ | `quantity * unit_cost` |
| `wbs_hint` | code32 | ✓ | Devirde WBS eşlemesi |
| `sort_order` | sint | ✗ | |

### 3.10 `pricing_scenarios` — V (sürüm çocuğu), sınıf R, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `estimate_version_id` | fk | ✗ | → `estimate_versions` |
| `scenario_code` | code32 | ✗ | UNIQUE `(estimate_version_id, scenario_code)` |
| `name` | name | ✗ | |
| `target_margin_pct` | pct | ✗ | |
| `adjustment_pct` | pct | ✓ | |
| `total_price` | money | ✗ | |
| `is_selected` | bool | ✗ | |
| `selected_guard` | fk (generated) | ✓ | `CASE WHEN is_selected = 1 THEN estimate_version_id END`; UNIQUE |

### 3.11 `boq_items` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `estimate_version_id` | fk | ✗ | → `estimate_versions` |
| `item_code` | code32 | ✗ | UNIQUE `(estimate_version_id, item_code)` |
| `description` | name | ✗ | |
| `quantity` | qty | ✗ | |
| `uom_id` | fk | ✗ | → `units_of_measure` |
| `unit_price` | money | ✓ | |
| `catalog_item_id` | fk | ✓ | → `catalog_items` |
| `sort_order` | sint | ✗ | |

## 4. Sözleşme (03 §10.3)

### 4.1 `contracts` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases` |
| `contract_no` | code | ✗ | UNIQUE |
| `contract_type` | status | ✗ | [`contract`, `loi`, `ntp`, `framework`, `amendment`] |
| `customer_party_id` | fk | ✗ | → `parties` |
| `current_version_id` | fk | ✓ | → `contract_versions` (aynı kök) |
| `status` | status | ✗ | [`draft`, `negotiation`, `signed`, `active`, `completed`, `terminated`, `cancelled`] |
| `signed_on` | date | ✓ | |
| `effective_from` | date | ✓ | |

### 4.2 `contract_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `contract_id` | fk | ✗ | → `contracts` |
| `version_no` | int | ✗ | UNIQUE `(contract_id, version_no)` |
| `locale` | locale | ✗ | |
| `currency_code` | currency | ✗ | → `currencies` |
| `contract_value` | money | ✓ | |
| `version_hash` | hash | ✓ | |
| `summary` | text | ✓ | |
| `status` | status | ✗ | [`draft`, `review`, `approved`, `executed`, `superseded`] |
| `effective_from` | date | ✓ | |
| `effective_until` | date | ✓ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `executed_at` | ts | ✓ | |

### 4.3 `contract_parties` — V (sürüm çocuğu), sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `contract_version_id` | fk | ✗ | → `contract_versions` |
| `party_id` | fk | ✗ | → `parties` |
| `contract_role` | status | ✗ | [`employer`, `contractor`, `consultant`, `guarantor`, `subcontractor`, `financier`] |
| `signatory_name` | name | ✓ | |

UNIQUE `(contract_version_id, party_id, contract_role)`.

### 4.4 `contract_documents` — V (sürüm çocuğu), sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `contract_version_id` | fk | ✗ | → `contract_versions` |
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `document_role` | status | ✗ | [`signed_contract`, `annex`, `specification`, `schedule`, `bond`, `insurance`, `other`] |

UNIQUE `(contract_version_id, document_revision_id, document_role)`.

### 4.5 `contract_obligations` — V (sürüm çocuğu; durum mutable), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `contract_version_id` | fk | ✗ | → `contract_versions` |
| `obligation_code` | code32 | ✗ | UNIQUE `(contract_version_id, obligation_code)` |
| `obligation_type` | status | ✗ | [`delivery`, `payment`, `insurance`, `bond`, `reporting`, `warranty`, `hse`, `legal`] |
| `description` | text | ✗ | |
| `responsible_party_id` | fk | ✓ | → `parties` |
| `due_on` | date | ✓ | |
| `status` | status | ✗ | [`open`, `met`, `breached`, `waived`] |

### 4.6 `contract_milestones` — V (sürüm çocuğu), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `contract_version_id` | fk | ✗ | → `contract_versions` |
| `milestone_code` | code32 | ✗ | UNIQUE `(contract_version_id, milestone_code)` |
| `name` | name | ✗ | |
| `planned_on` | date | ✗ | |
| `payment_pct` | pct | ✓ | |
| `payment_amount` | money | ✓ | |
| `description` | text | ✓ | |

## 5. Tek yönlü Operasyona devir (03 §10.3)

### 5.1 `operation_handoffs` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `business_case_id` | fk | ✗ | → `business_cases`; UNIQUE (business case başına tek devir kökü) |
| `prepared_by_employee_id` | fk | ✗ | → `personnel` |
| `status` | status | ✗ | [`preparing`, `in_review`, `accepted`, `rejected`, `cancelled`] |
| `accepted_version_id` | fk | ✓ | → `operation_handoff_versions` (aynı kök); `accepted` ise zorunlu |
| `accepted_by_personnel_id` | fk | ✓ | → `personnel` |
| `accepted_at` | ts | ✓ | |

### 5.2 `operation_handoff_versions` — V, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `operation_handoff_id` | fk | ✗ | → `operation_handoffs` |
| `version_no` | int | ✗ | UNIQUE `(operation_handoff_id, version_no)` |
| `proposal_version_id` | fk | ✗ | → `proposal_versions`; exact onaylı teklif |
| `contract_version_id` | fk | ✓ | → `contract_versions`; exact sözleşme/LOI/NTP (zorunluluk 05 §16 madde 3) |
| `baseline_snapshot` | json | ✗ | Fiyat baseline, takvim, varsayım/istisna, risk, açık konu; immutable |
| `snapshot_hash` | hash | ✗ | |
| `manifest_document_revision_id` | fk | ✓ | → `document_revisions` |
| `status` | status | ✗ | [`draft`, `submitted`, `accepted`, `rejected`, `superseded`] |
| `submitted_by_personnel_id` | fk | ✓ | → `personnel` |
| `submitted_at` | ts | ✓ | |
| `decision_reason` | text | ✓ | |

### 5.3 `handoff_items` — V (sürüm çocuğu; tamamlanma mutable), sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `handoff_version_id` | fk | ✗ | → `operation_handoff_versions` |
| `item_code` | code32 | ✗ | UNIQUE `(handoff_version_id, item_code)` |
| `item_type` | status | ✗ | [`document`, `baseline`, `assumption`, `risk`, `open_issue`, `checklist`] |
| `description` | text | ✗ | |
| `document_revision_id` | fk | ✓ | → `document_revisions` |
| `completion_state` | status | ✗ | [`pending`, `complete`, `waived`, `not_applicable`] |
| `waived_by_personnel_id` | fk | ✓ | → `personnel` |
| `sort_order` | sint | ✗ | |

### 5.4 `handoff_reviews` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `handoff_version_id` | fk | ✗ | → `operation_handoff_versions` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel`; Proje Grubu |
| `decision` | status | ✗ | [`accepted`, `rejected`, `returned`] |
| `comment` | text | ✓ | |
| `decided_at` | ts | ✗ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

Handoff kabulü tek transaction'da: sürüm + teklif/sözleşme snapshot kilidi → kabul kaydı → `PRJ-n` üretimi → `projects` ve başlangıç workstream/gate instance'ları → `business_cases.lifecycle_segment = operation` → Personel Hareketleri kaydı. Hata hâlinde kısmi `PRJ-n`, project veya kabul kalmaz ([14 §3.7](14-durum-makineleri-ve-olay-katalogu.md)).

## 6. Bu bölümün DB-G4/DB-G8 kontrol listesi

- ERD-07 tabloları eşlendi; eklenen tablo: `business_development_activity_participants`; eklenen kolonlar: `business_cases.acquisition_stage/outcome`, `proposals.is_selected` guard'ı, `tender_deadlines.alert_generated_at`.
- Açık kararlar (05 §16 madde 1–4; 03 §19 madde 7–8): party subtype/rol eşzamanlılığı, tek teklif kökü vs alternatif teklifler, devirde sözleşme/LOI/NTP zorunluluğu, tek business case'ten birden fazla proje olasılığı, global sequence kapsamı.
- Sözlük `TKLF-n → PRJ-n` eşliğini `business_codes` composite FK'sı ile korur; sequence yeniden kullanılmaz.
