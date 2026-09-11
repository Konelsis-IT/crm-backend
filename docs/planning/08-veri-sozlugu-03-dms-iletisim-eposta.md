# Konelsis Kurumsal Platform — Veri sözlüğü 3: DMS, PDF çıktısı, kayıtlı iletişim ve e-posta alımı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Uygulanan kapsam yalnız M01'dir.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Ortak sözleşme:** [06 §1](06-veri-sozlugu-01-cekirdek-ve-personel.md)  
**ERD:** ERD-05 (doküman/dosya zinciri, konuşma, e-posta alımı)

## 1. Dosya ve doküman zinciri (03 §8.1)

### 1.1 `file_objects` — M (içerik immutable, tarama durumu mutable), sınıf I, +S1 +S2

Binary object storage'da; bu tablo yalnız metadata taşır. İndirme yetkisi her istekte bağlı iş nesnesi üzerinden hesaplanır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `storage_disk` | code32 | ✗ | Private disk adı |
| `storage_key` | `VARCHAR(512) ascii` | ✗ | UNIQUE; rastgele anahtar, orijinal ad içermez |
| `original_name` | name | ✗ | Metadata |
| `extension` | code32 | ✗ | Lowercase; allowlist |
| `mime_type` | short | ✗ | Sunucu tarafında tespit edilen |
| `declared_mime_type` | short | ✓ | İstemcinin bildirdiği |
| `byte_size` | bigu | ✗ | `CHECK (byte_size > 0)` |
| `sha256` | hash | ✗ | UNIQUE; aynı içerik ikinci kez yüklenirse mevcut nesne bağlanır |
| `scan_status` | status | ✗ | [`pending`, `clean`, `infected`, `quarantined`, `failed`, `skipped`] |
| `scanned_at` | ts | ✓ | |
| `scanner_reference` | short | ✓ | |
| `quarantine_reason` | short | ✓ | |
| `is_derived` | bool | ✗ | Thumbnail/preview türevi |
| `derived_from_file_object_id` | fk | ✓ | → `file_objects`; `is_derived = 1` ise zorunlu |
| `derivation_kind` | status | ✓ | [`thumbnail`, `preview`, `pdf_render`] |
| `image_width` | int | ✓ | |
| `image_height` | int | ✓ | |
| `uploaded_by_personnel_id` | fk | ✗ | → `personnel` |
| `uploaded_at` | ts | ✗ | |
| `retention_policy_id` | fk | ✓ | → `retention_policies`; bağlı doküman yoksa |
| `status` | status | ✗ | [`active`, `purged`] |
| `purged_at` | ts | ✓ | |

### 1.2 `document_types` — R, sınıf I, +S1 +S2

Sözlüğe eklenen katalog (N-09): doküman tipi/disiplin, numaralandırma ve varsayılan sınıf.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `discipline` | code32 | ✗ | [`general`, `commercial`, `engineering`, `electrical`, `automation`, `civil`, `hse`, `quality`, `hr`, `finance`, `legal`, `social_media`] |
| `numbering_prefix` | code32 | ✗ | Document no üretimi |
| `is_controlled` | bool | ✗ | Kontrollü doküman vs sıradan ek |
| `default_classification_id` | fk | ✗ | → `security_classifications` |
| `default_retention_policy_id` | fk | ✗ | → `retention_policies` |
| `allowed_extensions` | json | ✓ | Allowlist konfigürasyonu |
| `max_byte_size` | bigu | ✓ | |
| `status` | status | ✗ | [`active`, `inactive`] |

