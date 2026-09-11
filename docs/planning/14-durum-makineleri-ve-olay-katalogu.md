# Konelsis Kurumsal Platform — Durum makineleri ve olay kataloğu

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Uygulanan durum makineleri şimdilik personel ve sistem hesabı durumlarıdır.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Kaynak:** [00 §5–6](00-ana-urun-ve-surec-plani.md), [01 §8–9, §15](01-teknik-mimari-plani.md), [03 §5–13](03-veri-tabani-tasarim-plani.md), [04 §5–6](04-sosyal-medya-ve-kurumsal-fonksiyon-plani.md), veri sözlüğü 06–12

## 1. Ortak kurallar

- Durum kolonları formdan düzenlenmez. Her geçiş bir application use-case'i (transition service) ile yapılır; use-case Policy kontrolü, guard doğrulaması ve `personnel_activities` kaydını aynı transaction'da yazar. Ayrı durum değişikliği ve giden kutusu tablosu yoktur (D-47).
- Tablolarda **Guard** kolonu geçişin ön koşulunu, **Yetki** kolonu geçişi başlatabilecek rol/capability'yi, **Yan etki / olay** kolonu üretilen kayıt ve `event_type` değerini verir.
- Terminal durumlardan çıkış yoktur; düzeltme yeni sürüm/kayıt üretir.
- Zaman/uyarı durumları (`due_soon`, `overdue`, `escalated`, `expiring`) içerik durumu değildir; scheduler tarafından zaman alanlarından türetilir ve olay üretir.
- Her makine `personnel_activities.action_code` değerinde aşağıdaki kodla izlenir.
- Durum değerleri veri sözlüğündeki `CHECK` listeleriyle aynıdır; makine ve sözlük arasında fark bulunursa model dondurulmaz.

## 2. Durum makineleri

### 2.1 SM-EMP — `personnel.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `preboarding` | Personel kartı oluşturuldu | HR Admin | `personnel.created` |
| `preboarding` | `active` | Aktif employment + `personnel_id` dolu + primary position | HR Admin | `personnel.activated`; hesap daveti |
| `active` | `on_leave` | Onaylı izin başlangıcı | Scheduler/HR | `personnel.leave_started` |
| `on_leave` | `active` | İzin bitişi | Scheduler/HR | `personnel.leave_ended` |
| `active`, `on_leave` | `suspended` | Gerekçe zorunlu | HR Admin + onay | `personnel.suspended`; kullanıcı `suspended` |
| `active`, `on_leave`, `suspended` | `separated` | Ayrılış tarihi; açık pozisyon/team/delegation/approval/proje rolleri için kapanış görevleri üretildi | HR Admin + onay | `personnel.separated`; kullanıcı `disabled`; ilgili `report_assignments` waiver kuyruğu |
| `separated` | `archived` | Retention tetiği (15 §4) | Scheduler | `personnel.archived`; kişisel alan anonimleştirme kuyruğu |

Her geçiş `personnel_status_histories` satırı üretir. `separated` geçmiş kayıtları silmez; alıcı/owner snapshot'ları değişmez.

### 2.2 SM-RTV — `report_template_versions.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `draft` | | Report Coordinator | |
| `draft` | `published` | ≥ 1 bölüm, ≥ 1 soru, TR/EN etiketler tam, `requires_pdf` ise her dil için template version, `external_check_mode ≠ off` ise capability aktif | Report Coordinator + onay | `definition_hash` hesaplanır; önceki `published` → `superseded`; `report_template.version_published` |
| `published` | `superseded` | Yeni sürüm yayımlandı | Sistem | Açık dönemler eski sürümde kalır |
| `published` | `withdrawn` | Açık dönem yoksa veya dönemler kapatıldı | Report Coordinator + onay | `report_template.version_withdrawn` |
| `draft` | `withdrawn` | | Report Coordinator | |

### 2.3 SM-RPER — `report_periods.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `scheduled` | Scheduler dönem üretti; `(schedule, period_start)` idempotent | Scheduler | `report_period.generated` |
| `scheduled` | `open` | `opens_at` geldi; assignment'lar üretildi | Scheduler | `report_period.opened` |
| `open` | `closed` | `grace_until` geçti ve tüm assignment'lar terminal veya yönetici kapattı | Scheduler/Report Coordinator | `report_period.closed` |
| `scheduled`, `open` | `cancelled` | Schedule pasifleşti/hata | Report Coordinator | assignment'lar `waived` |

### 2.4 SM-RASG — `report_assignments.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `planned` | Hedef resolver çözdü; `idempotency_key` UNIQUE | Scheduler | `report_assignment.created` |
| `planned` | `open` | Dönem açıldı; `report_submissions` (1:1, `not_started`) oluşturuldu | Scheduler | `report_assignment.opened` (çalışana bildirim) |
| `open` | `waived` | Gerekçe + yetkili | Unit Manager/Report Coordinator | `report_assignment.waived` |
| `open` | `closed` | Submission `locked`/`rejected` veya dönem kapandı | Sistem | `report_assignment.closed` |

