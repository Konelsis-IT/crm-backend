# M01 — Platform temeli ve personel yönetimi: uygulama teslim notu

**Durum:** Kod hazır; şema dosyaları yazıldı, **çalıştırılmadı**. Şemanın yeniden kurulması bekleniyor (bkz. §5).
**Tarih:** 7 Eylül 2026 (0.6 revizyonu; ilk sürüm 4 Eylül 2026)
**Yetki:** Kullanıcının DB-G8 onayı (kapsam M01) ve 5 Eylül 2026 tarihli yön değişikliği kararları (D-15R, D-42…D-59), [docs/planning/17](../planning/17-db-g8-onay-paketi-ve-karar-defteri.md).

## 1. 5 Eylül 2026 yön değişikliği

Kullanıcı kararıyla ilk sürümün on dokuz temel tercihi değişti. Bu belge yeni tasarımı anlatır; eski UUID/aktör tasarımı geçersizdir.

| Karar | Önceki tasarım | Yeni karar | Etki |
|---|---|---|---|
| D-15R | UUIDv7 `BINARY(16)` kimlikler | Klasik `BIGINT UNSIGNED AUTO_INCREMENT` | Bütün tablolar, modeller, şema dosyaları ve planlama belgeleri güncellendi; UUID altyapısı (değer nesnesi, cast, binding builder, bağlantı resolver'ları) tamamen kaldırıldı |
| D-42 | Ayrı giriş hesabı tablosu ve ayrı çalışan tablosu | Tek **personel** kaydı: hem çalışan kartı hem giriş hesabı | Giriş hesabı tablosu `personnel` oldu; fotoğraf, TC kimlik no, telefon, departman, görev, sicil no, işe giriş tarihi, yetkinlik alanları eklendi |
| D-43 | `principals` ortak aktör tablosu | Aktör kavramı kaldırıldı; kayıt doğrudan **personele** bağlanır | `principals` silindi, `*_principal_id` → `*_personnel_id` |
| D-44 | `audit_events`, hash zincirli teknik denetim kaydı | **Personel Hareketleri** (`personnel_activities`), hash zinciri yok | Arayüzde Türkçe cümleler, okunur değişiklik satırları; teknik kod ve kimlik numarası gösterilmiyor |
| D-45, D-56 | Dış sistem ekranları ve erişim bilgileri | **Entegrasyon arayüzü tamamen kaldırıldı.** Bağlantılar kod içinde kurulur, kullanıcı tek sistem kullandığını düşünür | `integration_*`, `external_id_mappings`, `inbox_messages`, `sync_*` tabloları, modelleri, ekranları ve çevirileri silindi |
| D-46, D-49 | Ayrı bildirim tabloları ve menüde bildirim alanı planlanıyordu | **Yalnız Filament bildirim zili** | Panelde zil açık; menüde bildirim başlığı yok. "Tüm bildirimler" listesi ilerideki işlerdedir ([02 §11 E-04](../planning/02-modul-bazli-ilerleme-plani.md)) |
| D-47 | Giden kutusu (`outbox_messages`, `outbox_deliveries`) ve ayrı durum değişikliği tablosu (`status_changes`) | **Kaldırıldı.** Böyle bir teslim mantığı kurulmayacaktır | Olay ve durum geçmişinin tek kaynağı Personel Hareketleri'dir |
| D-48 | Arayüzden yönetilen sistem hesapları (`service_accounts`) | **Şimdilik kaldırıldı.** Otomatik işleri yazılımcı kod, job veya dış yapay zekâ bağlantısı ile yapar | Tablo, model ve ekran yok; uygulamanın kendi işlediği kayıtlarda hareket satırında "Sistem" gösterilir |

Güvenlik notu: entegrasyon arayüzü kaldırıldığı için dış sistem parolası veritabanında tutulmaz; bağlantı bilgisi `.env` veya `config` üzerinden okunur (D-56). **Personel giriş parolaları her zaman hash'lenerek saklanır.**

## 2. Ne teslim edildi (arayüzde görünen)

`/admin` panelinde üç menü grubu:

**Personel**

- **Personel** — fotoğraf, ad soyad, TC kimlik no, telefon, e-posta, parola, departman, görev, işe giriş tarihi, yetkinlikler (seviye ve not ile), durum. Listede fotoğraf yuvarlak avatar, fotoğrafı olmayanda ad soyaddan üretilen baş harf avatarı; yetkinlikler rozet olarak (ilk üçü, kalanı genişletilebilir); departman, durum ve yetkinlik filtreleri. Telefon, e-posta ve WhatsApp tıklanabilir (D-54). Liste, sağ üstteki düğmeyle **kart görünümüne** çevrilebilir. Kart modunda tablo tamamen kalkar; her kayıt çerçeveli bir kartta, fotoğraf üstte ve bilgiler ortalanmış olarak görünür, boş alanlar çizilmez. Seçim oturumda saklanır ve sayfa yenilense de korunur (D-57). Kaydın altında **Personel Hareketleri** listesi vardır (D-53).
- **Departmanlar** — ad, üst departman, departman yöneticisi, bağlı personel sayısı. Kod ekranda sorulmaz; addan otomatik üretilir (D-51).

**Ayarlar**

- **Yetkinlikler** — kod, ad, kategori (elektrik, otomasyon, mekanik, yazılım, saha, İSG, kalite, ticari, idari), o yetkinliğe sahip personel sayısı.

**Kullanıcı menüsü (sağ üst)**

- **Profilim** — kişi kendi kartını görür ve düzenler: fotoğraf, ad soyad, TC kimlik no, telefon, e-posta ve parola. Departman, görev, işe giriş tarihi, durum ve yetkinlikler burada salt okunurdur; onları yalnız yetkili personel Personel ekranından değiştirir (D-52). Menüdeki avatar personelin fotoğrafıdır.

**Personel Hareketleri**

Ayrı bir menü başlığı değildir. Bir personelin kaydını açtığınızda, kartın altında o kişinin ne zaman ne yaptığı listelenir (D-53). İşlem adı Türkçe cümledir ("Personel bilgileri güncellendi"), değişiklikler "Durum: Aktif → İzinde" biçiminde yazılır. Teknik kod, alt tire, aktör kelimesi veya kimlik numarası gösterilmez.

## 3. Mimari ve dosya haritası

```text
Filament UI (app/Filament)      → yalnız girdi, Policy, servis çağrısı, sunum
  Resources/{Personnel(+ActivitiesRelationManager), Departments, Competencies}
  Auth/PersonnelProfile (kendi profili)
  Support/{DomainNotifications, PersonnelAvatar, PersonnelFormData}
Services (app/Services)         → bütün yazma işleri (kural S-2)
  AbstractService               → index, show, create, update, delete
  Personnel/{PersonnelService, DepartmentService, CompetencyService,
             SyncPersonnelCompetencies}
  Audit/{ActorContext, ActivityRecorder, ActivityInput}
  Support/{TransactionRunner, OptimisticLock}
  Numbering/AllocateBusinessNumber
  Platform/{FeatureFlags, SchemaReadiness}
  Authorization/RoleResolver
Exceptions (app/Exceptions)     → AbstractException + iş hataları (kural S-1)
  {StaleRecord, InvalidTransition, CodeAlreadyInUse, RecordNotFound,
   ModelNotResolved}, Personnel/{EmailAlreadyInUse, SelfParentNotAllowed}
Query (app/Query)               → PersonnelQueries, ActivityFilterOptions
Domain (app/Models, app/Enums)  → ilişkiler, cast'ler, Türkçe etiketli enum'lar,
                                   AppendOnly / HasAuditColumns concern'leri
Infrastructure                  → SchemaChangeGuard, KonelsisMigration temel sınıfı
Support                         → ActivityLabels (teknik kod → Türkçe metin),
                                   ContactLinks (tel / mailto / WhatsApp adresleri)
```

Katman sınırı korundu: Filament sınıfları iş kuralı, transaction veya açık Eloquent sorgusu içermez; yazma işlemleri servislerden, okuma sorguları Query katmanından geçer. `tools/safe-verify.php` bu sınırı tarar ve ihlali FAIL sayar.

Uygulanan kararlar: D-12 (framework `notifications` tablosu korunur), D-13, **D-15R** (klasik AUTO_INCREMENT kimlik), D-16 (RBAC şeması M03'te; şimdilik `RoleResolver` e-posta listeleri), D-27 (trigger yok; model seviyesinde `AppendOnly`), D-39, **D-42…D-59**.

### 3.1 Personel Hareketleri

`ActivityRecorder`, işlemi yapan personeli, ilgili kaydı ve değişiklik özetini aynı transaction içinde `personnel_activities` tablosuna yazar. Uygulamanın kendi yaptığı işlerde personel alanı boş kalır ve listede "Sistem" gösterilir. Parola, API anahtarı ve token benzeri alanların değerleri `konelsis.activity.hidden_keys` listesine göre maskelenir. Kayıt yalnız eklenir; `AppendOnly` güncelleme ve silmeyi engeller.

`ActivityLabels`, `personnel.status_changed` gibi teknik anahtarları `lang/tr/activity.php` çevirileriyle Türkçe cümleye ("Personel durumu değiştirildi") çevirir. Çeviri bulunmazsa nokta ve alt tireleri boşluğa çevirip ilk harfi büyüterek okunur metin üretir; kullanıcı hiçbir durumda snake_case görmez.

### 3.2 Durum değişiklikleri

Personel durumu formdan düzenlenemez; yalnız tablo işlemleriyle ve enum içindeki `allowedTargets()` ile tanımlı izinli geçişlere göre değişir. Her geçiş tek bir Personel Hareketleri kaydı üretir; ayrı geçmiş veya teslim tablosu yoktur (D-47).

### 3.3 Eşzamanlılık

`OptimisticLock::save()` güncellemeyi `row_version` eşitliğiyle yapar; kayıt aradan değişmişse işlem `Halt` ile durur ve kullanıcıya Filament bildirimi gösterilir. Formda `row_version` gizli alan olarak taşınır.

### 3.4 İş hataları (kural S-1)

Her iş hatası `App\Exceptions\AbstractException` sınıfından türer ve `app/Exceptions` altında toplanır. Genel olanlar doğrudan klasörde, alana özel olanlar `app/Exceptions/Personnel` gibi alt klasörde durur.

Sınıfın içinde mesaj yoktur. Metin `lang/tr/exceptions.php` ve `lang/en/exceptions.php` dosyalarından gelir; çeviri anahtarı sınıf adından üretilir. Böylece bir hatanın metnini değiştirmek için kod açılmaz.

| Sınıf | Anahtar |
|---|---|
| `StaleRecordException` | `exceptions.stale_record` |
| `Personnel\EmailAlreadyInUseException` | `exceptions.personnel.email_already_in_use` |

Fırlatma `::make(['email' => $email])` biçimindedir; yer tutucular oradan doldurulur. Anahtar bulunamazsa `exceptions.generic` metnine düşülür, yani kullanıcı hiçbir zaman ham anahtar görmez. Arayüz tarafında `DomainNotifications` artık hata tipine göre metin kurmaz, doğrudan `userMessage()` çağırır.

### 3.5 Servis katmanı (kural S-2)

Application katmanı kaldırılmıştır. İşlem başına ayrı sınıf yazılmaz; bütün yazma işleri `app/Services` altındadır ve her servis `App\Services\AbstractService` sınıfından türer.

`AbstractService` beş işlemi hazır verir: `index`, `show`, `create`, `update`, `delete`. Boş bir servis bile beşine sahiptir ve modelini kendi adından bulur:

```php
final class PersonnelService extends AbstractService {}

app(PersonnelService::class)->delete(12);
```

Kurallar:

- Bir işlem özel kural istiyorsa yalnız o metot override edilir; diğer dördü atadan gelmeye devam eder.
- Beş işlemin dışındaki işler aynı servise ek metot olur. Personel durumu değiştirme buna örnektir (`changeStatus`).
- Her yazma tek transaction içinde çalışır ve Personel Hareketleri kaydı üretir. İşlem kodu `{kayit_turu}.{islem}` biçimindedir; Türkçe karşılığı `lang/tr/activity.php` dosyasına eklenir.
- Veride `row_version` varsa sürüm kilidi kendiliğinden uygulanır.
- Gelen veriyi biçimlendirmek için `create` ve `update` kopyalanmaz, `prepare()` override edilir.

Filament'in kendi form ve tablo yapısıyla yaptığı sade kayıt işlemleri için servise başvurulmaz. Servis, iş bir form kaydından fazlasıysa veya aynı iş panel dışından (job, komut, zamanlanmış görev) da çalışacaksa devreye girer.

## 4. Şema dosyaları (yazıldı, çalıştırılmadı)

| Dosya | Grup | İçerik |
|---|---|---|
| `0001_01_01_000000_create_personnel_table.php` | B00 | `personnel` (giriş + çalışan alanları + iz kolonları), `password_reset_tokens`, `sessions`. İki adımlı doğrulama kolonu kaldırıldı (D-50). |
| `0001_01_01_000001_create_cache_table.php`, `..._000002_create_jobs_table.php` | B00 | Framework önbellek ve kuyruk tabloları |
| `0001_01_01_000003_create_notifications_table.php` | B00 | Filament veritabanı bildirimleri (`notifiable_id BIGINT UNSIGNED`) |
| `2026_09_05_100100_b01_create_reference_tables.php` | B01 | Ülke, para birimi, ölçü birimi, güvenlik sınıfı, saklama politikası, organizasyon, tüzel kişilik, iş takvimi, iş numarası tahsisi |
| `2026_09_05_100200_b02_create_personnel_structure_tables.php` | B02 | `departments`, `competencies`, `personnel_competencies` + B00/B01 tablolarındaki personel iz kolonlarının foreign key'leri |
| `2026_09_05_100300_b08_create_activity_tables.php` | B08 | `reference_types`(+`usages`), `personnel_activities` |
| `database/seeders/*` | B24 | İlk yönetici personeli, referans verisi, departman/yetkinlik başlangıç listesi, kayıt türü listesi |

Fiziksel kurallar `App\Infrastructure\Database\Migrations\KonelsisMigration` içindedir: `$table->id()` ile `BIGINT UNSIGNED AUTO_INCREMENT` kimlik, UTC `DATETIME(6)`, iz kolonu setleri, isimlendirilmiş `UNIQUE`/`FOREIGN KEY`/`CHECK`, kod kolonlarında `utf8mb4_0900_as_cs`, generated guard kolonları. SQLite yalnız geçici yerel iskelet olduğundan orada CHECK ve collation adımları atlanır.

## 4.1 Yerel PHP ayarı: fotoğraf yükleme

`php artisan serve` yerleşik sunucuyu ayrı bir süreçte başlatır ve o sürece yalnız izin verilen ortam değişkenlerini geçirir. `TMP`, `TEMP` ve `USERPROFILE` bu listede yoktur. Windows'ta PHP, bu üçü olmadan geçici klasör olarak `C:\Windows` yolunu seçer; bu klasör normal kullanıcıya yazma izni vermez. Sonuç iki ayrı hatadır:

```text
PHP Request Startup: File upload error - unable to create a temporary file
stream_get_meta_data(): Argument #1 ($stream) must be of type resource, false given
```

Birincisi gelen dosyanın alınamaması, ikincisi Livewire'ın `TemporaryUploadedFile` içinde `tmpfile()` çağrısının başarısız olmasıdır. İkisinin de kökeni aynıdır.

Bu durumda Livewire'ın yükleme uç noktası 422 döner ve arayüzde "failed to upload" görünür. Uygulama kodunda sorun yoktur; doğrulama, geçici dosyaya yazma ve hedef diske taşıma adımlarının hepsi ayrı ayrı çalışır durumdadır. Ayar `PHP_INI_SYSTEM` olduğu için çalışma zamanında `ini_set` ile değiştirilemez, makine geneli `php.ini` ise yönetici yetkisi ister.

Proje bunu kendi içinde çözer, ek bir adım gerekmez:

| Parça | Görevi |
|---|---|
| `.php-ini/konelsis.ini` | `upload_tmp_dir` ve `sys_temp_dir` değerlerini `storage/app/upload-tmp` klasörüne sabitler. Yollar yerleşik sunucunun çalışma dizinine (`public/`) göre yazıldığı için proje taşınsa da geçerlidir |
| `App\Providers\LocalUploadSupportProvider` | Yalnız `local` ortamda ve konsolda çalışır. `PHP_INI_SCAN_DIR` değişkenini bu klasöre yöneltir ve `PHP_INI_SCAN_DIR`, `TMP`, `TEMP`, `USERPROFILE` değişkenlerini `ServeCommand::$passthroughVariables` listesine ekleyerek yerleşik sunucuya aktarır. Geçici klasör yoksa oluşturur |

Sonuç: `php artisan serve` hiçbir bayrak veya ortam değişkeni olmadan çalışır ve yükleme başarılı olur. Doğrulandı: düz `php artisan serve` ile yapılan gerçek yükleme isteği 200 ve geçici dosya yolu döndürdü.

Dosyaların tamamı proje içinde kalır: PHP'nin geçici dosyası `storage/app/upload-tmp`, Livewire'ın ara dosyası `storage/app/private/livewire-tmp`, kalıcı fotoğraf `storage/app/public/personel-fotograflari`. Üçü de `storage/app/.gitignore` ile sürüm kontrolü dışındadır.

### Görsel adresleri porta bağlı değildir

`public` diskinin adresi `config/filesystems.php` içinde `/storage` olarak, yani köke göre tanımlıdır. Varsayılan Laravel ayarı bunu `APP_URL` üzerinden mutlak adres olarak üretir; o zaman `APP_URL` 8000 portunu gösterirken sunucu 8003'te çalışıyorsa fotoğraflar bulunamaz ve kırık görünür. Köke göre adres, panel hangi port veya makine adıyla açılırsa açılsın doğru çalışır. Dışarıya mutlak adres gerektiğinde (örneğin e-posta) `url()` ile üretilir.

Gerçek bir sunucuda (nginx + PHP-FPM, Apache) bu sağlayıcı devreye girmez; oradaki `upload_tmp_dir` sunucu yapılandırmasının parçasıdır.

## 5. Şemanın yeniden kurulması (kullanıcı tarafından)

Şema dosyaları 5 ve 7 Eylül 2026 kararlarıyla değişti: iki adımlı doğrulama kolonu (D-50) ve bütün entegrasyon tabloları (D-56) çıkarıldı; departman `org_units`'e taşındı, sertifika/eğitim ve organizasyon hiyerarşisi eklendi (D-60, D-61, D-62). Mevcut veritabanında eski tablolar hâlâ duruyor; temiz bir şema için sıfırdan kurulmalıdır. Kodlama ajanları bu adımları çalıştırmaz ve doğrulama adımı olarak da öneremez; kullanıcı kendi kabuğunda yapar. Sıra:

1. `KONELSIS_SCHEMA_CHANGES_ALLOWED` ve `KONELSIS_DESTRUCTIVE_SCHEMA_ALLOWED` yalnız o kabukta `true` yapılır.
2. Şema sıfırdan kurulur ve seed çalıştırılır: `php artisan migrate:fresh --seed`.
3. İki bayrak `false` yapılır.
4. Personel fotoğraflarının görünmesi için bir kez `php artisan storage:link`.
5. Migration gruplarının uygulanıp uygulanmadığı **doğrudan veritabanı şemasından** okunur (D-92, `App\Services\Platform\SchemaReadiness`); `.env` içinde batch listesi tutulmaz. Bir grup uygulanmamışsa ona bağlı ekranlar arayüzde görünmez, migration çalıştığı anda kendiliğinden açılır. Yetkili hesaplar da `.env`'de değil `SystemAccountSeeder`'dadır (D-91).
6. Daha önce `php artisan optimize` veya config önbelleği alındıysa `php artisan config:clear`.

Canlı ortam (18 Eylül 2026, D-105): `migrate:fresh` ve diğer yıkıcı komutlar `SchemaChangeGuard` tarafından canlıda bayraktan bağımsız reddedilir ve bu koruma gevşetilmez. Canlıda sıfırdan kurulum gerekiyorsa tablolar veritabanı konsolundan (DBA) silinir, ardından yalnız `KONELSIS_SCHEMA_CHANGES_ALLOWED=true php artisan migrate --seed --force` çalıştırılır. Seed zinciri yalnız gerçek veriyi yükler; kurgusal örnek seeder'lar ve `PersonnelSnapshotSeeder` depodan silinmiştir.

Roll-forward ilkesi geçerlidir: geri alma yerine düzeltici şema dosyası; `down()` adımları yalnız kullanıcı kararıyla, production dışında ve `assertDestructiveAllowed()` bayrağıyla çalışır.

## 6. Yapılan güvenli doğrulamalar

- `php -l` proje genelinde (189 PHP dosyası).
- `php tools/safe-verify.php`: composer script taraması, Filament sınır taraması, çalışma zamanı şema değişikliği taraması, yıkıcı ifade alarmı — hepsi temiz.
- Sınıf yükleme ve panel kaynak kaydı kontrolü: `Filament::getPanel('admin')->getResources()` beş kaynağı listeliyor; profil sayfası `admin/profile` yolunda kayıtlı.
- Oturum açmış yönetici olarak sayfa oluşturma denemesi: panel, profil, personel listesi/detay/düzenleme, departmanlar, yetkinlikler ve dış sistemler ekranlarının tamamı 200 döndü.
- Filament 5.7 / Laravel 13 API imzaları vendor kaynağından doğrulandı (`recordActions`, `toolbarActions`, `using`, `limitList`, `expandableLimitedList`, `defaultImageUrl`, `Schema::components`, `handleRecordCreation/Update`, `Heroicon` enum'u).

Yapılmayanlar (politika gereği): Pint, Laravel/PHP test paketi, Artisan şema ve seed komutları, her türlü veritabanı sıfırlama işlemi, bağımlılık veya plugin kurulumu.

## 7. Bilinen sınırlar ve sonraki adım

- Roller hâlâ geçicidir (`KONELSIS_SYSTEM_ADMIN_EMAILS`, `KONELSIS_AUDITOR_EMAILS`); kalıcı rol/yetki şeması M03'tedir ve `RoleResolver` tek değişiklik noktasıdır.
- Bildirim üretimi ve e-posta kanalı ilerideki modüllerdedir. Şu an panelde yalnız Filament bildirim zili açıktır; menüde bildirim alanı yoktur (D-49).
- Personelin özel alanları (banka, sağlık, ücret) bu sürümde yoktur; ayrı yetkilendirme kararıyla eklenecektir.
- TC kimlik numarası 11 hane olarak doğrulanır; mod-11 algoritma doğrulaması eklenmedi.
- Referans veri yönetim ekranları (ülke, para birimi, birim, takvim) M02 kapsamındadır; M01'de seed ile gelir.
- Sonraki modül M02 için gerçek organizasyon verisi (D-01) gerekir ve ayrı yetkilendirme ister.