### 1.3 `documents` — M, sınıf (satır bazlı `classification_id`), +S1 +S2 +S3

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_no` | code | ✗ | UNIQUE |
| `title` | name | ✗ | |
| `document_type_id` | fk | ✗ | → `document_types` |
| `is_controlled` | bool | ✗ | Tip varsayılanından kopyalanır |
| `owner_personnel_id` | fk | ✗ | → `personnel` |
| `owner_org_unit_id` | fk | ✓ | → `org_units` |
| `project_id` | fk | ✓ | → `projects` |
| `functional_area_id` | fk | ✓ | → `functional_areas` |
| `classification_id` | fk | ✗ | → `security_classifications` |
| `retention_policy_id` | fk | ✗ | → `retention_policies` |
| `default_language` | locale | ✗ | |
| `description` | text | ✓ | |
| `current_revision_id` | fk | ✓ | → `document_revisions` (aynı kök; composite FK) |
| `status` | status | ✗ | [`draft`, `active`, `superseded`, `obsolete`, `on_hold`, `archived`] |

### 1.4 `document_revisions` — V, sınıf (dokümandan), +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_id` | fk | ✗ | → `documents` |
| `revision_no` | int | ✗ | UNIQUE `(document_id, revision_no)` |
| `revision_code` | code32 | ✗ | Görünen kod (`A`, `B`, `00`, `01`); UNIQUE `(document_id, revision_code)` |
| `language` | locale | ✗ | |
| `title` | name | ✗ | Revizyon anındaki başlık |
| `purpose` | status | ✗ | [`for_information`, `for_review`, `for_approval`, `for_construction`, `as_built`, `final`] |
| `status` | status | ✗ | [`draft`, `in_review`, `approved`, `issued`, `superseded`, `withdrawn`] |
| `change_summary` | text | ✓ | |
| `content_hash` | hash | ✗ | Bağlı dosya hash'lerinden türetilir |
| `prepared_by_personnel_id` | fk | ✗ | → `personnel` |
| `prepared_at` | ts | ✗ | |
| `checked_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `approved_at` | ts | ✓ | |
| `issued_at` | ts | ✓ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |
| `superseded_by_revision_id` | fk | ✓ | → `document_revisions` (aynı doküman) |

### 1.5 `document_revision_files` — V (revizyon çocuğu), +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `file_object_id` | fk | ✗ | → `file_objects` |
| `file_role` | status | ✗ | [`original`, `native`, `pdf`, `preview`, `thumbnail`, `signature_page`] |
| `sort_order` | sint | ✗ | |

UNIQUE `(document_revision_id, file_object_id, file_role)`; `original` rolünde revizyon başına tek satır (generated guard).

### 1.6 `document_links` — M, sınıf (dokümandan), +S1

Kontrollü genel referans; izinli hedefler registry ile sınırlıdır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_id` | fk | ✗ | → `documents` |
| `document_revision_id` | fk | ✓ | → `document_revisions`; exact revizyona sabitlenmiş bağ |
| `target_type` | code32 | ✗ | Registry: `report_answer`, `task`, `functional_area`, `social_content_version`, `proposal_version`, `contract_version`, `project`, `message`, `personnel`, `party`, `business_case` |
| `target_id` | fk | ✗ | |
| `link_role` | status | ✗ | [`attachment`, `evidence`, `reference`, `deliverable`, `source`] |
| `linked_by_personnel_id` | fk | ✗ | → `personnel` |

UNIQUE `(document_id, target_type, target_id, link_role)`.

### 1.7 `document_reviews` — A, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `reviewer_personnel_id` | fk | ✗ | → `personnel` |
| `review_type` | status | ✗ | [`check`, `approve`, `qa`] |
| `decision` | status | ✗ | [`approved`, `approved_with_comments`, `rejected`] |
| `comment` | text | ✓ | |
| `decided_at` | ts | ✗ | |
| `approval_request_id` | fk | ✓ | → `approval_requests` |

### 1.8 `document_distributions` — A, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `recipient_personnel_id` | fk | ✗ | → `personnel` |
| `distribution_kind` | status | ✗ | [`for_action`, `for_information`, `controlled_copy`] |
| `requires_acknowledgement` | bool | ✗ | |
| `distributed_by_personnel_id` | fk | ✗ | → `personnel` |
| `distributed_at` | ts | ✗ | |
| `transmittal_id` | fk | ✓ | → `transmittals` |

UNIQUE `(document_revision_id, recipient_personnel_id, distribution_kind)`.

### 1.9 `document_acknowledgements` — A, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_revision_id` | fk | ✗ | → `document_revisions` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `acknowledgement_kind` | status | ✗ | [`read`, `accepted`, `trained`] |
| `acknowledged_at` | ts | ✗ | |
| `comment` | text | ✓ | |

UNIQUE `(document_revision_id, personnel_id, acknowledgement_kind)`.

### 1.10 `transmittals` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `transmittal_no` | code | ✗ | UNIQUE |
| `project_id` | fk | ✓ | → `projects` |
| `recipient_party_id` | fk | ✓ | → `parties` |
| `recipient_description` | name | ✗ | |
| `purpose` | status | ✗ | [`for_information`, `for_review`, `for_approval`, `for_construction`, `final`] |
| `status` | status | ✗ | [`draft`, `issued`, `acknowledged`, `cancelled`] |
| `issued_by_personnel_id` | fk | ✓ | → `personnel` |
| `issued_at` | ts | ✓ | |
| `cover_document_revision_id` | fk | ✓ | → `document_revisions` |
| `external_reference` | short | ✓ | |

