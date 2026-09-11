# Konelsis Kurumsal Platform — Güvenlik sınıflandırma ve saklama matrisi

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Retention süreleri hâlâ hukuk/finans doğrulaması bekler.  
**Sürüm:** 0.8 / 5 Eylül 2026  
**Kaynak:** [01 §12, §16](01-teknik-mimari-plani.md), [03 §2, §5.4, §8, §9.5, §13](03-veri-tabani-tasarim-plani.md), [04 §11](04-sosyal-medya-ve-kurumsal-fonksiyon-plani.md), veri sözlüğü 06–12

## 1. Veri sınıfları ve işlem kuralları

| Sınıf | Kod | Tanım | Varsayılan erişim | Export/indirme | Dış AI'ya gönderim | Şifreleme | Log/audit |
|---|---|---|---|---|---|---|---|
| Public | **P** | Kamuya açık referans (ülke, para birimi, platform kataloğu) | Tüm oturumlu kullanıcılar | Serbest | İzinli (capability sınırında) | At-rest (disk) | Teknik log |
| Internal | **I** | Şirket içi operasyon verisi | Policy scope'una göre (birim/proje/alan) | Yetkili Query Service export'u, audit'li | Yalnız capability allowlist'inde ve `max_input_classification ≥ I` | At-rest | Business audit (kritik aksiyonlar) |
| Confidential | **C** | Ticari/finansal/kişisel iş verisi | Dar Policy scope + sahiplik | Yalnız yetkili rol; formula-injection temizliği; audit zorunlu | Yalnız açık capability grant ve alan allowlist'i | At-rest + TLS; seçili alanlar uygulama şifrelemesi | Business + security audit; download audit |
| Restricted | **R** | Ücret, sağlık, kimlik, banka, CV, disiplin/performans, marj, credential referansı, kritik altyapı | Yalnız açıkça atanmış rol (HR Admin, Finance, ilgili yönetici) — hiyerarşi otomatik erişim vermez | Varsayılan kapalı; istisna onaylı ve audit'li | Daima kapalı (`security_classifications.external_analysis_allowed = 0`) | Alan düzeyinde şifreleme (`*_enc`, `encryption_key_version`) | Her okuma güvenlik audit'i |

Genel kurallar:

- Sınıf, tablo varsayılanı ile başlar; `documents`, `conversations`, `functional_areas`, `business_cases`, `projects` gibi köklerde satır bazlı `classification_id` tablo varsayılanını yalnız yükseltebilir.
- Yönetici olmak özel mesaj, ücret, sağlık, disiplin/performans, aday/CV veya geçmiş ast verisine erişim vermez; Policy + veri sınıfı + sahiplik birlikte değerlendirilir.
- Bildirim gövdesi ve e-posta hassas veri taşımaz; yetkili kayda deep link verir.
- Teknik log, business audit ve security audit ayrıdır; mesaj/doküman/prompt içeriği teknik loga yazılmaz.
- Personel giriş parolaları hash'lenerek saklanır ve hiçbir kayıtta açık görünmez. Dış sistem bağlantı bilgisi veritabanında tutulmaz; entegrasyon kod içinde yapılır ve bilgiler `.env`/`config` üzerinden okunur (D-56).

## 2. Tablo sınıflandırma matrisi

Sütunlar: sınıf; PII (kişisel veri) var mı; şifreli alanlar; Policy kapsamı (erişim kararında kullanılan boyut); export; dış AI.

