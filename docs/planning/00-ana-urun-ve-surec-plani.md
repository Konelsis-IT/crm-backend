# Konelsis Kurumsal İşletme Yönetim Platformu — Ana ürün ve süreç planı

**Durum:** DB-G8 onaylandı (4 Eylül 2026); 5 Eylül 2026 kullanıcı revizyonu (D-15R, D-42…D-46) bu belgeye işlendi. Uygulama kapsamı yalnız M01'dir; sonraki modüller ayrı yetkilendirme ister.  
**Sürüm:** 0.9 / 11 Eylül 2026

## 1. Ürün vizyonu

Platform klasik bir satış CRM'i değil; Konelsis'in çalışan, kurumsal raporlama, kurumsal iletişim/sosyal medya, iş geliştirme, teklif, proje, satın alma, lojistik, finans, saha, elektrik/otomasyon, test, devreye alma, kalite, doküman ve servis süreçlerini aynı denetim omurgasında birleştiren işletme sistemi olacaktır. EPC yaşam döngüsü proje merkezlidir; Sosyal Medya gibi kurumsal fonksiyonlar projeden bağımsız çalışabilir.

Konelsis'in kamuya açık şirket bağlamı şu ürün kararlarını doğrudan etkiler:

- Enerji, maden, su ve sanayi projeleri aynı çekirdeği kullanacak; proje tipi tek bir teknolojiye indirgenmeyecektir.
- GES, HES, RES, BESS, EMS, ENH, şalt/trafo, SCADA/DCS, PLC/RTU, inşaat, mekanik ve otomasyon bileşenleri bir projede birlikte bulunabilecektir.
- Türkiye ve yurt dışı operasyonlar için ülke, para birimi, saat dilimi, iş takvimi, dil ve yerel mevzuat bağlamı korunacaktır.
- EPC sorumluluğu keşif ve tekliften test, ilk enerji, geçici/kesin kabul, garanti ve servise kadar izlenecektir.
- Şirket bilgi tabanındaki kaynak güven sınıfları korunacak; şirket beyanı, kamu kaydı ve iç onaylı veri birbirine karıştırılmayacaktır.

## 2. Değişmez ürün ilkeleri

- Tek Konelsis kurulumu vardır; SaaS tenant modeli yoktur. Erişim departman, pozisyon, proje, görev ve veri sınıfına göre kapsamlanır.
- Sistem yalnız şirket personeline açıktır. Müşteri, tedarikçi veya taşeron portalı bu planın kapsamında değildir.
- Her personelin tek bir kaydı vardır; aynı kayıt hem personel kartı hem giriş hesabıdır (D-42).
- Durum değişiklikleri doğrudan alan düzenleyerek yapılamaz; yetkili Filament Action, uygulama servisi, geçiş doğrulaması ve değişmez geçmiş üzerinden yürür.
- Bir kayıt onaylandıktan veya karşı tarafa gönderildikten sonra üzerine yazılmaz; yeni sürüm/revizyon oluşturulur.
- Kritik kayıtlar hard delete edilmez; iptal, hükümsüz, arşiv veya superseded durumu kullanılır.
- Arayüz Türkçe ve İngilizcedir; dil seçici `TR / EN` gösterir.
- İş verisi TR ve EN tutulabilir. Seçilen belge dilindeki zorunlu alan eksikse çıktı sessizce başka dile düşmez; eksikler gösterilerek üretim durdurulur.
- Uygulama Filament-native geliştirilir. Rapor ve belge PDF çıktısı için seçilen Blade/HTML yaklaşımına özel onay verilmiştir; bu onay başka ekranlarda özel Blade/HTML/CSS/JavaScript izni değildir.
- Plugin kurulumu yalnız kullanıcı belirli plugin'i ayrıca onaylarsa yapılır.
- Üretim ana veritabanı MySQL 8.4 LTS ve InnoDB'dur; veri tabanı fiziksel tasarımı MySQL yetenek ve sınırlarına göre yapılır.
- İş alanı, organizasyon birimi ve proje farklı kimliklerdir. Bugün departman olmayan bir iş alanı tarihçeli personel/ekip sorumluluğuyla çalışabilir ve ileride verileri taşınmadan bir departmana bağlanabilir.