### 1.11 `transmittal_items` — V (issued sonrası immutable), +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `transmittal_id` | fk | ✗ | → `transmittals` |
| `document_revision_id` | fk | ✗ | → `document_revisions`; UNIQUE `(transmittal_id, document_revision_id)` |
| `sort_order` | sint | ✗ | |
| `copies` | tint | ✗ | |

### 1.12 `document_templates` — M, sınıf I, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name_tr` | name | ✗ | |
| `name_en` | name | ✗ | |
| `output_kind` | status | ✗ | [`report_pdf`, `document_pdf`, `letter_pdf`, `xlsx_export`] |
| `status` | status | ✗ | [`active`, `retired`] |

### 1.13 `document_template_versions` — V, sınıf I, +S1 +S2

TR ve EN sürümleri ayrı satırlardır; eksik dil fallback yapmaz.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `document_template_id` | fk | ✗ | → `document_templates` |
| `version_no` | int | ✗ | UNIQUE `(document_template_id, locale, version_no)` |
| `locale` | locale | ✗ | |
| `view_key` | code | ✗ | Onaylı Blade/HTML çıktı view anahtarı (DTO render) |
| `layout_config` | json | ✓ | Logo/font/sayfa ayarı referansları; yol içermez |
| `required_field_keys` | json | ✓ | Bu dilde zorunlu alan listesi (konfigürasyon) |
| `status` | status | ✗ | [`draft`, `published`, `superseded`] |
| `content_hash` | hash | ✗ | |
| `published_by_personnel_id` | fk | ✓ | → `personnel` |
| `published_at` | ts | ✓ | |

### 1.14 `generated_outputs` — M (tamamlanınca immutable), sınıf (kaynaktan), +S1 +S2

Yeniden üretim önceki çıktıyı ezmez; yeni `output_no` alır.

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `source_type` | code32 | ✗ | [`report_submission_version`, `document_revision`, `transmittal`, `social_content_version`, `proposal_version`] |
| `source_id` | fk | ✗ | |
| `template_version_id` | fk | ✗ | → `document_template_versions` |
| `locale` | locale | ✗ | |
| `output_format` | status | ✗ | [`pdf`, `xlsx`, `docx`] |
| `output_no` | int | ✗ | UNIQUE `(source_type, source_id, locale, output_no)` |
| `idempotency_key` | key32 | ✗ | UNIQUE |
| `personnel_id` | fk | ✗ | → `personnel` |
| `requested_at` | ts | ✗ | |
| `status` | status | ✗ | [`queued`, `rendering`, `completed`, `failed`, `blocked_missing_fields`, `superseded`] |
| `missing_fields_snapshot` | json | ✓ | Seçilen dilde eksik zorunlu alanlar |
| `file_object_id` | fk | ✓ | → `file_objects`; `completed` ise zorunlu |
| `output_hash` | hash | ✓ | |
| `completed_at` | ts | ✓ | |
| `safe_error_code` | code32 | ✓ | |

### 1.15 `legal_holds` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `name` | name | ✗ | |
| `reason` | text | ✗ | |
| `personnel_id` | fk | ✗ | → `personnel` |
| `approved_by_personnel_id` | fk | ✓ | → `personnel` |
| `status` | status | ✗ | [`draft`, `active`, `released`] |
| `starts_at` | ts | ✗ | |
| `released_at` | ts | ✓ | |
| `release_reason` | text | ✓ | |

### 1.16 `legal_hold_documents` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `legal_hold_id` | fk | ✗ | → `legal_holds` |
| `document_id` | fk | ✗ | → `documents` |
| `document_revision_id` | fk | ✓ | → `document_revisions`; NULL = tüm revizyonlar |
| `added_by_personnel_id` | fk | ✗ | → `personnel` |
| `added_at` | ts | ✗ | |

UNIQUE `(legal_hold_id, document_id, document_revision_id)`; hold `active` iken bağlı doküman/dosya retention purge kuyruğuna alınamaz.

## 2. Kayıtlı konuşma dizileri (03 §8.2)