Zaman olayları (durum değişmez): `due_at − reminder_offset` → `report_assignment.due_soon`; `due_at` geçti, waiver yok, gönderilmiş sürüm yok, `overdue_flagged_at` NULL → `report_assignment.became_overdue` (bir kez; kolon set edilir); `grace_until` geçti → `report_assignment.escalated` (rule'a göre).

### 2.5 SM-RSUB — `report_submissions.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| `not_started` | `draft` | İlk cevap kaydedildi; sürüm 1 `draft` | Atanan çalışan | `report_submission.drafted` |
| `draft` | `ready_for_check` | Deterministik kontroller (zorunlu alan, tip, dosya, tarih) geçti | Çalışan | `report_submission.ready_for_check` |
| `ready_for_check` | `external_check_pending` | Şablon `external_check_mode ≠ off` ve capability grant aktif | Çalışan (Action) veya otomatik | `external_analysis_requests` oluşur; `report_submission.external_check_requested` |
| `ready_for_check` | `submitted` | `external_check_mode = off` veya `advisory` sonucu alındı; sürüm `submitted`, `answer_set_hash` hesaplandı | Çalışan | `report_submission.submitted`; reviewer bildirimi |
| `external_check_pending` | `submitted` | Sonuç `compliant`/`advisory_findings` veya servis kesintisinde manuel devam (advisory) | Çalışan/Sistem | `report_submission.submitted` |
| `external_check_pending` | `revision_required` | Sonuç `revision_required` ve mod `revision_required` | Servis (external action) | `report_submission.revision_required` |
| `external_check_pending` | `ready_for_check` | Zaman aşımı/hata; mod `revision_required` ise manuel inceleme yolu açılır | Sistem | `report_submission.external_check_failed` |
| `submitted` | `under_review` | Reviewer açtı | Reviewer snapshot | `report_submission.review_started` |
| `submitted`, `under_review` | `approved` | Reviewer kararı `approved` (policy varsa approval request `approved`) | Reviewer/Policy | `report_reviews` satırı; `report_submission.approved` |
| `submitted`, `under_review` | `revision_required` | Karar `revision_required`, yorum zorunlu | Reviewer | `report_submission.revision_required` |
| `submitted`, `under_review` | `rejected` | Karar `rejected` | Reviewer | `report_submission.rejected`; assignment `closed` |
| `revision_required` | `draft` | Yeni sürüm (`version_no + 1`) açıldı; eski sürüm `superseded` | Çalışan | `report_submission.revision_started` |
| `approved` | `locked` | PDF üretildi (gerekliyse) veya kilit süresi doldu | Sistem | `report_submission.locked`; `report_metric_facts` projeksiyonu; assignment `closed` |

Gönderilen sürüm yerinde değişmez; düzeltme yeni sürümdür.

### 2.6 SM-GENOUT — `generated_outputs.status`

`queued → rendering → completed`; `rendering → failed`; `queued/rendering → blocked_missing_fields` (seçilen dilde zorunlu alan eksik; `missing_fields_snapshot` dolu); yeni üretim önceki `completed` çıktıyı `superseded` yapar, dosyayı silmez. Olaylar: `generated_output.completed`, `generated_output.failed`, `generated_output.blocked`.

### 2.7 SM-NTF — `notification_instances.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `open` | Rule eşleşti; `dedupe_key` yeni; alıcılar snapshot | Notification service | `notification.created`; delivery attempts |
| `open` | `acknowledged` | `requires_acknowledgement = 1` ve tüm zorunlu alıcılar (veya ilk alıcı; rule'a göre) ack verdi | Alıcı/vekil | `notification.acknowledged` |
| `open` | `expired` | Bilgi bildirimi; retention süresi | Scheduler | |
| `open`, `acknowledged` | `resolved` | Kaynak iş çözüldü (alert/task kapanışı) | Sistem | `notification.resolved` |
| `open` | `cancelled` | Kaynak olay iptal | Sistem | |

Escalation, durum değiştirmeden `escalation_level` artırır ve `notification_escalations` + yeni alıcı satırı üretir: `notification.escalated`. Çözülemeyen alıcı → `notification_recipients.resolution_status = unresolved` + `notification.recipient_unresolved` (operasyon kutusu).

### 2.8 SM-ALERT — `business_alerts.state`

`open → acknowledged` (ack satırı) `→ in_resolution` (çözüm çalışması başladı) `→ resolved` (kabul edilen `business_alert_resolutions` satırı) `→ closed` (onaylayan kapattı); `open/acknowledged/in_resolution → cancelled` (kaynak geçersiz). Olaylar: `business_alert.opened`, `.acknowledged`, `.resolved`, `.closed`, `.cancelled`. Kritik alert acknowledgment olmadan kapanmaz; `ack_sla` aşımı `notification.escalated` üretir.

### 2.9 SM-TASK — `tasks.status`

`open → in_progress → done`; `open/in_progress → blocked` (hard predecessor açık) `→ in_progress`; `open/in_progress/blocked → cancelled`. Olaylar: `task.created`, `task.assigned`, `task.completed`, `task.cancelled`, `task.overdue` (zaman). Sistem görevleri `idempotency_key` ile tekildir.

### 2.10 SM-DOC — `document_revisions.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `draft` | Dosya `scan_status = clean` | Doküman sahibi | `document_revision.drafted` |
| `draft` | `in_review` | `is_controlled = 1`; reviewer atandı | Sahip | `document_revision.review_requested` |
| `in_review` | `approved` | `document_reviews` kararı / approval request `approved`; `content_hash` sabit | Reviewer/Policy | `document_revision.approved` |
| `in_review` | `draft` | Karar `rejected` (yeni revizyon önerilir) | Reviewer | |
| `approved` | `issued` | Dağıtım/transmittal yapıldı; `issued_at` | Sahip | `document_revision.issued`; `documents.current_revision_id` güncellenir; önceki `issued` → `superseded` |
| `draft` (kontrolsüz ek) | `issued` | `is_controlled = 0` | Sahip | |
| `issued`, `approved` | `superseded` | Yeni revizyon yayımlandı | Sistem | |
| `draft`, `in_review`, `approved`, `issued` | `withdrawn` | Gerekçe; legal hold yoksa | Sahip + onay | `document_revision.withdrawn` |

Karantinadaki dosya (`file_objects.scan_status ∈ {infected, quarantined, pending}`) revizyona bağlanamaz ve indirilemez.

### 2.11 SM-FILE — `file_objects.scan_status`

`pending → clean | infected | failed | skipped`; `infected → quarantined`; `failed → pending` (retry). `quarantined` dosya iş kanıtı sayılmaz. Olaylar: `file_object.scanned`, `file_object.quarantined`.

### 2.12 SM-EMAIL — `inbound_email_messages.status`

`received → validated → processed`; `validated → needs_review` (eşleşme belirsiz) `→ processed`; `received/validated → failed → retrying → processed | permanently_failed`. Duplicate (`message_id_hash` veya `content_hash`) `ignored` olarak `email_processing_attempts`'e yazılır, yeni satır açılmaz. Olaylar: `inbound_email.received`, `.processed`, `.needs_review`, `.failed`, `.permanently_failed`.

### 2.13 SM-FA — `functional_areas.governance_mode`

`functional_team → org_unit_owned` (departmanlaşma): `functional_area_transition_events` satırı + efektif tarihli `owner` binding + eski team/personel sorumluluklarının `valid_until` kapanışı + transfer ledger (`functional_area_transition_items`) çalıştırılır; geçmiş snapshot'lar değişmez; kimlikler taşınmaz. `org_unit_owned → hybrid` ve `hybrid → org_unit_owned` aynı desenle. Geri dönüş (`org_unit_owned → functional_team`) yalnız açık kararla. Olaylar: `functional_area.transition_started`, `.transition_completed`, `.responsibility_assigned`, `.responsibility_ended`.

### 2.14 SM-SACC — `social_accounts.status`

`draft → verifying → active`; `active → suspended → active`; `active → transferring → active | closed`; `draft/verifying → closed`. `active` için doğrulanmış `public_url` ve sahiplik kanıtı gerekir. `mode` (`manual`/`connected`) ayrı alandır; connection kaybı `mode`'u `manual`'a düşürmez, `social_account_connections.status` değişir. Olaylar: `social_account.registered`, `.verified`, `.suspended`, `.transferred`, `.closed`, `.connection_expiring`, `.connection_expired`.

### 2.15 SM-SCI — `social_content_items.lifecycle`

`idea → planned` (owner + hedef hesap/tarih) `→ in_production` (sürüm 1 `draft`) `→ completed` (tüm hedefler `published`/`cancelled`) `→ archived`; `idea/planned/in_production → cancelled`. Olaylar: `social_content.created`, `.planned`, `.completed`, `.cancelled`.

### 2.16 SM-SCV — `social_content_versions.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `draft` | | `content_creator` | |
| `draft` | `submitted` | Zorunlu diller (TR/EN matrisi) dolu; varlıklar exact revizyon; checklist tam; `content_hash` hesaplandı | `content_creator` | `social_content_version.submitted`; reviewer bildirimi |
| `submitted` | `changes_requested` | `social_content_reviews` kararı | `reviewer` | `social_content_version.changes_requested` |
| `changes_requested` | `draft` | Yeni sürüm açılır; eski `superseded` | `content_creator` | |
| `submitted` | `approval_pending` | Tüm review tipleri `approved`; approval request açıldı (risk matrisi) | Sistem | `social_content_version.approval_requested` |
| `approval_pending` | `approved` | Approval request `approved`; maker-checker (creator ≠ approver) | `approver` | `social_content_version.approved` (hash kilitlenir) |
| `approval_pending` | `rejected` | | `approver` | `social_content_version.rejected` |
| `approved` | `superseded` | Yeni sürüm onaylandı | Sistem | Açık target'lar yeni sürüme taşınmaz; yeniden planlanır |

### 2.17 SM-SCT — `social_content_targets.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `planned` | Sürüm `approved`; hesap `active`; `locale` çevirisi var | `publisher` | `social_target.planned` |
| `planned` | `scheduled` | `mode = connected` ve `schedule.publish` capability | `publisher` | `social_target.scheduled` |
| `scheduled`, `planned` | `queued` | Tetik zamanı; kuyruğa idempotent iş | Scheduler | `social_target.queued` |
| `queued` | `publishing` | Worker aldı | Worker | |
| `publishing` | `published` | Platform post ID/URL alındı (API) veya yetkili manuel kanıt (manuel) | Worker/`publisher` | `social_publications.state = published`; `social_target.published` |
| `publishing` | `failed` | Hata/timeout; published sayılmaz | Worker | `social_target.failed` |
| `failed` | `retrying` | Retry policy içinde | Worker | |
| `retrying` | `publishing` | | Worker | |
| `failed` | `manual_action_required` | Retry tükendi | Sistem | Kritik bildirim `function_owner`/`publisher` |
| `manual_action_required` | `published` | Manuel kanıt kaydı | `publisher` | |
| `manual_action_required`, `planned`, `scheduled`, `queued` | `cancelled` | Gerekçe | `publisher`/`function_owner` | `social_target.cancelled` |

### 2.18 SM-SPUB — `social_publications.state`

`requested → in_progress → published`; `in_progress → failed`; `requested/in_progress → cancelled`; `published → removed | corrected` (yalnız `social_publication_corrections` ile; `published` satırı silinmez). Aynı `idempotency_key` ikinci yayın üretmez.

### 2.19 SM-SDP — `social_special_day_plans.readiness_status`

`unplanned → owner_assigned → drafting → review_pending → approved → scheduled → published`; her adım ilgili content item/version/target durumundan servis tarafından türetilir ve `readiness_changed_at` güncellenir. Yan sonuçlar: `not_applicable` (yetkili karar, gerekçe), `missed` (occurrence geçti, `published` değil), `cancelled`. Olaylar: `special_day_plan.readiness_changed`, `.marked_not_applicable`, `.missed`.

### 2.20 SM-SDRI — `social_special_day_reminder_instances.status`

`scheduled → triggered` (predicate doğru; alıcılar snapshot; `notification_instances`/`tasks` üretildi); `scheduled → skipped_predicate` (predicate yanlış, örn. T−3'te readiness ≥ `drafting`); `scheduled → superseded` (occurrence/policy değişti; yeni instance üretilir); `scheduled → cancelled`; `scheduled → failed → scheduled` (retry). Geç girilen occurrence ilk taramada tek `triggered` kritik instance üretir (`is_late_entry`). Olaylar: `special_day_reminder.triggered`, `.skipped`, `.superseded`.

### 2.21 SM-BC — `business_cases.acquisition_stage` (+ `lifecycle_segment`)

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `business_development` | `business_number_allocations` + `TKLF-n` aynı transaction | İş Geliştirme | `business_case.created`, `business_code.issued` |
| `business_development` | `offer_preparation` | Bid kararı `bid`; İş Geliştirme kapanış checklist'i tam; Teklif sahibi atandı | İş Geliştirme + Teklif | `business_case.handed_to_proposal` |
| `offer_preparation` | `offer_review` | Teklif sürümü `review` | Teklif | |
| `offer_review` | `offer_preparation` | Onay reddi | Onaycı | |
| `offer_review` | `submitted` | Final fiyat onayı + sürüm `submitted` + gönderim kanıtı | Onay matrisi | `proposal.submitted_to_customer` |
| `submitted` | `negotiation` | Müşteri geri bildirimi | Teklif | |
| `negotiation` | `offer_preparation` | Yeni sürüm gerekli | Teklif | |
| `submitted`, `negotiation` | `won` | Kabul/LOI/NTP/sözleşme kaydı | Teklif + Yönetim | `business_case.won` |
| `won` | `handover_preparing` | `operation_handoffs` açıldı | Teklif | |
| `handover_preparing` | `handover_review` | Handoff sürümü `submitted` | Teklif | `operation_handoff.submitted` |
| `handover_review` | `handover_preparing` | Proje Grubu `returned/rejected` | Proje Grubu | |
| `handover_review` | `handover_accepted` | Kabul transaction'ı (2.24) | Proje Grubu + onay | `lifecycle_segment = operation`; `PRJ-n`; `project.opened` |
| `business_development` … `negotiation` | `lost` | Gerekçe kodu | Teklif/Yönetim | `business_case.lost`; `TKLF-n` korunur |
| Terminal olmayan tüm durumlar | `cancelled` | Gerekçe | Yönetim | `business_case.cancelled` |

`operation → acquisition` geçişi yoktur. Operasyon içi ticari konular `commercial_clarifications`/`project_changes` ile yürür.

### 2.22 SM-PROP — `proposal_versions.status`

`draft → review` (bütün zorunlu bölümler; kritik rotada Proje Grubu görüşü dokümanı) `→ approved` (approval request approved; `version_hash` kilit) `→ submitted` (müşteriye gönderim kanıtı) `→ superseded` (yeni sürüm approved); `review → draft` (ret); `draft/review/approved → withdrawn`. Final sürüm değişirse eski onaylar geçersizleşir (`approval_requests.status = invalidated`), yeni sürüm tekrar onaya gider.

### 2.23 SM-CONTR — `contract_versions.status`

`draft → review → approved → executed`; `review → draft`; `approved/executed → superseded` (amendment yeni sürüm). `contracts.status` türetilir: ilk `executed` sürüm → `signed/active`.

### 2.24 SM-OH — `operation_handoffs.status` ve kabul transaction'ı

`preparing → in_review` (sürüm `submitted`; tüm mandatory `handoff_items` `complete/waived/not_applicable`) `→ accepted | rejected`; `rejected → preparing` (yeni sürüm); `preparing/in_review → cancelled`.

Kabul tek transaction'da, sabit kilit sırasıyla: `business_cases` (FOR UPDATE) → `operation_handoffs` → `operation_handoff_versions` + exact `proposal_versions`/`contract_versions` satırları → `business_codes` `project` satırı (aynı `sequence_no`; UNIQUE ihlali = ikinci dönüşüm reddi) → `projects` → `project_workstreams` (altı grup) + `project_stage_instances` (şablon snapshot) + ilk `project_focus_histories` → `business_cases.lifecycle_segment = operation`, `outcome = won` → `personnel_activities` (`operation_handoff.accepted`, `project.opened`). Herhangi bir adım başarısızsa tamamı geri alınır; kısmi `PRJ-n`, project veya kabul kalmaz.

### 2.25 SM-PRJ — `projects.status`

`opening → active` (ilk workstream `active`) `→ acceptance` (commissioning package'lar `commissioned`/`accepted`) `→ warranty` (provisional acceptance certificate) `→ closed` (final acceptance + açık punch A/B yok + finans kapanışı); `active/acceptance → suspended → active`; `opening/active/acceptance/suspended → cancelled`. `procurement`, `field`, `software` genel durum değildir. Olaylar: `project.opened`, `.activated`, `.acceptance_started`, `.warranty_started`, `.closed`, `.suspended`, `.cancelled`.

### 2.26 SM-WS — `project_workstreams.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `not_ready` | Devirde oluşur | Sistem | |
| `not_ready` | `ready` | Tüm hard predecessor'lar `completed`/`waived` veya waiver; department handoff (varsa) `accepted` | Sistem/PM | `workstream.ready` |
| `ready` | `active` | Owner başlattı | Workstream owner | `workstream.activated` |
| `active` | `review` | Kapanış kanıtları | Owner | `workstream.review_requested` |
| `review` | `completed` | PM/gate onayı | PM | `workstream.completed`; bağlı successor'lar `ready` değerlendirilir |
| `review` | `active` | Ret | PM | |
| `active`, `review` | `blocked` | Gerekçe zorunlu (hard dependency, malzeme, izin) | Owner/PM | `workstream.blocked` |
| `blocked` | `active` | Blokaj kalktı | Owner | `workstream.unblocked` |
| `not_ready`, `ready`, `active` | `waived` | Yetkili waiver + risk owner | Waiver yetkilisi (D-22) | `workstream.waived` |
| `not_ready`, `ready`, `active` | `cancelled` | Proje iptal/kapsam dışı | PM | |

Primary focus değişimi workstream durumunu değiştirmez: `project_focus_histories` yeni satır + eskisi `ended_at`; `project.focus_changed` (yön `backward` ise gerekçe ve bildirim).

**D-68 eki (8 Eylül 2026):** İleri yönde odak değişimi, mevcut odağın `focus_expectations` içindeki zorunlu beklentileri karşılanmadan reddedilir (`ProjectStepReadiness::missingMandatory`); gerekçeli zorlama (`force`) ile aşılabilir ve eksikler hareket kaydına yazılır. `ProjectService::advanceFocus` isteğe bağlı olarak mevcut workstream'i `ready/blocked → active → review → completed` yolunda yürütür (bir guard'a takılırsa olduğu yerde bırakır) ve sıradaki workstream'i `not_ready → ready → active` yapar; `project.focus_advanced` hareketi yazılır. Adım sırası yalnız birincil odağı belirler; diğer departmanlar kendi sekmelerinden her zaman kayıt ekleyip düzeltebilir.

### 2.27 SM-GATE — `project_stage_instances.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| `not_started` | `preparing` | Hard predecessor gate'ler `passed`/`conditionally_passed`; instance owner | Owner | `gate.preparing` |
| `preparing` | `ready_for_review` | Tüm `applicable` + `is_mandatory_snapshot` requirement'lar `accepted` veya `waived` (stage_waivers) | Owner | `gate.ready_for_review` |
| `ready_for_review` | `approval_pending` | Approval request açıldı (node policy) | Sistem | `gate.approval_requested` |
| `approval_pending` | `passed` | Approval `approved` + `stage_reviews.decision = passed`; `reviewed_hash` = kanıt seti | Onaycı | `gate.passed`; `projects.current_macro_gate_code`; bağlı handoff'lar tetiklenir |
| `approval_pending` | `conditionally_passed` | Karar `conditionally_passed`; koşul + `condition_due_on` + risk owner | Onaycı | `gate.conditionally_passed`; takip görevi |
| `approval_pending` | `rejected` | Karar `rejected` | Onaycı | `gate.rejected` |
| `rejected` | `preparing` | | Owner | |
| `passed`, `conditionally_passed` | `reopened` | Gerekçe + yetkili (audit) | PM + onay | `gate.reopened` |
| `reopened` | `preparing` | | Owner | |

### 2.28 SM-DH — `department_handoffs.status`

`preparing → in_review` (sürüm submitted; SLA başlar) `→ accepted` (alıcı workstream owner kabulü; hedef workstream `ready/active` aynı use-case) `| rejected → preparing`; `preparing/in_review → cancelled`. SLA aşımı → `department_handoff.sla_breached` (kritik iş).

### 2.29 SM-PR — `purchase_requisitions.status`

`draft → submitted → technical_approved → budget_approved → approved` (sıralı onay adımları tek approval request içinde) `→ partially_ordered → ordered`; `submitted/technical_approved/budget_approved → rejected → draft`; terminal olmayan → `cancelled`. Teknik/bütçe onayı olmayan talep siparişe dönüşmez; limit üstü talep policy eşiğine göre doğru onaycıya gider.

### 2.30 SM-PO — `purchase_orders.status`

`draft → approved` (PO sürümü approved) `→ issued` (tedarikçiye gönderim; PO belgesi DMS'de) `→ acknowledged → partially_delivered → delivered → closed` (üçlü eşleştirme tamam); `draft/approved → cancelled`; sürüm değişikliği `purchase_order_versions` yeni satır (`change_reason` zorunlu), eski `superseded`.

### 2.31 SM-GR — `goods_receipts.status`

`draft → received → inspected → posted` (`inventory_transactions` `receipt` satırları idempotent; `posted_at`); `inspected → rejected` (tamamı ret); kabul edilmeyen miktar için NCR/`receipt_inspections`. Posting sonrası satır düzeltmesi ters hareketle yapılır, güncelleme ile değil.

### 2.32 SM-INV — `supplier_invoices.status`

`received → matching → matched` (üçlü eşleştirme toplamları tutuyor) `→ approved_for_payment → paid`; `matching → disputed → matching`; `received/matching/disputed → cancelled`. Uyuşmayan PO/teslim/fatura `approved_for_payment` olamaz.

### 2.33 SM-PAY — `payment_requests.status`

`draft → submitted → approved → scheduled → paid`; `submitted → rejected → draft`; terminal olmayan → `cancelled`. `paid`, `payments` satırı ile aynı transaction'da.

### 2.34 SM-NCR — `ncrs.status`

`open → root_cause → corrective_action → verification → closed`; `open/root_cause → cancelled`; `verification → corrective_action` (etkinlik doğrulanamadı). Kritik NCR `open` olduğunda kritik iş üretir.

### 2.35 SM-PUNCH — `punch_items.status`

`open → in_progress → ready_for_check → cleared`; `ready_for_check → in_progress`; `open/in_progress → cancelled`. Kategori `A` punch açıkken enerjilendirme gate'i geçemez.

### 2.36 SM-TEST — `test_executions.status`

`planned → in_progress` (ekipman kalibrasyonları geçerli; plan sürümü `published`) `→ passed | failed | aborted`; `failed → superseded` (retest yeni execution, `retest_of_execution_id`). Adım düzeyinde `test_execution_steps.outcome` `pending → pass | fail | not_applicable | waived`; hold point adımı `hold_point_released_*` olmadan sonraki adım başlatılamaz. Kabul gate'i zorunlu ölçüm/kanıt ve yetkili onay olmadan geçmez.

### 2.37 SM-INCIDENT — `incidents.status`

`reported → investigating → actions_open → closed`; kritik/yüksek olay anında kritik iş + escalation.

### 2.38 SM-APR — `approval_requests.status`

| Kaynak | Hedef | Guard | Yetki | Yan etki / olay |
|---|---|---|---|---|
| — | `pending` | Policy sürümü çözüldü; adımlar materialize; `active_guard` UNIQUE | Sistem | `approval.requested` |
| `pending` | `in_progress` | İlk adım aktif; alıcılar bildirildi | Sistem | |
| `in_progress` | `approved` | Mode'a göre adımlar tamamlandı; her karar `approved_subject_hash = subject_hash` | Onaycılar | `approval.approved` |
| `in_progress` | `rejected` | Herhangi zorunlu adım `rejected` | Onaycı | `approval.rejected` |
| `pending`, `in_progress` | `cancelled` | Talep sahibi/yetkili | Talep sahibi | `approval.cancelled` |
| `pending`, `in_progress` | `expired` | SLA + policy | Scheduler | `approval.expired` (escalation) |
| `pending`, `in_progress` | `invalidated` | Subject hash değişti / sürüm geri çekildi / policy superseded | Sistem | `approval.invalidated`; yeni istek gerekir |

Görevler ayrılığı: `requires_maker_checker = 1` ise `personnel_id` hiçbir adımda karar veremez (`sod_conflict`). Vekil karar `delegation_snapshots` ile kaydedilir.

### 2.39 SM-WF — `workflow_instances.status`

`running ↔ waiting` (adım bekliyor); `running → completed | rejected | cancelled | failed`. Instance başladığı definition sürümüne sabittir.

### 2.40 SM-EAR — `external_analysis_requests.status`

`draft → queued → sent → processing → succeeded`; `queued/sent/processing → failed → retrying → sent`; `queued/sent/processing → expired` (timeout) `| cancelled`. Callback imzası/nonce doğrulanamazsa sonuç reddedilir ve `external_analysis_attempts.outcome = rejected`. Tekrarlanan callback aynı `result_hash` ile ikinci sonuç/aksiyon üretmez.

### 2.41 SM-EACT — `external_action_requests.status`

`proposed → awaiting_human_approval` (grant `requires_human_approval`) `→ approved → executing → executed`; `proposed → approved` (otomatik izinli); `awaiting_human_approval → rejected`; `executing → failed`; `proposed/awaiting_human_approval → expired`. `executed` yalnız normal application use-case başarıyla çalıştığında ve `external_action_executions` (1:1) yazıldığında.

### 2.43 İK ve diğer küçük makineler

- SM-LEAVE `leave_requests.status`: `draft → submitted → approved | rejected`; `submitted → withdrawn`; `approved → cancelled` (başlangıçtan önce).
- SM-EXP `expense_claims.status`: `draft → submitted → approved → paid`; `submitted → rejected → draft`; `draft/submitted → cancelled`.
- SM-TS `timesheets.status`: `draft → submitted → approved → locked`; `submitted → rejected → draft`.
- SM-WFR `workforce_requests.status`: `draft → submitted → approved → fulfilled`; `submitted → rejected`; terminal olmayan → `cancelled`. İşe alma kararı ile muhasebe/bordro açılış handoff'u ayrıdır: `personnel.activated` olayı finans/bordro görevini üretir; muhasebe işe alma kararı vermez.
- SM-CAND `candidates.status` / `applications.stage`: aday verisi rıza ve retention'a bağlıdır; `hired` olan aday `personnel` kaydına dönüştürülürken CV/mülakat notları restricted kapsamda kalır.
- SM-DELEG `delegations.status`: `pending → active` (onay) `→ expired` (`valid_until`) `| revoked`.
- SM-OPP `opportunities.stage`: `identified → qualified → bid_decision_pending → bid | no_bid`; `bid → converted_to_proposal`; `identified/qualified/bid_decision_pending → dropped`.
- SM-TN `tender_notices.status`: `captured → screening → pursuing | not_pursued`; `pursuing → submitted → awarded | lost`; terminal olmayan → `cancelled`.
- SM-SR `service_requests.status`: `received → triaged → scheduled → in_progress → resolved → closed`; terminal olmayan → `cancelled`.
- SM-WO `work_orders.status`: `planned → dispatched → in_progress → done → verified`; terminal olmayan → `cancelled`.
- SM-STOCKCOUNT `stock_counts.status`: `planned → counting → review → approved → posted` (fark hareketleri idempotent); terminal olmayan → `cancelled`.
- SM-TRANSFER `stock_transfers.status`: `draft → approved → dispatched → received → posted`; terminal olmayan → `cancelled`.

## 3. Makineler arası değişmezler

1. Yalnız `approved` sosyal içerik sürümü hedeflenebilir; hedef, hesap, dil veya zaman değişirse yeni target ve etki matrisine göre yeniden onay gerekir.
2. `report_periods.template_version_id` dönem açılışındaki yayımlı sürümdür; sonraki sürüm açık dönemi değiştirmez.
3. Gate `passed` olmadan bağlı department handoff `accepted` olamaz; handoff `accepted` olmadan hedef workstream `active` olamaz (hard dependency).
4. `PRJ-n` yalnız 2.24 transaction'ında ve yalnız aynı business case için üretilir; ikinci üretim `uk_business_codes_case_kind` ile reddedilir.
5. Kritik bildirim `read` ile kapanmaz; `acknowledged` ve gerekirse `resolved` gerekir.
6. `inventory_transactions` yalnız `goods_receipts.posted`, `stock_transfers.posted`, `stock_counts.posted`, iş paketi malzeme çıkışı ve servis iş emri üzerinden üretilir; doğrudan ekleme yoktur.
7. Onay kararı anında `subject_hash` yeniden doğrulanır; uyuşmazlık `invalidated` üretir.
8. Dış AI servisi hiçbir makinede doğrudan durum yazmaz; yalnız `external_action_requests` → normal use-case yoluyla ve grant allowlist'iyle.
9. `personnel.status = separated` olan personel yeni atama/onay alamaz; açık sorumlulukları transfer ledger'ı ile yeniden çözülür.
10. Occurrence tarihi değişirse çalışmış reminder güncellenmez; `superseded` + yeni instance.

## 4. Kritik tetikleyici kataloğu (`business_alerts.trigger_code`)

| Kod | Kaynak olay/koşul | Severity | Owner resolver | Ack |
|---|---|---|---|---|
| `report.overdue` | `report_assignment.became_overdue` | warning → high (grace sonrası) | line_manager | ✓ (high) |
| `report.review_sla` | `submitted` + `sla_minutes` aşıldı | warning | reviewer → org_unit_manager | ✓ |
| `tender.deadline_near` | `tender_deadlines.due_at_utc − eşik` | high | business_case owner | ✓ |
| `approval.overdue` | `approval.expired` / adım SLA | high | ars.resolved_personnel_id → line_manager | ✓ |
| `project.critical_path_delay` | `delay_events` hard dependency üzerinde | critical | project_manager | ✓ |
| `project.gate_condition_due` | `condition_due_on` yaklaştı/geçti | high | risk_owner | ✓ |
| `budget.margin_breach` | commitments+actuals > budget eşiği | critical | project_manager + executive | ✓ |
| `stock.threshold` | Bakiye projeksiyonu < eşik | warning | procurement | — |
| `delivery.late` | `delivery_schedules.planned_delivery_on` geçti, `delivered` değil | high | logistics owner | ✓ |
| `test.failed` / `ncr.critical` | `test_executions.failed`, `ncrs.severity = critical` | high/critical | software/field owner | ✓ |
| `hse.incident` | `incidents.severity ∈ {high, critical}` | critical | project_manager + executive | ✓ + resolution |
| `finance.tax_payment_due` | `tax_obligations.due_on` / `supplier_invoices.due_date` yaklaştı | high | accounting | ✓ |
| `special_day.t_minus_4` | SM-SDRI `T_MINUS_4` triggered | warning | content_creator + function_owner | ✓ (görev) |
| `special_day.t_minus_3` | `T_MINUS_3` predicate doğru | critical | function_owner + escalation target | ✓ |
| `special_day.late_entry` | Occurrence T−4'ten geç girildi | critical | function_owner | ✓ |
| `social.approval_sla` | Sürüm `submitted`/`approval_pending` SLA aşımı | high | approver → function_owner | ✓ |
| `social.publish_failed` | SM-SCT `manual_action_required` | high | publisher + function_owner | ✓ |
| `social.credential_expiring` | SM-INTCONN `expiring/expired` | high/critical | connection owner + function_owner | ✓ |
| `social.metric_sync_lag` | `last_success_at` eşiği aştı | warning | analyst | — |
| `external_check.timeout` | SM-EAR `expired` | warning | requester | — |
| `department_handoff.sla` | `department_handoff.sla_breached` | high | target workstream owner | ✓ |

## 5. İşlem kataloğu (`personnel_activities.action_code`)

Sütunlar: olay, sürüm, aggregate, üreten use-case, minimum payload (ek olarak her kayıtta `id`, `subject_id`, `occurred_at`, `personnel_id`), idempotency/dedupe anahtarı, tüketiciler (N = notification rule, P = projector/read model, J = job, X = harici endpoint).

### 5.1 Organizasyon ve personel

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `personnel.created` | 1 | personnel | `CreateEmployee` | personnel_id, personnel_no | aggregate+version | P (personnel_directory) |
| `personnel.activated` | 1 | personnel | `ActivateEmployee` | personnel_id, personnel_id, legal_entity_id | | N (HR/finans handoff), P |
| `personnel.suspended` / `.separated` / `.archived` | 1 | personnel | `ChangeEmployeeStatus` | personnel_id, reason_code, effective_at | | N, P, J (kapanış görevleri, anonimleştirme) |
| `personnel.position_assigned` / `.position_ended` | 1 | personnel | `AssignPosition` | personnel_id, position_id, valid_from/until, is_primary | assignment_id | N (personel/yönetici), P (org tree) |
| `personnel.manager_changed` | 1 | personnel | `AssignReportingRelationship` | personnel_id, manager_employee_id, relation_type, valid_from | relationship_id | N, P |
| `org_unit.created` / `.relation_changed` / `.dissolved` | 1 | org_unit | `ManageOrgUnit` | org_unit_id, parent_id, valid_from | | P (org tree) |
| `delegation.activated` / `.revoked` / `.expired` | 1 | delegation | `GrantDelegation` / scheduler | delegation_id, grantor, delegate, capability, scope | delegation_id+status | N, P |
| `certification.expiring` / `.expired` | 1 | personnel_certification | scheduler | personnel_id, certification_id, valid_until | cert_id+status | N, alert |
| `functional_area.responsibility_assigned` / `.responsibility_ended` | 1 | functional_area | `AssignFunctionalAreaRole` | area_id, role_code, target, valid_from/until | responsibility_id | N, P |
| `functional_area.transition_started` / `.transition_completed` | 1 | functional_area | `BindFunctionalAreaToOrgUnit` | area_id, from_mode, to_mode, target_org_unit_id, effective_at | transition_event_id | N, J (transfer ledger), P |

### 5.2 Raporlama

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `report_template.version_published` / `.version_withdrawn` | 1 | report_template | `PublishReportTemplateVersion` | template_id, version_id, version_no | version_id | P, N (koordinatör) |
| `report_period.generated` / `.opened` / `.closed` | 1 | report_period | scheduler `GenerateReportPeriods` | period_id, schedule_id, period_start/end, due_at | schedule+period_start | J (assignment üretimi), P |
| `report_assignment.created` / `.opened` | 1 | report_assignment | `GenerateReportAssignments` | assignment_id, personnel_id, due_at, template_version_id | assignment idempotency_key | N (çalışan), P (inbox) |
| `report_assignment.due_soon` | 1 | report_assignment | scheduler | assignment_id, due_at | assignment+offset | N |
| `report_assignment.became_overdue` | 1 | report_assignment | scheduler | assignment_id, personnel_id, reviewer snapshot | assignment_id (tek) | N (çalışan + line_manager), alert |
| `report_assignment.escalated` | 1 | report_assignment | scheduler | assignment_id, level | assignment+level | N (escalation chain) |
| `report_assignment.waived` / `.closed` | 1 | report_assignment | `WaiveReportAssignment` | assignment_id, reason | | P |
| `report_submission.submitted` | 1 | report_submission | `SubmitReport` | submission_id, version_id, version_no, answer_set_hash | version_id | N (reviewer), P, J (PDF gerekiyorsa) |
| `report_submission.external_check_requested` / `.external_check_failed` | 1 | report_submission | `RequestExternalReportCheck` | submission_id, version_id, request_id | request idempotency | X (analysis endpoint), P |
| `report_submission.approved` / `.revision_required` / `.rejected` | 1 | report_submission | `ReviewReport` | submission_id, version_id, decision, reviewer | review_id | N (çalışan), P |
| `report_submission.locked` | 1 | report_submission | `LockReport` | submission_id, version_id | | P (metric facts, compliance) |
| `generated_output.completed` / `.failed` / `.blocked` | 1 | generated_output | `RenderOutput` job | output_id, source_type/id, locale, file_object_id | output idempotency | N (talep eden) |

### 5.3 Bildirim, kritik iş, görev

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `notification.created` | 1 | notification_instance | `DispatchNotification` | notification_id, rule_version_id, severity, recipient_count | dedupe_key | J (channel delivery) |
| `notification.acknowledged` / `.resolved` / `.escalated` / `.recipient_unresolved` | 1 | notification_instance | ilgili use-case | notification_id, recipient_id, level | recipient+action | P (operasyon kutusu), N |
| `business_alert.opened` / `.acknowledged` / `.resolved` / `.closed` / `.cancelled` | 1 | business_alert | `RaiseBusinessAlert` … | alert_id, trigger_code, severity, subject | dedupe_key | N, P |
| `task.created` / `.assigned` / `.completed` / `.cancelled` / `.overdue` | 1 | task | `CreateTask` … | task_id, context, assignee, due_at | task idempotency_key | N, P |

### 5.4 DMS, iletişim, e-posta

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `file_object.uploaded` / `.scanned` / `.quarantined` | 1 | file_object | `StoreUploadedFile` / scan job | file_object_id, sha256, scan_status | file_object_id+status | J (thumbnail), N (uploader, karantina) |
| `document_revision.drafted` / `.review_requested` / `.approved` / `.issued` / `.withdrawn` | 1 | document | `CreateDocumentRevision` … | document_id, revision_id, revision_code, status | revision_id+status | N, P (register), J (distribution) |
| `document.distributed` / `.acknowledged` | 1 | document | `DistributeDocument` / `AcknowledgeDocument` | revision_id, recipient, kind | distribution/ack id | N, P |
| `transmittal.issued` | 1 | transmittal | `IssueTransmittal` | transmittal_id, revision_ids | transmittal_id | N, P |
| `legal_hold.activated` / `.released` | 1 | legal_hold | `ManageLegalHold` | hold_id, document_ids | hold_id+status | J (retention purge kilidi) |
| `message.sent` / `.edited` / `.redacted` | 1 | conversation | `PostMessage` … | conversation_id, message_id, sequence, mentions | message_id+version | N (mention/üye), P (unread) |
| `inbound_email.received` / `.processed` / `.needs_review` / `.failed` / `.permanently_failed` | 1 | inbound_email_message | `IngestInboundEmail` | message_id, mailbox_id, subject_hash, links | message_id_hash | J (attachment DMS), N (mailbox owner), P |

### 5.5 Sosyal medya

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `social_account.registered` / `.verified` / `.suspended` / `.transferred` / `.closed` | 1 | social_account | `RegisterSocialAccount` … | account_id, platform, status | account_id+status | N (function_owner), P (health) |
| `social_account.connection_expiring` / `.connection_expired` | 1 | social_account | scheduler | account_id, connection_id, expires_at | connection_id+status | N, alert |
| `social_content.created` / `.planned` / `.completed` / `.cancelled` | 1 | social_content_item | `CreateSocialContent` … | item_id, kind, owner | item_id+lifecycle | P (calendar) |
| `social_content_version.submitted` / `.changes_requested` / `.approval_requested` / `.approved` / `.rejected` | 1 | social_content_item | `SubmitSocialContentForReview` / `ApproveSocialContentVersion` | item_id, version_id, content_hash | version_id+status | N (reviewer/approver/creator), P |
| `social_target.planned` / `.scheduled` / `.queued` / `.published` / `.failed` / `.cancelled` | 1 | social_content_target | `ScheduleSocialPublication` / `RequestSocialPublication` / `RecordManualSocialPublication` | target_id, account_id, planned_at_utc, publication_id | target_id+status | J (publish worker), N, P (calendar) |
| `social_publication.corrected` / `.removed` | 1 | social_publication | `RecordPublicationCorrection` | publication_id, action | correction_id | P, N |
| `special_day_occurrence.verified` / `.superseded` | 1 | special_day_definition | `VerifySpecialDayOccurrence` | occurrence_id, local_date | occurrence_id+state | J (plan/reminder üretimi) |
| `special_day_plan.readiness_changed` / `.marked_not_applicable` / `.missed` | 1 | special_day_plan | `UpdateSpecialDayReadiness` | plan_id, readiness | plan_id+readiness | P (readiness), N |
| `special_day_reminder.triggered` / `.skipped` / `.superseded` | 1 | special_day_plan | `DispatchSpecialDayReminder` | instance_id, step_code, recipients snapshot | dedupe_key | N, J (görev), alert |
| `social_metrics.ingested` | 1 | social_account | `SyncSocialMetrics` / `RecordManualMetrics` | batch_id, account_id, window, observation_count | source_dedupe_key | P (performance) |
| `social_metrics.sync_failed` | 1 | social_account | `SyncSocialMetrics` | account_id, safe_error_code | run idempotency | N, alert |

### 5.6 Party, İş Alım, devir

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `party.created` / `.role_assigned` / `.merged` | 1 | party | `RegisterParty` … | party_id, role, merged_into | party_id+action | P (360) |
| `business_case.created` / `.stage_changed` / `.won` / `.lost` / `.cancelled` | 1 | business_case | `OpenBusinessCase` / `TransitionBusinessCase` | case_id, sequence_no, stage, outcome | case_id+stage | N, P (pipeline, lineage) |
| `business_code.issued` | 1 | business_case | `IssueBusinessCode` | case_id, code_kind, formatted_code | case+kind | P (lineage) |
| `tender.captured` / `.deadline_near` | 1 | tender_notice | `CaptureTenderNotice` / scheduler | notice_id, source, deadline | notice_id / deadline_id | N, alert |
| `proposal.version_approved` / `.submitted_to_customer` / `.withdrawn` | 1 | proposal | `ApproveProposalVersion` / `SubmitProposal` | proposal_id, version_id, version_hash | version_id+status | N, P |
| `contract.version_executed` | 1 | contract | `ExecuteContractVersion` | contract_id, version_id | version_id | N, P |
| `operation_handoff.submitted` / `.returned` / `.accepted` | 1 | operation_handoff | `SubmitOperationHandoff` / `AcceptOperationHandoff` | handoff_id, version_id, snapshot_hash | version_id+status | N (Proje Grubu / Teklif), P |

### 5.7 Operasyon

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `project.opened` / `.activated` / `.acceptance_started` / `.warranty_started` / `.closed` / `.suspended` / `.cancelled` | 1 | project | `AcceptOperationHandoff` / `TransitionProject` | project_id, code, status | project_id+status | N, P (portfolio) |
| `project.focus_changed` | 1 | project | `ChangePrimaryFocus` | project_id, from/to workstream, direction, reason | focus_history_id | N, P |
| `workstream.ready` / `.activated` / `.blocked` / `.unblocked` / `.review_requested` / `.completed` / `.waived` | 1 | project | `TransitionWorkstream` | project_id, workstream_id, group, status | workstream_id+status | N, P (board), J (successor evaluation) |
| `gate.preparing` / `.ready_for_review` / `.approval_requested` / `.passed` / `.conditionally_passed` / `.rejected` / `.reopened` | 1 | project | `TransitionStageGate` | project_id, stage_code, status, conditions | instance_id+status | N, P (gate readiness), J (handoff tetikleme) |
| `department_handoff.submitted` / `.accepted` / `.rejected` / `.sla_breached` | 1 | project | `SubmitDepartmentHandoff` / `AcceptDepartmentHandoff` | project_id, handoff_id, source/target | version_id+status | N, alert |
| `project.issue_raised` / `.risk_materialized` / `.delay_detected` / `.change_approved` / `.decision_recorded` | 1 | project | ilgili use-case | project_id, record_id, severity | record_id+status | N, P, alert (critical) |
| `purchase_requisition.submitted` / `.approved` / `.rejected` / `.ordered` | 1 | purchase_requisition | `SubmitRequisition` … | requisition_id, project_id, status | requisition_id+status | N, P |
| `rfq.issued` / `.closed`; `award.recommended` / `.approved` | 1 | supplier_rfq | ilgili use-case | rfq_id, invitee/quote ids | id+status | N, P |
| `purchase_order.issued` / `.acknowledged` / `.delivered` / `.closed` / `.version_superseded` | 1 | purchase_order | `IssuePurchaseOrder` … | po_id, version_id, supplier | version_id+status | N, P, J (commitment) |
| `delivery.late` | 1 | purchase_order | scheduler | schedule_id, planned_on | schedule_id (tek) | alert |
| `shipment.status_changed`; `customs.cleared` | 1 | shipment | `UpdateShipment` | shipment_id, status, eta | shipment_id+status | N, P |
| `goods_receipt.posted` | 1 | goods_receipt | `PostGoodsReceipt` | receipt_id, lines, transaction_ids | receipt_id | P (stock balance), J (invoice matching), J (site handoff) |
| `inventory.transaction_posted` | 1 | catalog_item | `PostInventoryTransaction` | transaction_id, type, item, qty, bins | idempotency_key | P (balance) |
| `stock.threshold_breached` | 1 | catalog_item | projector | item_id, warehouse_id, balance | item+warehouse+day | alert |
| `invoice.received` / `.matched` / `.disputed` / `.approved_for_payment` / `.paid` | 1 | supplier_invoice | `MatchInvoice` … | invoice_id, po/receipt lines | invoice_id+status | N, P (cost), alert (dispute) |
| `payment_request.approved` / `.paid` | 1 | payment_request | `ApprovePaymentRequest` / `RecordPayment` | request_id, amount, currency | id+status | N, P |
| `budget.version_approved`; `budget.margin_breach` | 1 | project_budget | `ApproveBudgetVersion` / projector | project_id, version_id, variance | version_id / project+day | N, alert |
| `engineering.revision_issued`; `technical_requirement.verified` | 1 | project | ilgili use-case | deliverable_id, revision_id | revision_id | N, P |
| `field.installed_quantity_recorded`; `field.photo_added` | 1 | project | `RecordInstalledQuantity` | work_package_id, qty | record_id | P (progress) |
| `inspection.completed`; `ncr.raised` / `.closed`; `punch.raised` / `.cleared`; `incident.reported` / `.closed`; `permit.issued` / `.expired` | 1 | project | ilgili use-case | record_id, severity/category | record_id+status | N, P, alert |
| `test.started` / `.step_completed` / `.hold_point_released` / `.passed` / `.failed`; `commissioning.package_commissioned`; `acceptance.certificate_recorded` | 1 | project | ilgili use-case | execution_id, step, outcome, certificate_no | id+status | N, P, alert (failed) |
| `warranty.expiring`; `service_request.received` / `.resolved`; `work_order.done`; `maintenance.due` | 1 | installed_asset | scheduler / ilgili use-case | asset_id, request_id, due_on | id+status | N, P, J (work order) |

### 5.8 Workflow, onay, entegrasyon, harici analiz

| Olay | v | Aggregate | Use-case | Payload | Idempotency | Tüketici |
|---|---|---|---|---|---|---|
| `approval.requested` / `.step_activated` / `.approved` / `.rejected` / `.cancelled` / `.expired` / `.invalidated` | 1 | approval_request | `RequestApproval` / `DecideApproval` | request_id, subject, step, decision, hash | request_id+status(+step) | N (onaycı/talep sahibi), P (onay kutusu), J (subject transition) |
| `workflow.started` / `.task_activated` / `.completed` / `.failed` | 1 | workflow_instance | workflow engine | instance_id, step_code | instance_id+status | N, P |
| `integration.connection_expiring` / `.connection_expired` / `.connection_failed` | 1 | integration_connection | scheduler / health check | connection_id, endpoint, expires_at | connection_id+status | N, alert |
| `inbox.message_received` / `.processed` / `.dead_lettered` | 1 | inbox_message | `ProcessInboxMessage` | message_id, endpoint, kind | endpoint+external_message_id | J, alert |
| `external_analysis.requested` / `.sent` / `.succeeded` / `.failed` / `.expired` | 1 | external_analysis_request | `RequestExternalAnalysis` / callback handler | request_id, capability, subject, result_code | request idempotency_key(+status) | N (requester), P (status), J (action requests) |
| `external_action.proposed` / `.awaiting_approval` / `.executed` / `.rejected` | 1 | external_action_request | `ProposeExternalAction` / `ExecuteExternalAction` | action_id, command_code, target | action idempotency_key | N (yönetici), P, audit |

## 6. Başlangıç bildirim kuralı seti (`notification_rules`)

| Kural kodu | Olay | Severity | Alıcı resolver'ları (seviye 0 → 1 → 2) | Kanal | Ack | Dedupe |
|---|---|---|---|---|---|---|
| `NR-RPT-OPENED` | `report_assignment.opened` | info | subject_employee | in_app | — | per_event |
| `NR-RPT-DUE-SOON` | `report_assignment.due_soon` | warning | subject_employee | in_app, email | — | per_subject_once |
| `NR-RPT-OVERDUE` | `report_assignment.became_overdue` | warning | subject_employee → line_manager → org_unit_manager | in_app, email | ✓ (1+) | per_subject_once |
| `NR-RPT-SUBMITTED` | `report_submission.submitted` | info | reviewer snapshot | in_app | — | per_event |
| `NR-RPT-REVIEWED` | `report_submission.approved/revision_required/rejected` | info/warning | subject_employee | in_app, email | — | per_event |
| `NR-RPT-REVIEW-SLA` | `report.review_sla` | warning | reviewer → org_unit_manager | in_app | ✓ | per_subject_window |
| `NR-CRIT-ALERT` | `business_alert.opened` (severity ≥ high) | critical | alert owner → line_manager → executive | in_app, email (mandatory) | ✓ | dedupe_key |
| `NR-TASK-ASSIGNED` | `task.assigned` | info | assignee | in_app | — | per_event |
| `NR-TASK-OVERDUE` | `task.overdue` | warning | assignee → task owner | in_app | — | per_subject_window |
| `NR-DOC-ACK` | `document.distributed` (`requires_acknowledgement`) | warning | recipient | in_app, email | ✓ | per_event |
| `NR-MSG-MENTION` | `message.sent` (mention) | info | mentioned personnel | in_app | — | per_event |
| `NR-APR-STEP` | `approval.step_activated` | warning | resolved approver | in_app, email | — | per_event |
| `NR-APR-EXPIRED` | `approval.expired` | high | approver → line_manager | in_app, email | ✓ | per_subject_once |
| `NR-SM-REVIEW` | `social_content_version.submitted` | info | functional_area_role: reviewer | in_app | — | per_event |
| `NR-SM-APPROVAL` | `social_content_version.approval_requested` | warning | functional_area_role: approver | in_app, email | — | per_event |
| `NR-SM-T4` | `special_day_reminder.triggered` (`T_MINUS_4`) | warning | functional_area_role: content_creator, function_owner | in_app, email | ✓ | dedupe_key |
| `NR-SM-T3` | `special_day_reminder.triggered` (`T_MINUS_3`) | critical | functional_area_role: function_owner → escalation_target | in_app, email (mandatory) | ✓ | dedupe_key |
| `NR-SM-PUBLISH-FAIL` | `social_target.failed` (manual_action_required) | high | functional_area_role: publisher, function_owner | in_app, email | ✓ | per_subject_once |
| `NR-SM-CRED` | `social_account.connection_expiring/expired` | high/critical | connection owner, function_owner | in_app, email | ✓ | per_subject_window |
| `NR-EXT-RESULT` | `external_analysis.succeeded/failed/expired` | info/warning | requester | in_app | — | per_event |
| `NR-PRJ-GATE` | `gate.passed/conditionally_passed/rejected` | info/warning | project_manager, workstream owners | in_app | — | per_event |
| `NR-PRJ-HANDOFF` | `department_handoff.submitted/accepted/rejected` | warning | target/source workstream owner | in_app, email | ✓ (submitted) | per_event |
| `NR-HSE` | `incident.reported` (severity ≥ high) | critical | project_manager → executive | in_app, email (mandatory) | ✓ | dedupe_key |

Alıcı çözümü olay anındaki snapshot'tır; sonradan departmanlaşma, manager değişikliği veya rol devri geçmiş alıcıyı değiştirmez. Üstten alta bildirim scope üzerinden, alttan üste bildirim etkin manager/escalation zincirinden çözülür; boş pozisyon veya pasif kullanıcı `recipient_unresolved` üretir ve operasyon kutusuna düşer.

## 7. DB-G8 kontrol listesi

- Her sözlük durum kolonu için makine tanımı vardır; makine kodları Personel Hareketleri işlem kodlarını oluşturur.
- İşlem kataloğu `personnel_activities.action_code` ve `notification_rules.event_type` değer listesini oluşturur; yeni işlem karar kaydı gerektirir.
- Açık kararlar: D-02 (pilot şablonlar ve escalation süreleri), D-19 (T−4/T−3 saatleri ve gün tabanı), D-22 (waiver yetkilisi), D-34 (workflow semantiği), D-35 (bildirim resolver kuralları), D-36 (sosyal onay rotaları).