## 3. Öncelik sırası

Yeni ürün sırası, önceki satış odaklı MVP sırasının yerine geçer:

1. Personel, organizasyon, üst-alt ilişkisi, hesap ve yetki temeli.
2. Tüm departmanlara açık günlük/haftalık/periyodik rapor sistemi.
3. Bildirim, rapor hatırlatma, kritik iş, acknowledgment ve escalation sistemi.
4. Temel doküman yönetimi ve kurum içi kayıtlı iletişim.
5. Projeden bağımsız kurumsal fonksiyonlar; ilk alan Sosyal Medya ve Kurumsal İletişimdir.
6. İş Alım süreci: İş Geliştirme + Teklif.
7. Operasyon süreci: Proje + Satın Alma + Muhasebe + Yazılım/Otomasyon + Lojistik + Saha.
8. İleri İK, depo, kalite/İSG, devreye alma, servis, entegrasyon ve analitik.

Proje modülü başlamadan önce genel raporlama çekirdeği tamamlanacaktır. Proje günlükleri daha sonra aynı rapor çekirdeğinin proje kapsamlı şablonları olarak çalışacaktır.

## 4. Kurumsal organizasyon modeli

### 4.1 Süreç üst grupları

**İŞ ALIM** iki gruptan oluşur:

- İş Geliştirme Grubu
- Teklif Grubu

**OPERASYON** altı gruptan oluşur:

- Proje Grubu
- Satın Alma Grubu
- Muhasebe Grubu
- Yazılım Grubu
- Lojistik Grubu
- Saha Grubu

İnsan Kaynakları proje yaşam döngüsünün bir aşaması değildir; şirket genelinde çalışan bağımsız kurumsal modüldür. Sosyal Medya bugün resmî departman değildir; `social_media` Functional Area'sı olarak atanmış ekip/personel sorumluluğunda çalışır. İleride departman kurulursa efektif tarihli organizasyon bağı eklenir, mevcut içerik ve hesap kayıtları taşınmaz. Sunucu yönetimi ve benzeri gelecekteki alanlar da aynı ayrıştırılmış genişleme modeliyle eklenir.

### 4.2 Hiyerarşi ve yetki

- Organizasyon birimleri, pozisyonlar ve personel atamaları tarihçeli tutulur.
- Fonksiyon yöneticisi, doğrudan amir, proje yöneticisi, proje rolü ve onay makamı ayrı kavramlardır.
- Üst-alt ilişkisi otomatik veri okuma hakkı vermez; hangi kayıtların görülebileceği Policy ve veri sınıfıyla belirlenir.
- Geçici vekâlet/yetki devri başlangıç-bitiş tarihi, kapsam ve veren/onaylayan bilgisiyle kaydedilir.
- Üst yönetici alt personele; alt personel de tanımlı escalation zinciriyle üst yönetime bildirim gönderebilir.
- Sosyal Medya Functional Area rolleri (`function_owner`, `content_creator`, `reviewer`, `approver`, `publisher`, `analyst`) organizasyon hiyerarşisinden ayrı atanır; aktif departman bağı yalnız Policy scope çözümüne girdi sağlar.

## 5. Şirket geneli raporlama omurgası

### 5.1 Rapor türleri

- Günlük, haftalık, aylık ve özel dönem raporları.
- Departman, pozisyon, kişi, ekip veya proje kapsamlı raporlar.
- Saha günlüğü, proje ilerleme, test/devreye alma, satın alma, kritik olay, yönetici ve performans raporları.
- Planlı rapor, talep üzerine rapor ve olay tetiklemeli rapor.

### 5.2 Şablon ve yaşam döngüsü