### 2.1 `conversations` — M, sınıf (satır bazlı), +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `conversation_type` | status | ✗ | [`company`, `functional_area`, `department`, `project`, `group`, `direct`] |
| `scope_type` | status | ✗ | [`none`, `org_unit`, `project`, `functional_area`, `team`] |
| `scope_id` | fk | ✓ | `scope_type <> none` ise zorunlu |
| `title` | name | ✓ | `direct` için NULL |
| `classification_id` | fk | ✗ | → `security_classifications` |
| `history_policy` | status | ✗ | [`full_history`, `from_join`, `none`] |
| `status` | status | ✗ | [`active`, `archived`, `locked`] |
| `last_message_sequence` | bigu | ✗ | Sequence tahsisi için kök satır kilidiyle artırılır |
| `direct_pair_key` | key32 | ✓ | UNIQUE; `direct` tipinde iki kullanıcı kimliğinin sıralı hash'i |

### 2.2 `conversation_memberships` — M, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `conversation_id` | fk | ✗ | → `conversations` |
| `personnel_id` | fk | ✗ | → `personnel` |
| `role` | status | ✗ | [`owner`, `admin`, `member`, `observer`] |
| `joined_at` | ts | ✗ | |
| `left_at` | ts | ✓ | |
| `history_visible_from` | ts | ✓ | `history_policy` sonucu snapshot |
| `is_muted` | bool | ✗ | |
| `active_guard` | tint (generated) | ✓ | `CASE WHEN left_at IS NULL THEN 1 END`; UNIQUE `(conversation_id, personnel_id, active_guard)` |

### 2.3 `messages` — M, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `conversation_id` | fk | ✗ | → `conversations` |
| `conversation_sequence` | bigu | ✗ | UNIQUE `(conversation_id, conversation_sequence)`; monoton |
| `author_personnel_id` | fk | ✗ | → `personnel` |
| `reply_to_message_id` | fk | ✓ | → `messages`; aynı konuşma composite FK `(conversation_id, reply_to_message_id)` |
| `message_kind` | status | ✗ | [`text`, `system`, `file_share`, `link_share`, `task_link`] |
| `status` | status | ✗ | [`sent`, `edited`, `redacted`, `removed_by_policy`] |
| `sent_at` | ts | ✗ | |
| `current_version_id` | fk | ✓ | → `message_versions` (aynı kök) |
| `redacted_at` | ts | ✓ | |
| `redacted_by_personnel_id` | fk | ✓ | → `personnel` |
| `redaction_reason` | short | ✓ | |

### 2.4 `message_versions` — A, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `message_id` | fk | ✗ | → `messages` |
| `version_no` | int | ✗ | UNIQUE `(message_id, version_no)` |
| `content_snapshot` | json | ✗ | Sanitize edilmiş gövde, link ve mention listesi; değiştirilmez |
| `content_hash` | hash | ✗ | |
| `edit_reason` | short | ✓ | |

### 2.5 `message_mentions` — M, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `message_id` | fk | ✗ | → `messages` |
| `mentioned_personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(message_id, mentioned_personnel_id)` |

### 2.6 `message_attachments` — M, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `message_id` | fk | ✗ | → `messages` |
| `file_object_id` | fk | ✓ | → `file_objects`; sıradan ek |
| `document_revision_id` | fk | ✓ | → `document_revisions`; kontrollü doküman |
| `caption` | short | ✓ | |
| `sort_order` | sint | ✗ | |

`CHECK` XOR: file/document hedeflerinden tam biri dolu.

### 2.7 `conversation_read_cursors` — M, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `conversation_id` | fk | ✗ | → `conversations` |
| `personnel_id` | fk | ✗ | → `personnel`; UNIQUE `(conversation_id, personnel_id)` |
| `last_read_sequence` | bigu | ✗ | |

### 2.8 `message_business_links` — M, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `message_id` | fk | ✗ | → `messages` |
| `target_type` | code32 | ✗ | Registry: `task`, `project_decision`, `business_alert`, `document`, `project`, `report_submission` |
| `target_id` | fk | ✗ | |
| `link_kind` | status | ✗ | [`created_from`, `references`] |

UNIQUE `(message_id, target_type, target_id, link_kind)`.

## 3. E-posta alımı (03 §8.3)