### 2.1 Çekirdek, kimlik, organizasyon, İK

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `organizations`, `countries`, `currencies`, `units_of_measure`, `security_classifications`, `retention_policies`, `business_calendars`, `business_calendar_weekdays`, `calendar_days` | P/I | — | — | Herkes (okuma); admin (yazma) | ✓ | ✓ |
| `legal_entities` | I | — | — | Executive/System Admin yazma | ✓ | ✓ |
| `personnel` | C | ✓ (e-posta, ad) | `password` hash | Kendi kaydı; System Admin | ✗ | ✗ |
| `personnel` | C/I | — | — | System Admin | ✗ | ✗ |
| `org_units`, `org_unit_relations`, `positions`, `teams`, `team_memberships` | I | — | — | Herkes okuma; HR Admin yazma | ✓ | ✓ (yapı) |
| `personnel` | C | ✓ (ad, iş e-postası/telefonu, foto) | — | Personel dizini: temel alanlar herkese; durum/tarih alanları HR + yöneticiye | ✓ (dizin alanları) | ✗ (varsayılan); yalnız ad/pozisyon allowlist'i |
| `personnel_private_profiles` | R | ✓ (kimlik, banka, sağlık, acil kişi) | `*_enc` alanlarının tamamı | Yalnız HR Admin; kendi kaydını görüntüleme | ✗ | ✗ |
| `employments`, `position_assignments`, `reporting_relationships`, `personnel_status_histories` | C/I | — | — | HR Admin; ilgili yönetici (kendi ekibi); kendisi | ✓ (HR) | ✗ |
| `delegations` | C | — | — | Grantor, delegate, System Admin, Auditor | ✓ (audit) | ✗ |
| `functional_areas*` | I | — | — | Alan rolleri; HR Admin | ✓ | ✓ (yapı) |
| `competencies`, `certifications`, `trainings` | I | — | — | Herkes okuma | ✓ | ✓ |
| `personnel_competencies`, `personnel_certifications`, `training_attendances` | C | ✓ | — | Kendisi, yöneticisi, HR, saha planlayıcı (sertifika geçerliliği) | ✓ (HR) | ✗ |
| `leave_types` | I | — | — | Herkes | ✓ | ✓ |
| `leave_requests`, `attendance_entries`, `timesheets`, `timesheet_lines` | C | ✓ | — | Kendisi, doğrudan amir, HR | ✓ (HR/finans) | ✗ |
| `expense_claims`, `expense_lines` | C | ✓ (harcama) | — | Kendisi, amir, finans | ✓ (finans) | ✗ |
| `workforce_requests`, `staffing_options`, `resource_assignments` | C | — | — | Talep eden birim yöneticisi, HR, PM | ✓ (HR) | ✗ |
| `job_requisitions`, `job_post_versions` | I/R | — | — | HR, hiring manager; yayımlanan ilan metni I | ✓ (HR) | ✗ (varsayılan; ilan metni allowlist ile açılabilir — D-37) |
| `candidates`, `applications`, `interviews` | R | ✓ (aday kimliği, CV, notlar) | CV/notlar DMS restricted | Yalnız HR Admin ve atanmış mülakatçı (kendi mülakatı) | ✗ | ✗ |
| `performance_cycles` | I | — | — | HR | ✓ | ✓ |
| `performance_records`, `development_actions` | R | ✓ | — | Kendisi (final), reviewer, HR Admin | ✗ | ✗ |