- Her rapor, onaylı ve versiyonlanmış bir şablondan doğar.
- Şablon; zorunlu alanları, bölümleri, alan tiplerini, ek/kanıt kurallarını, teslim sıklığını, alıcı rolünü, inceleme/onay akışını ve AI kontrol modunu tanımlar.
- Yayımlanmış şablon değiştirilemez; yeni sürüm çıkarılır. Açılmış rapor dönemi başladığı şablon sürümünde kalır.
- Varsayılan akış: `Bekliyor → Taslak → Kontrole Hazır → Gönderildi → İncelemede → Onaylandı / Revizyon İstendi → Arşivlendi`.
- Süresi geçen fakat gönderilmeyen rapor sistem tarafından `Gecikmiş` işaretlenir; bu, raporun içerik durumundan ayrı tutulur.
- Rapor sürümü, gönderen, gerçek gönderim zamanı, dönem, şablon sürümü, inceleyen ve karar değişmez olarak korunur.

### 5.3 Rapor kontrolü

- Zorunlu alan, veri tipi, dosya, tarih ve imza/teyit kontrolleri CRM içinde deterministik kurallarla yapılır.
- `AI ile Kontrol Et` Filament Action'ı raporun onaylı payload'ını ayrı AI projesine gönderir.
- AI modeli, promptu, RAG/veri indeksi ve karar kodu bu projede bulunmaz.
- Şablon bazında AI kontrolü `Kapalı`, `Öneri`, `Revizyon Zorunlu` veya ileride açıkça onaylanan başka bir yetki düzeyinde yapılandırılabilir.
- Dış AI sonucu her zaman servis hesabı, istek kimliği, kaynak rapor sürümü, zaman, sonuç özeti ve uygulanan aksiyonla audit edilir.
- Dış AI servisi veritabanına doğrudan erişemez ve uygulama servislerini/Policy kurallarını atlayamaz.

### 5.4 Yönetici uyarıları

- Sistem teslim zamanı yaklaşan, eksik veya gecikmiş raporlar için çalışana uyarı gönderir.
- Escalation sırası şablon bazında tanımlanır: çalışan → doğrudan amir → departman yöneticisi → gerekli ise üst yönetim.
- **Personel kontrolü (11 Eylül 2026 eki, 02 M06A):** rapora özel değil, her türlü "yapılması gereken iş" (rapor, görev, talep, onay adımı, onay kapısı gereksinimi, doküman teyidi, sertifika yenileme) ortak bir yükümlülük kaydına bağlanır. Süre dolduğunda AI, gecikme süresi, işin önemi ve aciliyete göre politika sınırları içinde kişiye kademeli uyarı gönderir; geri sayım aşamasında kişi "N saat içinde tamamlamazsanız yöneticinize rapor gidecek" uyarısını canlı sayaçla görür; süre dolunca yöneticiye AI üretimli rapor gider ve kritik iş kaydı açılır. Yaptırım kararı insana aittir; AI yalnız değerlendirir, yazar ve süre/ton seçer.
- Yönetici rapordan bağımsız olarak personele bilgi, talep, uyarı veya kritik bildirim gönderebilir.
- Kritik bildirim yalnız okundu bilgisiyle kapanmaz; acknowledgment ve gerektiğinde çözüm kaydı ister.

## 6. İş yaşam döngüsü

### 6.1 Sabit ticari kimlik ve görünen kod

Her iş yaşam döngüsünün değişmeyen bir teknik kimliği ve tek sıra numarası vardır. Sıra numarası `5` olan kayıt:

- İş Alım sürecinde `TKLF-5`
- Operasyona geçince `PRJ-5`

olarak görünür. `TKLF-5` geçmiş kod olarak korunur, yeniden kullanılmaz ve `PRJ-5` ile aynı iş soy zincirine bağlıdır.

### 6.2 İş Alım

1. Kaynak e-posta, manuel kayıt veya izinli ihale kaynağı sisteme alınır.
2. İş Geliştirme; firma/müşteri portföyü, lisans/yeterlilik, ülke/pazar, toplantı/ziyaret, ön uygunluk ve Bid/No-Bid çalışmalarını tamamlar.
3. Teklif; teknik şartname, kapsam, marka listesi, sorumluluk matrisi, fiyat/fizibilite, tedarikçi teklifleri, saha keşfi, süre planı ve teklif sürümlerini hazırlar.
4. Kritik işlerde Proje Grubu görüşü alınır; final fiyat yetki matrisindeki en üst onaya gider.
5. İş Geliştirme ve Teklif kapanış kriterleri ile sözleşme/LOI/NTP koşulları sağlanınca Operasyon devir paketi hazırlanır.

