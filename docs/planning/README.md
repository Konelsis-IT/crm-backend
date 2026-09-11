# Konelsis planlama paketi

**Durum:** DB-G8 kullanıcı tarafından 4 Eylül 2026'da onaylandı (kapsam M01). 5 Eylül 2026'da kullanıcı altı kararı değiştirdi ([17 §3.6](17-db-g8-onay-paketi-ve-karar-defteri.md): D-15R klasik kimlik, D-42 personel = giriş hesabı, D-43 sistem hesapları, D-44 Personel Hareketleri, D-45 görünür erişim bilgileri, D-46 Filament bildirimleri) ve aynı gün üç karar daha verdi (D-47 giden kutusu kaldırıldı, D-48 sistem hesapları ertelendi, D-49 bildirimler yalnız zil). Bütün plan paketi bu kararlara göre güncellendi; ertelenen başlıklar [02 §11](02-modul-bazli-ilerleme-plani.md) listesindedir. M01 kodu hazır; şemayı kullanıcı kendi kabuğunda kurar. Sonraki modüller ayrı yetkilendirme ister.  
**Sürüm:** 1.2 / 7 Eylül 2026  
**Uygulama günlüğü:** [M01 teslim notu](../implementation/M01-platform-guvenlik-temeli.md)

Bu klasör, Konelsis Kurumsal İşletme Yönetim Platformu için birbirinden ayrılmış fakat aynı karar kayıtlarına bağlı planları içerir:

1. [Ana ürün ve süreç planı](00-ana-urun-ve-surec-plani.md)
2. [Teknik mimari planı](01-teknik-mimari-plani.md)
3. [Modül bazlı ilerleme planı](02-modul-bazli-ilerleme-plani.md)
4. [Veri tabanı tasarım planı](03-veri-tabani-tasarim-plani.md)
5. [Sosyal medya ve dönüşebilir kurumsal fonksiyon planı](04-sosyal-medya-ve-kurumsal-fonksiyon-plani.md)
6. [Veri tabanı ER diyagramları](05-veri-tabani-er-diyagramlari.md)

DB-G8 model dondurma paketi (0.7 ile eklendi):

7. [Veri sözlüğü 1 — sözleşme, çekirdek, kimlik, organizasyon, personel, İK](06-veri-sozlugu-01-cekirdek-ve-personel.md)
8. [Veri sözlüğü 2 — raporlama, bildirim, kritik iş, görev](07-veri-sozlugu-02-raporlama-ve-bildirim.md)
9. [Veri sözlüğü 3 — DMS, PDF çıktısı, kayıtlı iletişim, e-posta alımı](08-veri-sozlugu-03-dms-iletisim-eposta.md)
10. [Veri sözlüğü 4 — Sosyal Medya](09-veri-sozlugu-04-sosyal-medya.md)
11. [Veri sözlüğü 5 — Party, business case, ihale, teklif, sözleşme, devir](10-veri-sozlugu-05-party-ve-is-alim.md)
12. [Veri sözlüğü 6 — Operasyon](11-veri-sozlugu-06-operasyon.md)
13. [Veri sözlüğü 7 — workflow, onay, Personel Hareketleri, entegrasyon, harici analiz](12-veri-sozlugu-07-workflow-audit-entegrasyon.md)
14. [Cardinality ve constraint matrisi](13-cardinality-ve-constraint-matrisi.md)
15. [Durum makineleri ve olay kataloğu](14-durum-makineleri-ve-olay-katalogu.md)
16. [Güvenlik sınıflandırma ve saklama matrisi](15-guvenlik-siniflandirma-ve-saklama-matrisi.md)
17. [Migration üretim sırası ve DBA/DevOps teslim paketi](16-migration-uretim-sirasi-ve-dba-teslim-paketi.md)
18. [DB-G8 onay paketi ve karar defteri](17-db-g8-onay-paketi-ve-karar-defteri.md)

Plan paketinin kilitlenmiş üretim veri tabanı kararı **MySQL 8.4 LTS + InnoDB**'dur. SQLite yalnız mevcut yerel iskeletin geçici veritabanıdır; PostgreSQL bu projenin hedef topolojisinde yer almaz.

## Kodlamaya geçiş kilidi

Aşağıdaki koşulların tamamı sağlanmadan uygulama kodu, migration dosyası veya şema uygulaması başlatılmaz:

