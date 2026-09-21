<?php

declare(strict_types=1);

namespace App\Support;

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
 * Yeni surum: listenin BASINA yeni bir kayit eklenir; gruplar asagidaki
 * sabitlerle anahtarlanir (etiket ve simge ReleaseNotesSchema'da).
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
                        'Teknik "sistem yöneticisi" rolü kaldırıldı, yerine gerçek iş rolleri kondu.',
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
     * Yalniz yayin tarihi gelmis surumler (bugun dahil).
     *
     * @return list<array{version: string, date: string, groups: array<string, list<string>>}>
     */
    public static function published(): array
    {
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