### 6.3 İş Alım → Operasyon geçişi

- Geçiş tek yönlüdür; Operasyondaki kayıt İş Alım durumuna geri döndürülemez.
- Geçiş, Proje Grubunun teslim paketi kabulü ve yetkili onayla tamamlanır.
- Onaylı teklif, sözleşme, şartname, fiyat baseline'ı, takvim, marka/sorumluluk matrisi, varsayım/istisna, risk, açık konu ve doküman manifesti snapshot olarak kilitlenir.
- Operasyon sırasında ticari açıklama, ek iş veya değişiklik gerekirse İş Alım'a dönüş yapılmaz; Operasyon içinde `Değişiklik / Ticari Açıklama` kaydı açılır ve gerekli uzmanlara görev gönderilir.

### 6.4 Operasyon

Operasyon bağımlılık tabanlı çalışır:

- Sistem bir **güncel odak grubu** ve birden fazla **aktif paralel iş akışı** gösterir.
- Varsayılan odak sırası Proje → Satın Alma → Muhasebe → Lojistik → Saha → Yazılım'dır; proje şablonu ve gerçek bağımlılıklar bu sırayı özelleştirebilir.
- Odak Operasyon içinde ileri veya geri taşınabilir; neden, gönderen, alıcı, açık işler ve tarih geçmişte saklanır.
- Bir sonraki iş yalnız zorunlu ön koşulları karşılandığında açılır. İlgisiz paralel işler birbirini gereksiz yere bloke etmez.
- Satın alma tamamlandığında finansal kontrol/fatura işlemi, teslimat oluştuğunda lojistik/depo/saha işlemi, saha kurulumu tamamlandığında test/devreye alma işi idempotent olarak oluşturulur.
- Aşama geçişinde teslimat manifesti, belge sürümleri, kanıtlar, açık konular, sorumlu, SLA ve alıcının kabul/ret kararı bulunur.

## 7. Departman ve bağımsız iş alanı sorumlulukları

### Yönetim

- Yetki limitli onaylar, kritik iş ve gecikme escalation'ları.
- Portföy, teklif, proje, maliyet, nakit, risk, rapor uyumu ve departman performansı.

### İş Geliştirme

- Dünya Bankası, kamu ihale/EKAP, İller Bankası ve onaylı diğer kaynakların takibi.
- Müşteri/firma 360, portföy, lisans/yeterlilik, yıllık ilişki takibi.
- Toplantı, ziyaret, aksiyon, fırsat, ihale ve teklif mailbox kaydı.
- Taslak iş programı ve Teklif Grubuna eksiksiz handoff.

### Teklif

- Fiyat, fizibilite, BOQ, süre, teknik şartname, compliance/deviation, marka ve sorumluluk matrisi.
- E-posta, PDF, Excel, tedarikçi teklifi, saha fotoğrafı, rapor ve KML/KMZ dosyalarının kontrollü arşivi.
- Teklif versiyonları, kritik proje rotası, final fiyat ve yayımlama onayı.

### Proje

- Operasyon teslimini kabul, proje organizasyonu, WBS/CBS, baseline, milestone, risk, issue ve change.
- Proje kartı ve tablo görünümü; kapak görseli, proje kodu, güncel odak, aktif işler, gecikme ve sonraki adım.

### Satın Alma

- Proje ve ofis talepleri, ürün kataloğu, tedarikçi RFQ/teklif, karşılaştırma, sipariş ve toplam maliyet handoff'u.
- Tekrarlanan ürün ana kaydı; kategori, marka, görsel, teknik nitelik ve alternatif kuralları.

### Muhasebe

