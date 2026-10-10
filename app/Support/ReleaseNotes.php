<?php

declare(strict_types=1);

namespace App\Support;

use App\Query\Platform\FeatureReleaseQueries;
use App\Services\Platform\FeatureRegistry;
use Illuminate\Support\Carbon;

/**
 * Surum notlari (D-91, 16 Eylul 2026 kullanici karari).
 *
 * Notlar kodda tanimlidir ve panelde sag ust kullanici menusundeki "Surum
 * notlari" penceresinde gosterilir; her surum ayri bir acilir bolumdur, en
 * yenisi acik gelir. Metin yoneticiye yoneliktir: teknik ayrinti degil, ne
 * yapildigi ve ne ise yaradigi yazilir.
 *
 * Yayin tarihi gelmemis surumler gizlidir (kullanici karari): ileri tarihli
 * bir surum yazilabilir, panelde ancak o gun gelince gorunur.
 *
 * Surum numarasi git commit'iyle ve ozellik surumleriyle aynidir (D-151 /
 * D-152; 1.1 - 1.10 eski numaralama). Canlida yayin kaydi varsa notlar yayin
 * surumune kadar gorunur (`konelsis:release`). Yeni madde
 * Feature::NEXT_RELEASE numarali kayda yazilir; kayit yoksa listenin BASINA
 * acilir. Gruplar asagidaki sabitlerle anahtarlanir (etiket ve simge
 * ReleaseNotesSchema'da).
 */
final class ReleaseNotes
{
    public const FEATURES = 'features';

    public const IMPROVEMENTS = 'improvements';

    public const FIXES = 'fixes';

    public const NOTES = 'notes';