- Beş plan ve ERD paketinin kullanıcı tarafından onaylanması.
- Süreç/RACI çalışmasının ve İş Alım → Operasyon geçiş kriterlerinin onaylanması.
- Kavramsal ve mantıksal ERD, veri sözlüğü, durum makineleri ve yetki kapsamlarının onaylanması.
- Rapor şablonları, bildirim/escalation kuralları ve doküman sınıflandırmasının onaylanması.
- Dış AI projesi API sözleşmesinin ve izin verilen aksiyonların ayrıca onaylanması.
- Kritik veya yüksek etkili açık karar kalmaması.
- Şema değişikliklerini uygulayacak yetkili DBA/DevOps sürecinin belirlenmesi.
- Sosyal Medya RACI'si, resmî hesap envanteri, özel gün kataloğu, KPI sözlüğü ve entegrasyon kabiliyet matrisinin onaylanması.

Bu koşulların hangi karar maddelerine bağlı olduğu ve onay ifadesinin biçimi [DB-G8 onay paketi](17-db-g8-onay-paketi-ve-karar-defteri.md) belgesindedir. DB-G8 onayı tek başına kod yazdırmaz; kullanıcı implementation kapsamını da yetkilendirir.

Kodlama ajanları (Codex ve Claude Code) hiçbir zaman Pint, Laravel/PHP test paketi, Artisan migration komutu veya veritabanı reset/refresh/wipe/flush/drop/truncate işlemi çalıştırmaz.

## Kaynak önceliği

Çelişki olduğunda kaynaklar şu sırada ele alınır:

1. Kullanıcının son ve açık kararı.
2. Onaylanmış süreç/DB karar kayıtları.
3. 2 Eylül 2026 tarihli şirket işleyişi görselleri.
4. İlk kapsam promptu.
5. `konelsis_sirket_bilgi_veri_tabani.md` içindeki kamuya açık şirket bağlamı.

Şirket bilgi tabanındaki kamuya açık rakamlar, iştirakler, proje sayıları ve sertifika bilgileri doğrudan üretim ana verisi olarak yüklenmez; yetkili şirket kaynağından doğrulanır.

## Sürüm geçmişi

| Sürüm | Tarih | Değişiklik |
|---|---|---|
| 0.6 | 4 Eylül 2026 | Beş plan ve ERD paketi; Sosyal Medya ve Functional Area modeli |
| 0.7 | 4 Eylül 2026 | DB-G8 paketi: veri sözlüğü (7 bölüm), constraint matrisi, durum makineleri/olay kataloğu, güvenlik/saklama matrisi, migration sırası/DBA paketi, onay paketi ve karar defteri (D-01…D-41) |
| 0.8 | 5 Eylül 2026 | Kullanıcı revizyonu D-15R ve D-42…D-46: bütün kimlikler klasik `BIGINT UNSIGNED AUTO_INCREMENT`, UUID kaldırıldı; `users` ve `employees` tek `personnel` tablosunda birleşti; `principals` kaldırıldı; denetim kaydının adı **Personel Hareketleri**; dış sistem erişim bilgileri görünür; bildirimler Filament arayüzünde. 06–17 ve ERD paketi bu kararlara göre yeniden yazıldı. |
| 0.9 | 5 Eylül 2026 | Kullanıcı revizyonu D-47, D-48, D-49: giden kutusu ve ayrı durum değişikliği tablosu kaldırıldı, sistem hesapları ertelendi, bildirimler yalnız Filament zilinde. Ertelenen başlıklar planın sonunda [02 §11](02-modul-bazli-ilerleme-plani.md) listesinde. MySQL 8'in `AUTO_INCREMENT` kolonuna `CHECK` yasağı nedeniyle kendi kendine bağlanma kuralları uygulama katmanına taşındı. |
| 1.0 | 5 Eylül 2026 | Arayüz kararları D-50…D-55: iki adımlı doğrulama kaldırıldı; sicil no, dil, saat dilimi ve departman kodu ekranda gösterilmiyor; kendi profili sağ üstteki menüden açılıyor; Personel Hareketleri menüden çıkıp personel kartının altına taşındı; telefon, e-posta ve WhatsApp tıklanabilir; yetkinlikler Ayarlar grubuna alındı. |
| 1.1 | 5 Eylül 2026 | Karar D-56: dış sistem ve dış bağlantı ekranları ile `integration_*`, `external_id_mappings`, `inbox_messages`, `sync_*` tabloları projeden çıkarıldı. Entegrasyon kod içinde yapılır, arayüzde görünmez. |
| 1.2 | 7 Eylül 2026 | Kararlar D-58 ve D-59 (kural S-1 ve S-2): bütün iş hataları `AbstractException` altında toplandı ve mesajları dil dosyasına taşındı; Application katmanı kaldırılıp yerine `AbstractService` üzerinden beş temel işlemi hazır veren servis katmanı kuruldu. Kanonik politika bu iki kuralla güncellendi. |