- Satın alma faturası, proje gelir-gider referansı, vergi/ödeme ve personel işe girişinin finansal/resmî işlemleri.
- Resmî muhasebe defteri Zirve'de kalır; CRM operasyonel kayıt, proje maliyet görünümü ve entegrasyon referansını tutar.

### Lojistik

- Sevkiyat, taşıyıcı, gümrük, teslim tarihi, mal kabul, hasar/eksik, depo ve sahaya transfer.
- Görselde görev listesi boş olduğundan ayrıntılı kapsam RACI çalışmasında doğrulanacaktır; satın alma içindeki lojistik veriler kaybolmayacaktır.

### Yazılım Grubu

- Kullanıcıdaki mevcut ad korunur; kapsamı yazılımın yanında elektrik/otomasyon, SCADA/EMS, fiber/kamera, panel, test ve devreye almayı içerir.
- Teknik ihtiyaçtan satın alma talebi, saha tamamlanma kontrolü, arıza/değişiklik ve kanıtlı test süreçleri.

### Saha

- Onaylı plan, sözleşme yükümlülüğü ve prosedürlerin kişi bazlı okundu/kabul teyidi.
- Ekip/personel, günlük/aylık rapor, fotoğraf, metraj, ilerleme, aksaklık ve çözüm kaydı.

### Sosyal Medya — bağımsız kurumsal fonksiyon

- Resmî hesap envanteri, hesap sahipliği, erişim ve entegrasyon sağlığı.
- TR/EN içerik brief'i, sürüm, marka/hukuk/gizlilik kontrolü, onay, yayın planı ve manuel/API yayın kanıtı.
- İçerik görsel/video/dokümanlarının DMS'deki exact revizyonlara bağlanması.
- Ulusal, sektörel, kurumsal ve sponsorluk/topluluk özel günlerinin doğrulanmış takvimi.
- Varsayılan T−4 ilk hatırlatma; sorumlu atanıp içerik en az taslak hazırlığına ulaşmamışsa T−3 kritik eskalasyon.
- Platform tanımlarını koruyan hesap/içerik istatistikleri ve KPI dashboard'u.
- Platform entegrasyonu mümkün değilse manuel plan, yayın URL/kanıtı ve manuel metrik akışıyla çalışmaya devam etme.

### İnsan Kaynakları

- Personel kartı, yetkinlik, sertifika, eğitim, izin, devam, masraf, atama ve performans.
- İş gücü ihtiyacı, ilan sürümü, aday/CV, mülakat ve işe alım; aday ve hassas personel alanları ayrı güvenlik kapsamındadır.

## 8. Ortak platform kabiliyetleri

- Personel dizini ve profil kartları.
- Tarihçeli organizasyon hiyerarşisi, proje rolleri ve vekâlet.
- Standart rapor şablonları, dönemler, teslimler, inceleme ve PDF çıktısı.
- Kişisel görev kutusu, bildirim merkezi, manuel uyarı ve kritik iş.
- Proje, görev, departman ve bire bir kayıtlı iletişim; mesaj, belge ve link paylaşımı.
- Güçlü DMS: doküman numarası, revizyon, onay, dağıtım, okundu teyidi, transmittal, TR/EN, PDF/XLSX/DOCX/JPEG/fotoğraf/KMZ ve diğer teknik dosyalar.
- Proje ve her aşama için not, risk, issue, gecikme, kayıp/ticari maruziyet ve çözüm kayıtları.
- E-posta alımı, hata/retry takibi ve aynı mesajın tekilleştirilmesi.
- Kritik tetikleyici kataloğu: rapor gecikmesi, teklif tarihi, onay SLA, kritik yol, bütçe/marj, stok, teslimat, test/NCR, İSG, vergi/ödeme ve entegrasyon hatası.
- Dönüşebilir Functional Area sahipliği: personel/ekip/pozisyon sorumluluğu, tarihçeli departman bağı ve geçiş audit'i.
- Sosyal hesap envanteri, içerik takvimi, özel gün hazırlık kutusu, yayın/onay geçmişi ve platform metrikleri.
- Sosyal medya tetikleyicileri: özel gün T−4/T−3, içerik onay gecikmesi, yayın hatası, credential/bağlantı süresi ve metrik senkronizasyon gecikmesi.