    /**
     * En yeni surum en ustte. Tarih bicimi gun.ay.yil.
     *
     * @return list<array{version: string, date: string, groups: array<string, list<string>>}>
     */
    public static function all(): array
    {
        return [
            // Bekleme surumu (D-164, Feature::PARKED): bu ozellikler hicbir yayinla
            // acilmaz; kullanici bir ozelligi bir yayina aldiginda maddesi o
            // yayinin kaydina tasinir.
            [
                'version' => '5.0',
                'date' => '06.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'Personel kartında Personel Hareketleri sekmesi: kimin, ne zaman, hangi kayıtta ne yaptığı.',
                        'Onaylarda vekalet: onay yetkisini belirli bir süre için başka bir personele devretme.',
                        'Dokümanda hukuki tutma (belgeyi değişikliğe ve silinmeye karşı kilitleme) ve teslim tutanakları.',
                        'Proje kataloglarında proje bileşenleri, odak beklentileri ve onay kapısı şablonları.',
                        'Personel kartında haftalık kontrol sekmesi ve dikkat kartı; Analizler altında İş raporları (analiz panosu ve süre raporu).',
                        'Sosyal medyada Akış sekmesindeki depolama kutusu, Plan ve Analiz sekmeleri, blog yazma ve yönetici hesabı.',
                        'Sohbette grup açma ve mesajdan talep açma.',
                        'Zilin yanında Tüm bildirimler tablosu; masaüstü uyarıları: bildirim sesi ve Windows bildirimi.',
                        'Üst çubukta Hızlı işlemler; listelerde ve detay sayfalarında Excel ve PDF indirme.',
                        'Sağ üst menüde Sürüm notları penceresi; tam yetkili kişiler için UI Deneme kataloğu.',
                        'UI Deneme\'de departman panoları: üstteki seçiciyle Genel, Teklif - İş Geliştirme ve Yönetici panoları arasında geçilerek ekranın departmana göre nasıl değiştiği görülüyor. Sıkı, borsa ekranı gibi yoğun düzen: her zaman görünen gösterge bandı (açık potansiyel iş değeri, kaçan fırsat, verilen teklif, kazanma oranı, haftanın hareketleri), birleşik grafikler, hücre içi çubuk ve küçük çizgi grafikli teklif tablosu, sütun seçimi, görünen tablonun Excel ve PDF\'i, satıra tıklayınca ayrıntı penceresi. Yanında numaralı bileşen kataloğu var; beğendiklerinizi numarasıyla seçebilirsiniz. Durum düğmeleri bu denemede kayıt değiştirmiyor.',
                        'UI Deneme\'de Maliyet kalemleri: Kartal RES Hibrit GES 38,8 MWp maliyet Excel\'inin gerçek kalemleriyle numaralı tasarım seçenekleri. Kalemler Ürün/Hizmet, İdari Kadro ve Genel Giderler sekmelerinde kategorilerine göre gruplu, sıkı bir onay listesi olarak görünüyor; her kalemde hangi departmanın onayladığı, beklediği ya da reddettiği küçük işaretlerle, grup ve sekme başına onay ilerlemesi ve grubu toplu onaylama var. İcmal bazı seçeneklerde ayrı sekme, bazılarında sekmelerin üstünde genel toplam şeridi, sekme içi toplamlar, sağda özet paneli ya da maliyet şelalesi olarak gösteriliyor. Tutarlar TL, $ ya da € olarak görülebiliyor (Excel\'deki kur tablosuyla). Excel yüklendiğinde kalemlerin var olan katalogla eşleştirilmesi (katalogda / yeni / benzer kalem seçimi) ayrı bir adımda gösteriliyor. Excel\'deki satır rengi fiyat durumu olarak okunuyor: yeşil fiyatı kesin, sarı fiyatı henüz netleşmemiş kalem; netleşmeyenler satırda işaretli, süzülebiliyor ve toplamlarda ayrıca görünüyor. 7-9 numaralı seçenekler diğerlerinin birleşimleri (hepsi bir arada; şerit, sekme içi toplamlar ve İcmal sekmesi; fiyat ve onay odaklı). Deneme ekranı; kayıt değiştirmiyor.',
                    ],
                ],
            ],
            // D-185: maliyet listesi, Excel'den maliyet kalemi okuyucu ve satin alma hazirligiyla birlikte acilir.
            [
                'version' => '2.8',
                'date' => '09.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'Teklif kapsamında Maliyet listesi: her proje tipinin kapsam bölümünde, kapsam listesinin hemen yanında maliyet listesi (Excel) yükleniyor; birden fazla dosya eklenebiliyor, aynı adlı dosya o listenin yeni revizyonu oluyor. Maliyet listeleri teklif sayfasındaki kapsam kartında ve Sürümler penceresinde indirme bağlantısıyla, tüm belgeler ZIP\'inde "Maliyet listeleri" klasöründe duruyor. Satın almaya ve projeye gidecek ürün ve maliyetlerin ilk adımı.',
                    ],
                ],
            ],
            // D-185: proje modulu (kisa ad / lisans adi, proje tipi, proje tipi koordinatorleri).
            [
                'version' => '2.7',
                'date' => '09.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'Projelerde Kısa ad: proje adı alanı artık "Lisans adı". Proje listesinde kısa ad ve altında lisans adı tek sütunda, kartlarda, iş panosunda, seçim kutularında ve aramada kısa ad görünüyor; iki adla da aranabiliyor. Proje sayfasında ikisi birlikte.',
                        'Projelerde Proje tipi: GES, RES, TM, HES, BESS, ENH/EİH potansiyel işteki gibi simgeli düğmelerle seçiliyor; her tip için projenin kendi ölçüleri (MWp, MWe / MWh, km, adet), sözleşme tutarı ve bütçesi giriliyor. Listede Proje tipi sütunu, proje sayfasında tipler ve ölçü kartı var. Tekliften projeye dönüşünce tipler ve ölçüler kabul edilen tekliften kendiliğinden geliyor.',
                        'Proje tipi koordinatörü: proje müdürlerinden bağımsız olarak bir proje tipinin (GES, RES, TM, HES, BESS, ENH/EİH) bütün projeleriyle ilgilenen kişi. Proje Grubu > Proje tipi koordinatörleri ekranında her tipin koordinatörü atanıyor, değiştiriliyor ya da kaldırılıyor; GES koordinatörü Ertuğrul Şahin. Personel kartında ve detayında "GES koordinatörü" rozeti, proje sayfasında projenin tiplerinin koordinatörleri, proje listesinde koordinatör sütunu ve süzgeci var.',
                    ],
                    self::FIXES => [
                        'İş düzenleme ekranında "Kimden bekleniyor", "Talep eden" ya da "Bağlı kayıt" seçiliyken kaydetmek hata veriyordu; düzeltildi.',
                    ],
                ],
            ],
            [
                'version' => '2.6',
                'date' => '09.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'Teklif ve potansiyel iş sayfasında durum doğrudan değiştiriliyor: başlıktaki durum düğmesine basınca geçilebilecek durumlar kendi renkleri ve simgeleriyle açılıyor, seçilen durum hemen kaydediliyor. Teklifte bu Teklif durumu: Verilecek teklif, Verilen teklif, Onaylandı, Kaçan fırsat. Kazanıldı, Kaybedildi, İptal edildi, Onaylandı ve Kaçan fırsat gibi geri dönülmeyen durumlarda önce onay soruluyor. Teklifin yeni sürümü açılmıyor.',
                        'Günlük ve haftalık raporda "Panodan iş ekle": iş panosunda daha önce oluşmuş işlerinizi (bu dönemin işleri önce, sonra açık işler) arayıp seçince rapora iş kalemi olarak ekleniyor ve işin kartına bağlı kalıyor. Raporda olan iş ikinci kez seçilemiyor.',
                        'Günlük ve haftalık rapor yazılırken Öneriler listesi: dönemin rapora girmemiş işleri ve iş panosu önerileri (teklif, potansiyel iş, belge, talep gibi o dönemde yaptıklarınız) "Ekle" ile tek tıkla rapora giriyor; öneri aynı zamanda iş panosunda işe dönüşüyor. Eklenenler "Eklendi" olarak işaretleniyor.',
                        'Teklif ve potansiyel iş sayfasında ve düzenleme ekranında, başlıktaki simgeyle "Tüm belgeleri indir" (teklifin Dokümanlar sekmesinde ve potansiyel işin Belgeler kartında da var): kayıttaki bütün belgeler tek ZIP dosyası olarak, karışmasın diye klasörlere ayrılmış iniyor. Teklifte belge türleri (Şartname uygunluğu, Teklif mektubu…), kapsam listeleri, önceki sürümlerin belgeleri ve Genel belgeler (Genel katalog, referans listesi Excel\'i) ayrı klasörlerde; potansiyel işte kontrol listesi maddeleri, ek belgeler, kapsam listeleri ve her teklifin belgeleri kendi klasöründe. Arşivlenmiş belgeler Arşiv klasöründe.',
                        'Genel katalog ve Referans listesi her teklifte kendiliğinden görünüyor: teklifin Dokümanlar tablosunda diğer belgeler gibi birer satır. Genel katalog satırı (Dokümanlar\'a Genel Katalog türünde yüklenen en yeni katalog) PDF\'i yeni sekmede açıyor; Teklif belgeleri bölümünün başlığında ve Dokümanlar sekmesinde ayrıca "Genel katalog" düğmesi var. Referans listesi satırı teklifin proje tiplerinin referans sayısını gösteriyor (ör. "GES · 166 referans") ve tıklayınca o referansları Excel olarak indiriyor. İkisi de tüm belgeler ZIP\'ine giriyor. Katalog ve referanslar tekliflere kopyalanmıyor; Dokümanlar\'da yeni sürüm yüklenince ya da referans eklenince bütün tekliflerde yenisi görünüyor.',
                        'Ayarlar > Referanslar: şirketin bütün referansları (GES, HES, RES, Otomasyon / Process ve TM / ENH listelerindeki 429 referans) proje tipine göre sekmelerde: Tümü ve her proje tipi kendi simgesi ve referans sayısıyla. Arama, Referans ekle, düzenleme ve arşive alma; Excel çıktısı açık sekmenin proje tipini sizin listelerinizin biçiminde veriyor (her proje tipi ayrı sayfa, kırmızı başlıklı "GES REFERANSLARIMIZ" düzeni).',
                        'Teklifte Referanslar: teklif oluştur / düzenle ekranında ve teklif sayfasındaki kapsam kartında her proje tipi bölümünün (GES kapsamı, BESS kapsamı…) başlığında "Referanslar" düğmesi ve yanında indir simgesi var. Düğme o proje tipinin referanslarını pencerede tablo olarak açıyor; orada arayıp süzebilir, Excel\'e indirebilir ve yeni referans ekleyebilirsiniz. İndir simgesi aynı referansları doğrudan Excel olarak indiriyor.',
                        'Yeni proje tipi Otomasyon / Process: potansiyel işte, teklifte ve projede seçilebiliyor. Teklif kapsamında Toplam Maliyet ve Toplam Satış ile kapsam belgesi yükleme alanı var.',
                        'Potansiyel iş ve teklif düzenlerken "Yeni proje tipi eklemek istiyorum": kayıtlı proje tipleri rozet olarak görünüyor; kutu işaretlenince yeni tip seçilip kapsamı aynı ekranda girilebiliyor. Teklifte eklenen tip potansiyel işe de ekleniyor.',
                        'Excel, CSV ve Word belgeleri indirmeden görüntüleniyor: belge kartlarında dosyaya tıklayınca yeni sekmede önizleme açılıyor (Excel\'de sayfa sekmeleriyle tablo, Word\'de başlıklar, paragraflar ve tablolar); PDF ve görseller de yeni sekmede açılıyor. Kartın yanındaki küçük simge dosyayı indiriyor. Teklifin Dokümanlar tablosunda da "Önizle" simgesi var. Eski .xls / .doc dosyaları indirilerek açılıyor.',
                    ],
                    self::IMPROVEMENTS => [
                        'Teklif ve potansiyel iş düzenleme ekranında durum, başlıkta "Değişiklikleri kaydet" düğmesinin hemen yanındaki açılır düğmede: düğmede şu anki durum kendi rengiyle yazıyor, tıklayınca geçilebilecek durumlar açılıyor ve seçilen durum hemen kaydediliyor. Teklifte bu Teklif durumu (ör. "Verilen teklif"); formda ayrıca Teklif durumu alanı yok. "Verilen teklif" seçilince teklif gönderilmiş sayılıyor, "Onaylandı" seçilince potansiyel iş Kazanıldı oluyor, bütün teklifleri "Kaçan fırsat" olan potansiyel iş Kaybedildi oluyor.',
                        'Bir kaydı oluşturup ya da düzenleyip Kaydet\'e bastığınızda artık o kaydın sayfası açılıyor (sayfası olmayan kayıtlarda liste); aynı ekranda kalınmıyor. Taslak olarak kaydedilen kayıt kaldığı adımda açık kalıyor.',
                        'Devam eden 14 proje (MNG Phase 3, Sepsi Solar, Mor Yatırım, Ankatech, Aksa, belediye projeleri ve Salcia Tudor Wind) işverenleri, proje müdürleri ve proje tipleriyle Projeler\'e eklendi; lisans adları sonradan elle düzeltilecek.',
                        '"Proje yöneticisi" artık her yerde "Proje müdürü" olarak yazıyor.',
                        'Rapor metni ve kopyalanan metin sadeleşti: başlıklarda ve satırlarda simge (emoji) yok, yalnız başlık, kalın etiket ve madde işareti var. WhatsApp\'a yapıştırınca başlıklar kalın görünüyor.',
                        'Teklifte bir belge türüne (ör. Şartname uygunluğu) birden fazla belge yüklenebiliyor: Dokümanlar sekmesindeki "Belge yükle" ve teklif düzenleme ekranındaki belge kutuları birden çok dosya alıyor; her dosya ayrı belge oluyor. Önceden ikinci dosya ilkinin yerine geçiyordu. Potansiyel iş sayfasındaki Belgeler kartında "Belge ekle" ile bir kontrol listesi maddesine ya da ek belgelere birden çok dosya eklenebiliyor.',
                        'Dokümanlar\'a yüklenen Referanslar belgesi ve Genel katalog, yayımlanmamış (taslak) olsa da teklif ekranında bulunuyor; önceden yalnız yayımlanmış belge aranıyordu.',
                        'Tutarlar her yerde Türkçe yazılıyor ve para biriminin simgesiyle görünüyor. Tutar alanına 1000 yazınca 1.000, 1000,50 yazınca 1.000,50 oluyor; alanın yanında seçili para biriminin simgesi (₺, $, €, Romanya için lei) duruyor ve para birimi değişince simge de değişiyor. Listelerde, kartlarda, teklif ve proje sayfalarında, panolarda ve Excel / PDF çıktılarında tutarlar "50.000 ₺" gibi yazılıyor; para birimi seçimlerinde simge ve adı ("₺ Türk lirası") görünüyor.',
                        'Tekliflerde "Seçili" kaldırıldı: Teklifler listesinde, potansiyel işin Teklifler sekmesinde, teklif sayfasında ve Excel çıktısında Seçili sütunu, rozeti ve "Seçili yap" düğmesi yok. İş her zaman en son tekliften ve onun güncel sürümünden devam ediyor; projeye dönüşümde de bu teklif kullanılıyor.',
                        'Doküman sayfasının başlık düğmeleri kısaldı: "Bilgileri düzenle" artık "Düzenle"; güncel dosyayı önizle, indir ve paylaş yalnız simge olarak duruyor, adları üzerine gelince görünüyor.',
                        'Doküman sayfasında "Bağlı kayıtlar": belgenin yüklendiği teklif (hangi belge türünde ve hangi sürümde), potansiyel iş, sözleşme, ihale ve firma tıklanabilir bağlantılarla görünüyor; görme yetkiniz olmayan kayıt listelenmiyor.',
                        'Teklif oluştur tek ekranda: ekran Teklif adımında açılıyor; potansiyel iş seçimi, potansiyel iş kartı ve teklif alanları aynı ekranda. Potansiyel işi seçer seçmez teklif alanları açılıyor, İleri\'ye basmak gerekmiyor. Potansiyel iş adımı yalnız özet olarak duruyor.',
                        'Teklif oluştururken potansiyel işten gelen alanlar kendiliğinden doluyor: teklif başlığı (potansiyel işin başlığı, değiştirilebilir), teklif sorumlusu (potansiyel işin teklif sorumlusu, yoksa siz), proje tipleri ve kapsam bölümleri, GES kurulu gücü (MWp), para birimi ve ülke. Sizin yazdığınız değer ezilmiyor.',
                        'Teklif düzenlemede potansiyel iş kartı yeniden Teklif bilgileri\'nin üstünde: kompakt, katlanabilir, "Potansiyel işe git" bağlantısıyla.',
                        'Kapsam listesi ve maliyet listesi yükleme alanlarının altındaki uzun açıklamalar ile Referanslar belgesi / Genel katalog uyarısı kaldırıldı.',
                        'İş panosu sadeleşti: süzgeçler süzgeç simgesinin arkasında (Yoksayılanlar da bir süzgeç), Hızlı iş ekle solda bir düğmeyle açılıyor, Planlandı / Devam ediyor / Bekleniyor / Tamamlandı / İptal başlıkları kendi renginde, simgeli ve sayılı.',
                        'Teklif bilgilerinde başlık ve toplam fiyat altındaki "Boş bırakılırsa…" açıklamaları kaldırıldı; davranış aynı.',
                        'Kapsamdan hesaplanan marj alanı boşken kısaca "Kapsamdan hesaplanıyor" yazıyor.',
                        'Proje tipi ekleme kutusunun adı "Yeni proje tipi eklemek istiyorum" oldu; altındaki uzun açıklama kaldırıldı.',
                        'Tutar alanları: boş alanda 0,00 görünür; yazmaya başlayınca rakamlar virgülün soluna girer ve binlik ayraçla biçimlenir (1.500,00), kuruş her zaman görünür. Kuruşa tıklayarak ya da imleci virgülün sağına alarak kuruş yazılır; "1.234,5" ya da "1234.50" yapıştırılınca doğru tutara çevrilir.',
                        'Detay sayfalarında ve listelerde kayda ya da dosyaya giden bağlantılar artık kırmızı değil, normal yazı renginde; üzerine gelince altı çiziliyor. Kırmızı yalnız sol menüde, seçili sekmede, düğmelerde ve rozetlerde.',
                        'Teklif belgeleri, kapsam listesi, maliyet listesi ve potansiyel işin ek belgeleri sade yükleme kutularında: yüklü dosyalar küçük dosya kartları (tür simgesi, ad, revizyon; tıklayınca iner), yeni dosya küçük "Yükle" düğmesiyle ya da sürükleyip bırakarak ekleniyor.',
                        'Teklif sürümü artık sizin elinizde: teklifte "Düzenle" ile yaptığınız hiçbir değişiklik sürümü artırmıyor; alanlar, kapsam ve belgeler güncel sürümde değişiyor. Yeni sürüm gerektiğinde teklif sayfasındaki ya da düzenleme ekranındaki "Yeni teklif sürümü" düğmesine basıyorsunuz: güncel sürümün bütün bilgileri ve belgeleri düzenleme ekranındaki gibi dolu geliyor, istemediğiniz belgeyi kaldırıp yenilerini ekliyorsunuz; kaydedince Sürüm 2, Sürüm 3… oluşuyor, önceki sürüm Sürümler penceresinde saklanıyor. Teklif durumu değişmiyor.',
                        'Teklif belgelerinde revizyon yok: yüklediğiniz her dosya yeni belge oluyor. Düzenleme ve yeni sürüm ekranında her dosya kartının sağındaki küçük × ile belgeyi kaldırabiliyorsunuz (kart soluklaşıp üstü çiziliyor, "Geri al" ile vazgeçebilirsiniz; kaydedince uygulanıyor). Kaldırılan belge silinmiyor, Dokümanlar\'da duruyor.',
                        'Teklifin Dokümanlar sekmesinde "Yeni sürüm yükle" kaldırıldı; her satırda İndir ve çöp kutusu var. Çöp kutusu belgeyi tekliften kaldırıyor (Dokümanlar\'da kalıyor), teklif sürümü değişmiyor. Genel katalog satırında da İndir simgesi var; "Belge yükle" belgeyi güncel sürüme ekliyor.',
                        'Teklif sayfasındaki proje kapsamı düzenleme ekranıyla birebir aynı: her proje tipi kendi bölümünde (GES kapsamı…), aynı başlık, simge, Referanslar düğmesi, aynı alan yerleşimi ve aynı dosya kutularıyla; tek fark değerlerin yazı olarak görünmesi. Kapsam toplam satışı ve marj, düzenlemedeki Teklif bilgileri gibi Güncel sürüm kartında. Sürümler penceresindeki kapsam da aynı düzende.',
                        'Proje tipi seçimi küçüldü: tipler "GES" rozeti boyunda simgeli çipler; seçilen tip kendi renginde doluyor. Teklif oluştur ve düzenle ekranında Teklif bilgileri solda (4\'te 3), Proje tipi sağda (4\'te 1) yan yana; Teklif oluştur\'da da yeni proje tipi eklenebiliyor. "Kayıtlı tipler değişmez…" açıklaması kaldırıldı.',
                    ],
                ],
            ],
            [
                'version' => '2.5',
                'date' => '06.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'İş Geliştirme: sıcaklığı %0 olan kayıt Yatırımcı projesi, %0\'dan büyük olan Potansiyel iş. Liste Tümü / Yatırımcı Projeleri / Potansiyel İşler / Teklifte / Taslaklar sekmeleriyle geliyor; yeni kayıt "Yatırımcı Projesi oluştur" ile açılıyor.',
                        'Genel aramada kişiler çıkıyor: firmalardaki iletişim kişileri ve görüşülen kişiler, firmasıyla birlikte.',
                        'Görüşme notunda ve görüşme planında görüşülen kişi listede yoksa yanındaki + ile eklenebiliyor.',
                        'Proje durumuna YEKA eklendi; YEKA projesinde çağrı mektubu opsiyonel.',
                        'Günlük ve haftalık rapor Rapor oluştur ekranından da yazılabiliyor. O günün / haftanın işleri, görüşme notları ve potansiyel iş, teklif ve projelere yazdığınız raporlar öneri olarak geliyor; kaldırmadıklarınız rapora giriyor.',
                        'Rapor detayında düzenli rapor metni (başlıklar, maddeler, kalın yazılar) ve Kopyala düğmesi; listede satırdan kopyalama. Kopyalanan metin e-postaya ve Word\'e biçimiyle yapışıyor.',
                        'Raporlara özel PDF ve Excel: PDF kişiyi, tarihi ve rapor metnini düzenli sayfada veriyor; Excel özet ve rapor satırları sayfalarıyla geliyor.',
                    ],
                    self::IMPROVEMENTS => [
                        'Güncellemeler canlıdaki verilere dokunmuyor: verdiğiniz rol ve yetkiler, firmalar, kişiler, görüşmeler, potansiyel işler ve teklifler güncelleme sırasında değişmiyor, silinmiyor; sildiğiniz bir kayıt güncellemeyle geri gelmiyor.',
                        'Müşteri, Yatırımcı ve İşveren tek tip oldu: İşveren.',
                        'Sol menüde Potansiyel İşler artık İş Geliştirme; "Teklif sıcaklığı" yerine "Sıcaklık" yazıyor ve boş sıcaklık %0 görünüyor.',
                        'Teklifteki proje kapsamı oluştur / düzenle ekranında ve teklif detayında yarım genişlikte; marj alanında tek açıklama var.',
                        'Teklife dönüşmüş bir işi düzenlerken ekranda işin şu anda Teklif adımında olduğu yazıyor.',
                        'İhalelerde "Yakalandı" yerine "Tespit edildi" yazıyor.',
                        'Görüşme planı listesinde durumlar simgeli rozetlerle görünüyor.',
                        'Firmanın adı ikiye ayrıldı: Kısa ad ve Uzun ad (unvan). Kısa adı olan firma kısa adıyla, olmayan uzun adıyla görünüyor; listede uzun ad adın altında, firma kartında ikisi birlikte duruyor. Arama iki adla da buluyor.',
                        'İş Geliştirme kaydının Sınıflandırma bölümünde Tür seçilebiliyor: Otomatik (sıcaklığa göre), Yatırımcı projesi ya da Potansiyel iş. Seçilen tür sıcaklıktan bağımsız geçerli.',
                    ],
                ],
            ],
            [
                'version' => '2.4',
                'date' => '03.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'İş alımda Sözleşmeler.',
                        'Potansiyel işte teklif öncesi kontrol listesi: proje tipine göre GES ya da TM listesi. Soruların kutusuna bir tıklayınca ✓ (Evet), bir daha tıklayınca ✗ (Hayır), bir daha tıklayınca – (bilinmiyor) oluyor; her maddenin belgesi yanındaki küçük düğmeyle yükleniyor. Teklif sıcaklığı cevaplardan ve yüklenen belgelerden hesaplanıyor (her maddede üç soru ve belge eşit pay taşıyor) ve atan bir kalple gösteriliyor; belgesi olmayan maddede "Belge eklenmedi" yazıyor. Çağrı mektubunun geçerlilik süresi bittiyse teklif tipi kendiliğinden Bütçesel oluyor ve bu size söyleniyor.',
                        'Proje durumu (Lisanssız 5.1-C, Lisanssız 5.1-H, Önlisans, Lisans) Sınıflandırma bölümünde seçiliyor. Önlisans ya da Lisans seçilince çağrı mektubu opsiyonel oluyor ve ağırlığı diğer maddelere dağılıyor.',
                        'Potansiyel iş adımında Kaydet ya da İleri\'ye basınca bir özet penceresi açılıyor: hangi soruya ne cevap verildi, teklif sıcaklığı (başarı ihtimali), kaç soru cevaplandı, zorunlu belgeler ve eksikler. Açık soruları doldurursanız sıcaklığın en çok kaça çıkabileceği de yazıyor.',
                        'Potansiyel işe Ek belgeler yükleme ve potansiyel iş sayfasında Belgeler kartı.',
                        'Teklif sayfasında Sürümler düğmesi: eski bir sürümü seçince o sürümün bilgileri, proje kapsamı ve belgeleri bir pencerede açılıyor; belgeler tıklanınca iniyor. Sayfa her zaman güncel sürümü gösteriyor, ayrı sürüm sayfası yok.',
                        'Teklifin Dokümanlar sekmesinde Belge yükle: belge türünü seçip dosyayı yüklüyorsunuz. Aynı türde belge varsa yeni dosya o belgenin yeni revizyonu oluyor ve teklifin yeni sürümü açılıyor; satırdan da "Yeni sürüm yükle" ile aynısı yapılabiliyor.',
                        'İhale, potansiyel iş ve teklifte taslak kaydı: "Taslak olarak kaydet" ile kaydedip kaldığınız adımdan devam edebiliyorsunuz; listelerde Taslaklar sekmesi var.',
                        'Görüşme planlarken firma listede yoksa firma alanının yanındaki "Firma ekle" ile eklenebiliyor; eklenen firma görüşmede seçili geliyor.',
                        'Potansiyel İşler listesinde Tümü / İş Geliştirme / Teklifte / Taslaklar sekmeleri, her biri kayıt sayısıyla. Taslaklar yalnız kendi sekmesinde görünüyor.',
                        'Teklifler listesinde Proje tipi sütunu: Potansiyel İşler listesindeki gibi renkli rozetlerle.',
                    ],
                    self::IMPROVEMENTS => [
                        'İş alım sırası artık İhale → Potansiyel iş → Teklif → Proje. İhale, potansiyel iş seçmeden açılıyor; devam kararı verilince "Bu ihaleden potansiyel iş oluştur" ile ihale seçili olarak potansiyel iş açılıyor. İhale, potansiyel iş ve teklifin oluştur ve düzenle ekranları aynı dört adımlı düzende; ihale detayı da kartlarla ve "Bu iş nerede?" bölümüyle açılıyor.',
                        'Proje kapsamı (tutarlar ve kapsam listesi) potansiyel işten teklife taşındı. Alanlar proje tipine göre yeni düzende: GES\'te MWp, BESS\'te MWe ve MWh, ENH\'de km ile birim maliyet / satış ve toplamlar; miktar ve birim fiyat girilince toplam kendiliğinden yazılıyor. Marj elle girilmiyor, kapsamdan hesaplanıyor. BES adı BESS oldu.',
                        'Teklif ekranında başlık yarım genişlikte. Firmanın beklentileri, teklif mektubu, şartname uygunluğu, deviasyon listesi (eski adıyla Sapmalar), marka listesi ve sorumluluk matrisi küçük kutularda yan yana belge olarak yükleniyor; madde madde giriş yok.',
                        'Teklifi düzenleyip bir şeyi değiştirdiğinizde teklifin yeni sürümü kendiliğinden oluşuyor; önceki sürüm ve belgeleri saklanıyor, yeniden yüklenen belge aynı belgenin yeni revizyonu oluyor. Ayrı bir "yeni sürüm oluştur" adımı yok.',
                        'Potansiyel iş sayfasındaki Aktiviteler sekmesi kaldırıldı; görüşme notları kullanılıyor.',
                        'Görüşme notları ve görüşmeler silinmiyor, arşive alınıyor. Arşivdekiler listelerde görünmüyor; "Arşiv" süzgecinde Arşivlenenler seçilince görünüyor ve geri alınabiliyor. Bir not arşive alınınca Görüşme planındaki karşılığı da arşive gidiyor.',
                        'Görüşme planında sonucu girilmiş görüşmenin notu "Notu düzenle" ile düzeltilebiliyor. Notu yazan ya da görüşmeyi yapan kişi kendi notunu düzenleyip arşive alabiliyor.',
                        'Görüşme listesinde tarihi geçmiş ama sonucu girilmemiş görüşmeler "Gecikti" yerine "Geçmiş" yazıyor. Listeden doğrudan "Sonucu gir" ya da "Gerçekleşmedi" seçilebiliyor.',
                        'Oluşturma ve düzenleme ekranlarında hiçbir alan artık satırın tamamını kaplamıyor; her alan içeriği kadar yer tutuyor. Açıklama, not ve dosya yükleme alanları yarım satır.',
                        'Teklifin Dokümanlar sekmesinde yalnız güncel sürümün belgeleri listeleniyor; her belge bir kez görünüyor. Teklifte yalnız tutar gibi bir alan değiştiğinde belgeler çoğalmıyor, aynı belge yeni sürüme bağlanıyor.',
                        'Teklif sayfasındaki Proje kapsamı kartı yeni düzende: üstte marj, toplam maliyet ve toplam satış; altta her proje tipi kendi simgesiyle ayrı bir bölümde, değerler etiketli olarak.',
                        'Potansiyel iş detayında teklif öncesi kontrol listesi düzenleme ekranındaki görünümün aynısı (salt okunur). Teklif sıcaklığı sayfada tek yerde, madde belgeleri kendi maddesinin yanında.',
                        'Her durumun kendi rengi var. Potansiyel iş ve teklif sayfasının başında durum renkli bir düğme olarak görünüyor: detay sayfasında sabit, düzenleme sayfasında düğmeye basınca yeni durum kendi rengiyle seçiliyor ve hemen kaydediliyor. Durumu değiştirmek teklifin yeni sürümünü açmıyor ve formdaki diğer bilgileri değiştirmiyor. Listedeki "Durum değiştir" menüsü kaldırıldı.',
                        'Teklifin durumu (Taslak → İncelemede → Onaylandı → Gönderildi) teklif düzenleme ekranındaki durum düğmesinden değiştiriliyor; potansiyel işin durumu buna göre ilerliyor. Teklif Gönderildi olunca Teklif durumu "Verilen teklif" oluyor; potansiyel iş Kaybedildi olunca teklifleri "Kaçan fırsat" oluyor.',
                        'Listelerde taslak kayıtlar ilk bakışta ayırt ediliyor: satır açık sarı zeminli ve solda sarı şeritli, başlığın önünde kalem simgesi, altındaki "Taslak · ... adımında kaldı" yazısı sarı. Potansiyel iş, teklif ve ihale listelerinde aynı.',
                        'Her proje tipinin kendi simgesi var (GES güneş, RES dönen ok, TM şimşek, HES beher, BESS pil, ENH/EİH çift ok); listelerde, kartlarda, teklif kapsamında ve "Bu iş nerede?" bölümünde aynı simge kullanılıyor. Potansiyel işte proje tipi simgeli düğmelerle seçiliyor.',
                    ],
                ],
            ],
            [
                'version' => '2.3',
                'date' => '03.10.2026',
                'groups' => [
                    self::FEATURES => [
                        'Sosyal medyada Akış sekmesi: içerik kartları ve onay bekleyen paylaşımlar.',
                    ],
                    self::IMPROVEMENTS => [
                        'Teklif ve potansiyel iş sayfalarındaki "İş akışı" adımları yerine "Bu iş nerede?" bölümü geldi: potansiyel iş, teklifler ve proje alt alta; bulunduğunuz kayıt "Buradasınız" ile işaretli ve durumu tek cümleyle yazıyor (ör. "Müşterinin cevabı bekleniyor · 50 gündür"). Diğer kayıtların özet bilgileri etiketlerle görünüyor, "Potansiyel işe git" / "Projeye git" düğmeleri var. Proje henüz yoksa ne gerektiği ve "Projeye dönüştür" orada. Potansiyel işin teklif tablosu alttaki "Teklifler" sekmesinde.',
                        'Genel bakış yeni düzende: sayfa solda geniş, sağda dar iki sütun. Başlığın yanında Bugün (mavi), Geciken (kırmızı) ve Yaklaşan (turuncu) iş sayıları. Altında "Görevlerim ve işlerim": yalnız size ait işler, Geciken / Bugün / Yarın sekmeleri; her işte proje, durum, tarih ve sorumlu (fotoğrafıyla), satıra tıklayınca iş açılıyor. Sağda turuncu "Duyuru gönder", yaklaşan tarihler (her biri kart, kalan gün renkli: 3 gün ve altı kırmızı, 4–6 gün turuncu), son 3 duyuru ("Tümünü gör" tüm bildirimlere gider) ve yaklaşan sosyal medya paylaşımları (gün kutucuğu, platform işareti, "Bugün 12:00 / Yarın 10:00 / 3 gün sonra"; geciken paylaşım kırmızı çizgiyle).',
                        'Arama kutusu üst çubuğun soluna geçti ve genişledi; içinde "Taraf, proje, potansiyel iş ara…" yazıyor.',
                        'Raporlar\'da "Tümü" sekmesi yalnız üst yönetimde (Yönetim kurulu başkanı, İdari müdür), "Ekibim" sekmesi yalnız altında personel olanlarda görünüyor. Herkes kendi raporlarını, amirler ekiplerinin raporlarını görür; başka personelin ya da başka departmanın raporu listede de detay sayfasında da açılmaz.',
                        'Genel bakışın bölümleri, teklif ve potansiyel iş sayfalarındaki "Bu iş nerede?" bölümü ve Teklifler listesinin durum sekmeleri gerektiğinde tek tek kapatılabiliyor.',
                        'Sağ üstteki profil alanında artık fotoğraf ya da baş harflerin yanında adınız ve altında unvanınız (pozisyonunuz) yazıyor; yanında aşağı ok var, tıklayınca menü açılıyor. Dar ekranda yalnız fotoğraf kalıyor.',
                        '"Bildirim gönder" artık "Duyuru gönder" (Genel bakıştaki düğme ve sağ üst menü). Gönderilen duyuru herkesin bildirim ziline düşüyor ve sayfa yenilenmeden Genel bakıştaki Duyurular bölümünde görünüyor.',
                        'Düğme renkleri amaca göre: Kaydet, Oluştur, Gönder ve onay düğmeleri yeşil; İptal, Vazgeç, Kapat ve Geri çek kırmızı (gül tonu); Sil ve Reddet kırmızı; Düzenle turuncu; Yeni ve Ekle zümrüt yeşili; Görüntüle, İleri, Gönder ve İncelemeye gönder mavi. Renkli düğmelerin yazısı her zaman beyaz; açık renkler okunaklı koyu tonda.',
                        'Genel bakıştaki "Bugün ve yarın yapılacak işlerim" alt başlığı kaldırıldı.',
                        'Genel bakıştaki duyurular kısa görünüyor: başlık ve metnin ilk birkaç satırı. Duyuruya tıklayınca tamamı pencerede açılıyor; bağlantısı varsa pencerede "Bağlantıyı aç" düğmesi var.',
                        'Yeni özellikler sürüm sürüm açılıyor: her özellik geldiği sürümü taşıyor ve o sürüm yayınlanınca görünür oluyor. Sağ üstteki menüde kullanılan sürüm yazıyor.',
                    ],
                    self::FIXES => [
                        'Potansiyel iş düzenleme sayfasında Teklif adımına gelince teklifler iki kez görünüyordu; artık yalnız alttaki "Teklifler" sekmesinde. Teklif adımında seçili teklifin özeti duruyor.',
                    ],
                ],
            ],
            [
                'version' => '1.10',
                'date' => '22.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Raporlar > İş panosu: kendi kartların (Panom), ekibin, bir projenin ya da tüm projelerin (Yönetim) kartları beş durum sütununda. Kartlar sürükle-bırak ile taşınıyor (Bekleniyor\'a bırakınca kimden beklendiği, Tamamlandı\'ya bırakınca saat soruluyor, Geri al var); süzgeçler sayı gösteriyor ve pano tipiyle birlikte hatırlanıyor. Sistemde yaptığınız işler (teklif, ihale ilanı, belge, tedarik, talep, görüşme...) "Sistemden gelen öneriler" olarak geliyor; tek tıkla kart oluyor ya da yoksayılıyor.',
                        'Günü kapat ve Haftayı kapat: günün / haftanın kartları raporlara donmuş olarak yazılıyor; engeller ve plan kartlardan önceden doluyor, plana eklenen satır ertesi günün panosuna kart olarak düşüyor. Haftalık raporu amir inceliyor.',
                        'Raporlar > İşler: bütün kartların tablosu (süzgeç, gruplama, toplu durum / proje / kritik, Excel); kart detayında süreler ve geçmiş. Personel kartında İşler sekmesi; Haftalık kontrol ve Personel Hareketleri sekmelerini yalnız üst yönetim (yönetim kurulu başkanı ve idari müdür) görüyor.',
                        'Raporlar > Kontrol matrisi: bölüm sekmeleriyle personel × kriter çizelgesi günlük dolduruluyor; doldurma yalnız İnsan Kaynakları\'nda. Üst yönetim aynı ekranı salt okunur görüyor ve Haftalık görünümde haftanın toplamını okuyor: yalnız uygun olmayanlar sayısıyla (örneğin "Soft kayıtlar ✗4") yazılıyor, açıklama sütunu o haftanın bütün günlük açıklamalarını gösteriyor. Haftalık rapor sütunu sistemden geliyor.',
                        'Analizler > İş raporları (yalnız üst yönetim): Analiz panosu (harcanan saat, iş süresi, bekleme payı, termin uyumu, günü kapatma, açık kritik iş; önceki ayla karşılaştırmalı) ve Süre raporu (ana iş satırı açılıp kapanıyor, alt kartların sürelerini topluyor).',
                        'Organizasyon Birimleri ve Pozisyonlar listelerinde satıra tıklanınca detay sayfası açılıyor; birimin altında bağlı personel, pozisyonun altında o göreve atanan personel sekmede listeleniyor.',
                        'İş panosu tek ekrana indi: Panom / Ekip / Proje / Yönetim sekmeleri yerine "Kapsam" süzgeci (Kendi kartlarım, Ekibim, Tüm şirket) var; departman ve proje süzgeçleri her kapsamda çalışıyor, yönettiğiniz projeyi seçince o projedeki bütün kartlar geliyor. "Günü kapat" ve "Haftayı kapat" düğmeleri artık "Günlük rapora dönüştür" ve "Haftalık rapora dönüştür".',
                        'Yazdığınız rapor iş panosuna öneri olarak düşüyor; rapor oluştururken taslak ikonlu kartlardan seçiliyor ve seçim alanları tek tıklamayla işaretlenen düğmelere dönüştü.',
                        'Rapor detayı personel detayı gibi kompakt: üstte rapor kartı, yanında gönderim ve inceleme kutusu; günlük ve haftalık raporda işler durum sekmelerinde tablo halinde, yarın / hafta planı da tablo.',
                        'Panel artık "Genel bakış": yaklaşan tarihler ve duyurular yan yana, altında bugün ve yarın yapılacak işleriniz tablo halinde. Sol menüdeki gruplar kapalı başlıyor, tıklayınca açılıyor.',
                        'Günlük, haftalık ve aylık çalışma raporu artık Raporlar > Rapor oluştur ekranından seçilmiyor; bu raporları İş panosundaki Günü kapat ve Haftayı kapat üretiyor.',
                        'Gönderilen rapor artık yazarın bütün amirlerine ve üst yönetime (yönetim kurulu başkanı, idari müdür) aynı anda gidiyor: inceleyen seçilmemiş olsa bile rapor İnceleme kutum ve Ekibim listelerinde görünüyor ve hepsine bildirim düşüyor.',
                        'Bir kişi birden fazla müdüre bağlı olabiliyor: personel kartındaki Amir geçmişi sekmesinden "Ek amir ekle" ile işlevsel ya da proje amiri ekleniyor, "Kapat" ile kapatılıyor. Ek amir de o kişinin işlerini, raporlarını ve ekip kapsamını görüyor. Doğrudan amir tek kalıyor ve personel formundan değişiyor.',
                        'Rapor detayında "İlet" var: rapor üst amire ya da seçilen yöneticiye iletiliyor, açıklama geçmişe yazılıyor.',
                        'Personel kartında "Raporlar" sekmesi kişi hakkındaki raporları ve kişinin yazdığı raporları, "Talepler" sekmesi gelen ve açtığı talepleri alt alta gösteriyor. İşler ve Haftalık kontrol sekmelerinin simgesi var.',
                        'Üst çubukta, TR / EN düğmelerinin yanında "Hızlı işlemler", "İş panosu" ve "Kontrol matrisi" düğmeleri var; İş panosu ve Kontrol matrisi sol menüden kaldırıldı. Her düğmeyi yalnız o ekranı açma yetkisi olan görüyor.',
                        'Hızlı işlemler düğmesi tıklandığı yerde daire biçiminde açılıyor: Yeni iş, Rapor yaz ve Talep aç herkeste sabit; "Hızlı işlem ekle" ile kişi kendine en fazla üç işlem daha seçebiliyor (veritabanı güncellemesi B38 uygulandığında açılır).',
                        'Zilin yanındaki "Tüm bildirimleri gör" düğmesi bütün bildirimlerinizi, eskiler dahil, tablo halinde açıyor: okunmamışlar ayrı sekmede, satıra tıklayınca ilgili sayfa açılıyor; okundu / okunmadı işaretlenebiliyor.',
                        'İş panosunda "Sistemden gelen öneriler"deki bir öneriye tıklayınca ayrıntısı açılıyor: ne yapıldığı, ne zaman, hangi kayıtta, kaydın durumu ve kayda git bağlantısı. Karar oradan verilebiliyor ("İşe dönüştür" ya da "Yoksay").',
                        'Ayarlar sol menüden kaldırıldı; sağ üstteki profil düğmesine basınca açılan menüde. Menünün en üstünde kişinin adı yazıyor.',
                    ],
                    self::FIXES => [
                        'Ekrandaki saatler İstanbul saatine göre gösteriliyor (önceki 3 saat geri görünüyordu). Kayıtlar, Excel ve PDF\'teki saatler ve "bugün" hesapları da İstanbul gününe göre.',
                        'İş panosunda bir kartı bekleten personel seçildiğinde o kişiye bildirim gidiyor ve kart kendi listesinde görünüyor (önceden ikisi de olmuyordu).',
                        'İş panosunda tarih süzgeci düzeltildi: ileri tarihli bir hafta seçildiğinde geçmişin açık kartları artık listelenmiyor; bugünü içeren aralıkta açık kartlar devrediyor.',
                        'İşler > düzenleme sayfasının bozuk görünen alan yerleşimi düzeltildi.',
                        'Personel kartındaki Amir geçmişi sekmesinde "Ek amir ekle" düğmesi görünmüyordu; artık yöneticiler ve İnsan Kaynakları ek amir ekleyip kapatabiliyor.',
                        'Raporlar listesi bir süre açılmıyordu; düzeltildi.',
                        'İş panosunda uzun başlıklı ya da bekleme etiketli kartlar sütun dışına taşıyordu; artık sütun içinde kalıyor.',
                        'Doküman oluştururken "Belgenin aslı" bölümünde dosya yükleme alanı görünmüyordu; artık görünüyor ve dosya yükleniyor. "Sistemde yazıldı" seçildiğinde yazılan metin de artık ilk revizyon olarak kaydediliyor.',
                        'Onay politikası sürümünde "Nisap" seçilince "Yeterli onay sayısı" alanı, onay adımında çözümleyici türüne göre personel / pozisyon / rol alanları görünmüyordu; düzeltildi.',
                    ],
                    self::IMPROVEMENTS => [
                        'İş panosunda kapsam seçimi açılır kutu yerine düğme grubu (Kendi kartlarım / Ekibim / Tüm şirket). Raporlar listesindeki "Bugünün raporu" düğmesi kaldırıldı; günlük rapor İş panosundaki "Günlük rapora dönüştür" ile yazılıyor.',
                        'İş panosunda "Engellendi" sütunu artık "İptal": iptal edilen iş kapanır, ertesi güne devretmez ve günlük rapordaki Engeller alanına girmez. "Hızlı kalem" düğmesi "İş ekle", önerilerdeki "Kart yap" düğmesi "İşe dönüştür" oldu. Öneriler başlığının tamamına tıklanarak açılıp kapanıyor.',
                        'Üst çubukta İş panosu ve Kontrol matrisi simgeleri daha anlaşılır simgelerle değişti.',
                        'Kontrol matrisinde henüz kaydedilmemiş gün açıldığında bütün işaretler ✓ (uygun) gelir; uygun olmayan tıklanarak ✗, bir kez daha tıklanarak – yapılır. Hiçbir şeye dokunmadan Kaydet\'e basmak da ✓\'leri yazar. Kaydedilmiş günler olduğu gibi gelir; ileri tarihli günler boş başlar.',
                        'Bütün tablolarda satıra tıklamak kaydın detayını açıyor; "Görüntüle" / "Aç" düğmeleri kaldırıldı. Detay sayfası olmayan satırlarda (ör. amir geçmişi, onay adımları, talep hareketleri) tıklayınca ayrıntı penceresi açılıyor.',
                        'Tablolardaki Düzenle, Arşivle, Sil gibi düğmeler artık yalnız simge; üzerine gelince ne yaptığı yazıyor.',
                        'Pozisyon, yetkinlik, onay politikası, onay kapısı şablonu, şablon, faaliyet alanı ve operasyon grubu ekranlarında kod alanı kalktı; kod arka planda addan otomatik oluşuyor. Doküman tiplerinde numaralandırma öneki, Roller ekranında koruma adı artık görünmüyor. "Ad (TR)" gibi etiketler "Ad" oldu; ayrıca İngilizce ad girilmiyor. Onay kapısı şablonlarında kapı kodu da artık girilmiyor (sıradan otomatik: G0, G1…).',
                        'Firma takip listesindeki projeler iş dosyası olarak aktarılıyor: her proje, firmasının müşteri olduğu bir iş dosyası (proje tipi, kapsam, güç ve ÇED / lisans notuyla) olur. Taraf kartında yeni "İş Dosyaları" sekmesi o firmanın iş dosyalarını listeliyor; oradaki "İş dosyası oluştur" firmayı seçili getiriyor.',
                        'Bir özellik kapatıldığında menüden, sayfalardan, kısayollardan ve kartlardaki sekmelerden birlikte kalkıyor; açılınca hepsi geri geliyor. Sosyal medya sekmeleri, sohbette grup açma, Excel ve PDF indirme, bildirim sesi ve Windows bildirimi gibi parçalar da ayrı ayrı açılıp kapatılabiliyor.',
                        'Doküman ve belge alanlarına 1 GB\'a kadar dosya yüklenebiliyor: doküman ve revizyon oluşturma, proje Dokümanlar sekmesi, iş dosyasındaki müşteri beklentileri, teklif mektubu ve kapsam listesi dosyaları. Fotoğraf, sohbet ve talep eklerinde önceki sınırlar geçerli.',
                        'Tablolarda ve detay sayfalarında bağlı kayıtlar (personel, proje, birim, teklif…) tıklanabilir ve türünün simgesiyle görünüyor; personel adlarının yanında her zaman kişi simgesi var.',
                        'Yeni bildirim ya da sohbet mesajı geldiğinde kısa bir bildirim sesi çalıyor; başka sekmedeyken ya da pencere arkadayken Windows bildirimi de çıkıyor, tıklayınca ilgili sayfa açılıyor. Açmak için sağ üstteki profil menüsünden "Masaüstü bildirimlerini aç"a bir kez tıklayıp tarayıcıya izin verin.',
                        'Sihirbazlarda her adımda "İleri"nin hemen yanında yeşil "Kaydet" var; sona kadar ilerlemek zorunlu değil. İş dosyası oluştururken 1. adımda kaydedince yalnız iş dosyası, 2. adımda kaydedince iş dosyası ve teklif açılıyor. Proje oluşturma ve düzenlemede de her adımda kaydedilebiliyor; proje müdürü seçilmediyse oluşturan kişi yazılıyor.',
                        'İş dosyası sayfasındaki teklifler tablosunda "Teklif oluştur" artık Teklif oluştur ekranını o iş dosyası seçili olarak açıyor.',
                        'İş dosyası detay sayfasında kart yarım genişlikte, yanında oluştururken girilen bütün ayrıntılar (açıklama, kaynak, teklif tipi, ülke, para birimi, tüzel kişilik, gizlilik sınıfı, proje kapsamları) duruyor. İş dosyası adımı boş; Teklif adımında seçili teklifin özeti (fiyat, marj, geçerlilik, teklif durumu, belgeler) ve teklifler, Proje adımında proje, yönetici, tarihler ve saha adresi görünüyor.',
                        'Teklif detay sayfası iş dosyası sayfasıyla aynı düzende: üstte teklif kartı ve yanında güncel sürüm; İş dosyası adımında iş dosyasının özeti, Proje adımında proje ya da bu teklifi projeye dönüştürme. Sürümler, dokümanlar ve raporlar sayfanın altındaki sekmelerde.',
                        'Teklif oluştur ekranı iş dosyası sihirbazının Teklif adımı biçimine geldi: önce iş dosyası seçiliyor (zorunlu), seçilince iş dosyasının özeti (müşteri, başlık, teklif tipi, proje tipleri, tahmini değer, sorumlular) kartta görünüyor; teklif fiyat, marj, geçerlilik, teklif durumu ve belgeleriyle bu bağlamda oluşturuluyor. İş dosyasının projesi yoksa aynı ekrandan hemen projeye dönüştürülebiliyor.',
                        '"İş dosyası" artık "Potansiyel iş". Kodlar yıla göre otomatik: potansiyel iş POTIS-2026-0001, teklif TKLF-2026-0001, proje PRJ-2026-0001; her yıl 0001\'den başlıyor. Bir potansiyel işe birden fazla teklif bağlanabiliyor; her teklifin yanında bağlı olduğu POTIS kodu görünüyor (Teklifler listesinde ayrı sütun, teklif detayında rozet). Potansiyel işler listesinde müşteri adı taraf listesindeki gibi kısa, tamamı üzerine gelince görünüyor.',
                        'Potansiyel işte "İş Alım aşaması" artık "Durum". Sonuç durumdan kendiliğinden geliyor: Kazanıldı ve devir durumlarında "Kazanıldı", Kaybedildi ve İptal edildi durumlarında aynı adla, diğerlerinde "Açık". Detay sayfasının üstünde "Durum değiştir" düğmesi var. "Proje tip seçimi" sütunu "Proje tipi" oldu.',
                        'Taraflar listesinde süzgeçler birlikte (VE) çalışıyor ve seçilen alanı boş olan kayıtları getirmiyor: örneğin proje tipi HES seçilince proje tipi girilmemiş firmalar, köken seçilince kökeni girilmemiş firmalar listelenmiyor.',
                        'KKS teklif takip listesindeki teklifler aktarılıyor: her satır kendi TKLF numarasıyla bir teklif; aynı adlı potansiyel iş varsa ona bağlanıyor, yoksa açılıyor. Teklif durumu "Verilecek teklif" ya da "Verilen teklif"; listede kırmızı işaretli teklifler "Kaçan fırsat" ve bütün teklifleri kaybedilen potansiyel iş "Kaybedildi". Görüşme notları ve teklif tarihi teklif sürümünün özetinde.',
                        'Potansiyel işte durum değiştirirken yazılan uzun gerekçe kaydı engelliyordu; düzeltildi.',
                        'Teklifler listesi sekmelere ayrıldı: Tümü, Verilen Teklifler, Verilecek Teklifler ve Kaçan Fırsat; her sekmede teklif sayısı görünüyor.',
                        'Görüşme notları potansiyel işe ve tekliflere bağlanabiliyor: potansiyel iş ve teklif sayfalarında "Görüşme notları" sekmesi var, oradan eklenen not müşterinin taraf kartında da görünüyor. Taraf kartında not yazarken potansiyel iş ve konuşulan teklifler seçilebiliyor. KKS listesinden gelen görüşme notları tarihlerine göre ayrı görüşme kayıtları oldu; teklif özetinde artık yalnız teklif bilgileri var.',
                        'Sol menü daha derli toplu: daha dar, satırlar ve gruplar arasındaki boşluklar küçüldü, simgeler küçüldü; ekrana daha çok menü öğesi sığıyor ve içerik alanı genişledi.',
                    ],
                ],
            ],
            [
                'version' => '1.9',
                'date' => '21.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Listelere Excel indirme düğmesi (indirme simgesi) geldi: basınca tablo ekranda görüldüğü gibi hemen iniyor; yalnız görünen sütunlar, seçili süzgeçler, arama ve açık sekme dosyaya yazılıyor.',
                        'Kayıt ayrıntı sayfalarına "Dışa aktar" düğmesi geldi: kaydı Excel ya da PDF olarak hemen indirir. PDF\'te logo, kaydı hazırlayan ve tarih yer alıyor.',
                        'Dışa aktarma ilk olarak Taraflar, Dernekler, Görüşme planı, İş dosyaları, Teklifler, Sözleşmeler, Projeler, Talepler, Personel, Raporlar ve Dokümanlar\'da açıldı.',
                    ],
                    self::IMPROVEMENTS => [
                        'Dernekler kendi menüsüne ayrıldı: "Dernek oluştur" ile açılıyor, taraf tipi sorulmuyor ve dernekler artık Taraflar listesinde görünmüyor.',
                        'Taraf ayrıntısındaki kart kendi boyunu koruyor; Köken ve Faaliyet alanları sol karta taşındı.',
                        'Firma takip listesi 21.09.2026 sürümüyle güncellendi: 6 yeni firma açıldı (Berit Enerji, HVK Otomotiv, Met Yeşil Enerji, MFA Grup Enerji, Polateliges Enerji, Timur Yenilenebilir Enerji); 9 firmaya yeni notlar eklendi; AZRAX\'ın adresi eklendi, Ahlat Enerji\'nin adresi yeni merkeziyle (Egesa\'nın adresi) güncellendi; Cengiz Enerji\'deki yanlış web sitesi kaldırıldı; Egesa rakip firma olarak işaretlendi.',
                        'Taraflar listesinde uzun adlar 25 karakterde kısaltılıyor (tam ad üzerine gelince görünüyor, Excel\'e tam yazılıyor); Faaliyet alanları sütunu listeden kaldırıldı, süzgeçte ve ayrıntı kartında duruyor.',
                    ],
                    self::FIXES => [
                        'Taraf oluştururken "Taraf tipi" seçimi yeniden geldi.',
                        'Haftalık ziyaret planında benzer adlı firmalar ayrıldı: REİS ENERJİ ile REİS RS ENERJİ, FERNAS İNŞAAT ile FERNAS ŞİRKETLER GRUBU, LİMAK YENİLENEBİLİR ENERJİ ile LİMAK İNŞAAT, CENGİZ İNŞAAT ile CENGİZ HOLDİNG artık ayrı taraflar; görüşme notları ve planları doğru firmaya taşındı.',
                        'Canlıda talep yazışmasının biçimsiz görünmesi giderildi: stil ve betik dosyaları değiştikçe tarayıcı yeni sürümü kendiliğinden alıyor.',
                    ],
                    self::NOTES => [
                        'Kalan tablolar ve kayıtların alt tabloları (kişiler, adresler, görüşme notları vb.) sonraki adımda dışa aktarmaya açılacak.',
                    ],
                ],
            ],
            [
                'version' => '1.8',
                'date' => '21.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'İş Alım altına "Görüşme Planı" geldi. Hangi personelin ne zaman hangi firmayla görüşeceği ve görüştüğü aylık takvimde görünüyor; takvim sosyal medya takvimiyle aynı bileşeni kullanıyor. Güne tıklayınca o günün görüşmeleri açılıyor, oradan o güne görüşme planlanabiliyor. Personele göre süzülebiliyor.',
                        'Liste görünümünde Yaklaşan, Bugün, Geciken, Gerçekleşen ve Gerçekleşmeyen sekmeleri; personel, kanal ve tarih aralığı süzgeçleri var.',
                        'Planlı görüşmeye sorumlu personel ve katılacak personel (örneğin "Mustafa Güneş ile gidilecek") seçilebiliyor. Görüşmeden 1 gün önce ve günün sabahı ikisine de zil bildirimi gidiyor.',
                        'Görüşmenin sonucu "Sonucu gir" ile yazılıyor ve firmanın Görüşme notlarına düşüyor; görüşme olmadıysa "Gerçekleşmedi", tarih kaydıysa "Tarihi değiştir".',
                        'Firmaların Görüşme notlarına yazılan her not (yeni ya da geçmiş) Görüşme planı takvimine "gerçekleşti" olarak kendiliğinden düşüyor.',
                        'Görüşme notundaki "Sonraki adım" artık bildirim gönderiyor: tarih verilirse adım takvime planlı görüşme olarak düşüyor, 1 gün önce ve o günün sabahı görüşen personele hatırlatılıyor. Alanın yanındaki bilgi simgesi bunu açıklıyor.',
                        'Haftalık Ziyaret Planı (17 Ağustos – 18 Eylül 2026) sisteme aktarıldı: ziyaret ve telefon görüşmeleri Görüşme notlarına, gerçekleşmeyen ve bekleyen ziyaretler Görüşme planına yazıldı; listede olmayan firmalar açıldı.',
                    ],
                    self::NOTES => [
                        'Görüşen personeli boş olan tüm görüşme notlarına (daha önce aktarılanlar dahil) Ersin Özdemir yazıldı; personel sonradan kendi notunu güncelleyebilir.',
                        'Gerçekleşmiş görüşme Görüşme planından düzenlenmiyor; düzeltme firmanın Görüşme notlarından yapılıyor ve takvime yansıyor.',
                    ],
                ],
            ],
            [
                'version' => '1.7',
                'date' => '21.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Taraflara "Faaliyet alanları" eklendi. Firmanın ne iş yaptığı satır satır yazılıyor: proje tipi (GES, RES, HES, BES, TM, ENH/EİH ya da tümü), faaliyet alanı ve alt faaliyet alanı. Bir firma birden fazla satır alabiliyor; örneğin hem GES inverter hem BES batarya.',
                        'Taraflar listesine faaliyet süzgeci geldi: proje tipi, faaliyet alanı ve alt faaliyet alanı birlikte seçilerek aranıyor (örnek: HES + Türbin + Avrupa). Köken ve Rakip firma süzgeçleri de eklendi.',
                        'Taraf kartına "Köken" (Yerli / Avrupa / Çin / Diğer yabancı) ve "Rakip firma" onay kutusu eklendi. Rakip firmaları ilgili personel işaretliyor.',
                        'Yeni taraf tipi "Dernek / oda" ve menüde İhaleler\'in altında "Dernekler" geldi.',
                        'Faaliyet alanları Ayarlar\'dan yönetiliyor: yeni ana alan ya da alt alan eklenebiliyor, kullanılmayan alan pasife alınabiliyor.',
                        'Sektör haritası (Firma_Harita_Takip) sisteme aktarıldı: türbin, inverter, panel, depolama, trafo ve otomasyon üreticileri, HES / GES / RES proje firmaları, ÇED firmaları, kamu kurumları, dernekler ve büyük firmalar, yetkili kişileri ve telefonlarıyla. Sistemde zaten olan firmalar yeniden açılmadı, var olan kayda eklendi.',
                        'İhale ilanları ve İhale kaynakları tek "İhaleler" menüsünde iki sekme oldu. Haritadaki ihale takip kaynakları (EBRD, İslam Kalkınma Bankası, Proje Haber, Yatırımlar Dergisi, ANBA Haber, e-ÇED, YEM DER) İhale kaynaklarına eklendi.',
                    ],
                    self::NOTES => [
                        'Haritada "aranmayacak" yazan kişiler firmanın görüşme notlarına, "samimi" ve referans notları kişinin Network alanına yazıldı.',
                        'Rakip firma işareti aktarımda verilmedi; ilgili personel tarafından girilecek.',
                    ],
                ],
            ],
            [
                'version' => '1.6',
                'date' => '19.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Talep yazışması eklendi. Talep ilk mesaj sayılıyor; "Cevap gönder" ile verilen cevaplar 2., 3. mesaj olarak talebin içinde sırayla görünüyor. Talebin tarafları (kimden, kime, sorumlu, onay mercii) burada birbiriyle yazışabiliyor. Sohbet ekranından ayrıdır; çevrimiçi ya da yazıyor bilgisi yoktur.',
                        'Cevaba resim, PDF ve belge eklenebiliyor; resimler küçük görselle görünüp tıklayınca büyüyor, PDF ve metin dosyaları önizlenebiliyor. Yazıya yapıştırılan bağlantılar tıklanabilir oluyor.',
                        'Talebe "Yönlendir" seçeneği geldi. Talep sizinle ilgili değilse başka bir kişiye ya da departmana gerekçesiyle devredebiliyorsunuz; yeni muhatap ve talebi açan kişi bilgilendiriliyor.',
                        'Cevaplar, yönlendirmeler ve tüm talep adımları talebin Geçmiş tablosuna düşüyor.',
                    ],
                    self::NOTES => [
                        'Talep kapandıktan (tamamlandı, reddedildi, iptal) sonra yeni cevap yazılamaz; yazışma salt okunur kalır.',
                    ],
                ],
            ],
            [
                'version' => '1.5',
                'date' => '19.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Sosyal Medya modülü açıldı. Şirketin sosyal medya paylaşımları planlama, hazırlık, onay ve yayın aşamalarıyla tek ekrandan yönetiliyor.',
                        'Paylaşım akışı: içerikler durumuna göre (taslak, bekliyor, onaylandı, paylaşıldı) listeleniyor; acil işaretlenen içerik öne çıkıyor.',
                        'Fotoğraf, fotoğraf serisi ve video içerik eklenebiliyor. Büyük videolar parça parça yükleniyor.',
                        'Bir içerik aynı anda birden fazla platforma ayrılabiliyor; platformlar yalnız logolarıyla gösteriliyor.',
                        'İçerik ayrıntısında ekip içerik üzerine yorum yazabiliyor, yorumu görselin ilgili noktasına işaretleyebiliyor ve çözüldü olarak kapatabiliyor.',
                        'Fotoğraf araçları eklendi: kırpma, hazır boyut ayarları (kare, dikey, yatay vb.) ve çizim işaretleri. Her düzenleme yeni bir sürüm olarak saklanıyor, istenen sürüme geri dönülebiliyor.',
                        'Serideki fotoğrafların sırası değiştirilebiliyor, istenmeyen fotoğraf kaldırılıp geri alınabiliyor.',
                        'Görüntülenen sürüm tek tek, fotoğraf serisi ise tek seferde zip olarak indirilebiliyor.',
                        'Onay akışı: içerik hazırlayan kişi kendi içeriğini onaylayamıyor; onaylanan içerikte değişiklik yapılırsa içerik yeniden onaya düşüyor. Paylaşıldı işaretli içerik kilitleniyor.',
                        'Plan ekranı: aylık takvim ve ajanda görünümü; özel günler takvimde işaretleniyor ve yaklaşan içerikler panoda görünüyor.',
                        'Hatırlatmalar: yaklaşan ve geciken içerikler için sorumlulara otomatik bildirim gidiyor.',
                        'İlham ve rakip takibi: izlenen rakip hesaplar ve ilham içerikleri kaydedilebiliyor.',
                        'Analiz ekranı: hesap bazında istatistik girişleri yapılıyor, dosya (rapor) yüklenebiliyor ve sonuçlar grafiklerle izleniyor.',
                        'Ayarlar: şirket hesaplarının tanıtım yazısı ve platform bağlantıları, kategoriler, özel günler ve sorumlu pozisyonlar buradan yönetiliyor.',
                    ],
                    self::IMPROVEMENTS => [
                        'Mobil görünümde sekmeler yana kaydırılabiliyor.',
                        'Renkli düğmelerin yazıları beyaz ve okunur hale getirildi.',
                        'Ajanda kartlarında rozetlerin üst üste binmesi giderildi.',
                    ],
                    self::NOTES => [
                        'Şirket hesapları hazır tanımlı gelir; arayüzden yeni hesap eklenmez, yalnız tanıtım yazısı ve bağlantılar düzenlenir.',
                        'Modülün çalışması için ilk kurulumda hesap ve özel gün verileri yüklenmiş olmalıdır.',
                    ],
                ],
            ],
            [
                'version' => '1.4',
                'date' => '18.09.2026',
                'groups' => [
                    self::IMPROVEMENTS => [
                        'Geliştirme sırasında kullanılan örnek personel, proje, teklif ve doküman verileri kurulumdan çıkarıldı; sistem yalnız gerçek verilerle açılıyor.',
                        'Gerçek firma ve personel verilerinin aktarımı düzenlendi.',
                    ],
                ],
            ],
            [
                'version' => '1.3',
                'date' => '19.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Kurum içi sohbet açıldı. Personel artık sistemin içinden birbirine yazabiliyor; iş yazışması için ayrı bir uygulamaya gerek kalmıyor.',
                        'Birebir ve grup sohbeti kurulabiliyor.',
                        'Sohbete dosya eklenebiliyor, sistemdeki dokümanlar sohbet üzerinden paylaşılabiliyor.',
                        'Sık kullanılan sohbetler listenin üstüne sabitlenebiliyor.',
                        'Karşı tarafın çevrimiçi olduğu ve o an yazmakta olduğu görünüyor.',
                        'Okunmamış mesaj sayısı sağ alttaki sohbet düğmesinde görünüyor.',
                        'Sohbetin içinden doğrudan talep açılabiliyor; konuşulan iş kaybolmuyor, talebe dönüşüyor.',
                    ],
                ],
            ],
            [
                'version' => '1.2',
                'date' => '16.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Yetki sistemi gerçek rol yapısına geçirildi. Artık kimin neyi göreceğini rolü belirliyor; her pozisyonun kendi rolü var.',
                        'Roller pozisyonlarla eşleştirildi. Bir kişiye pozisyon verildiğinde rolü ve yetkileri kendiliğinden geliyor.',
                        'Ortak alanlar herkeste açık: pano, kendi raporları, talepler, personel rehberi ve organizasyon şeması.',
                        'Departman ekranları yalnız o departmanda açık. Satın alma tedariki, iş geliştirme müşteri ve teklifi, proje ekibi proje kayıtlarını görüyor.',
                        'Departman müdürlerine ek yetki verildi: onaylar, ekip raporlarını inceleme ve departmana duyuru gönderme.',
                        'Üç rol şirket genelinde tam yetkili: Yönetici, Geliştirici ve salt okuma için Denetçi.',
                        'Personel ünvan sistemi getirildi. Görev kişinin departmandaki işini, ünvan şirket genelindeki kademesini gösteriyor.',
                        'Şirket organizasyon yapısı sisteme işlendi: eksik departmanlar açıldı, görev listeleri tanımlandı, 28 personel departmanı, görevi, amiri, telefonu ve e-postasıyla eklendi.',
                        'Sürüm notları sisteme eklendi. Sağ üstteki menüden her sürümde ne değiştiği görülebiliyor.',
                        'Taraf kartına "Network" (nereden tanındığı) ve "Ziyaret önceliği" (Acil ziyaret / Rutin görüşme / Telefon) alanları eklendi; Taraflar listesi ziyaret önceliğine göre süzülebiliyor. Network alanı kişi kayıtlarında da var.',
                        'Taraf kartına "Görüşme notları" eklendi: tarih, kanal, görüşülen kişi, görüşen personel, konu, not ve sonraki adım tek listede tutuluyor.',
                        'Taraf formuna kuruma ait, kişiden bağımsız iletişim bilgileri (e-posta, telefon, web sitesi) eklendi.',
                        'Firma takip listesindeki 181 firma adresleri, iletişim bilgileri, yetkili kişileri, ziyaret öncelikleri ve görüşme notlarıyla sisteme aktarıldı. Listedeki projeler bir sonraki adımda eklenecek.',
                        'Taraf kartına "Arşivle" ve "Arşivden çıkar" geldi: kayıt silinmiyor, gerekçeyle arşive alınıyor; listede "Arşiv" süzgeciyle bulunuyor ve geri alınabiliyor.',
                        'Taraf ayrıntı sayfası personel sayfasındaki kart yapısına geçti: adres, tüm iletişim bilgileri (tıklanabilir), network ve yetkili kişi tek kartta; yanda ziyaret önceliği, görüşme sayısı ve son görüşme özeti.',
                        'İletişim bilgileri tıklanabilir oldu: telefon arar, e-posta yazar, web sitesi açılır. Taraf kartında her satırın yanında küçük kopyala düğmesi (telefonlarda WhatsApp da) var; kişiler listesinde satır menüsünden WhatsApp ve kopyalama yapılabiliyor.',
                        'İş dosyasında "Kritiklik" yerine "Teklif tipi" (Bütçesel / Kat\'i) geldi; "Proje tipi" seçimi "Proje kategorisi" adıyla korundu.',
                        'İş dosyasına "Proje tip seçimi" eklendi: GES, RES, TM, HES, BES, ENH/EIH işaretlenince her tipin kendi alanları açılıyor (GES: kurulu güç, maliyet, satış, MW başı maliyet/satış ve hesaplanan toplam satış; RES: Respark malzeme/inşaat/montaj; TM: toplam maliyet, toplam satış, fider başı maliyet) ve her tip için Excel kapsam listesi yüklenebiliyor. HES, BES ve ENH/EIH alanları tanımlanınca eklenecek.',
                        'Sihirbazın Teklif ve Proje adımlarının başında önceki adımların özet kartı görünüyor; yazılan her bilgi karta anında düşüyor, Proje adımında iş dosyası ve teklif özeti birlikte görülüyor.',
                        'Teklif adımına "Teklif durumu" (Verilecek teklif / Verilen teklif / Onaylandı / Kaçan fırsat), "Firmanın beklentileri" ve "Teklif mektubu" belge yükleme alanları geldi; Dokümanlar bölümüne bir kez yüklenen Referanslar belgesi ve Genel katalog her teklife "ekleyelim mi?" önerisiyle bağlanıyor.',
                        'İş dosyasında gizlilik sınıfından "Kısıtlı" kaldırıldı; para birimi listesi TRY, USD, EUR ve RON ile sınırlandı; Sorumlular bölümü "Kontrol eden" ve "Hazırlayan" oldu.',
                        'İş dosyası formu yeniden düzenlendi: "Müşteri ve başlık" ile "Sınıflandırma", "Ticari bilgiler" ile "Sorumlular" yan yana durur; "Proje kategorisi" alanı bu formdan kaldırıldı (başka ekranlarda duruyor); Proje tip seçimi iki satır oldu.',
                    ],
                    self::FIXES => [
                        'Taraf kartındaki taraf tipi, adresler, iletişim noktaları, kişiler, lisanslar, sertifikalar ve yıllık değerlendirmeler yeniden görünüyor.',
                        'Bir kaydı görebilen kişi artık o kaydın alt listelerini de görüyor; yetki ana kayıttan devralınıyor.',
                        'Sohbet penceresi dar ve kısa ekranlara uyumlu hale getirildi: telefonda ve yatay/kısa pencerede tam ekran açılıyor, başlığı ve Kapat düğmesi üst çubuğun altında kalmıyor; dokunmatik cihazda sohbet satırı ve mesaj yanındaki düğmeler (sabitle, sil, talep aç) üzerine gelmeden görünüyor.',
                    ],
                    self::IMPROVEMENTS => [
                        'Sistem temiz veriyle başlatıldı. Geliştirme sırasında kullanılan örnek proje, müşteri, teklif, doküman ve personel kayıtları kaldırıldı.',
                        'Yetki artık sunucu ayar dosyasına bağlı değil; roller doğrudan sistemden okunuyor.',
                        'Teknik yönetim rolü kaldırıldı, yerine gerçek iş rolleri kondu.',
                        'Çalışma ve deneme arayüzleri yalnız geliştirme ortamında görünüyor; canlıda menüde yer almıyor.',
                        'Adres satırına yalnız site adresi yazıldığında doğrudan yönetim paneli açılıyor.',
                        'Taraflar listesi taraf tipine göre sekmelere ayrıldı: müşteri, tedarikçi, taşeron, işveren, resmî kurum ve diğerleri. Her sekmede kayıt sayısı görünüyor.',
                        'Yeni taraf açarken en az bir taraf tipi girmek zorunlu oldu. Tipler satır satır ekleniyor; her satırda tip, durum, onaylayan ve geçerlilik tarihleri var. Bir taraf aynı anda birden fazla tipte olabiliyor.',
                        '"Roller" listesinin adı "Taraf tipi" oldu.',
                        'Taraf durumu dört seçeneğe indi: Aktif, Pasif, Yasaklı, Aday.',
                        'Kişi ve iletişim bilgisi tek ekranda birleşti. Kişiyi ekleyip altına o kişiye ait tüm iletişim bilgilerini yazıyorsunuz: iş telefonu, cep, e-posta, faks; sınırsız satır.',
                        'Müşterinin lisansları proje kartında da görünüyor; proje ekibi taraf kartına girmeden kontrol edebiliyor.',
                        '"Asıl" işareti "Varsayılan" olarak adlandırıldı; hangi adresin, telefonun ve muhatabın öncelikli olduğu daha anlaşılır.',
                        'Taraf formundan kullanılmayan varsayılan dil alanı kaldırıldı.',
                        '"Oluştur ve yeni oluştur" düğmesi tüm ekranlardan kaldırıldı; kayıt Kaydet ile açılır, yeni kayıt için form yeniden açılır.',
                        'Tüm tarih alanları tek düzene indirildi: gün.ay.yıl, saat girilmiyor, her ekranda aynı boyut. Kişi, taraf tipi ve yıllık değerlendirme pencereleri de bu düzene geçti.',
                        'Kişi ekleme formundan "Kayıtlı taraf" alanı kaldırıldı; Taraflar listesindeki "Roller" sütunu "Tipi" oldu.',
                        'Çalışma alanı genişletildi: ekranlar artık geniş monitörde ortada dar bir şeritte kalmıyor, tablolar ve formlar ekranın tamamını kullanıyor.',
                    ],
                    self::NOTES => [
                        'Ünvan alanı bilerek boş bırakıldı; her personel kendi ünvanını sisteme kendisi girecek.',
                        'Kimlik numarası ve işe giriş tarihi bilgileri henüz girilmedi.',
                        'Personele giriş için geçici parola tanımlandı; ilk girişten sonra değiştirilmesi gerekiyor.',
                    ],
                ],
            ],
            [
                'version' => '1.1',
                'date' => '15.09.2026',
                'groups' => [
                    self::FEATURES => [
                        'Rapor sistemi getirildi. Personel artık sistem üzerinden rapor yazıyor; rapor tipine göre ekran değişiyor.',
                        'On hazır rapor taslağı tanımlandı: günlük, haftalık ve aylık çalışma raporu, proje durum raporu, ürün performans raporu, teklif değerlendirme, iş dosyası değerlendirme, yönetici değerlendirmesi, İK görüşü ve sistem verileri raporu.',
                        'Günlük, haftalık ve aylık raporlar iş panosu biçiminde; işler Planlandı, Devam ediyor, Tamamlandı ve Engellendi sütunlarında görünüyor.',
                        'Tamamlanmayan işler bir sonraki rapora otomatik taşınıyor.',
                        'Raporlar sayısal özet üretiyor: toplam çalışma saati, tamamlanan ve açık iş sayısı kendiliğinden hesaplanıyor.',
                        'Raporlar personel, proje, ürün, teklif ve iş dosyası kayıtlarına bağlanabiliyor.',
                        'Rapor inceleme akışı eklendi. Gönderilen raporu amir onaylıyor, revizyon istiyor veya reddediyor.',
                        'Kişi değerlendirme raporları gizli tutuluyor; değerlendirilen personele gösterilmiyor.',
                        'Talep sistemine onay aşaması getirildi. Talep artık talep eden, talep edilen ve onaylayan olarak üç aşamalı çalışabiliyor.',
                        'Onaya tabi talepte iş tamamlandığında talep kapanmıyor, önce onay merciine gidiyor.',
                    ],
                    self::IMPROVEMENTS => [
                        'Talepler artık personel, müşteri ve proje kartlarında görünüyor.',
                        'Talep detay sayfası yeniden düzenlendi; talep ve taraflar bilgisi yan yana geldi.',
                        'Talep geçmişi tablo biçiminde eklendi; kimin ne zaman hangi işlemi yaptığı okunur halde.',
                        'Talep oluşturma formunda "kimden" ve "kime" bölümleri yan yana getirildi.',
                        'Rapor ve talep ekranlarındaki etiketler Türkçeleştirildi.',
                    ],
                    self::FIXES => [
                        'Talep eden kendi talebini artık kabul edemiyor, tamamlayamıyor ve reddedemiyor; bu işlemler yalnız talep edilen kişide.',
                        '"Onaya gönder" düğmesindeki anlam karmaşası giderildi; düğme artık açık talepte onay merciini seçtiriyor.',
                        'Rapor yazarı kendi raporunu inceleyemiyor.',
                        'Sohbet düğmesi artık sihirbaz "İleri" ve "Kaydet" düğmelerinin üstüne binmiyor.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Gosterilen surumler. Canlida yayin kaydi varsa (D-151 / D-152) o surume
     * kadar olanlar; yoksa (yerel ortam, ilk yayindan once) yayin tarihi
     * gelmis olanlar (bugun dahil).
     *
     * @return list<array{version: string, date: string, groups: array<string, list<string>>}>
     */
    public static function published(): array
    {
        $released = app(FeatureRegistry::class)->publishedVersion();

        if ($released !== null) {
            // Gosterilen tarih surumun canlida yayinlandigi gundur (D-153).
            $dates = app(FeatureReleaseQueries::class)->publishedDates();

            return array_values(array_map(
                static function (array $release) use ($dates): array {
                    if (isset($dates[$release['version']])) {
                        $release['date'] = DisplayTime::format($dates[$release['version']], 'd.m.Y');
                    }

                    return $release;
                },
                array_filter(
                    self::all(),
                    static fn (array $release): bool => version_compare($release['version'], $released, '<='),
                ),
            ));
        }

        $today = Carbon::now()->startOfDay();

        return array_values(array_filter(
            self::all(),
            static fn (array $release): bool => self::releasedOn($release['date'])?->lte($today) ?? false,
        ));
    }

    /** Yayimlanmis en yeni surumun numarasi; hic yoksa bos. */
    public static function latestVersion(): string
    {
        return (string) (self::published()[0]['version'] ?? '');
    }

    /** Tarih metnini (gun.ay.yil) tarihe cevirir; bozuksa null. */
    private static function releasedOn(string $date): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d.m.Y', $date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