### 3.1 `mailboxes` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `code` | code | ✗ | UNIQUE |
| `address` | email | ✗ | |
| `normalized_address` | email | ✗ | UNIQUE |
| `purpose` | status | ✗ | [`proposal_inbox`, `tender_inbox`, `general_inbox`, `notification_sender`] |
| `provider_code` | code32 | ✗ | Sağlayıcı (D-08 kararı) |
| `polling_interval_minutes` | sint | ✗ | |
| `status` | status | ✗ | [`active`, `paused`, `disabled`] |
| `last_success_at` | ts | ✓ | |

### 3.2 `inbound_email_messages` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `mailbox_id` | fk | ✗ | → `mailboxes` |
| `internet_message_id` | `VARCHAR(998) ascii` | ✗ | RFC 5322 Message-ID |
| `message_id_hash` | key32 | ✗ | UNIQUE `(mailbox_id, message_id_hash)` |
| `content_hash` | hash | ✗ | UNIQUE `(mailbox_id, content_hash)`; Message-ID değişse de duplicate engeli |
| `thread_key` | hash | ✓ | In-Reply-To/References'tan türetilir |
| `from_address` | email | ✗ | |
| `from_name` | name | ✓ | |
| `subject` | name | ✓ | |
| `sent_at` | ts | ✓ | |
| `received_at` | ts | ✗ | |
| `byte_size` | bigu | ✗ | |
| `raw_file_object_id` | fk | ✗ | → `file_objects`; özgün `.eml` |
| `classification_id` | fk | ✗ | → `security_classifications` |
| `status` | status | ✗ | [`received`, `validated`, `needs_review`, `processed`, `failed`, `retrying`, `permanently_failed`] |
| `processed_at` | ts | ✓ | |

### 3.3 `inbound_email_recipients` — A, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `inbound_email_message_id` | fk | ✗ | → `inbound_email_messages` |
| `recipient_type` | status | ✗ | [`to`, `cc`, `bcc`] |
| `address` | email | ✗ | |
| `normalized_address` | email | ✗ | |
| `display_name` | name | ✓ | |
| `sort_order` | sint | ✗ | |

### 3.4 `inbound_email_attachments` — M, sınıf C, +S1 +S2

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `inbound_email_message_id` | fk | ✗ | → `inbound_email_messages` |
| `file_object_id` | fk | ✗ | → `file_objects`; UNIQUE `(inbound_email_message_id, file_object_id)` |
| `original_name` | name | ✗ | |
| `content_id` | short | ✓ | Inline görsel |
| `is_inline` | bool | ✗ | |
| `sort_order` | sint | ✗ | |
| `document_revision_id` | fk | ✓ | → `document_revisions`; DMS'e aktarıldıysa |

### 3.5 `inbound_email_business_links` — M, sınıf C, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `inbound_email_message_id` | fk | ✗ | → `inbound_email_messages` |
| `target_type` | code32 | ✗ | Registry: `business_case`, `tender_notice`, `proposal`, `project`, `party`, `task` |
| `target_id` | fk | ✗ | |
| `link_kind` | status | ✗ | [`auto_matched`, `manual`] |
| `linked_by_personnel_id` | fk | ✗ | → `personnel` |

UNIQUE `(inbound_email_message_id, target_type, target_id)`.

### 3.6 `email_processing_attempts` — A, sınıf I, +S1

| Kolon | Tip | Null | Kural / açıklama |
|---|---|---|---|
| `inbound_email_message_id` | fk | ✗ | → `inbound_email_messages` |
| `attempt_no` | tint | ✗ | UNIQUE `(inbound_email_message_id, attempt_no)` |
| `started_at` | ts | ✗ | |
| `finished_at` | ts | ✓ | |
| `outcome` | status | ✗ | [`succeeded`, `failed`, `retry_scheduled`, `needs_review`] |
| `safe_error_code` | code32 | ✓ | |
| `next_attempt_at` | ts | ✓ | |

## 4. Bu bölümün DB-G8 kontrol listesi

- ERD-05 tabloları eşlendi; eklenen tablo: `document_types` (N-09).
- Açık kararlar: dosya tip/boyut allowlist ve saklama (03 §19 madde 5–6, D-08 e-posta sağlayıcısı, D-09 PDF motoru), chat saklama/redaction ve ayrılan personel erişimi (03 §19 madde 4), controlled-reference allowlist (05 §16 madde 11).
- `generated_outputs.source_type` ile `document_links.target_type` değerleri [12 §5](12-veri-sozlugu-07-workflow-audit-entegrasyon.md) registry'sinden üretilir.