## 9. AI sınırı

- CRM içinde AI modeli, prompt orkestrasyonu, RAG, embedding/vector store, fine-tune, agent runtime veya AI karar mantığı bulunmaz.
- AI işleri ayrı projede geliştirilir. CRM yalnız onaylı özellik için Filament Action/button ve izole API istemci altyapısı sağlar.
- İlk kullanım raporun standart şablona uygunluk kontrolüdür.
- İleride ihale ön eleme, şartname özeti, gecikme tahmini, stok öngörüsü, sözleşme soru-cevap ve personel asistanı ayrı API kabiliyetleri olarak eklenebilir.
- Sosyal medya metni, etiket veya görsel önerisi ileride istenirse aynı ayrı AI projesi sınırına tabidir; AI taslak üretse bile içerik onayı ve yayını gerçekleştiremez.
- Dış servis geniş yetki alabilse dahi her kabiliyet ayrı allowlist, servis hesabı, veri kapsamı, audit ve gerektiğinde insan/onay matrisiyle açılır.
- Dış servis doğrudan DB'ye yazamaz; yalnız normal uygulama komutlarını çağırabilir.

## 10. Başarı ölçütleri

- Zamanında günlük/haftalık rapor teslim oranı ve gecikme süresi.
- Yönetici inceleme/onay SLA'sı ve tekrar revizyon oranı.
- Eksik rapor/kanıt, okunmamış kritik bildirim ve çözümlenmemiş alarm sayısı.
- İhale kaydından Bid/No-Bid ve teklif yayımına geçen süre.
- Tekliften Operasyona eksiksiz handoff oranı.
- Proje gate reddi, handoff iadesi, kritik yol gecikmesi ve açık issue yaşlandırması.
- Satın alma teslim süresi, stok doğruluğu, eksik/hasarlı teslim ve fatura eşleşme süresi.
- Test kanıt tamlığı, NCR/punch kapanma süresi ve kabul dosyası eksikliği.
- Yetkisiz erişim, başarısız entegrasyon, dosya karantina ve audit istisnaları.
- Sosyal içerik üretim/onay çevrim süresi, planlanan içeriğin zamanında yayın oranı ve özel günlerde T−4'e kadar owner/içerik atanma oranı.
- Platform tanımıyla erişim, gösterim, etkileşim, takipçi büyümesi ve link tıklaması; metrik senkronizasyon tazeliği ve yayın/bağlantı hata oranı.

## 11. Plan onay kapıları

- **PG0 — Kaynak ve kapsam:** Görsel maddeleri, şirket bağlamı ve son kullanıcı kararları izlenebilirlik matrisinde birleşir.
- **PG1 — Organizasyon ve raporlama:** Personel/hiyerarşi, rapor türleri, şablonlar, alıcılar ve SLA'lar onaylanır.
- **PG2 — İş Alım:** İş Geliştirme/Teklif sorumlulukları, kapanış kriterleri ve onay limitleri onaylanır.
- **PG3 — Operasyon:** Odak grubu, paralel iş akışları, handoff ve proje tipi şablonları onaylanır.
- **PG4 — DMS/güvenlik:** Belge sınıfları, revizyon, saklama, paylaşım ve HR gizliliği onaylanır.
- **PG5 — AI API:** Dış API sözleşmesi, veri kapsamı, sonuç kodları ve izin verilen aksiyonlar onaylanır.
- **PG-SM — Sosyal Medya:** Functional Area RACI'si, hesap/platform envanteri, içerik/onay politikası, özel gün kataloğu, T−4/T−3 kuralları, KPI sözlüğü ve entegrasyon kabiliyetleri onaylanır.
- **PG6 — DB:** Kavramsal/mantıksal/fiziksel tasarım ve veri sözlüğü onaylanır.
- **PG7 — Uygulamaya hazır:** Beş plan ve ERD paketi onaylı, kritik açık karar yok ve DBA/DevOps şema süreci belirlenmiştir.

PG7 tamamlanmadan uygulama koduna geçilmez.