### 2.2 Raporlama, bildirim, görev

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `report_templates`, `report_template_versions`, `report_sections`, `report_questions`, `report_question_options`, `report_workflow_bindings`, `report_schedules*`, `report_periods` | I | — | — | Report Coordinator yazma; herkes okuma | ✓ | ✓ (şablon tanımı `template_definition` artifact'ı) |
| `report_assignments`, `report_submissions`, `report_submission_versions`, `report_answers*`, `report_reviews`, `report_status_transitions` | C (şablon sınıfı yükseltebilir) | ✓ (gönderen) | — | Gönderen, reviewer snapshot, birim yöneticisi (scope), Report Coordinator, Auditor | ✓ (Query Service; sınıfa göre) | Yalnız şablonda `external_check_mode ≠ off`, capability grant ve şablon sınıfı ≤ capability üst sınırı; `restricted` cevap alanları asla |
| `report_metric_facts` | C | — | — | Dashboard scope | ✓ | ✗ |
| `notification_rules*` | I | — | — | System Admin/Report Coordinator | ✓ | ✗ |
| `notification_instances`, `notification_recipients`, `notification_delivery_attempts`, `notification_receipts`, `notification_acknowledgements`, `notification_escalations` | I | ✓ (alıcı) | — | Alıcı; System Admin; Auditor | ✗ | ✗ |
| `notification_preferences` | I | — | — | Kendisi | ✗ | ✗ |
| `tasks`, `task_assignments`, `task_dependencies` | I (bağlam sınıfı yükseltebilir) | — | — | Owner, assignee, bağlam scope'u | ✓ | ✗ |
| `business_alerts*` | I/C | — | — | Owner, ack veren, scope yöneticileri | ✓ | ✗ |

### 2.3 DMS, iletişim, e-posta

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `file_objects` | Bağlı nesnenin sınıfı | Olabilir | Object storage at-rest; private bucket; signed URL | İndirme yetkisi her istekte bağlı iş nesnesinden hesaplanır | Download audit zorunlu | Yalnız bağlı revizyon izinliyse |
| `document_types`, `document_templates`, `document_template_versions` | I | — | — | System Admin/DMS admin | ✓ | ✓ |
| `documents`, `document_revisions`, `document_revision_files`, `document_links`, `document_reviews`, `document_distributions`, `document_acknowledgements`, `transmittals`, `transmittal_items` | Satır `classification_id` | Olabilir | — | Doküman sahibi, dağıtım alıcıları, proje/birim scope'u, sınıf eşiği | Sınıfa göre; C ve üzeri audit'li | Yalnız `classification ≤ capability üst sınırı` ve açık artifact seçimi |
| `generated_outputs` | Kaynağın sınıfı | Olabilir | — | Kaynak kaydın Policy'si | Kaynakla aynı | ✗ |
| `legal_holds`, `legal_hold_documents` | C | — | — | Hukuk/Executive/Auditor | ✓ (hukuk) | ✗ |
| `conversations`, `conversation_memberships`, `messages`, `message_versions`, `message_mentions`, `message_attachments`, `conversation_read_cursors`, `message_business_links` | Satır `classification_id` (varsayılan I; `direct` C) | ✓ | — | Yalnız aktif üyeler (+ `history_visible_from`); yönetici zinciri özel konuşmaya erişmez; Auditor yalnız hukuki süreçte ve kayıtlı gerekçe ile | ✗ (yalnız hukuki export, audit'li) | ✗ |
| `mailboxes` | C | — | Credential secret manager'da | System Admin, mailbox owner | ✗ | ✗ |
| `inbound_email_messages`, `inbound_email_recipients`, `inbound_email_attachments`, `inbound_email_business_links`, `email_processing_attempts` | C | ✓ (gönderen/alıcı) | — | Mailbox purpose'a bağlı rol (İş Geliştirme/Teklif), bağlı business case scope'u | ✓ (bağlı kayıtla) | Yalnız DMS'e aktarılan ve izinli ek |

### 2.4 Sosyal medya

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `social_platforms`, `social_metric_definitions`, `social_platform_metric_mappings`, `special_day_calendars`, `special_day_definitions`, `special_day_translations`, `special_day_occurrences` | P/I | — | — | Analyst/function_owner yazma; herkes okuma | ✓ | ✓ |
| `social_accounts`, `social_account_identifier_histories` | C | — | Credential yok; sahiplik kanıtı DMS | Alan rolleri; hesap listesi I görünümü (ad, URL) herkese | ✓ (alan) | ✗ (hesap adı/URL allowlist ile) |
| `social_account_connections`, `social_connection_health_checks`, `social_webhook_receipts`, `social_sync_runs`, `social_metric_sync_cursors` | C | — | `secret_reference` yalnız referans | function_owner, System Admin | ✗ | ✗ |
| `social_campaigns`, `social_content_items`, `social_content_versions`, `social_content_version_translations`, `social_content_assets`, `social_content_reviews`, `social_content_targets` | C (yayımlanana kadar); yayımlanan içerik P | — | — | creator (kendi), reviewer, approver, publisher, function_owner; taslak içerik alan dışına kapalı | ✓ (alan) | Yalnız ileride onaylanacak ayrı capability (metin/etiket önerisi); taslak sonuç insan onayını atlayamaz |
| `social_publications`, `social_publication_attempts`, `social_publication_corrections` | I (yayımlanmış) | — | — | Alan rolleri; herkes okuma (yayın URL) | ✓ | ✓ (URL/metrik) |
| `social_reminder_policy_versions*`, `social_special_day_rules`, `social_special_day_plans`, `social_special_day_reminder_instances`, `social_reminder_instance_recipients` | I | ✓ (alıcı) | — | Alan rolleri | ✓ | ✗ |
| `social_metric_ingestion_batches`, `social_account_metric_observations`, `social_publication_metric_observations`, `social_kpis*` | I | — | Raw payload object storage'da, token/PII temizlenmiş | analyst, function_owner; dashboard Executive | ✓ | ✓ (toplam metrikler) |

### 2.5 Party, İş Alım, devir

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `parties`, `party_roles`, `organization_profiles`, `addresses`, `party_licenses`, `party_certificates` | I | Firma verisi | — | İş Geliştirme, Teklif, Satın Alma, PM; herkes ad okuma | ✓ | ✓ (firma adı/rol) |
| `person_profiles`, `communication_points` (kişi), `contact_relationships` | C | ✓ (kişi iletişim) | — | İş Geliştirme/Teklif/Satın Alma scope'u; rıza durumu | ✓ (rıza varsa) | ✗ |
| `party_annual_reviews` | C | — | — | İş Geliştirme yönetimi | ✓ | ✗ |
| `business_cases`, `business_codes`, `opportunities`, `opportunity_stage_histories`, `business_development_activities*`, `tender_sources`, `tender_notices*`, `tender_requirements`, `tender_deadlines` | C | ✓ (katılımcılar) | — | Case owner, İş Geliştirme, Teklif, Yönetim; pipeline özeti Executive | ✓ | Yalnız ileride onaylanacak ihale ön eleme/şartname özeti capability'si; şartname dokümanı artifact olarak |
| `proposals`, `proposal_versions`, `proposal_documents`, `compliance_items`, `deviations`, `brand_items`, `responsibility_matrix_items`, `boq_items` | C | — | — | Teklif Grubu, Proje Grubu (kritik rota), onay matrisi | ✓ (Teklif) | ✗ (varsayılan) |
| `proposal_versions.margin_pct`, `estimate_versions`, `estimate_lines`, `pricing_scenarios` | R | — | — | Yalnız Teklif fiyatlandırma rolü, final onaycı, Executive | ✗ | ✗ |
| `contracts*`, `operation_handoffs*`, `handoff_items`, `handoff_reviews` | C | — | — | Teklif, Hukuk, Proje Grubu, PM | ✓ (hukuk/PM) | ✗ |

### 2.6 Operasyon

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `projects`, `project_components`, `project_workstreams`, `workstream_dependencies`, `project_focus_histories`, `work_packages*`, `stage_*`, `project_stage_*`, `department_handoff*`, `wbs_nodes`, `milestones`, `progress_snapshots`, `project_tasks`, `project_issues`, `project_risks`, `delay_events`, `recovery_actions`, `project_decisions` | I/C (proje `classification_id`) | — | — | Proje rolü (PM, workstream owner, ekip), yönetim portföy görünümü | ✓ (proje scope) | Yalnız ileride onaylanacak gecikme tahmini capability'si (özet alanlar) |
| `cbs_nodes`, `wbs_cbs_mappings`, `schedule_baselines`, `budget_baselines`, `project_changes`, `commercial_clarifications` | C | — | — | PM, Muhasebe, Yönetim | ✓ | ✗ |
| `commercial_exposures`, `project_budgets`, `budget_versions`, `budget_lines`, `commitments`, `actual_cost_references`, `supplier_invoices`, `invoice_lines`, `invoice_matches`, `tax_obligations`, `payment_requests`, `payment_request_invoices`, `payments`, `cash_flow_forecasts`, `progress_claims*`, `retentions` | R | — | — | Muhasebe/Finans, PM (kendi projesi özet), Executive; onay matrisi | ✗ (yalnız finans export'u audit'li) | ✗ |
| `catalog_categories`, `brands`, `catalog_items`, `approved_equivalents`, `units_of_measure` | I | — | — | Satın Alma yazma; herkes okuma | ✓ | ✓ |
| `purchase_requisitions*`, `supplier_rfqs*`, `supplier_rfq_invitees`, `purchase_orders`, `purchase_order_versions`, `purchase_order_lines`, `delivery_schedules` | C | — | — | Satın Alma, talep eden, PM, onay matrisi | ✓ | ✗ |
| `supplier_quotes*`, `supplier_quote_lines`, `bid_comparisons*`, `award_recommendations` | R | — | — | Satın Alma değerlendirme rolü, onaycı | ✗ | ✗ |
| `carriers`, `shipments`, `shipment_items`, `customs_records`, `warehouses`, `warehouse_locations`, `bins`, `goods_receipts*`, `receipt_inspections`, `stock_lots`, `serialized_items`, `stock_reservations`, `inventory_transactions`, `stock_counts*`, `stock_transfers*` | I/C (gümrük C) | — | — | Lojistik, depo, Satın Alma, PM, Saha | ✓ | ✗ |
| `engineering_deliverables`, `engineering_revisions`, `technical_requirements`, `work_plans`, `procedures`, `method_statements` | C (doküman sınıfı) | — | — | Yazılım/Teknik Grup, PM, Saha (dağıtım) | ✓ | Doküman capability'si ile |
| `crews`, `crew_memberships`, `equipment_usages`, `installed_quantities`, `site_photos` | I | ✓ (ekip, fotoğrafta kişi olabilir) | — | Saha, PM; fotoğraf rıza notu | ✓ | ✗ (fotoğraf) |
| `inspections`, `ncrs`, `corrective_actions`, `permits`, `punch_items`, `quality_evidence` | I | — | — | Kalite/İSG, Saha, PM, tedarikçi sorumluluğu | ✓ | ✗ |
| `incidents` | C (kişi detayı R) | ✓ | Kişi kimlikleri restricted profil/DMS'de | İSG, PM, Executive; yasal bildirim | ✓ (İSG) | ✗ |
| `test_plan_*`, `test_equipment`, `calibration_certificates`, `installed_assets`, `test_executions`, `test_execution_steps`, `test_execution_equipment`, `test_evidence`, `commissioning_*`, `acceptance_certificates` | I/C | — | — | Yazılım/Test Grubu, PM, müşteri witness kaydı | ✓ | Kanıt/format değerlendirmesi capability'si ile (yalnız izinli rapor aksiyonu) |
| `warranties`, `service_requests`, `work_orders`, `maintenance_plans` | I | — | — | Servis, PM | ✓ | ✗ |

### 2.7 Workflow, audit, entegrasyon, harici analiz

| Tablo | Sınıf | PII | Şifreli alan | Policy kapsamı | Export | Dış AI |
|---|---|---|---|---|---|---|
| `workflow_definitions*`, `approval_policies*`, `approval_steps` | I | — | — | System Admin | ✓ | ✗ |
| `workflow_instances`, `workflow_tasks`, `approval_requests`, `approval_request_steps`, `approval_decisions`, `delegation_snapshots` | Subject sınıfı | ✓ (karar veren) | — | Subject Policy'si + onaycı; Auditor | ✓ (audit) | ✗ |
| `personnel_activities` | C | ✓ (personel) | `changes` içinde parola/anahtar maskeli; `ip_address` açık | Auditor, System Admin; kayıt sahibi kendi kaydının geçmişi | ✓ (Auditor) | ✗ |
| `reference_type_registry`, `reference_type_usages` | I | — | — | System Admin | ✓ | ✓ |
| `external_capabilities`, `external_capability_actions`, `service_capability_grants`, `service_capability_grant_actions` | C | — | — | System Admin + Executive onayı | ✓ (audit) | ✗ |
| `external_analysis_requests`, `external_analysis_artifacts`, `external_analysis_attempts`, `external_analysis_results`, `external_analysis_result_references`, `external_action_requests`, `external_action_executions` | Subject sınıfı (≤ capability üst sınırı) | ✓ (requester) | — | Requester, subject Policy'si, Auditor | ✓ (audit) | Zaten dış servise ait; ikinci servise iletilmez |

## 3. Alan düzeyinde kısıtlar

| Alan/veri | Kural |
|---|---|
| Ücret, bordro, banka (IBAN), TC kimlik/pasaport, doğum tarihi, sağlık, acil kişi | `personnel_private_profiles.*_enc`; uygulama katmanı şifreleme (envelope encryption, `encryption_key_version`); anahtar rotasyonu DB-G7 D-06; ekran maskeleme; export yasak |
| Aday CV, mülakat notu, referans | DMS `restricted` dokümanı; `candidates.retention_until` ve rıza; HR Admin + atanmış mülakatçı |
| Performans notu, disiplin | `performance_records` R; kendisi yalnız `final` sonrası |
| Teklif marjı, tahmin/maliyet, tedarikçi karşılaştırması | R; Filament tablolarında kolon bazlı `hidden` yetersizdir — Query Service alan seçimi ve Policy |
| Personel parolası | Yalnız hash; hiçbir ekranda, log, hareket kaydı veya dışa aktarımda açık görünmez |
| Kritik altyapı bilgisi (SCADA adresleri, şalt tek hat) | Doküman sınıfı R; dış AI kapalı; SCADA/OT'ye bu platformdan komut yok |
| Sosyal içerikte personel/müşteri/proje/lokasyon | Kamuya açıklama checklist'i (`checklist_snapshot`) + `public_disclosure_reference` zorunlu; onay olmadan `approved` olamaz |
| Fotoğraf/videoda kişi | `social_content_assets.consent_document_revision_id`; `site_photos` rıza notu |
| IP/user agent | Yalnız hash |
| E-posta ham içeriği | `.eml` object storage'da, C sınıfı; DMS'e yalnız iş belgesi olarak aktarılır |

## 4. Saklama politikası kataloğu (`retention_policies`)

Süreler öneridir; TTK, VUK, İş Kanunu/SGK, KVKK ve sözleşme yükümlülükleri hukuk/finans tarafından doğrulanmadan (D-38) `active` yapılmaz.

| Kod | Kapsam | Tetik | Süre (öneri) | Disposition | Not |
|---|---|---|---|---|---|
| `RET-REF` | Referans/katalog tabloları | `manual` | Süresiz | `review` | Pasifleştirme ile |
| `RET-AUDIT` | `personnel_activities`, `approval_decisions` | `created_at` | 10 yıl; sonra cold archive | `cold_archive` | Silinmez; legal hold öncelikli |
| `RET-HR-CORE` | `personnel`, `employments`, `position_assignments`, `reporting_relationships`, `personnel_status_histories` | `separation` | Ayrılış + 10 yıl | `anonymize` (kişisel alanlar) | İş kayıtları personel snapshot'ı ile korunur |
| `RET-HR-PRIVATE` | `personnel_private_profiles` | `separation` | Ayrılış + yasal süre (öneri 10 yıl; SGK/İş Kanunu doğrulaması) | `purge` | Şifreli alanlar anahtar imhasıyla |
| `RET-HR-LEAVE` | `leave_requests`, `attendance_entries`, `timesheets`, `expense_claims` | `separation` | Ayrılış + 5 yıl (VUK) / 10 yıl (TTK) — doğrulanacak | `anonymize` | Finansal bağlantılı olanlar `RET-FIN` |
| `RET-CANDIDATE` | `candidates`, `applications`, `interviews`, CV dokümanları | `closed_at` (süreç kapanışı) | Rıza yoksa 6 ay; açık rızayla 24 ay | `purge` | `retention_until` satırda; `consent_status` |
| `RET-PERF` | `performance_records`, `development_actions` | `separation` | Ayrılış + 2 yıl | `purge` | Hukuk doğrulaması |
| `RET-REPORT` | Rapor gönderim/sürüm/cevap/PDF | `archived_at` (dönem kapanışı) | 5 yıl; proje raporları proje kapanışı + 10 yıl | `cold_archive` | KPI fact'leri anonim toplam olarak kalabilir |
| `RET-NOTIF` | `notification_*` (rule hariç) | `closed_at` | 12 ay | `purge` | Kritik ack kayıtları `RET-AUDIT` özetine taşınır |
| `RET-TASK` | `tasks`, `task_assignments` | `closed_at` | 3 yıl | `cold_archive` | Proje bağlamlı görevler proje retention'ı |
| `RET-DOC-CONTROLLED` | Kontrollü doküman/revizyon/dosya | `project_close` / `archived_at` | Proje kapanışı + 10 yıl; kurumsal prosedürler süresiz | `cold_archive` | Sözleşme/garanti süresine göre uzayabilir |
| `RET-DOC-ATTACHMENT` | Sıradan ekler (mesaj, rapor eki) | Bağlı kaydın retention'ı | Bağlı kayıtla aynı | Bağlı kayıtla aynı | |
| `RET-FILE-QUARANTINE` | `scan_status ∈ {infected, quarantined, failed}` dosyalar | `created_at` | 30 gün | `purge` | Güvenlik audit'i kalır |
| `RET-FILE-DERIVED` | Thumbnail/preview | Kaynak dosya ile | Kaynakla aynı | `purge` | Yeniden üretilebilir |
| `RET-CHAT` | `conversations`, `messages`, `message_versions` | `archived_at` | 3 yıl (D-04) | `anonymize` sonra `purge` | Ayrılan personelin özel konuşması: erişim kapanır, içerik retention'a kadar kalır |
| `RET-EMAIL` | `inbound_email_*` ve `.eml` | `received_at` | İş kaydına bağlıysa bağlı kayıt; bağlı değilse 12 ay | `purge` | Teklif mailbox'ı business case retention'ına bağlanır |
| `RET-SOCIAL-CONTENT` | İçerik sürümleri, onaylar, yayın kayıtları | `archived_at` | Yayımlanan: 5 yıl; yayımlanmayan taslak: 12 ay | `cold_archive` / `purge` | Düzeltme/silme kanıtı `RET-AUDIT` |
| `RET-SOCIAL-RAW` | Raw provider payload, webhook payload referansı | `created_at` | 30 gün | `purge` | Hash ve provenance DB'de kalır |
| `RET-SOCIAL-METRIC` | Metric observation/batch | `created_at` | 5 yıl | `cold_archive` | KPI sonuçları formül sürümüyle yeniden üretilebilir |
| `RET-BD` | Business case, opportunity, activity, tender | `closed_at` (outcome) | Kazanılan: proje retention; kaybedilen/iptal: 5 yıl | `cold_archive` | `TKLF-n` kodu süresiz korunur (soy zinciri) |
| `RET-PROPOSAL` | Teklif sürümleri, tahmin, BOQ | `closed_at` | 10 yıl | `cold_archive` | Marj/tahmin R sınıfı; erişim daralır |
| `RET-CONTRACT` | Sözleşme sürümleri ve belgeleri | `project_close` | Sözleşme bitişi + 10 yıl (TTK) | `cold_archive` | Garanti/dava süresi uzatır |
| `RET-PROJECT` | Proje çekirdek, workstream, gate, handoff, WBS/CBS, issue/risk/change | `project_close` | Kapanış + 10 yıl | `cold_archive` | As-built ve kabul belgeleri süresiz önerilir |
| `RET-FIN` | Fatura, ödeme, bütçe, commitment, vergi, hakediş | `created_at` (mali yıl sonu) | 10 yıl (TTK/VUK doğrulaması) | `cold_archive` | Resmî defter Zirve'de |
| `RET-PROC` | Talep, RFQ, teklif, karşılaştırma, PO | `closed_at` | 10 yıl | `cold_archive` | Tedarikçi teklifleri R |
| `RET-STOCK` | Stok hareketleri, sayım, transfer | `created_at` | 10 yıl | `cold_archive` | Append-only |
| `RET-HSE` | Kaza/olay, izin, denetim, NCR | `closed_at` | Olay: 15 yıl (İSG mevzuatı doğrulaması); izin/denetim: proje retention | `cold_archive` | Kişi verisi R |
| `RET-TEST` | Test icra, kanıt, kalibrasyon, kabul sertifikası | `project_close` | Varlık ömrü + garanti; en az 10 yıl | `cold_archive` | |
| `RET-SERVICE` | Servis talebi, iş emri, bakım | `closed_at` | 5 yıl | `cold_archive` | Kurulu varlık kaydı süresiz |
| `RET-EXTERNAL` | Harici analiz istek/sonuç/aksiyon | `completed_at` | 3 yıl | `cold_archive` | Sonuç JSON'unda PII bulunmamalı |
| `RET-SESSION` | `sessions`, `password_reset_tokens`, framework `notifications` | `created_at` | Session lifetime; framework bildirimi 90 gün | `purge` | |
| `RET-TECH-LOG` | Uygulama/teknik loglar (DB dışı) | `created_at` | 90 gün | `purge` | Security audit ayrı ve daha uzun |

Disposition kuralları:

- `purge`: satır ve bağlı object storage nesnesi silinir; audit özet kaydı kalır.
- `anonymize`: kişisel alanlar sabit maskeye çevrilir; iş kaydı, personel snapshot'ı ve hash'ler korunur.
- `cold_archive`: satır arşiv şemasına/soğuk depoya taşınır veya `archived_at` ile pasifleşir; erişim Auditor/Executive.
- `review`: otomatik işlem yok; yıllık gözden geçirme görevi.
- Aktif `legal_holds` kapsamındaki doküman/dosya ve bağlı kayıtlar hiçbir disposition'a girmez.
- Retention işi scheduler tarafından yürütülür, her işlem `personnel_activities` üretir ve kodlama ajanları tarafından hiçbir zaman manuel çalıştırılmaz.

## 5. KVKK ve veri sahibi hakları

| Konu | Uygulama |
|---|---|
| İşleme amacı ve dayanak | Her `retention_policies.legal_basis` alanı; tablo matrisi PII sütunu envanterdir |
| Veri minimizasyonu | Sözlükte PII yalnız `personnel`, `personnel_private_profiles`, `person_profiles`, `communication_points`, `candidates`, `incidents` ve iletişim/bildirim alıcı snapshot'larında; ek PII alanı karar kaydı gerektirir |
| Erişim/düzeltme talebi | Personel: kendi profil ekranı; aday/dış kişi: HR/İş Geliştirme üzerinden `personnel_activities` ile kayıtlı işlem |
| Silme talebi | Retention/legal hold kontrolü sonrası `anonymize`/`purge`; yasal saklama süresi devam ediyorsa ret gerekçesi kaydedilir |
| Rıza | `person_profiles.consent_status`, `candidates.consent_status`, sosyal varlık `consent_document_revision_id`, fotoğraf rıza notu |
| Yurt dışı aktarım | Object storage/secret manager/dış AI lokasyonu D-06 ve D-26; R sınıfı dış servise gitmez |
| Güvenlik olayı | `personnel_activities` + security audit; yetkisiz erişim/olağan dışı export alarmı ([01 §17](01-teknik-mimari-plani.md)) |

## 6. Rol × alan erişim matrisi (başlangıç)

Değerler: **G** görüntüleme, **Y** yazma/oluşturma, **O** onay/karar, **E** export, **—** yok. Satır scope'u (birim/proje/alan/sahiplik) Policy ile daraltılır; menü gizlemek yetki değildir.

| Alan | System Admin | HR Admin | Unit Manager | Personel | Report Coordinator | Executive | Auditor | Sosyal roller |
|---|---|---|---|---|---|---|---|---|
| Personel/rol/sistem hesabı | G Y | — | — | — | — | G | G | — |
| Personel dizini (I alanlar) | G | G Y E | G | G | G | G | G | G |
| Personel C alanları (employment, atama) | — | G Y E | G (ekibi) | G (kendi) | — | G (özet) | G | — |
| Personel R alanları (private profile, performans) | — | G Y | — | G (kendi, final) | — | — | G (gerekçeli) | — |
| Aday/işe alım | — | G Y O E | G (kendi talebi) | — | — | G (özet) | G | — |
| Rapor şablonu/takvim | G | — | — | — | G Y O | G | G | — |
| Rapor gönderimi | — | — | G O (ekibi) | G Y (kendi) | G E | G (özet) | G | — |
| Bildirim/kritik iş | G | — | G O (ekibi) | G (kendi) | G | G O | G | G (alan) |
| DMS kontrollü doküman | G Y | G Y (HR belgeleri) | G Y O (birim) | G (dağıtım) | — | G | G | G Y (alan) |
| Konuşmalar | — (içerik) | — | — (özel) | G Y (üye) | — | — | G (hukuki) | G Y (alan) |
| Functional Area yönetişimi | G Y | G Y O | — | — | — | G O | G | function_owner: G Y |
| Sosyal hesap/içerik/yayın | G | — | — | — | — | G (özet) | G | creator Y, reviewer O(review), approver O, publisher Y(yayın), analyst G E |
| Party/İş Geliştirme/Teklif | — | — | — | — | — | G O | G | — |
| Teklif marjı/tahmin (R) | — | — | — | — | — | G O | G | — |
| Proje/workstream/gate | G | — | G (birim projeleri) | G (atanan) | — | G O | G | — |
| Satın alma/lojistik | — | — | G O (talep) | G (talep eden) | — | G O | G | — |
| Finans (R) | — | — | — | — | — | G O | G | — |
| Saha/kalite/İSG/test | — | G (sertifika) | G | G Y (atanan) | — | G | G | — |
| Entegrasyon/harici capability | G Y | — | — | — | — | G O | G | — |
| Audit görünümü | G | G (HR) | — | G (kendi kaydı) | — | G | G E | — |

İş Geliştirme, Teklif, Proje, Satın Alma, Muhasebe, Yazılım, Lojistik, Saha ve Kalite/İSG grupları için birim/proje rolü bazlı satırlar D-01 organizasyon verisiyle tamamlanır. Her custom Filament Action ayrıca `authorize` edilir; her Resource için Policy zorunludur.

## 7. Export, indirme ve audit kuralları

- Export yalnız Filament ExportAction + Query Service kapsamı ile; R sınıfı varsayılan export dışı; C sınıfı export'u audit'e `action_code = export.*` ile yazılır.
- CSV/XLSX üretiminde formula-injection temizliği (`=`, `+`, `-`, `@`, sekme/CR başlangıçları) zorunludur.
- İndirme her istekte bağlı iş nesnesinin Policy'siyle yeniden yetkilendirilir; signed URL kısa ömürlüdür; `personnel_activities` `download` kaydı tutulur.
- Olağan dışı export/indirme (hacim, saat, sınıf) güvenlik alarmı üretir.
- Dış AI'ya giden her artifact `external_analysis_artifacts` ile kayıtlıdır; capability üst sınırını aşan sınıf reddedilir; CV, ücret, sağlık, disiplin/performans ve kritik altyapı verileri varsayılan kapalıdır.

## 8. DB-G8/PG4 kontrol listesi

- Her sözlük tablosu bu matriste sınıflandırıldı; PII envanteri KVKK kayıt defterine girdi sağlar.
- Açık kararlar: D-04 (chat saklama/redaction/ayrılan personel), D-06 (şifreleme anahtarı, storage lokasyonu, backup), D-33 (DMS sınıf/legal hold/allowlist), D-37 (personel klonu/harici AI rızası), D-38 (retention sürelerinin hukuk/finans doğrulaması).
- Retention scheduler ve anonimleştirme use-case'leri DB-G8 sonrası M01/M04 kapsamında tasarlanır; bu belge şema veya kod üretmez.
