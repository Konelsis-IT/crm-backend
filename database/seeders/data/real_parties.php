<?php

declare(strict_types=1);

/**
 * Firma takip listesi (Firma_Takip_Listesi.xlsx, 14 Eylul 2026 surumu, 16 Eylul
 * 2026 kullanici talimatiyla aktarildi). RealPartySeeder bu diziyi okur.
 *
 * Uretici: Excel satirlari firma adina gore birlestirildi; "FIRMA KARTVIZIT"
 * adres / web sitesi / telefon / e-posta olarak, "YETKILI KISI" kisi ve
 * kanallari olarak, "NOTLARIMIZ" gorusme notu olarak ayristirildi.
 * "ZIYARET ONCELIGI" 1=acil ziyaret, 2=rutin gorusme, 3=telefon.
 * 'projects' anahtari ILERIDE proje aktarimi icin saklanir; seeder simdilik
 * kullanmaz (kullanici karari: projeler proje gelistirmeleri bitince).
 *
 * Bu dosya elle duzenlenebilir; yeniden uretilirse elle yapilan duzeltmeler
 * kaybolur.
 */

return [
    [
        'name' => 'ACM ENERJİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İşçi Blokları Mah. Mevlana Blv. Ege Plaza No:182B Kat:8 İç kapı No:34 Çankaya/Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.acmmuhendislik.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 533 962 22 95',
            ],
            [
                'type' => 'email',
                'value' => 'info@acmmuhendislik.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Osman Güler',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 552 287 71 32',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'YOL TESVİYESİ YAPILMAKTA. 15 ŞUBATTAN SONRA TEKLİF TOPLAYACAKLAR. Çed olumlu.yaşam alanları ve imar tartışması var. Yargıya taşındı konu.',
            ],
        ],
        'projects' => [
            [
                'name' => 'KARDELEN 2 GES',
                'status' => 'ÖNLİSANS',
                'type' => 'GES EDT',
                'power' => '30MW/30MWH',
                'excel_row' => 2,
            ],
            [
                'name' => 'SOĞUKPINAR GES',
                'status' => 'ÖNLİSANS',
                'type' => 'GES EDT',
                'power' => '21MW/21MWH',
                'excel_row' => 3,
            ],
            [
                'name' => 'KARDELEN 4 GES',
                'status' => 'ÖNLİSANS',
                'type' => 'GES EDT',
                'power' => '20MW/20MWH',
                'excel_row' => 4,
            ],
        ],
    ],
    [
        'name' => 'ADİS ELEKTRİK ENERJİSİ TEDARİK ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'FERKO SİGNATURE ESENTEPE BÜYÜKDERE CAD.NO:175 ŞİŞLİ',
            'district' => 'Şişli',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 809 23 47',
            ],
            [
                'type' => 'website',
                'value' => 'adisenerji.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'info@adisenerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Özgür Tatar',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 536 741 28 80',
                    ],
                ],
            ],
            [
                'name' => 'İdris Küpeli',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 733 13 53',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => '(17.08.2026 Özgür Bey projenin karar aşamasında olduğunu henüz EPC firmalarından teklif toplanmadığını söyledi. Proje hakkında istek ve firma tanıtımımız mail olarak iletildi.) ÇED ve Mühendislik Raporu: Projenin dosya hazırlık süreçleri Nartus Enerji tarafından koordine edilmiştir. 20/08',
            ],
            [
                'on' => '2026-08-20',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ÇED ve Mühendislik Raporu: Projenin dosya hazırlık süreçleri Nartus Enerji tarafından koordine edilmiştir. 20/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'GAZİANTEP YAVUZELİ GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 5,
            ],
            [
                'name' => 'EDİRNE ENEZ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '100 MW',
                'excel_row' => 6,
            ],
            [
                'name' => 'BURSA NİLÜFER RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '100 MW',
                'excel_row' => 7,
            ],
            [
                'name' => 'KIRKLARELİ MERKEZ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '100 MW',
                'excel_row' => 8,
            ],
            [
                'name' => 'Güngören RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '100,00 MWm / 100,00 MWe 100,00 MWh / 100,00 MWe',
                'excel_row' => 9,
            ],
        ],
    ],
    [
        'name' => 'AG MARS YENİLENEBİLİR ELEKTRİK VE ELEKTRİK ÜRETİMİ SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Kırıkkale',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'OSMANGAZİ MAH. ALPARSLAN TÜRKEŞ BLV. TÜRKIYE PETROL OFISI TP. /639 MERKEZ / KIRIKKALE',
            'district' => 'Merkez',
            'city' => 'Kırıkkale',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Ali Gümüşsoy',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KIRIKKALE/KESKİN AG MARS-4 DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '66,76 MWm / 49,95 MWe 50,00 MWh / 50,00 MWe',
                'regulatory_note' => 'çed olumllu tarihi 22.01.2026',
                'excel_row' => 10,
            ],
        ],
    ],
    [
        'name' => 'AĞUSTOS ELEKTRİK ÜRETİM VE DEPOLAMA A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Aşağı Öveçler Mah. 1322 Cad. Eroğlu Apt No:38 A Çankaya/AnkaraTURKIYE',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 323 79 00',
            ],
            [
                'type' => 'email',
                'value' => 'rifat.celebi@sungen.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ömer Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 542 402 19 55',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => 'proje ankara etimesgutta. (27.08.2026 Mail atıldı.) (28.08.2026 ömer bey ile iletişime geçildi. Mustafa Bey ile görüştüklerini söylediler. Önlisansları mevcut. Lisans almak için çalışmaları var. İmar ruhsat için uğraşıyorlarmış. Farklı eih projelerinden bahsedildi süreç başladığında iletişime gececekler.)',
            ],
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => 'proje ankara yenimahallede. (27.08.2026 Mail atıldı.) (28.08.2026 ömer bey ile iletişime geçildi. Mustafa Bey ile görüştüklerini söylediler. Önlisansları mevcut. Lisans almak için çalışmaları var. İmar ruhsat için uğraşıyorlarmış. Farklı eih projelerinden bahsedildi süreç başladığında iletişime gececekler.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'Ağustos Tuluntaş Güneş Enerji Santrali (10 Mwe) Ve Enerji Depolama Tesisi (10 MWe)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 11,
            ],
            [
                'name' => 'Ağustos Yuva Güneş Enerji Santrali (10 MWe) ve Elektrik Depolama Tesisi (10 MWe)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'IDK tarihi 12.05.2026',
                'excel_row' => 12,
            ],
        ],
    ],
    [
        'name' => 'AHLAT ENERJİ ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'Denizli',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Sümer Mah. 2482/2 Sk. Skycity B Blok İş Merkezi No:4 /1 İç Kapı No:129 Merkezefendi / DENİZLİ',
            'district' => 'Merkezefendi',
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 258 252 12 02',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 549 442 97 35',
            ],
            [
                'type' => 'email',
                'value' => 'info@novitasenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'TATVAN MÜSTAKİL DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '65 MWe 76,88 MWm/ 195 MWe 195 MWh',
                'excel_row' => 13,
            ],
        ],
    ],
    [
        'name' => 'AKDAĞ GRANİT MERMER VE MADEN SANAYİ TİCARET ANONİM ŞİRKETİ',
        'city' => 'Elazığ',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Organize Sanayi Böl. 4. Yol Yazıkonak / Elazığ / Türkiye',
            'district' => null,
            'city' => 'Elazığ',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://akdagmermer.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 533 253 30 55',
            ],
            [
                'type' => 'email',
                'value' => 'info@akdagmermer.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Harun Ayyıldız',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 781 84 63',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'ayyildizharun@gmail.com',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '3 ay sonra teklif toplanacak. (28.08.2026 Mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'Elazığ/Palu 6,99192 MWm/6,5 MWe KAPASİTELİ ARAZİ TİPİ GÜNEŞ ENERJİ SANTRALİ (GES)',
                'type' => 'GES',
                'power' => '6,99192 MWm/6,5 MWe',
                'excel_row' => 14,
            ],
        ],
    ],
    [
        'name' => 'Akensa Enerji Mühendislik Danışmanlık Sanayi ve Ticaret Limited Şirketi Çal Enerji Üretim Anonim Şirketi',
        'city' => 'Ankara',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Aziziye Mahallesi, Hoşdere Caddesi, Güven Apartmanı, No:159/8 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 803 28 23',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 057 45 47',
            ],
            [
                'type' => 'email',
                'value' => 'ecedproje@gmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Aksaray 1 GES AKSARAY / AĞAÇÖREN',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '10,00 MWm / 10,00 MWe',
                'excel_row' => 15,
            ],
            [
                'name' => 'Aksaray 2 Depolamalı GES AKSARAY / AĞAÇÖREN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,50 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 16,
            ],
            [
                'name' => 'Aksaray 3 Depolamalı GES AKSARAY / AĞAÇÖREN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,50 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 03.04.2026',
                'excel_row' => 17,
            ],
        ],
    ],
    [
        'name' => 'AKFEN ELEKTRİK ENERJİSİ TOPTAN SATIŞ A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'İlkbahar Mahallesi Turan Güneş Bulvarı Galip Erdem Caddesi No:3 Çankaya /ANKARA /TURKIYE',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 408 14 00',
            ],
            [
                'type' => 'email',
                'value' => 'bsolmaz@akfen.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Metin Yıldırım',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 415 89 35',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 954 18 87',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '(Mustafa Güneş)',
            ],
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => '(27.08.2026 Mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'power' => '101 MW',
                'excel_row' => 18,
            ],
            [
                'name' => 'ERZURUM GELİNKAYA GÜNEŞ ENERJİ SANTRALİ (35,75 MWm / 30 MWe - 44,88 ha) VE ELEKTRİK DEPOLAMA TESİSİ (30 MWe / 60 MWh)',
                'type' => 'GES EDT',
                'power' => '30 MW',
                'excel_row' => 19,
            ],
            [
                'name' => 'AMASYA RÜZGÂR ENERJİ SANTRALİ (6 TÜRBİN - 30 MWm/30 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (30 MWe/60 MWh)',
                'type' => 'RES EDT',
                'power' => '30 MW',
                'excel_row' => 20,
            ],
            [
                'name' => 'Aydın Rüzgâr Enerji Santrali (8 Türbin-30,88 MWm/30 MWe) ve Elektrik Depolama Tesisi (30 MWe/60 MWh)',
                'type' => 'RES EDT',
                'power' => '30 MW',
                'regulatory_note' => 'çed olumlu tarihi 06.01.2026',
                'excel_row' => 21,
            ],
            [
                'name' => 'VAN GÜNEŞ ENERJİ SANTRALİ (66 MWm / 50 MWe - 73,62 ha) VE ELEKTRİK DEPOLAMA TESİSİ (50 MWe / 120 MWh)',
                'type' => 'GES EDT',
                'power' => '50 MW',
                'excel_row' => 22,
            ],
            [
                'name' => 'OSMANİYE SARITEPE RÜZGÂR ENERJİ SANTRALİ (20 TÜRBİN -95 MWm/95 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (95 MWe/200 MWh)',
                'type' => 'RES EDT',
                'power' => '95 MW',
                'regulatory_note' => 'çed olumlu tarihi 15.05.2026',
                'excel_row' => 23,
            ],
            [
                'name' => 'KAHRAMANMARAŞ SARITEPE RÜZGÂR ENERJİ SANTRALİ (20 TÜRBİN -95 MWm/95 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (95 MWe/200 MWh)',
                'type' => 'RES EDT',
                'power' => '95 MW',
                'excel_row' => 24,
            ],
            [
                'name' => 'GAZİANTEP SARITEPE RÜZGÂR ENERJİ SANTRALİ (20 TÜRBİN -95 MWm/95 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (95 MWe/200 MWh)',
                'type' => 'RES EDT',
                'power' => '95 MW',
                'excel_row' => 25,
            ],
            [
                'name' => 'ÇANAKKALE ÜÇPINAR RÜZGÂR ENERJİ SANTRALİ (12 TÜRBİN-50 MWm/50 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (50 MWe/100 MWh)',
                'type' => 'RES EDT',
                'power' => '50 MW',
                'excel_row' => 26,
            ],
        ],
    ],
    [
        'name' => 'AKIN GES ELEKTRİK ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'MERKEZ MAH.GEÇİT SOKAK AKIN İŞ MERKEZİ NO:4/5 ŞİŞLİ / İSTANBUL',
            'district' => 'Şişli',
            'city' => 'İstanbul',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Ahmet Akın',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 212 246 00 35',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'NEVŞEHİR/ DERİNKUYU Özlüce Ges',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '21,6216 MWm / 15,40 MWe 15,40 MWh / 15,40 MWe',
                'excel_row' => 27,
            ],
        ],
    ],
    [
        'name' => 'AKİ GÜÇ VE ENERJİ SİSTEMLERİ A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'BÜYÜKESAT MAHATMA GANDHİ CAD.NO:74 GOP(ALFA) İNÖNÜ MAH.1748.CAD.NO:1 YENİMAHALLE (İNAVİTAS)',
            'district' => 'İnönü',
            'city' => 'Eskişehir',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 230 32 57',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 920 00 18',
            ],
            [
                'type' => 'email',
                'value' => 'info@alfasolarenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'ALFA SOLAR VE İNAVİTAS ENERJİ KOYUNCU NAKLİYE ORTAK',
            ],
            [
                'on' => '2026-08-19',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'alfa solar ,inavitas(endoks ve wattox) koyuncu nakliyat ortak girişimidir. (19/08) alfa ahmet bey ile konuşuldu. Epc insos enerji konya firması ile yürüyorlarmış. Maalesef halletmişler.(20/08)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ÇORUM MEZİTÖZÜ GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '55MWe 77MWm /55 Mwe 55 MWh',
                'excel_row' => 28,
            ],
        ],
    ],
    [
        'name' => 'AKSA ENERJİ TİCARETİ A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Rüzgarlıbahçe Mahallesi, Özalp Çıkmazı No:10 34805 Kavacık Beykoz - İSTANBUL',
            'district' => 'Beykoz',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'aksaenerji.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'enerji@aksa.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Şaban Cemil Kazancı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 681 00 00',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 507 977 62 20',
                    ],
                ],
            ],
            [
                'name' => 'Ahmet Serdar Nişli',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Korkut Öztürkmen',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Tülay Kzancı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Murat Kirazlı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MERSİN RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '112,00 MWm / 100,08 MWe 100,00 MWh / 100,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 6.02.2024',
                'excel_row' => 29,
            ],
        ],
    ],
    [
        'name' => 'Aksa Yenilenebilir Enerji Üretim Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Rüzgarlıbahçe Mahallesi, Özalp Çıkmazı No:10 34805 Kavacık Beykoz - İSTANBUL',
            'district' => 'Beykoz',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'aksaenerji.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'enerji@aksa.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Şaban Cemil Kazancı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 681 00 00',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 507 977 62 20',
                    ],
                ],
            ],
            [
                'name' => 'Ahmet Serdar Nişli',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Korkut Öztürkmen',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Tülay Kzancı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Murat Kirazlı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'İhaleye katıldık. Alamadık.Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Sonuçlandı 9. sıradayız.Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA KAYIT',
            ],
        ],
        'projects' => [
            [
                'name' => 'KARAMAN BESS',
                'type' => 'GES',
                'excel_row' => 30,
            ],
            [
                'name' => 'MİGROS 2 GES',
                'type' => 'GES',
                'excel_row' => 31,
            ],
            [
                'name' => 'ESKiŞEHİR MİHALGAZİ',
                'type' => 'RES',
                'power' => '140 MW',
                'excel_row' => 32,
            ],
            [
                'name' => 'PAMUK GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '58,00 MWm / 40,50 MWe 96,32 MWh / 81,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 29.08.2026',
                'excel_row' => 33,
            ],
            [
                'name' => 'Manisa RES MANİSA',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '82,16 MWe / 88 MWm 90,5749 MWh',
                'excel_row' => 34,
            ],
        ],
    ],
    [
        'name' => 'AKSOY GRUP',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Değirmen Yolu Cad. No:28 Asia Ofispark A Blok Kat:1 34752 Ataşehir - İstanbul Türkiye',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 216 571 49 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@aksoygroup.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Çetin İmir',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 363 86 19',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-15',
                'channel' => 'other',
                'subject' => null,
                'text' => '15.06.2026 TARİHİNDE ZUHAL HANIM',
            ],
        ],
        'projects' => [
            [
                'power' => '4,9 MW',
                'excel_row' => 35,
            ],
        ],
    ],
    [
        'name' => 'ALANYA ÖZKAYMAK TURİZM İŞLETMECİLİĞİ A.Ş.',
        'city' => 'Konya',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'FERİTPAŞA MH. GÜRAĞAÇ SK. NO:7/E SELÇUKLU/KONYA',
            'district' => 'Selçuklu',
            'city' => 'Konya',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 332 352 92 89',
            ],
            [
                'type' => 'email',
                'value' => 'info@ak-ko.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Gözde Hanım',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 545 634 62 98',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => '(zuhal hanım denizli projesi 30 mw için fizibilite çalışması notu düşmüş). 27.08.2026 Mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'E-Zone 2 Burdur GES Elektrik Üretim ve Depolama Tesisi (27,600 MWe)',
                'type' => 'GES EDT',
                'power' => '27,6 MW',
                'excel_row' => 36,
            ],
        ],
    ],
    [
        'name' => 'ALTIN YENİLEBİLİR ENERJİ TİC.LTD.ŞTİ.',
        'city' => 'Diyarbakır',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'FIRAT MAH. 553. SK. TANLAR ŞEHRİ TERAS TANLAR ŞEHRİ TERAS B-BLOK 38/2 B KAYAPINAR / DİYARBAKIR',
            'district' => 'Kayapınar',
            'city' => 'Diyarbakır',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Muhammed Balkaya',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 531 949 55 55',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'köşe firmalarada soracağız.19/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'BATMAN GERCÜŞ GES VE EDT',
                'status' => 'IDK',
                'type' => 'GES EDT',
                'power' => '12 MW',
                'regulatory_note' => 'nihai karar tarihi 22.10.2025',
                'excel_row' => 37,
            ],
            [
                'name' => 'BATMAN GERCÜŞ GES VE EDT',
                'status' => 'IDK',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 11.11.2025',
                'excel_row' => 38,
            ],
        ],
    ],
    [
        'name' => 'ANKATECH ENERJİ MÜHENDİSLİK MÜŞAVİRLİK ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'RÜZGARLIBAHÇE MAH. ÖZALP ÇIKMAZI SK. NO:10 BEYKOZ / İSTANBUL',
            'district' => 'Beykoz',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 544 770 86 28',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 554 427 91 87',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 681 00 00',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KAYSERİ KOCASİNAN FATİH GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 20,00 MWh / 20,00 MWe',
                'excel_row' => 39,
            ],
        ],
    ],
    [
        'name' => 'ANKUTSAN ANTALYA KUTU SANAYİ OLUKLU MUKAVVA KAĞIT TİC. A.Ş.',
        'city' => 'Antalya',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Antalya Organize Sanayi Bölgesi 2. Kısım Mahallesi, 22. Cadde, No:6 Döşemealtı/Antalya /TURKIYE',
            'district' => 'Döşemealtı',
            'city' => 'Antalya',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 242 249 77 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@ankutsan.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 422 26 80',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => '(27.08.2026 Mail atıldı.) (28.08.2026 +90532 422 26 80 arandı kendileri yapacaklarını söylediler lakin 444 2 257 aranarak adana hattına bağlanılcak.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'KONYA EMİRGAZİ GÜNEŞ ENERJİSİ SANTRALİ (14,24 MWm / 11,00 MWe Kapasite- 22,07 Ha)',
                'status' => '5.1.h',
                'type' => 'GES EDT',
                'power' => '11 MW',
                'excel_row' => 40,
            ],
        ],
    ],
    [
        'name' => 'Aral Toprak Kömür ve Nakliye İth. İhr. San. ve Tic. Ltd. Şti.',
        'city' => 'Manisa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Koşukırı Mevkii, Eski Maniye Yolu Üzeri, No: 162 - Tugutlu / Manisa',
            'district' => null,
            'city' => 'Manisa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://araltugla.com.tr/tugla',
            ],
            [
                'type' => 'phone',
                'value' => '+90 236 312 10 02',
            ],
            [
                'type' => 'email',
                'value' => 'info@araltugla.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 236 313 10 02',
            ],
            [
                'type' => 'phone',
                'value' => '+90 236 312 70 02',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MANİSA/TURGUTLU Güneş Enerji Santrali (1,5015 MWm /1,350 MWe - 1,998 hektar )',
                'type' => 'GES',
                'power' => '1,5015 MWm /1,350 MWe',
                'excel_row' => 41,
            ],
        ],
    ],
    [
        'name' => 'ARTFA Enerji İnşaat Mühendislik Mimarlık Sanayi ve Ticaret',
        'city' => 'Ankara',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Mustafa Kemal Mah. 2118. Cadde No:4d İç Kapı No:2 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 123 45 67',
            ],
            [
                'type' => 'email',
                'value' => 'info@artfaenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 577 50 41',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 577 50 42',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 538 845 17 36',
            ],
            [
                'type' => 'email',
                'value' => 'info@ulusoyenerji.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ADIYAMAN/GÖLBAŞI',
                'type' => 'GES EDT',
                'power' => '10 MW 10 MWh',
                'excel_row' => 42,
            ],
        ],
    ],
    [
        'name' => 'ASAŞ ALÜMİNYUM SAN. VE TİC. A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'ASAŞ Alüminyum Sanayi ve Ticaret A.Ş. Rüzgarlı Bahçe Mah., Kumlu Sok. No.2 Asaş İş Merkezi, 34810 Kavacık, Beykoz – İstanbul, Türkiye',
            'district' => 'Beykoz',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.asastr.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Özgür Çalık',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 680 07 80',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 535 894 50 13',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'ozgur.calık@asaştr.com',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'info@asastr.com',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Mail atıldı dönüş bekleniyor. (28.08.2026 Mail atıldı.)',
            ],
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '(28.08.2026 Mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'AFYON/DİNAR ASAŞ GENÇALİ GÜNEŞ ENERJİ SANTRALİ (GES) (32,0034 MWm / 22,90 MWe / 33,72 ha ALAN)',
                'type' => 'GES',
                'power' => '32,0034 MWm / 22,90 MWe',
                'excel_row' => 43,
            ],
            [
                'name' => 'VAN/GÜRPINAR Gedik Güneş Enerji Santrali (71,5 MWm / 46,9 MWe) (71,40 ha)',
                'type' => 'GES',
                'power' => '71,4 MW',
                'excel_row' => 44,
            ],
            [
                'name' => 'UŞAK /EŞME YEŞİLKAVAK GÜNEŞ ENERJİ SANTRALİ (62,41664 MWm/ 62,41664MWp/41,000 MWe - 62,9839 ha)',
                'type' => 'GES',
                'power' => '41 MW',
                'regulatory_note' => 'nihai karar tarihi 06.08.2026',
                'excel_row' => 45,
            ],
        ],
    ],
    [
        'name' => 'ASCE TEKNİK YAPI SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Gaziantep',
        'network' => 'Telefon numaraları çed dosyasından alınmıştır',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Sarıgüllük Mah. Zübeyde Hanım Bul. A Blok Sitesi Hayat Evler No:64a Şehitkamil / Gaziantep',
            'district' => 'Şehitkamil',
            'city' => 'Gaziantep',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.ascegyo.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 342 211 30 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@ascegyo.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 342 339 18 00',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 546 497 11 08',
            ],
            [
                'type' => 'email',
                'value' => 'oyeniekinci@sanko.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'İZMİR/BERGAMA',
                'type' => 'GES',
                'power' => '8.95 MWm / 8,36 MWe',
                'excel_row' => 46,
            ],
        ],
    ],
    [
        'name' => 'ASY GAYRİMENKUL İNŞAAT ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'KISIKLI MAH. FERAH CAD. /1 /4 ÜSKÜDAR / İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Mehmet Fatih Çağlar',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 443 77 84',
                    ],
                ],
            ],
            [
                'name' => 'Eyüp Önalan',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Osman Özdemir',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ANKARA/GÖLBŞIÖZBEK GÖLBAŞI DEPOLAMALI GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '20,0005 MWm / 20,00 MWe 20,00 MWh / 20,00 MWe',
                'excel_row' => 47,
            ],
        ],
    ],
    [
        'name' => 'AXİS ENERJİ ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Yenibosna Merkez Mah. Kuyumcukent Sk. No:36 D:10 Bahçelievler/İSTANBUL',
            'district' => 'Bahçelievler',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 544 740 22 54',
            ],
            [
                'type' => 'email',
                'value' => 'e.yilmaz@hkenergy.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Halife Koç Hk Elektrik',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 212 979 27 57',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'SELİN İNŞ.BÜNYESİNDE BU FİRMA',
            ],
            [
                'on' => '2026-08-20',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'çed başvuruları MGS (03124798400) ((Özge Hanım 05554293686 çed süreci için 6ay ile 1 yıl kadar uzatma olduğu bilgisi verildi diğer projelerle ilgili kısa bilgi verilip bilgilerinin e ced uzerinden takip önerildi.) ama firmaya ulaşamıyoruz. (18/08) ÇED başvuru süreçlerini yürüten ve teknik dosyaları hazırlayan MGS Proje Müşavirlik Mühendislik firmasıdır.Sektörel bilgi ve ortaklık süreçleri için bu aracı kurumla veya projenin ilk aşamasını hazırlayan Selin İnşaat Turizm Ltd. Şti. (20/08) (20.08.2026 Eda hanım ile görüşüldü. RES için henüz proje aşamasında olduklarını, türbin şirketleri ile anlaşma yapmadıklarını ve EPC firmalardan teklif istemek için min 1 yıl süre öngördüklerini belirtti.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'TEKİRDAĞ MALKARA',
                'status' => 'IDK',
                'type' => 'RES',
                'power' => '49,5 MW',
                'excel_row' => 48,
            ],
            [
                'name' => 'KULU GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,00 MWm / 10,00 MWe 12,54 MWh / 10,00 MWe',
                'excel_row' => 49,
            ],
        ],
    ],
    [
        'name' => 'AZRAX ELEKTRİK TEDARİK VE DEPOLAMA LTD.ŞTİ.',
        'city' => null,
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TÜREB ÜYESİ ORADAN ALINACAK BİLGİLER',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'tureb üyesi oradan öğrenilecek.',
            ],
        ],
        'projects' => [
            [
                'name' => 'EDİRNE ENEZ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '30 MW',
                'regulatory_note' => 'nihai karar tarihi 04.06.2026',
                'excel_row' => 50,
            ],
        ],
    ],
    [
        'name' => 'B. ERGÜNLER YOL YAPI İNŞ. TAAHHÜT MADENCİLİK NAKLİYECİLİK SAN. VE TİC. A.Ş',
        'city' => 'Bursa',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Konak Mah Merkez(120) Sk. Nilüfer Park Evleri Sit. G Blok Apt. No: 4 G/1 Nilüfer - BURSA',
            'district' => 'Nilüfer',
            'city' => 'Bursa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'ergunleryolyapi.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 224 441 50 34',
            ],
            [
                'type' => 'email',
                'value' => 'info@ergunleryolyapi.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Durali Ferik',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 200 41 91',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-18',
                'channel' => 'email',
                'subject' => null,
                'text' => '(18.06.2026 Beyçelik referansı ile kontak kuruldu. Şuan arazide sorun varmış. Tanıtım maili iletildi. info@esinruzgarenerjisi.com )',
            ],
        ],
        'projects' => [
            [
                'power' => '27 MW',
                'excel_row' => 51,
            ],
        ],
    ],
    [
        'name' => 'BALAT ENERJİ ÜRETİM İNŞAAT TURİZM VE TİC.LTD.ŞTİ.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Göztepe Mahallesi İnönü Caddesi Başkule Plaza No:122/123 Bağcılar/İSTANBUL',
            'district' => 'Bağcılar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 553 450 06 18',
            ],
            [
                'type' => 'email',
                'value' => 'huseyin.koc74@gmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => '(21.08.2026 Farklı bir firmaya devretmeyi planlıyorlar tanıtım ve devretmeyi düşündükleri firma bilgisi için mail atılldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'TEKİRDAĞ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '23,8 MW',
                'excel_row' => 52,
            ],
        ],
    ],
    [
        'name' => 'BAŞTAŞ BAŞKENT ÇİMENTO SAN. TİC. A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Ankara Samsun Karayolu 35. Km. ELMADAĞ/ANKARA /TURKIYE',
            'district' => 'Elmadağ',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 864 01 00',
            ],
            [
                'type' => 'email',
                'value' => 'murathan.sahin@vicat.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Emrah Erözkan',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 536 065 49 95',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => '(27.08.2026 Mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'Güneş Enerji Santrali (17,90 MWm / 17,00 MWe - 22,303 Ha)',
                'status' => '5.1.h',
                'type' => 'GES',
                'power' => '17 MW',
                'excel_row' => 53,
            ],
        ],
    ],
    [
        'name' => 'BATMAN 1 YENİLENEBİLİR ELEKTRİK ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bul. No: 3, Next Level Ofis A Blok, D: 80-81, Kat 16, Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://egesa.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 496 40 96',
            ],
            [
                'type' => 'email',
                'value' => 'info@egesa.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Eyup Taymur',
                'role' => 'decision_maker',
                'network' => 'Egesa nın sahibi epc firması',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 151 27 11',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'EGESA',
            ],
        ],
        'projects' => [
            [
                'name' => 'BATMAN-1 GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '20,00 MWm / 20,00 MWe 20,00 MWh / 20,00 MWe',
                'excel_row' => 54,
            ],
        ],
    ],
    [
        'name' => 'BATMAN 2 YENİLENEBİLİR ELEKTRİK ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bul. No: 3, Next Level Ofis A Blok, D: 80-81, Kat 16, Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://egesa.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 496 40 96',
            ],
            [
                'type' => 'email',
                'value' => 'info@egesa.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Eyup Taymur',
                'role' => 'decision_maker',
                'network' => 'Egesa nın sahibi epc firması',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 151 27 11',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'EGESA',
            ],
        ],
        'projects' => [
            [
                'name' => 'BATMAN-2 GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '20MWe 20 MWm/ 20 MWe 20 MWh',
                'excel_row' => 55,
            ],
        ],
    ],
    [
        'name' => 'BAYLAZ GRUP ENERJİ İNŞAAT ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Birlik, 448. Caddesi 109/7, 06610 Çankaya/Ankara, Türkiye',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.baylazgrup.com',
            ],
            [
                'type' => 'email',
                'value' => 'baylazinsaat@gmail.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Osman Baylaz',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 495 58 91',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ANTALYA/ KORKUTELİ Antalya 4 RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '23,60 MWm / 20,00 MWe 20,00 MWh / 20,00 MWe',
                'excel_row' => 56,
            ],
        ],
    ],
    [
        'name' => 'BEZMİALEM VAKIF ÜNİVERSİTESİ',
        'city' => 'Eskişehir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Levent Güzel',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 538 512 80 07',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA KAYIT',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Teklif verilecek.teklif verildi.işi beklemeye almışlar.haber verecekler',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR GES',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '18,48 MW',
                'excel_row' => 57,
            ],
        ],
    ],
    [
        'name' => 'BİEN SERAMİK',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Acıbadem Mah. Gömeç Sok. No:39, 34660 Kadıköy, İstanbul TURKIYE',
            'district' => 'Kadıköy',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.bienseramik.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 614 10 60',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Umut Öztürk',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 538 683 68 75',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Zuhal Hanım görüşmüş. Çine de yeni yatırım vae.',
            ],
        ],
        'projects' => [
            [
                'power' => '30 MW',
                'regulatory_note' => 'çed olumlu tarihi 05.01.2026',
                'excel_row' => 58,
            ],
        ],
    ],
    [
        'name' => 'BİNOM ENERJİ DANIŞMANLIK SAN. VE TİC. A.Ş.',
        'city' => 'İstanbul',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Beylikdüzüosb Mh. Birlik Sanayi Sitesi 3. Cd. Kelesoğlu Plaza Blok No:3 İç Kapı No:20 Beylikdüzü / İSTANBUL',
            'district' => null,
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://gokhanergroup.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 879 02 15',
            ],
            [
                'type' => 'email',
                'value' => 'info@binomenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 571 13 55',
            ],
            [
                'type' => 'email',
                'value' => 'Ufuk.Bayam@artasenergy.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'EDİRNE/ENEZ',
                'type' => 'GES EDT',
                'power' => '10 MW 10 MWh',
                'regulatory_note' => 'çed olumlu tarihi 23.12.2025',
                'excel_row' => 59,
            ],
        ],
    ],
    [
        'name' => 'BİRLER ÇELİK SANAYİ VE TİCARET A.Ş.',
        'city' => 'İstanbul',
        'network' => 'Telefon numarası ve mail çed dosyasından alınmıştır',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Demirciler Sitesi 7. Sokak No: 9 Zeytinburnu / İSTANBUL',
            'district' => 'Zeytinburnu',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.birler.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 546 18 83',
            ],
            [
                'type' => 'email',
                'value' => 'info@birler.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 781 84 63',
            ],
            [
                'type' => 'email',
                'value' => 'ayyildizharun@gmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ELAZIĞ/PALU 2,2572 MWm/1,88 MWe KAPASİTELİ ARAZİ TİPİ GÜNEŞ ENERJİ SANTRALİ (GES)',
                'type' => 'GES',
                'power' => '2,2572 MWm / 1,88 MWe',
                'regulatory_note' => 'çed olumlu tarihi 20.10.2026',
                'excel_row' => 60,
            ],
        ],
    ],
    [
        'name' => 'BURKAY UĞUR KAUÇUK KİMYA VE PETROL ÜRÜNLERİ SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Bursa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Barakfaki Sanayi Bolgesi,Ankara Yolu 16. Km, 16450Kestel, Bursa',
            'district' => 'Kestel',
            'city' => 'Bursa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.burkayugur.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 224 384 15 28',
            ],
            [
                'type' => 'email',
                'value' => 'info@burkayugur.com',
            ],
            [
                'type' => 'email',
                'value' => 'samet.kayis@bbsolarenerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Recep Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 257 42 11',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '(28.08.2026 amail atıldı)',
            ],
        ],
        'projects' => [
            [
                'name' => 'BALIKESİR/ KARESİ BURKAY KİMYA GÜNEŞ ENERJİ SANTRALİ (1,237 MWm/0,99 MWe - 1,34 ha)',
                'type' => 'GES',
                'power' => '0.99 MW',
                'regulatory_note' => 'çed olumlu tarihi 09.03.2026',
                'excel_row' => 61,
            ],
        ],
    ],
    [
        'name' => 'CEMAK ENERJİ ÜRETİM A.Ş.',
        'city' => 'Tokat',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Yeşiltepe Mah. Yavuz Selim Blv. Can Sitesi No:397/4 Ortahisar/TRABZON',
            'district' => 'Ortahisar',
            'city' => 'Trabzon',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'cemakenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 850 307 32 45',
            ],
            [
                'type' => 'email',
                'value' => 'info@cemakenerji.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 551 598 88 61',
            ],
            [
                'type' => 'email',
                'value' => 'timucin.kars@cemakenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-20',
                'channel' => 'email',
                'subject' => null,
                'text' => '(20.08.2026 Timuçin bey ile görüşüldü imar izinlerialınmamış henüz teklif toplama için yaklaşık 4 ay bir süre var dendi. Tanıtım maili iletildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR ÇİFTELER GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 20.10.2025',
                'excel_row' => 62,
            ],
        ],
    ],
    [
        'name' => 'CENGİZ ELEKTRİK TOPTAN SATIŞ A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Altunizade Mahallesi Kısıklı Caddesi No:37 34662 Üsküdar İstanbul ATG Ofis Blokları Eti Mahallesi Celal Bayar Bulvarı No:78 / 225 Çankaya / Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 468 00 15',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 554 53 00',
            ],
            [
                'type' => 'email',
                'value' => 'enerjisatis@cengiz.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 468 11 75',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 468 11 80',
            ],
            [
                'type' => 'email',
                'value' => 'ges@cengiz.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ANKARA/POLATLI',
                'type' => 'GES EDT',
                'power' => '75 MW 75 MWh',
                'excel_row' => 63,
            ],
        ],
    ],
    [
        'name' => 'CENGİZ ENERJİ SAN. VE TİC. A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Altunizade Kısıklı Cad. No: 37/1 34662 Üsküdar, İstanbul / TÜRKİYE',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.bienseramik.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 468 11 75',
            ],
            [
                'type' => 'email',
                'value' => 'Atacan.UCERLER@cengiz.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 554 251 63 19',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ÇERKEŞ RES PROJESİNE YARDIMCI KAYNAK GES (25 MWm / 25 MWe- 37,50 ha)',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '25 MW',
                'regulatory_note' => 'çed olumlu tarihi 13.08.2025',
                'excel_row' => 64,
            ],
        ],
    ],
    [
        'name' => 'Çamlıca HES Elektrik Üretim Anonim Şirketi',
        'city' => 'Kayseri',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Maslak Mah. Bilim Sk. Sun Plaza No:5 A İç Kapı No:7 Sarıyer/İSTANBUL',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://saves.com.tr/#anasayfa2',
            ],
            [
                'type' => 'phone',
                'value' => '+90 352 000 00 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@camlicahes.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 275 77 00',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 543 417 08 09',
            ],
            [
                'type' => 'email',
                'value' => 'levent.karaman@saves.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-31',
                'channel' => 'email',
                'subject' => null,
                'text' => '(31.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'DİYARBAKIR/ ÇINAR Cemre Depolamalı GES (GES Kurulu Güç: 130,746 MWm/ 93,450 MWe/ 143,62 ha Enerji Depolama Kurulu Güç: 94 MWe/ 94 MWh)',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '130,746 MWm / 93,450 MWe 94,00 MWh / 94,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 17.02.2026',
                'excel_row' => 65,
            ],
            [
                'name' => 'Cemre Depolamalı GES DİYARBAKIR / ÇINAR',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '130,746 MWm / 93,450 MWe 94,00 MWh / 94,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 17.02.2026',
                'excel_row' => 66,
            ],
        ],
    ],
    [
        'name' => 'Çatak Elektrik Üretim ve Ticaret A.Ş.',
        'city' => 'Ankara',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Osmangazi Mah. Kavacık Cad. No:16 Sancaktepe / İSTANBUL',
            'district' => 'Sancaktepe',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 405 60 80',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 538 614 69 77',
            ],
            [
                'type' => 'email',
                'value' => 'fkeskin@ekoced.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'YILDIZLAR GRUP',
            ],
        ],
        'projects' => [
            [
                'name' => 'NİĞDE/ALTUNHİSAR',
                'type' => 'GES EDT',
                'power' => '75 MW 75 MWh',
                'regulatory_note' => 'çed olumlu tarihi 29.07.2026',
                'excel_row' => 67,
            ],
        ],
    ],
    [
        'name' => 'ÇEKİM İNŞAAT BETON A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Hilal Mahallesi 701. Sok. No:28/6 Çankaya/Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 441 20 14',
            ],
            [
                'type' => 'email',
                'value' => 'buket.gunduzkanat@ozegrup.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 615 50 03',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => 'çed başvuruları Mitto cons bul. (17.08.2026 tarihinde Nejla Hanım ile iteşime geçildi. Mail ile bilgi talebi iletebilirsiniz dendi. Mail atıldı.Gizlilik nedeniyle işveren firma ile iletişime geçilmesi istendi.)(21.08.2026 Buket Hanım ile görüşüldü EPC olarak teklif vermek istediğimizi ilettik. tanıtım maili istendi ve iletildi.)(25.08.2026 mail ulaştımı öğrenmek için arandı. müsair-t olmadığını müsaitliğinde kontrol edip döneceği bildirildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR SİVRİHİSAR GES VE EDT',
                'status' => 'IDK',
                'type' => 'GES EDT',
                'power' => '120 MW',
                'regulatory_note' => 'iptal/iade tarih 23.07.2026',
                'excel_row' => 68,
            ],
        ],
    ],
    [
        'name' => 'ÇELİKLER HOLDİNG',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Nenehatun Caddesi No:104 -106 Gaziosmanpaşa / Ankara TÜRKİYE',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://celiklerholding.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 446 10 20',
            ],
            [
                'type' => 'email',
                'value' => 'info@celiklerholding.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Başak Çimen',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 549 26 20',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-19',
                'channel' => 'email',
                'subject' => null,
                'text' => '19.06.2026 tarihinde zuhal hanım mesaj iletmiş kapasite 195 yazıyor.',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'ÇOKA ENERJİ SANAYİ TİCARET ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'MUTLUKENT MAH. 2051 SK. /8 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Özgür Peker',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 491 38 38',
                    ],
                ],
            ],
            [
                'name' => 'Mümin Altunışık',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ÇOKA RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '25,00 MWm / 25,00 MWe 25,00 MWh / 25,00 MWe',
                'excel_row' => 70,
            ],
        ],
    ],
    [
        'name' => 'DEPOWER ENERJİ ÜRETİM LİMİTED ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'FULYA MAH. BÜYÜKDERE CAD. PEKİNTAŞ GROUP NO:32 İÇ KAPI NO: 11 ŞİŞLİ/İSTANBUL',
            'district' => 'Şişli',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 212 12 22',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 533 063 06 64',
            ],
            [
                'type' => 'email',
                'value' => 'anil.kuzu@spienergy.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MANİSA/AHMETLİ SEYDİKÖY-1 GÜNEŞ ENERJİ SANTRALİ',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '60 MWe 85 MWm/ 60 MWe 60 MWh',
                'excel_row' => 71,
            ],
        ],
    ],
    [
        'name' => 'DEVLET SU İŞLERİ GENEL MÜDÜRLÜĞÜ DSİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'authority',
        'address' => [
            'line1' => 'Mustafa Kemal Mahallesi Anadolu Bulvarı No:9 PK:06530 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 454 54 54',
            ],
            [
                'type' => 'email',
                'value' => 'dsi.gnlmud@hs01.kep.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Yunus Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 770 90 90',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'zuhal hanım kapasite 100 yazmış ve ihale olacak notu düşmüş.',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'DİYAR BATARYA SİSTEMLERİ VE YENİLENEBİLİR ENERJİ YATIRIMLARI ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'ÇANKAYA MAH.ÇANKAYA CAD.NO:28/5 ÇANKAYA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'zedur.net',
            ],
            [
                'type' => 'email',
                'value' => 'info@zedur.net',
            ],
        ],
        'contacts' => [
            [
                'name' => 'ramazan@ekonorm.com',
                'role' => 'technical_contact',
                'network' => 'Çed',
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'ramazan@ekonorm.com',
                    ],
                    [
                        'type' => 'phone',
                        'value' => '+90 312 466 10 90',
                    ],
                ],
            ],
            [
                'name' => 'Nejat Recai Dursun',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 496 45 40',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'ZEDUR ENERJİ',
            ],
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Zedur Holdinge bağlı bir firma. Bu hafta iletişime geçilecek. (17.08.2026 telefonla görüşüldü kırıkkale\'de projemiz yok dendi. Tanıtım ve farklı projeler hakkında bilgi için mail iletildi. )(özdemir karabulut\'a) bu kişi ile detaylı görüşülecek. (Ekonorm Fethi bey bilgileri verecek.(04/09)',
            ],
        ],
        'projects' => [
            [
                'name' => 'KIRIKKALE GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '50MW',
                'excel_row' => 73,
            ],
            [
                'name' => 'KONYA/ YUNAK YUNAK GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '22,00 MWm / 15,00 MWe 15,00 MWh / 15,00 MWe',
                'excel_row' => 74,
            ],
        ],
    ],
    [
        'name' => 'DOĞUŞ ÇAY VE GIDA MADDELERİ ÜRETİM PAZARLAMA İTH. İHR. A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Altıntepe Mah. Cihadiye Cad. No:94 Maltepe / İstanbul',
            'district' => 'Maltepe',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.doguscay.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 587 53 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@doguscay.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Yeter Korkmaz',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 531 492 25 11',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'yeterkorkmaz@doguscay.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'TEDAŞ DİCLE TARAFINDAN ONAY ALMAYA ÇALIŞIYORLAR. 19 ŞUBATTA ONAY ALIRLARSA TEKLİF TOPLAYACAKLAR. NİSAN SONU YOL DÜZELTMESİ BİTECEK. Proje, yasal izin, planlama ve ÇED süreçlerinin yürütüldüğü hazırlık aşamasında bulunuyor.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Güneş Enerji Santrali / Şanlıurfa Viranşehir',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '7 MWe',
                'regulatory_note' => 'nihai karar tarihi 14.08.2026',
                'excel_row' => 75,
            ],
        ],
    ],
    [
        'name' => 'DSS Savunma Enerji Elektrik Üretim ve Depolama Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Ehlibeyt Mh. 1259. Sk. No: 7/1 06520 Balgat / Ankara / Türkiye',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.bendiseurope.com/en',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 472 35 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@bendisholding.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 536 863 30 10',
            ],
            [
                'type' => 'email',
                'value' => 'kerdogan@bendisholding.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Tatvan Depolamalı RES BİTLİS / TATVAN',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '29,00 MWm / 28,80 MWe 28,80 MWh / 28,80 MWe',
                'regulatory_note' => 'çed olumlu tarihi 03.10.2025',
                'excel_row' => 76,
            ],
        ],
    ],
    [
        'name' => 'Ecowind 1 Enerji Anonim Şirketi',
        'city' => 'Denizli',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Sümer Mah. 2482 Sok. Skycity B Blok İş Merkezi No: 4/1 İç Kapı No: 129 Merkezefendi / DENİZLİ',
            'district' => 'Merkezefendi',
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://ecogreenenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 258 252 12 02',
            ],
            [
                'type' => 'email',
                'value' => 'info@ecogreenenerji.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 549 442 97 35',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 442 97 35',
            ],
            [
                'type' => 'email',
                'value' => 'info@novitasenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MARDİN/YEŞİLLİ ARTUKLU MÜSTAKİL DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '60MWe 85 MWm/60MWh 60MWe',
                'regulatory_note' => 'çed olumlu tarihi 06.03.2025',
                'excel_row' => 77,
            ],
            [
                'name' => 'Tatvan Müstakil DGES BİTLİS / AHLAT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '76,877 MWm / 65,00 MWe 65,00 MWh / 65,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 23.01.2025',
                'excel_row' => 78,
            ],
            [
                'name' => 'TATVAN MÜSTAKİL DGES',
                'type' => 'GES EDT',
                'power' => '65 MWe 76,88 MWm/ 65 MWe 65 MWh',
                'regulatory_note' => 'çed olumlu tarihi 11.11.2024',
                'excel_row' => 79,
            ],
            [
                'name' => 'ARTUKLU MÜSTAKİL DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '85,00 MWm / 60,00 MWe 60,00 MWh / 60,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 29.09.2025',
                'excel_row' => 80,
            ],
        ],
    ],
    [
        'name' => 'EFOR ENERJİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'İÇERENKÖY DESTAN SOK.NO:6 EFOR PLAZA ATAŞEHİR',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'eforholding.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 252 07 46',
            ],
            [
                'type' => 'email',
                'value' => 'info@eforholding.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Cem Ersamut',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 534 667 79 63',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'EFOR HOLDİNG',
            ],
            [
                'on' => '2026-09-04',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Dökümanlar değerlendiriliyor. (2 adet 400kV/33,6 kV TM ve Şalt sahası) Teklif verilmedi. (ibrahim akkuş-muhammet akkuş sahip-solpeg can tutaşı sorumlu 04/09)',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Dökümanlar değerlendiriliyor. (1 adet 400kV/33,6 kV TM ve Şalt sahası). Teklif verilmedi.',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR YEKA GES 1 VE YEKA GES 2',
                'type' => 'GES',
                'power' => '180-150 mwP',
                'regulatory_note' => 'çed olumlu tarihi 31.07.2026',
                'excel_row' => 81,
            ],
            [
                'name' => 'BALIKESİR YEKA RES',
                'type' => 'RES',
                'power' => '120MWe',
                'regulatory_note' => 'çed olumlu tarihi 12.06.2026',
                'excel_row' => 82,
            ],
        ],
    ],
    [
        'name' => 'EGESA ELEKTRİK İNŞAAT ENERJİ ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bul. No: 3, Next Level Ofis A Blok, D: 80-81, Kat 16, Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://egesa.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 496 40 96',
            ],
            [
                'type' => 'email',
                'value' => 'info@egesa.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Eyup Taymur',
                'role' => 'decision_maker',
                'network' => 'Egesa nın sahibi epc firması',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 151 27 11',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'SARUHANLI-1',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '34,00 MWm / 34,00 MWe 34,39 MWh / 34,00 MWe',
                'excel_row' => 83,
            ],
        ],
    ],
    [
        'name' => 'EKVATOR ENERJİ',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Adalet Mah. Anadolu Cad. No:41 Megapol Tower Kat:22 35530 Bayraklı/İzmir',
            'district' => 'Bayraklı',
            'city' => 'İzmir',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 850 840 36 00',
            ],
            [
                'type' => 'phone',
                'value' => '+90 232 290 05 06',
            ],
            [
                'type' => 'email',
                'value' => 'bilgi@ekvatorenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'email',
                'subject' => null,
                'text' => 'mail atıldı.',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'Elestaş Elektrik Üretim A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Halkalı Merkez Mah. Dereboyu Cad. Küçükler Holding No: 68 Kat: 2 Küçükçekmece / İSTANBUL',
            'district' => 'Küçükçekmece',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 466 10 90',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Volkan Nur',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 539 513 92 38',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-19',
                'channel' => 'email',
                'subject' => null,
                'text' => '(19.06.2026 tarihinde Şuan kurum süreçleri devam ediyor. 2027 yılının projesi ama bu yıla yetiştirmeye çalışıyoruz dedi. Firmamızı tanıtı mail iletildi. volkannur@elestas.com.tr)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ŞANLIURFA GES',
                'type' => 'GES',
                'power' => '30 MW',
                'regulatory_note' => 'çed olumlu tarihi 18.05.2026',
                'excel_row' => 85,
            ],
        ],
    ],
    [
        'name' => 'ENAR ENERJİ ELEKTRİK ÜRETİM TİCARET A.Ş.',
        'city' => 'Ankara',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bulv. No:3/A Next Level D:80-81-82 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 286 50 31',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 505 657 75 01',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 505 346 80 66',
            ],
            [
                'type' => 'email',
                'value' => 'info@ankaced.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Iğdır-3 Güneş Enerji Santrali (40 MWm / 40 MWe - 61,44 ha) ve Elektrik Depolama Tesisi (40 MWh / 40 MWe - 0,40 ha )',
                'type' => 'GES EDT',
                'power' => '40 MW',
                'regulatory_note' => 'çed olumlu tarihi 03.03.2026',
                'excel_row' => 86,
            ],
            [
                'name' => 'Esenboğa-1 GES ANKARA / ALTINDAĞ',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 10,00 MWe / 10,00 MWh',
                'excel_row' => 87,
            ],
        ],
    ],
    [
        'name' => 'ENDA ENERJİ HOLDİNG',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Şehit Nevres Bulvarı, No:10, Deren Plaza, Kat:7, Konak-35210, İZMİR',
            'district' => 'Konak',
            'city' => 'İzmir',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 232 463 98 11',
            ],
            [
                'type' => 'email',
                'value' => 'enda@endaenerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Taylan Kabaş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 065 15 94',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-19',
                'channel' => 'email',
                'subject' => null,
                'text' => '(19.06.2026 HES yanına kurulacak GES Zuhal Hanım notu) mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'EĞLENCE 1 GES',
                'type' => 'GES',
                'power' => '9,5 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 10.07.2025',
                'excel_row' => 88,
            ],
        ],
    ],
    [
        'name' => 'ENERGEN ENERJİ YATIRIMLARI SANAYİ VE DIŞ TİCARET ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Büyükesat Mah. Mahatma Gandi Cad. No: 47 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 447 09 19',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 405 60 80',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 530 969 97 94',
            ],
            [
                'type' => 'email',
                'value' => 'h.balci@yildizlar.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Müfit Eren',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KARAMAN DRES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '240,00 MWm / 200,00 MWe 200,00 MWh / 200,00 MWe',
                'regulatory_note' => 'ÇED Olumlu tarihi 30.06.2025',
                'excel_row' => 89,
            ],
        ],
    ],
    [
        'name' => 'ENERJİ İŞLERİ GENEL MÜDÜRLÜĞÜ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'authority',
        'address' => [
            'line1' => 'NASUH AKAR MAH.TÜRKOCAĞI CAD.NO:2 ÇANKAYA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 546 46 46',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'YEKA GES-2026 Yarışma ve İhale TakvimiSon Başvuru Tarihi: 13 Ekim 2026 saat 10:00 ile 12:00 arasında teklifler bakanlığa elden teslim edilecektir. Mustafa bey ile gidilecek. 19/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'MALATYA DARENDE GES',
                'status' => 'ÖNLİSANS',
                'type' => 'GES',
                'power' => '40 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 20.02.2025',
                'excel_row' => 90,
            ],
        ],
    ],
    [
        'name' => 'ENERJİSA',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bulv. (Eskişehir Yolu) No: 9 A1 Blok Kat: 15 YDA Center Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 216 579 05 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'İbrahim Atalay',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 534 230 64 86',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'power' => '91 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 19.02.2025',
                'excel_row' => 91,
            ],
        ],
    ],
    [
        'name' => 'Enerjisa Enerji Üretim Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Barbaros Mahallesi Çiğdem Sokak Ağaoğlu My Office Apt. No:1/16 Ataşehir/İSTANBUL',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 216 512 40 00',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 544 120 88 58',
            ],
            [
                'type' => 'email',
                'value' => 'kurumsal@enerjisauretim.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Balkaya RES (260 MWm / 260 MWe, 52 Adet Türbin) KIRKLARELİ/VİZE',
                'status' => 'LİSANS',
                'type' => 'RES',
                'power' => '260,00 MWm / 260,00 MWe',
                'regulatory_note' => 'ÇED Olumlu tarihi 19.09.2024',
                'excel_row' => 92,
            ],
        ],
    ],
    [
        'name' => 'Entek Elektrik Üretim Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Entek Elektrik Üretimi A.Ş. Çamlıca İş Merkezi Ünalan Mahallesi Ayazma Caddesi B1 Blok 34700 Üsküdar / İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.entekelektrik.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 217 11 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@entekelektrik.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ömer Koç',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 544 120 88 58',
                    ],
                ],
            ],
            [
                'name' => 'Ali Koç',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Eren RES SİVAS / GÜRÜN',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '60,00 MWm / 55,00 MWe 55,00 MWh / 55,00 MWe',
                'regulatory_note' => 'ÇED Olumlu tarihi 19.09.2024',
                'excel_row' => 93,
            ],
            [
                'name' => 'ANKARA/ŞEREFLİKOÇHİSAR Büyükkışla RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '99,9 MWm / 100,8 MWe 121,41 MWh / 99,9 MWe',
                'regulatory_note' => 'ÇED Olumlu tarihi 19.09.2024',
                'excel_row' => 94,
            ],
            [
                'name' => 'Adatoprakpınar GES ESKİŞEHİR / SEYİTGAZİ',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '70,00 MWm / 50,00 MWe 50,00 MWh / 50,00 MWe',
                'regulatory_note' => 'ÇED Olumlu tarihi 31.07.2024',
                'excel_row' => 95,
            ],
        ],
    ],
    [
        'name' => 'ENTEK ELEKTRİK ÜRETİMİ ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Entek Elektrik Üretimi A.Ş. Çamlıca İş Merkezi Ünalan Mahallesi Ayazma Caddesi B1 Blok 34700 Üsküdar / İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.entekelektrik.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 217 11 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@entekelektrik.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ömer Koç',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 262 317 10 00',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 544 120 88 58',
                    ],
                ],
            ],
            [
                'name' => 'Ali Koç',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Büyükkışla RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '99,90 MWm / 100,80 MWe 121,41 MWh / 99,90 MWe',
                'excel_row' => 96,
            ],
        ],
    ],
    [
        'name' => 'ERTOK ENERJİ İNŞ. VE SAN. TİC. LTD. ŞTİ.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'ÇATALÇEŞME MAH.SARAY CAD.ERTOK HIRDAVAT NO:291 ÇEKMEKÖY',
            'district' => 'Çekmeköy',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'er-tok.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 429 08 97',
            ],
            [
                'type' => 'email',
                'value' => 'info@er-tok.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Muazaffer Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 465 55 09',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => '(17.08.2026 Muzaffer Bey ile görüşüldü. Şuan çok müsait olmadığı için yarın daha detaylı görüşelim dendi.) (21.08.2026 proje için tekrar başvuru yaptıklarını iafede ettile tanıtım maili atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ARTVİN BORÇKA',
                'type' => 'HES',
                'power' => 'HKT 21,58 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 05.08.2024',
                'excel_row' => 97,
            ],
        ],
    ],
    [
        'name' => 'ERZURUM BELEDİYESİ',
        'city' => 'Erzurum',
        'network' => null,
        'priority' => 3,
        'role' => 'authority',
        'address' => [
            'line1' => 'MuratPaşa Mahallesi Merkezi Yönetim Cad. No:2 Erzurum Büyükşehir Belediye Başkanlığı 25100 Erzurum',
            'district' => null,
            'city' => 'Erzurum',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 442 344 10 00',
            ],
            [
                'type' => 'phone',
                'value' => '444 1 625',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 535 019 49 09',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Muhammed Kaya',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 413 68 34',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'power' => '20 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 29.04.2024',
                'excel_row' => 98,
            ],
        ],
    ],
    [
        'name' => 'Esbesa Enerji İnşaat Mühendislik Nakliyat Sanayi ve Ticaret Anonim Şirketi',
        'city' => 'Ankara',
        'network' => 'Telefon çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Çukurambar Mah. Öğretmenler Cad. Usta Sitesi A Blok No:1/2 Çankaya / Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://esbesa.com.tr/tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 820 64 36',
            ],
            [
                'type' => 'email',
                'value' => 'esbesa@esbesa.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 466 10 90',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 544 232 64 89',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Esbesa-3 GES ESKİŞEHİR / ODUNPAZARI',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '10 MWe',
                'excel_row' => 99,
            ],
        ],
    ],
    [
        'name' => 'EXEN SOLAR ENERJİ ÜRETİM VE DEPOLAMA ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Tantavi Mahallesi Estergon Caddesi Exen İstanbul F Blok 24/F İçkapı no:249 Ümraniye/İSTANBUL',
            'district' => 'Ümraniye',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 539 422 64 66',
            ],
            [
                'type' => 'email',
                'value' => 'yusuf.akbas@360enerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Gazi Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 488 51 53',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-18',
                'channel' => 'other',
                'subject' => null,
                'text' => '(18.06.2026 360Enerji projesiymiş. Gazi Bey ve Hüseyin Bey projeyi görüşüyormuş)',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje mardin kızıltepede',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje mardin ARTUKLU,KIZILTEPE de',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje van tuşba da.',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje mardin artuklu da',
            ],
        ],
        'projects' => [
            [
                'name' => 'SİVRİCE DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,5 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 18.05.2026',
                'excel_row' => 100,
            ],
            [
                'name' => 'DARA ELEKTRİK DEPOLAMA TESİSİ VE (10 MWe / 10 MWh) GÜNEŞ ENERJİ SANTRALİ (12,5 MWm / 10 MWe) (15,28 ha)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 101,
            ],
            [
                'name' => 'ALIMLI ELEKTRİK DEPOLAMA TESİSİ (10 MWe / 10 MWh) GÜNEŞ ENERJİ SANTRALİ (12,5 MWm / 10 MWe) (14,81 ha)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 102,
            ],
            [
                'name' => 'HOŞAP ELEKTRİK DEPOLAMA TESİSİ (10 MWe / 10 MWh) GÜNEŞ ENERJİ SANTRALİ (12,5 MWm / 10 MWe) (15,24 ha)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 103,
            ],
            [
                'name' => 'İSTASYON ELEKTRİK DEPOLAMA TESİSİ (10 MWe / 10 MWh) GÜNEŞ ENERJİ SANTRALİ (12,5 MWm / 10 MWe) (15,23 ha)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 21.05.2026',
                'excel_row' => 104,
            ],
            [
                'name' => 'MEZOPOTAMYA ELEKTRİK DEPOLAMA TESİSİ (10 MWe / 10 MWh) VE GÜNEŞ ENERJİ SANTRALİ (12,5 MWm / 10 MWe) (15,27 ha)',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 105,
            ],
        ],
    ],
    [
        'name' => 'FERNAS ŞİRKETLER GRUBU',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'BİRLİK MAH. DOĞUKENT BLV.495.SOKAK NO:2 ÇANKAYA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'fernas.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 454 16 00',
            ],
            [
                'type' => 'email',
                'value' => 'bilgi@fernas.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'alper.esatoglu@fernas.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Alper Esatoğlu Batuhan Öztürk 0 541 572 57 75',
                'role' => 'technical_contact',
                'network' => 'Alper.esatoglu@fernas.com.tr',
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-20',
                'channel' => 'other',
                'subject' => null,
                'text' => '90 MW iş tamamlanmak üzere. Devam Projelerinde görüşeceğiz. metgün ile organik, ortaklık düzeyinde ve proje bazlı çok güçlü bağları var.20/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'KIRKLARELİ GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10MW',
                'excel_row' => 106,
            ],
        ],
    ],
    [
        'name' => 'FİBA YENİLEBİLİR ENERJİ HOLDİNG A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'ALTUNİZADE MAH.KISIKLI CAD.NO:4 SARKUYSAN AK İŞ MERKEZİ A BLOK K:2 ÜSKÜDAR',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'fibaenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 554 54 00',
            ],
            [
                'type' => 'email',
                'value' => 'fibayenilebilirenerji@fibaenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Rıza Ersamut',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 549 474 30 99',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'mail tanıtım da yapılacak.19/08 (21.08.2026 Rıza Bey arandı kendidi yurtdışı ile ilgilendiğini iletti. Firma no arandı Cuma günleri home office çalışıldığı Pazartesi aranılması istendi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'BALIKESİR RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '40 MW',
                'excel_row' => 107,
            ],
        ],
    ],
    [
        'name' => 'Gesdoğa Enerji Üretim Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Ayazağa Mah. Azerbaycan Cad. 1B Blok No: 3B İç Kapı No: 25 Sarıyer / İSTANBUL',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 812 50 50',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 542 440 31 32',
            ],
            [
                'type' => 'email',
                'value' => 'info@mensisgroup.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Taylıca GES ŞANLIURFA / EYYÜBİYE-HARRAN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '30,00 MWm / 25,00 MWe 25,00 MWh / 25,00 MWe',
                'excel_row' => 108,
            ],
        ],
    ],
    [
        'name' => 'GMC SOLAR İNŞAAT TAAHHÜT GIDA ENERJİ ÜRETİM SANAYİ VE TİCARET A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'SARAY MAH. FATİH SULTAN MEHMET BLV. /327 KAHRAMANKAZAN / ANKARA',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Hasan Gümüş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 549 793 38 44',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'YOZGAR/ YENİFAKILI BEKTAŞLI DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '30,00 MWm / 30,00 MWe 30,00 MWh / 30,00 MWe',
                'excel_row' => 109,
            ],
        ],
    ],
    [
        'name' => 'GUGLER SU TRÜBÜNLERİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Kutan Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 536 259 36 19',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA KAYIT',
            ],
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ben arayacağım kendisi ile bu hafta görüşeceğim. 19/08',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'GÜLLE ENTEGRE TEKSTİL EMLAK A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Cihangir Mah. Sarızeybek Cad. No:8/1-A Blok Avcılar/İSTANBUL',
            'district' => 'Avcılar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 422 12 81',
            ],
            [
                'type' => 'email',
                'value' => 'info@gulletekstil.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Hikmet Gülle',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 479 84 00',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'hikmet.gülle@gülletekstil.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => 'MAİL ATILDI DÖNÜŞ BEKLENİYOR, TEKRAR GÖRÜŞÜLECEK. (28.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'TEKİRDAĞ/ ERGENE',
                'type' => 'RES',
                'power' => '4.8 MWm 1 Türbin',
                'regulatory_note' => 'çed olumlu tarihi 18.05.2026',
                'excel_row' => 111,
            ],
        ],
    ],
    [
        'name' => 'GÜLSAN HOLDİNG',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'RÜZGARLIBAHÇE MAH.CUMHURİYET CAD.NO:22 GÜLSAN PLAZA KAVACIK',
            'district' => null,
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'gulsanholding.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 681 02 00',
            ],
            [
                'type' => 'email',
                'value' => 'contact@gulsanholding.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Özgür İstekli',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 537 811 40 23',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-18',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Teklif iletildi. Pvsyt raporu istendi ve gönderildi.yusuf beyin yakın takibinde. 18/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'DOĞANKAYA HES\'E YARDIMCI KAYNAK',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '9,9 MW',
                'excel_row' => 112,
            ],
        ],
    ],
    [
        'name' => 'GÜMÜŞTAŞ MADENCİLİK',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Burhaniye Mah. Kısıklı Cad. No:65 Üsküdar/İstanbul',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 216 580 93 13',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 580 82 41',
            ],
            [
                'type' => 'email',
                'value' => 'info@gumustasmaden.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Bağlantu aranacak.',
            ],
        ],
        'projects' => [
            [
                'name' => 'GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '6,23 MW',
                'regulatory_note' => 'çed olumlu tarihi 17.12.2024',
                'excel_row' => 113,
            ],
        ],
    ],
    [
        'name' => 'HADİM YENİLENEBİLİR ENERJİ ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'KORKUTREİS MAH. HANIMELİ SK. DİLEK APT. 5/13 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 231 07 72',
            ],
        ],
        'contacts' => [
            [
                'name' => '(0312)-2310772',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KONYA/ HADİM NEPSUN HADİM GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '34,99 MWm / 25,00 MWe 25,00 MWh / 25,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 19.08.2026',
                'excel_row' => 114,
            ],
        ],
    ],
    [
        'name' => 'HARPUT TEKSTİL SANAYİ VE TİCARET A.Ş.',
        'city' => 'Bursa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Mustafakemalpaşa OSB Güllüce Mah. 15 Nolu Cadde No: 1/3 Mustafakemalpaşa/BURSA',
            'district' => 'Mustafakemalpaşa',
            'city' => 'Bursa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.harputtekstil.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 224 532 02 23',
            ],
            [
                'type' => 'email',
                'value' => 'info@harputtekstil.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Muhsin Yaşar',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 224 242 86 50',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'email',
                'subject' => null,
                'text' => 'MUHSİN BEYE TANITIM MAİLİ ATILACAK.',
            ],
        ],
        'projects' => [
            [
                'name' => 'ANKARA /HAYMANA',
                'type' => 'GES EDT',
                'power' => '250 MW 250 MWh',
                'excel_row' => 115,
            ],
        ],
    ],
    [
        'name' => 'HATAY BELEDİYESİ',
        'city' => 'Hatay',
        'network' => null,
        'priority' => 3,
        'role' => 'authority',
        'address' => [
            'line1' => 'Kisecik Mahallesi Hatay Büyükşehir Belediyesi Yerleşkesi ANTAKYA/HATAY',
            'district' => 'Antakya',
            'city' => 'Hatay',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 326 219 63 00',
            ],
            [
                'type' => 'email',
                'value' => 'iletisim@hatay.bel.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ihaleyi takip edelim.daha yapılmamış',
            ],
        ],
        'projects' => [
            [
                'name' => 'HATAY HASSA GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '40 MW',
                'excel_row' => 116,
            ],
        ],
    ],
    [
        'name' => 'HAVİN ENERJİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'kızılırmak mah.dumlupınar bul.next level no:3a/82 ankara',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Ahmet Çelik Eyüp Taymur',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 151 27 11',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-25',
                'channel' => 'other',
                'subject' => null,
                'text' => 'duru çevre çed firması ile konuşulacak.bilgi almaya çalışılacak. (19/08) lisansı sonlandırılmış ve havin enerji isimli firmaya (ahmet çelik-kızılırmak mah.dumlupınar bul.next level no:3a/82 ankara) devredilmiştir. 2026 mayıs ayında. (25.08.2026 iletişim bulunamadı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'İZMİR ALİAĞA GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '20 Mwe 28,08 MWm/ 20MWe 20,23 MWh',
                'excel_row' => 117,
            ],
        ],
    ],
    [
        'name' => 'HİSAR BİOGAZ TARIM VE HAYVANCILIK A.Ş.',
        'city' => 'Eskişehir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kazım Özalp Mah. Rabat Sk. No:17/4 Çankaya/Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 440 16 72',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 472 53 66',
            ],
            [
                'type' => 'email',
                'value' => 'yavuz.sapmaz@gmail.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ahmet Köker',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'ahmetkoker@esa-grup.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'ÇED FİRMASIYLA KONUŞULDU. BARUT ENERJİDEN YETKİLİLERE ULAŞILMAYA ÇALIŞILDI.',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR/SİVRİHİSAR',
                'type' => 'GES EDT',
                'power' => '100MW/ 100MWh',
                'excel_row' => 118,
            ],
        ],
    ],
    [
        'name' => 'HİVE ELEKTRİK ÜRETİM SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'AŞAĞI ÖVEÇLER MAH.1322.CAD.NO:61/1 ÇANKAYA/ANKARA ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Tolga Metin',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 534 685 66 86',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ELMADAĞ-1',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '81,675 MWm / 72,60 MWe 165,12 MWh / 150,00 MWe',
                'excel_row' => 119,
            ],
            [
                'name' => 'BALA-2',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '55,688 MWm / 49,50 MWe 55,04 MWh / 50,00 MWe',
                'excel_row' => 120,
            ],
        ],
    ],
    [
        'name' => 'IC HOLDİNG İÇTAŞ ENERJİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'KIZILIRMAK SOKAK NO:31 KIZILAY',
            'district' => 'Kızılırmak',
            'city' => 'Çankırı',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'ictas.com.tr',
            ],
            [
                'type' => 'website',
                'value' => 'icholding.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 417 09 57',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Gülsüm Köse Kardeş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 543 725 32 16',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'visit',
                'subject' => null,
                'text' => 'Teklif için ek süre talep edildi. Ziyaret edelim. Teklif gönderilmedi.',
            ],
        ],
        'projects' => [
            [
                'name' => 'YÖRGÜÇ DRES',
                'status' => 'LİSANS',
                'type' => 'RES',
                'power' => '45 MW',
                'excel_row' => 121,
            ],
        ],
    ],
    [
        'name' => 'IĞDIR HOŞHABER ELEKTRİK ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'MASLAK MAH. AOS 55. SK. 42 MASLAK A BLOK 252/2 SARIYER / İSTANBUL',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Muhammed İbrahim Soysal',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 442 97 35',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'IĞDIR DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '132,01 MWm / 95,00 MWe 97,124 MWh / 95,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 14.07.2026',
                'excel_row' => 122,
            ],
        ],
    ],
    [
        'name' => 'İÇDAŞ ÇELİK ENERJİ TERSANE VE ULAŞIM A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Mahmutbey Mahallesi Dilmenler Caddesi No:20 34218 Bağcılar/İstanbul',
            'district' => 'Bağcılar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.icdas.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '444 8 423',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Barış Bora',
                'role' => 'decision_maker',
                'network' => 'Pbx',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 928 35 28',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'baris.bora@icdas.com.tr',
                    ],
                ],
            ],
            [
                'name' => 'Ayhan Devetaş',
                'role' => 'decision_maker',
                'network' => 'Pbx',
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 212 604 04 93',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'ayhan.devetas@icdas.com.tr',
                    ],
                ],
            ],
            [
                'name' => 'Tarık Ekrem Özcan',
                'role' => 'decision_maker',
                'network' => 'Pbx',
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 212 604 05 39',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'tarik.ozcan@icdas.com.tr',
                    ],
                ],
            ],
            [
                'name' => 'Gülsüm Köse Kardeş',
                'role' => 'decision_maker',
                'network' => 'Pbx',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 543 725 32 16',
                    ],
                ],
            ],
            [
                'name' => 'Sadık Gökçe Karabağ',
                'role' => 'decision_maker',
                'network' => 'Pbx',
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 286 334 50 50',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'icdas@icdas.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ÇED SÜRECLERİ SON AŞAMADA ŞARTNAME YAZILMAKTAYMIŞ.TEKLİF TOPLAYACAKLARNazka Çevre Mühendislik tarafından hazırlanan dosya ile halkın görüşüne açılan proje, İDK toplantısı ile nihai onay aşamalarına taşındı.Üretilen enerjinin aktarımı için yaklaşık 5 kilometre uzunluğunda 154 kV enerji iletim hattı yapılarak Gömce GES Trafo Merkezine (TM) bağlanması planlanmakta.',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ÇED SÜRECLERİ SON AŞAMADA ŞARTNAME YAZILMAKTAYMIŞ.TEKLİF TOPLAYACAKLAR çed olumlu kararı geçersiz sayılmış.proje askıda ve güncellenmektedir.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Proje ili Denizli Bekili İÇDAŞ DENİZLİ GÜNEŞ ENERJİ SANTRALİ (44 MWe/ 50,331 MWm/ 61,41 ha)',
                'status' => '5.1.h',
                'type' => 'GES EDT',
                'power' => '44 MWe',
                'regulatory_note' => 'çed olumlu tarihi 10.07.2026',
                'excel_row' => 123,
            ],
            [
                'name' => 'Proje ili Kütahya Merkez İÇDAŞ Kızık Güneş Enerji Santrali (51,8 MWm /50 MWe/ 51,4 Ha)',
                'status' => '5.1.h',
                'type' => 'GES',
                'power' => '50 MWe',
                'regulatory_note' => 'çed olumlu tarihi 13.01.2026',
                'excel_row' => 124,
            ],
        ],
    ],
    [
        'name' => 'İLLER BANKASI',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'EMNİYET MAH.HİPODRUM CAD. NO:9/21 YENİMAHALLE',
            'district' => 'Yenimahalle',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 508 70 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Aslı Harmanlık Olgun',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 535 380 40 09',
                    ],
                ],
            ],
            [
                'name' => 'Yasin Zengin',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 507 927 94 77',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-18',
                'channel' => 'visit',
                'subject' => null,
                'text' => 'Afra Solar Enerji Limited Şirketi - Halil Söyler İş Ortaklığı kazanmış ve projenin EPC (Tasarım, Tedarik ve Kurulum) yüklenicisi olmuştur.260 mio tl 18/08 iller bankası mustafa bey ile ziyaret edilecek.19/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'VAN ERCİŞ GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '7,5 MW',
                'regulatory_note' => 'çed olumlu tarihi 09.01.2026',
                'excel_row' => 125,
            ],
        ],
    ],
    [
        'name' => 'İNOVATİF ENERJİ TEDARİK ANONİM ŞİRKETİ',
        'city' => 'Nevşehir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Cumhuriyet Mah. 518. Sok. No:3 İç Kapı No:1 Avanos/Nevşehir',
            'district' => 'Avanos',
            'city' => 'Nevşehir',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 384 511 44 77',
            ],
            [
                'type' => 'email',
                'value' => 'muhasebe@inovatifenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Çed Firması',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 057 45 47',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'ecedproje@gmail.com',
                    ],
                ],
            ],
            [
                'name' => 'Ömer Fatih Keha',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 538 405 46 81',
                    ],
                ],
            ],
            [
                'name' => 'Hamdi Alp',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak. (21.08.2026 firma teklif toplama sürecinde tanıtım maili iletildi iletişim devam edecek.)',
            ],
            [
                'on' => '2026-06-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak. (21.06.2026 firma teklif toplama sürecinde tanıtım maili iletildi iletişim devam edecek.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'HATAY ANTAKYA RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '75 MW',
                'regulatory_note' => 'çed olumlu tarihi 16.12.2025',
                'excel_row' => 126,
            ],
            [
                'name' => 'ÇANAKKALE BAYRAMİÇ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '100 MW',
                'excel_row' => 127,
            ],
            [
                'name' => 'BALIKESİR KEPSUT RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '50 MW',
                'excel_row' => 128,
            ],
            [
                'name' => 'Süleymanbey RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '50,00 MWm / 50,00 MWe 50,00 MWh / 50,00 MWe',
                'regulatory_note' => 'çed olumlu yarihi 15.02.2024',
                'excel_row' => 129,
            ],
        ],
    ],
    [
        'name' => 'Kalen Elektrik Toptan Satış ve Ticaret Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'mimar sinan mahallesi çavuşderes caddesi No:41A İç Kapı No:30 Üsküdar / İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 573 05 05',
            ],
            [
                'type' => 'email',
                'value' => 'cbulgurcu@kalyonholding.com',
            ],
            [
                'type' => 'email',
                'value' => 'kalyonenerjiig@kalyonholding.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Sultanlar DGES Elektrik Üretim Anonim Şirketi',
            ],
        ],
        'projects' => [
            [
                'name' => 'Sultanlar Depolamalı GES KAHRAMANMARAŞ / ELBİSTAN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '156,00 MWm / 120,00 MWe 120,00 MWh / 120,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 07.11.2024',
                'excel_row' => 130,
            ],
        ],
    ],
    [
        'name' => 'Kalyon YEKA GES 5 Elektrik Üretim Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Ehlibeyt, Mevlana Blv. No:201, NEV201 İş Merkezi C Blok Kat: 33 PK:06520 Çankaya/ANKARA Mimar Sinan Mah. Çavuşdere Cad. No: 41/A - 30 NEVOFİS Üsküdar PK: 34672 Üsküdar/İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.kalyonenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 573 05 05',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 680 41 80',
            ],
            [
                'type' => 'email',
                'value' => 'enerji-iletisim@kalyonenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'G-24 Karapınar GES KONYA / KARAPINAR',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '500 MW YEKA',
                'regulatory_note' => 'çed olumlu tarihi 29.04.2025',
                'excel_row' => 131,
            ],
        ],
    ],
    [
        'name' => 'KARDEMİR ÇELİK SAN. A.Ş.',
        'city' => 'Karabük',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Hacıeyüplü Mah. 3075 Sk. No:15/1 Merkezefendi / DENİZLİ',
            'district' => 'Merkezefendi',
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.kar-demir.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 370 418 20 01',
            ],
            [
                'type' => 'email',
                'value' => 'yatirimciiliskileri@kardemir.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 530 236 00 19',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'VAN/İPEKYOLU',
                'type' => 'GES',
                'power' => '15 MWm / 12 MWe',
                'regulatory_note' => 'çed olumlu tarihi 06.02.2025',
                'excel_row' => 132,
            ],
        ],
    ],
    [
        'name' => 'Karen Anadolu Elektrik Üretim Limited Şirketi',
        'city' => 'Kahramanmaraş',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Karacasu Karaziyaret Mahallesi Fatih Sultan Mehmet Caddesi No:1/A Dulkadiroğlu / Kahramanmaraş',
            'district' => 'Dulkadiroğlu',
            'city' => 'Kahramanmaraş',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://karengrubu.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 344 236 38 00',
            ],
            [
                'type' => 'phone',
                'value' => '+90 344 236 33 07',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 231 07 72',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 447 09 64',
            ],
            [
                'type' => 'email',
                'value' => 'bcelik@ozcelikelektromekanik.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'ÇED başvurusunda yazan firma: NEPSUN SOLAR HİDRO ENERJİ SAVUNMA SANAYİ TİC. LTD. ŞTİ.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Nepsun Kızılkale GES ANKARA / ŞEREFLİKOÇHİSAR',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 23.12.2024',
                'excel_row' => 133,
            ],
        ],
    ],
    [
        'name' => 'KASTAMONU ENTEGRE / HAYAT KİMYA',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Altunizade Mh. Kısıklı Cd.No:13 34662 Üsküdar / İstanbul / Türkiye',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.kastamonuentegre.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Bülent Kurşun',
                'role' => 'decision_maker',
                'network' => 'Enerji müdürü',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 157 12 80',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'telefon görüşmesi yapıldı yusuf bey tarafından. İlgili kişi mail istedi. Tekrar detaylar mail incelendikten sonra konuşulacak. 19/08 aranacak tanıtım.',
            ],
        ],
        'projects' => [
            [
                'regulatory_note' => 'hkt tarihi 15.05.2025',
                'excel_row' => 134,
            ],
        ],
    ],
    [
        'name' => 'KAYAHAN İNŞAAT TAAH. SAN. VE TİC. A. Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Ufuk Üni. Cad. No: 11B/4 Arma Kule Çankaya/Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.kayahangrup.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'info@kayahangrup.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Burak Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 220 23 06',
                    ],
                ],
            ],
            [
                'name' => 'Yusuf Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => 'BURAK BEYLE GÖRÜŞÜLDÜ. YUSUF BEY OLMADIĞI İÇİN GÖRÜŞME İÇİN GÜN BELİRLENEMEDİ. HAFTAYA ZİYARETE GİDİLECEK. (28.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ELAZIĞ/KOVANCILAR',
                'type' => 'GES',
                'power' => '1.5 MW',
                'excel_row' => 135,
            ],
        ],
    ],
    [
        'name' => 'KİMPACK',
        'city' => 'Gaziantep',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => '1.Organize Sanayi Bölgesi 83118 Nolu Cad. No:5 Başpınar / Gaziantep',
            'district' => null,
            'city' => 'Gaziantep',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'kimpack.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 342 211 21 21',
            ],
            [
                'type' => 'email',
                'value' => 'info@kimpack.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Hasan Kurtarır',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 700 96 03',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'email',
                'subject' => null,
                'text' => 'mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'ÇATI GES',
                'power' => '17,4 MW',
                'excel_row' => 136,
            ],
        ],
    ],
    [
        'name' => 'KİPAŞ HOLDİNG',
        'city' => 'Kahramanmaraş',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Karacasu Karaziyaret Mahallesi Fatih Sultan Mehmet Caddesi No:1/A Dulkadiroğlu , Kahramanmaraş',
            'district' => 'Dulkadiroğlu',
            'city' => 'Kahramanmaraş',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'kipas.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 344 236 38 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ökkeş Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 541 527 34 63',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '17.0.2026 tarihinde zuhal hanım görüşmüş Çağrı mektubu hazır 20 mw üzeri( 100 mw a kadar) tek parça araziler arıyor. Ayrıca ilerleyen günlerde 15 mw civarı bir çatı projeleri de olacak.',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'KOCATEPE ARSLANLAR ELEKTRİK ÜRETİM A.Ş.',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'ALSANCAK MAH.1476 SOK.AKSOY PLAZA NO:2/26 KONAK',
            'district' => 'Konak',
            'city' => 'İzmir',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 491 69 58',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'İZMİR MERKEZ GÖZÜKÜYOR. Yerel tepkiler ve gerginlikten dolayı beklemede. Köşe firmalara soracağız. (19/08)',
            ],
        ],
        'projects' => [
            [
                'name' => 'AĞRI DİYADİN İPEKTEPE HİBRİT ARAZİ GES',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '9,9 MW',
                'excel_row' => 138,
            ],
        ],
    ],
    [
        'name' => 'KUTEN ENERJİ ÜRETİM SANAYİ TİC. A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Mustafa Kemal Mah, Dumlupınar Bul., No: 274/4 İç Kapı No:7 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 530 694 34 10',
            ],
            [
                'type' => 'email',
                'value' => 'csksonmez@hotmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'email',
                'subject' => null,
                'text' => 'mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'KUTEN DEPOLAMALI GES GÜNEŞ ENERJİ SANTRALİ (10 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (10 MWe)',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 17.06.2025',
                'excel_row' => 139,
            ],
        ],
    ],
    [
        'name' => 'Kuvvet Enerji Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'CEVİZLİ MAH.TANSEL CAD.NO:76 CİVİL KULE KAT:7 MALTEPE',
            'district' => 'Maltepe',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'selenkaenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 850 441 75 65',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 447 28 26',
            ],
            [
                'type' => 'email',
                'value' => 'info@selenkaenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'YEMİŞLİ DGES ENERJİ ÜRETİM ANONİM ŞİRKETİ depolama res ges dökümanında firması üretim lisanslı',
            ],
        ],
        'projects' => [
            [
                'name' => 'Yemişli DGES MARDİN / MİDYAT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '13,728 MWm / 10,000 Mwe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 03.07.2025',
                'excel_row' => 140,
            ],
        ],
    ],
    [
        'name' => 'KUVVET ENERJİ SAN.TİC.LTD.ŞTİ.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'CEVİZLİ MAH.TANSEL CAD.NO:76 CİVİL KULE KAT:7 MALTEPE',
            'district' => 'Maltepe',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'selenkaenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 850 441 75 65',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 447 28 26',
            ],
            [
                'type' => 'email',
                'value' => 'info@selenkaenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'SELENKA ENERJİ GRUBUNA AİT',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
        ],
        'projects' => [
            [
                'name' => 'ANKARA POLATLI GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '55 MW',
                'regulatory_note' => 'çed olumlu tarihi 06.08.2025',
                'excel_row' => 141,
            ],
            [
                'name' => 'MARDİN MİDYAT GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 29.11.2025',
                'excel_row' => 142,
            ],
        ],
    ],
    [
        'name' => 'KVRS MİL ENERJİ SAN.VE TİC.A.Ş.',
        'city' => 'Denizli',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'SIRAKAPILAR MAH.495 SOK.NO:24/15 MERKEZEFENDİ',
            'district' => 'Merkezefendi',
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 258 212 70 72',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Mustafa Zeybek',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 764 46 69',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 159 51 92',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'baris.erol@zeongrup.com.tr',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'mustafa.zeybek@zeongrup.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'phone',
                'subject' => null,
                'text' => '(21.08.2026 mustafa zeybek ile görüşüldü. Projenin imar süreci devam ediyor dendi. EPC firmalarından henüz fiyat toplanmamış toplama durumu için mail atarsanız portföyümüze ekleyebilirz dendi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'AYDIN DEPOLAMALI GES',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '10 MW',
                'excel_row' => 143,
            ],
        ],
    ],
    [
        'name' => 'KYS BLOK',
        'city' => 'Kayseri',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Çatakdere Mahallesi Melik Caddesi No : 300 Talas / KAYSERİ',
            'district' => 'Talas',
            'city' => 'Kayseri',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'kysblok.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '444 3 597',
            ],
            [
                'type' => 'email',
                'value' => 'info@kysblok.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Mustafa Kahya',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 209 11 71',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'power' => '15 MW',
                'regulatory_note' => 'çed olumlu tarihi 10.01.2025',
                'excel_row' => 144,
            ],
        ],
    ],
    [
        'name' => 'LİMAK İNŞAAT',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'HAFTA SOKAK NO:9 GOP',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'limak.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 446 88 00',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 803 03 97',
            ],
            [
                'type' => 'email',
                'value' => 'limak@limak.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Can Değirmenci (genel Müdür 0 530 640 94 50) Barış Budak (kıdemli Proje Md.0 530 690 44 23)',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ÇANKAYA GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '13 MW',
                'regulatory_note' => 'çed olumlu tarihi 24.01.2025',
                'excel_row' => 145,
            ],
            [
                'name' => 'POLATLI GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 02.05.2025',
                'excel_row' => 146,
            ],
        ],
    ],
    [
        'name' => 'Lodos Karaburun Elektrik Üretim A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İÇERENKÖY MAHALLESİ, DESTAN SOKAK, EFOR PLAZA, NO:6, İÇ KAPI NO:12 ATAŞEHİR/İSTANBUL',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 216 255 13 13',
            ],
            [
                'type' => 'email',
                'value' => 'merih.sakarya@efrenerjiyatirim.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 301 25 84',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => 'proje izmir karaburunda. 27.08.2026 Mail atıldı.',
            ],
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => 'proje muğla KAVAKLIDERE ,MENTEŞE,YATAĞAN da. 27.08.2026 Mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'KARABURUN RÜZGAR ENERJİ SANTRALİ YARDIMCI KAYNAK GES (99,9999 MWe/99,9999 MWp, 99,9999 MWm-Toplam:118,9 ha) PROJESİ',
                'type' => 'GES',
                'power' => '100 MW',
                'regulatory_note' => 'çed olumlu tarihi 29.08.2025',
                'excel_row' => 147,
            ],
            [
                'name' => 'Lodos RES 54 MWm / 49,5MWe - 9 Türbin',
                'type' => 'RES',
                'power' => '50 MW',
                'excel_row' => 148,
            ],
        ],
    ],
    [
        'name' => 'MAKİ ELEKTRİK ENERJİ OPERASYONLARI YÖNETİMİ A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'GOP MAH.NENEHATUN CAD. NO:32/1 ÇANKAYA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'makienerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 446 17 80',
            ],
            [
                'type' => 'email',
                'value' => 'info@makienerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Hale Yiğit',
                'role' => 'technical_contact',
                'network' => 'Hale.yigit@makienerji.com.tr',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 539 711 63 20',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'İş askıya alındı çed firması en-çev.buna da sor.19/08 (21.08.2026 ibrahi bey 1 hafta tatil olduğunu tatil dönüşü 31ağustos iletişim sağlamamız istendi.) (7.09.2026 İbrahim Bey ile görüşüldü. Projenin izin süreçlerinde olduğunu belirtti. Hüseyin Beyi ve şirketi tanıdığını teklif toplama aşamasında teklif isteyeceklerini belritti.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'KOFÇAZ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '90 MW',
                'excel_row' => 149,
            ],
        ],
    ],
    [
        'name' => 'MAKSİMA ELEKTRİK İNŞAAT TİCARET ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'HİLAL MAH. SUKARNO CAD. HİLAL APT. NO:4/1 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Sami Güzel',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 262 91 87',
                    ],
                ],
            ],
            [
                'name' => 'Sezai Açık',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Eren Satıcı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR/ALPU AKTEPE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,98 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 02.01.2025',
                'excel_row' => 150,
            ],
        ],
    ],
    [
        'name' => 'MATLI ŞİRKETLER GRUBU MATLI YEM',
        'city' => 'Bursa',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'PANAYIR 602 SOK.NO:14 MATLI PLAZA OSMANGAZİ',
            'district' => 'Osmangazi',
            'city' => 'Bursa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'matli.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 224 999 12 00',
            ],
            [
                'type' => 'email',
                'value' => 'matli@matli.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Samet Hongur',
                'role' => 'technical_contact',
                'network' => 'Samet.hongur@matli.com.tr',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 917 65 86',
                    ],
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 768 63 18',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'emre.kanca@matli.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak.(21.08.2026 .İmar sürecinde proje. Henüz ihale aşamasında değil dendi. tanıtım maili iletildi.)',
            ],
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak.(21.08.2026 numra sahibi artık çalışmıyormuş.İmar sürecinde proje. Henüz ihale aşamasında değil dendi. tanıtım maili iletildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ÇANAKKALE RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '25 MW',
                'excel_row' => 151,
            ],
            [
                'name' => 'BURSA KARACABEY RES VE EDT',
                'type' => 'RES EDT',
                'power' => '21,9 MW',
                'regulatory_note' => 'çed olumlu tarihi 30.01.2025',
                'excel_row' => 152,
            ],
        ],
    ],
    [
        'name' => 'Mazıdağı Enerji Üretim Anonim Şirketi Spon Enerji',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Maslak, AOS 55. Sk No:42 No: 2 A Blok D: 252 (Kat: 11 D: 02, 34398 Sarıyer/İstanbul',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 944 52 66',
            ],
            [
                'type' => 'email',
                'value' => 'info@novitasenerji.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 549 442 97 35',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Mazıdağı DGES MARDİN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '13,728 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'ÇED Olumlu 10.03.2025',
                'excel_row' => 153,
            ],
        ],
    ],
    [
        'name' => 'MEKES ENERJİ SANAYİ VE TİCARET LTD. ŞTİ.',
        'city' => 'Eskişehir',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İmişehir Organize Sanayi Bölgesi Osmangazi Bulv. No:15 ESKİŞEHİR',
            'district' => null,
            'city' => 'Eskişehir',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://mekesmuhendislik.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 507 991 24 72',
            ],
            [
                'type' => 'email',
                'value' => 'info@mekesmuhendislik.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 247 75 88',
            ],
            [
                'type' => 'phone',
                'value' => '+90 342 338 12 88',
            ],
            [
                'type' => 'email',
                'value' => 'inan270@hotmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MEKES-2 DEPOLAMALI GES GÜNEŞ ENERJİ SANTRALİ GAZİANTEP/NİZİP',
                'type' => 'GES EDT',
                'power' => '10 MWe/10 MWm 10 MWe/10 MWh',
                'regulatory_note' => 'ÇED Olumlu 16.01.2025',
                'excel_row' => 154,
            ],
        ],
    ],
    [
        'name' => 'MENDERES TEKSTİL',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'ADALET MAH.MANAS BUL NO:47 A BLOK K:42 BAYRAKLI',
            'district' => 'Bayraklı',
            'city' => 'İzmir',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'menderes.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 232 435 05 65',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Onur Bahçıvan (0536 275 59 23)',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'visit',
                'subject' => null,
                'text' => 'yakından ilgileniliyor. Her an nokta atışı ziyaret edilebilir.haber bekliyoruz. 19/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'AKÇA GES',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '60 MW',
                'regulatory_note' => 'ÇED Olumlu 17.06.2025',
                'excel_row' => 155,
            ],
        ],
    ],
    [
        'name' => 'MESCİER DEMİR ÇELİK SAN. VE TİC. A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'FATİH SULTAN MEHMET MAH. POLİGON CAD. BUYAKA 2 SİT. KULE 3 KAT 21 ÜMRANİYE',
            'district' => 'Ümraniye',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.mescierdc.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 335 11 11',
            ],
            [
                'type' => 'email',
                'value' => 'info@mescier.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 370 415 63 00',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 437 44 25',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Mescier Uşak Arazi Güneş Enerji Santrali Kapasite Artışı (30 MWm/30MWe / 15,29 ha\'dan 45 MWm/30MWe / 35,3474 ha\'a) talebi var.',
            ],
        ],
        'projects' => [
            [
                'name' => 'MESCİER UŞAK ARAZİ GÜNEŞ ENERJİ SANTRALİ UŞAK/EŞME',
                'type' => 'GES',
                'power' => '45 MWm / 30 MWe',
                'regulatory_note' => 'ÇED Olumlu 13.01.2025',
                'excel_row' => 156,
            ],
        ],
    ],
    [
        'name' => 'MİKROKAL MADEN A.Ş',
        'city' => 'Niğde',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => null,
        'channels' => [
            [
                'type' => 'website',
                'value' => 'http://www.mikrokal.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 388 214 15 00',
            ],
            [
                'type' => 'email',
                'value' => 'mikrokal@mikrokal.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 546 480 81 36',
            ],
            [
                'type' => 'email',
                'value' => 'info@bilgirmuhendislik.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'Ata Sanayi Karşısı Hıdırlık Mevkii NİĞDE/TÜRKİYE',
            ],
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '(28.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'NİĞDE/MERKEZ',
                'type' => 'GES',
                'power' => '139 MWm',
                'regulatory_note' => 'ÇED IDK aşamasında 23.06.2026',
                'excel_row' => 157,
            ],
        ],
    ],
    [
        'name' => 'Mikrom Enerji İnşaat Tarım Hayvancılık Sanayi ve Ticaret Anonim Şirketi AG Van Yenilenebilir Elektrik Üretimi Sanayi ve Ticaret Anonim Şirketi',
        'city' => 'Kırıkkale',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Erenler Mah. Şehit Kd. Bçvş. Ömer Halisdemir Cad. No: 15/4 İç Kapı No:2 Yahşihan / KIRIKKALE',
            'district' => 'Yahşihan',
            'city' => 'Kırıkkale',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 533 650 00 48',
            ],
            [
                'type' => 'email',
                'value' => 'kenan@mikrom.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'Mikrom enerji şirketi bölünerek AG Van şirketi\'ne devredilmiştir',
            ],
        ],
        'projects' => [
            [
                'name' => 'Yumrutepe Depolamalı GES VAN / TUŞBA',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '35,00 MWm / 30,00 MWe 30,00 MWh / 30,00 MWe',
                'regulatory_note' => 'ÇED Olumlu 09.01.2025',
                'excel_row' => 158,
            ],
        ],
    ],
    [
        'name' => 'MİNA MARBLE MERMER MADEN TİCARET ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'EHLİBEYT MAH. TEKSTİLCİLER CAD. 16/15 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Serdar Ataseven',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '444 2 282',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KARS/KAĞIZMAN TOZOR GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'ÇED Olumlu 23.12.2024',
                'excel_row' => 159,
            ],
        ],
    ],
    [
        'name' => 'MOR YATIRIM ENERJİ SAN.TİC.A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'teklif verildi, iş alındı. Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA KAYIT',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
        ],
        'projects' => [
            [
                'name' => 'YOZGAT ÇEKEREK GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '13 MW',
                'regulatory_note' => 'ÇED Olumlu tarihi 26.12.2025',
                'excel_row' => 160,
            ],
            [
                'name' => 'GİRESUN ŞEBİNKARAHİSAR GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '10 MW',
                'regulatory_note' => 'ÇED olumlu tarihi 23.02.2026',
                'excel_row' => 161,
            ],
        ],
    ],
    [
        'name' => 'NAZAR TEKSTİL SANAYİ VE TİCARET A.Ş.',
        'city' => 'Kahramanmaraş',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kılılı Mah. Balsuyu Blv. No:53 İç Kapı No:1 Türkoğlu/KAHRAMANMARAŞ',
            'district' => 'Türkoğlu',
            'city' => 'Kahramanmaraş',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 344 629 24 49',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 551 260 65 80',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 530 956 55 98',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '(zuhal hanım eski proje takip listesine 40 MW kapasiteli proje için hande hanım yazıp hüseyin beye sorulacak yazmış)',
            ],
        ],
        'projects' => [
            [
                'name' => 'NAZAR DİYARBAKIR GES (28,028 MWm / 22 MWe-33,82 ha.)',
                'type' => 'GES',
                'power' => '22 MW',
                'regulatory_note' => 'çed olumlu tarihi 25.03.2025',
                'excel_row' => 162,
            ],
        ],
    ],
    [
        'name' => 'NECAT İNŞAAT VE DIŞ TİCARET SANAYİ A.Ş.',
        'city' => 'İstanbul',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kısıklı Mah. Alemdağ Cd. No:75 34660 Üsküdar / İstanbul',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://necat.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 428 46 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@necat.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 507 269 35 77',
            ],
            [
                'type' => 'email',
                'value' => 'finans@server.global',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ANKARA/POLATLI',
                'type' => 'GES EDT',
                'power' => '83,916 MW 71,263 MWh',
                'excel_row' => 163,
            ],
        ],
    ],
    [
        'name' => 'NESA TEKSTİL SANAYİ VE TİCARET A.Ş.',
        'city' => 'Denizli',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Nesa Tekstil San. Tic. A.Ş.Organize Sanayi Bölgesi, Nevzat Koru Cad. No.:6',
            'district' => null,
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.nesatekstil.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 258 269 17 90',
            ],
            [
                'type' => 'phone',
                'value' => '+90 258 269 16 86',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Orhan Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 351 19 77',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'orhan@nesatekstil.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-01',
                'channel' => 'email',
                'subject' => null,
                'text' => 'ÇED SÜRECİ TAMAMLANDI. TEKLİF VERİLECEK DÖKÜMANLARIN GELMESİ BEKLENİYOR.ARAZİ KEŞFİ VE ZİYARET EDİLECEK. EPDK onaylı kararla proje Seyitgazi/Kırka sınırlarından Mihalıççık bölgesindeki yeni sahaya kaydırılmıştır.Depolamalı GES konseptine dönüştürülmüştür. (01.09.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'NESA GÜNEŞ ENERJİ SANTRALİ (10,54 MWm/9,3 MWe-9,78 ha) Eskişehir Seyitgazi',
                'status' => '5.1.h',
                'type' => 'GES',
                'power' => '9,3 MWe',
                'regulatory_note' => 'çed olumlu tarihi 07.01.2025',
                'excel_row' => 164,
            ],
        ],
    ],
    [
        'name' => 'NESMA ENERİ YATIRIM A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'HARBİYE MAHALLESİ CUMHURİYET CADDESİ ELMADAĞ İŞHANI APT. NO:32 KAT:3 ŞİŞLİ/İSTANBUL / TURKIYE',
            'district' => 'Şişli',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.artibirenerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 252 40 43',
            ],
            [
                'type' => 'email',
                'value' => 'projegelistirme@artibirgrup.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 309 14 87',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '(artıbir grup şirketinden artıbir enerji bağlı olabilir.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'POZANTI RES VE ENERJİ DEPOLAMA TESİSİ',
                'type' => 'RES EDT',
                'power' => '50 MW',
                'regulatory_note' => 'çed olumlu tarihi 25.12.2024',
                'excel_row' => 165,
            ],
        ],
    ],
    [
        'name' => 'NOVİTAS ENERJİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'KIZILIRMAK MAH.DUMLUPINAR BLV.NO:9A/562 K:13 YDA CENTER',
            'district' => 'Dumlupınar',
            'city' => 'Kütahya',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'novitasenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 442 97 35',
            ],
            [
                'type' => 'email',
                'value' => 'info@novitasenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Hasan Gürsakal',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 765 31 21',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'yakın takip ediliyor.HG ile devamlı temasta',
            ],
        ],
        'projects' => [
            [
                'name' => 'KIRGILLI GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10MW',
                'regulatory_note' => 'çed olumlu tarihi 05.03.2025',
                'excel_row' => 166,
            ],
        ],
    ],
    [
        'name' => 'Novus Grup Yatırım Enerji Üretim Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Ehlibeyt Mah. Mevlana Bulv. No: 201C İç Kapı No:65 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 472 29 79',
            ],
            [
                'type' => 'email',
                'value' => 'muhasebe@airtech.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'mail ve telefon bilgileri Çed raporundan alınmıştır',
            ],
        ],
        'projects' => [
            [
                'name' => 'Bodrum 1 GES MUĞLA / BODRUM',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 07.03.2025',
                'excel_row' => 167,
            ],
        ],
    ],
    [
        'name' => 'ONAK ENERJİ SİSTEMLERİ SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Şanlıurfa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'BATIKENT MAH. 8004 SK. DG9 1/2 /9DG EYYÜBİYE / ŞANLIURFA',
            'district' => 'Eyyübiye',
            'city' => 'Şanlıurfa',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Nadir Kızılelma',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 542 765 15 45',
                    ],
                ],
            ],
            [
                'name' => 'Oktay Kızılelma',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ŞABKIURFA/ SİVEREK KIZILBURÇ 1',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,0005 MWm / 12,00 MWe 12,00 MWh / 12,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 28.11.2025',
                'excel_row' => 168,
            ],
        ],
    ],
    [
        'name' => 'OVA ENERJİ SAN.A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'ETİLER MAH.NİSPETİYE CAD.ETİLER SOK.NO:1 BEŞİKTAŞ',
            'district' => 'Beşiktaş',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'erdem.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'ERDEM HOLDİNG',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Teklif verildi.Hüseyin Güneş Bey konu ile ilgili ve onun dediğine göre işlem yap',
            ],
        ],
        'projects' => [
            [
                'name' => 'ÇORUM MERKEZ GES VE EDT',
                'status' => 'IDK',
                'type' => 'GES EDT',
                'power' => '42,59 MW',
                'regulatory_note' => 'çed olumlu tarihi 18.12.2025',
                'excel_row' => 169,
            ],
            [
                'name' => 'ÇAYIRLAR DRES',
                'status' => 'LİSANS',
                'type' => 'RES',
                'power' => '43,2 MW',
                'regulatory_note' => 'çed olumlu tarihi 05.01.2026',
                'excel_row' => 170,
            ],
            [
                'name' => 'İZMİR BERGAMA',
                'status' => 'IDK',
                'type' => 'RES',
                'power' => '33 MW',
                'regulatory_note' => 'çed olumlu tarihi 06.02.2026',
                'excel_row' => 171,
            ],
            [
                'name' => 'MANİSA YUNUSEMRE',
                'status' => 'IDK',
                'type' => 'RES',
                'power' => '33 MW',
                'regulatory_note' => 'çed olumlu tarihi 10.03.2026',
                'excel_row' => 172,
            ],
        ],
    ],
    [
        'name' => 'OYAK Çimento',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Çukurambar Mahallesi 1480. Sokak No: 2A/56 Çankaya/ ANKARA / TURKIYE',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.oyakcimento.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 220 02 90',
            ],
            [
                'type' => 'email',
                'value' => 'kvkkmerkez@oyakcimento.com',
            ],
            [
                'type' => 'email',
                'value' => 'iletisim@oyakcimento.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Gökhan Kızılırmak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 411 56 72',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'Yeşil Dönüşüm ve Atık Isı Yatırımları',
            ],
            [
                'on' => '2026-08-19',
                'channel' => 'email',
                'subject' => null,
                'text' => '(19.08.2026 tarihinde mail iletildi.) (21.08.2026 Gökhan kızılırmak ile görüşüldü şuan aktif bir enerji yatırımları olmadığı iletildi.)',
            ],
        ],
        'projects' => [
            [
                'regulatory_note' => 'çed olumlu tarihi 12.02.2025',
                'excel_row' => 173,
            ],
        ],
    ],
    [
        'name' => 'OYAK Enerji Şirketleri Genel Merkezi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Cevizli Mah. Tugay Yolu Cad. No:10-C, İç Kapı No: 88 Maltepe / İstanbul / TURKIYE',
            'district' => 'Maltepe',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.oyakenerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 606 72 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@oyakenerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ömer Faruk Yıldız',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 536 903 94 95',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'omer.yildiz@oyen.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'email',
                'subject' => null,
                'text' => '(19.08.2026 tarihinde mail iletildi.) (26.08.2026 opratör yönlendirmesi ile Ömer Faruk Beye ulsşıldı. Telefonda kısa bir firma tanıtımı yapıldı ve tanıtım maili atabilmek için kendisinin amil adresi istendi.)',
            ],
        ],
        'projects' => [
            [
                'regulatory_note' => 'çed olumlu tarihi 18.02.2025',
                'excel_row' => 174,
            ],
        ],
    ],
    [
        'name' => 'OZANTEKS TEKSTİL A.Ş.',
        'city' => 'Denizli',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'BOZBURUN MAH.7042 SOK.NO:6',
            'district' => null,
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'ozanteks.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 258 371 64 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Özgür Sönmez',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'ozgur.sonmez@ozanteks.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-20',
                'channel' => 'email',
                'subject' => null,
                'text' => 'cw enerji ile anlaştığı duyumu var teyit edilecek telefonla. (20.08.2026 arandı Özgür Bey\'in maili verildi. Proje durumunu ve firma tanıtımı için mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'DİYARBAKIR SİLVAN GES',
                'status' => 'IDK',
                'type' => 'GES',
                'power' => '10 MW',
                'regulatory_note' => 'çed olumlu tarihi 18.02.2025',
                'excel_row' => 175,
            ],
        ],
    ],
    [
        'name' => 'OZE 2 ENERJİ ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'BÜYÜKESAT MAH. MAHATMA GANDİ CAD. 1/49 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Levent Gündüzkanat',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 441 20 14',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ÇEKİM İNŞAAT-2 DEPOLAMALI GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '120,00 MWm / 120,00 MWe 120,00 MWh / 120,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 06.03.2025',
                'excel_row' => 176,
            ],
        ],
    ],
    [
        'name' => 'OZE 3 ENERJİ ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'BÜYÜKESAT MAH. MAHATMA GANDİ CAD. 1/49 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Levent Gündüzkanat',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 441 20 14',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ÇEKİM-3 DEPOLAMALI GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '120,00 MWm / 120,00 MWe 120,00 MWh / 120,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 06.03.2025',
                'excel_row' => 177,
            ],
        ],
    ],
    [
        'name' => 'Öz Kut Elektrik Üretimi Madencilik Taşımacılık İnşaat İthalat İhracat Sanayi ve Ticaret Limited Şirketi',
        'city' => 'Hakkari',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Pehlivan Mah. Çölemerik Cad. No:10 İç Kapı No: 17 Merkez/Hakkari',
            'district' => 'Merkez',
            'city' => 'Hakkari',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 532 068 21 30',
            ],
            [
                'type' => 'email',
                'value' => 'enerjibirimi@outlook.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Yapım işi başka firmaya verilmiş, saha çalışmaları başlamış.',
            ],
        ],
        'projects' => [
            [
                'name' => 'G3-Hakkari-1-1 GES HAKKARİ / MERKEZ',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '11,997 MWm / 10,00 MWe',
                'regulatory_note' => 'çed olumlu tarihi 07.03.2025',
                'excel_row' => 178,
            ],
        ],
    ],
    [
        'name' => 'Özerka Enerji Elektrik Üretim Sanayi ve Ticaret Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İşçi Blokları Mah. Mevlana Bulv. Ege Plaza No: 182b İç Kapı No: 110 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://ozerka.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 426 02 03',
            ],
            [
                'type' => 'email',
                'value' => 'info@ozerka.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'G-24 Malatya GES MALATYA',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '100 MWm / 75,00 MWe YEKA',
                'regulatory_note' => 'çed olumlu tarihi 10.09.2025',
                'excel_row' => 179,
            ],
        ],
    ],
    [
        'name' => 'Özyaşar Tel ve Galvanizleme Sanayi Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => 'Çed firmasının bilgileri',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Seyitnizam Mah. Demirciler Sitesi 10. Cadde Aydaş İş Merkezi, B Blok, No:7, Kat:1, 34015, Zeytinburnu, İstanbul / Türkiye',
            'district' => 'Zeytinburnu',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.ozyasar.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 634 04 44',
            ],
            [
                'type' => 'email',
                'value' => 'info@ozyasar.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 542 187 07 84',
            ],
            [
                'type' => 'email',
                'value' => 'info@globalced.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '(28.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'MALATYA / DOĞANŞEHİR ÖZYAŞAR GES ERKENEK8,073 MWm/ 7,000 MWe KURULU GÜCÜNDE GÜNEŞ ENERJİ SANTRALİ',
                'type' => 'GES',
                'excel_row' => 180,
            ],
        ],
    ],
    [
        'name' => 'PANGES PANDA ALÜMİNYUM',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA BİLGİLER',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Teklif gönderildi. Revizyon istendi. Hüseyin Bey\'in söylediğine göre hareket edilecek.',
            ],
        ],
        'projects' => [
            [
                'type' => 'GES',
                'power' => '5 MW',
                'excel_row' => 181,
            ],
        ],
    ],
    [
        'name' => 'PARS ENERJİ YATIRIM SANAYİ VE TİC. A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Esentepe Mah. Büyükdere Cad. Yonca Apt. C Blok No:151 Kat:3 Daire:38 Şişli/İSTANBUL',
            'district' => 'Şişli',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'pars-invest.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 347 06 13',
            ],
            [
                'type' => 'email',
                'value' => 'pars@pars-invest.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 302 68 38',
            ],
            [
                'type' => 'email',
                'value' => 'batikol@aisfund.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'PARS INVEST',
            ],
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak. (21.08.2026 arandı meltem korkmaz ile görüşüldü yetkili kişilere yönlendirme yapılması için talep ve tanıtım mail istendi atıldı) (25.08.2026 meltem hanım ile görüşüldü mail birol beye iletildiği bilgisi verildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'TEKİRDAĞ HAYRABOLU RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '48 MW',
                'excel_row' => 182,
            ],
            [
                'name' => 'EDİRNE UZUNKÖPRÜ RES VE EDT',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '48 MW',
                'excel_row' => 183,
            ],
        ],
    ],
    [
        'name' => 'PASİFİK DOĞALTAŞ MADENCİLİK SAN.TİC.A.Ş.',
        'city' => 'Denizli',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'KAKLIK MAH.MERMER FABRİKALRI KÜMESİ NO:10/1 HONAZ',
            'district' => 'Honaz',
            'city' => 'Denizli',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'pacificnaturalstone.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 258 244 00 75',
            ],
            [
                'type' => 'email',
                'value' => 'info@pacificnaturalstone.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 536 253 77 07',
            ],
            [
                'type' => 'email',
                'value' => 'enerjibirimi@outlook.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak.(21.08.2026 kişisel no arandı Çed firması olduklarını işveren firmaya iletim yapılacak dendi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'BALIKESİR RES',
                'status' => 'ÖNLİSANS',
                'type' => 'RES',
                'power' => '1 MW',
                'excel_row' => 184,
            ],
        ],
    ],
    [
        'name' => 'PİK ENERJİ DAFNE ELEKTRİK ÜRETİM LTD. ŞTİ.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Akpınar Mah. Dikmen Cad. Neşem Apart. No:566/9 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.mimaray.com.tr/kurumsal/girisimlerimiz/dafne-elektrik',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 476 76 16',
            ],
            [
                'type' => 'email',
                'value' => 'info@mimaray.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Alperan Tamer',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 642 96 22',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'ARAÇ BARAJI VE HES YARDIMCI KAYNAK GÜNEŞ ENERJİ SANTRALİ(4,9787 MWp / 4,9787 MWm / 4,0 MWe - 6,7 ha) PROJESİ İÇİN TEKLİF İLETİLDİ. ARAZİ İLE İLĞİLİ SIKINTILAR ÇÖZÜLMÜŞ. REVİZE TEKLİF VERİLECEK.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Proje ili KASTAMONU',
                'status' => 'LİSANS',
                'type' => 'HES',
                'power' => '5 MWe',
                'excel_row' => 185,
            ],
        ],
    ],
    [
        'name' => 'proWIND ALTERNATİF ENERJİ SAN.TİC.LTD.ŞTİ.',
        'city' => 'Kahramanmaraş',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'HAYRULLAH MAH.AZERBAYCAN BUL 64 DİVAN APT.K:3 D:24 ONİKİŞUBAT',
            'district' => 'Onikişubat',
            'city' => 'Kahramanmaraş',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'prowind.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 344 234 21 10',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Teklif verildi.çok yakından takip ediliyor görüşmeler devam ediyor. Yusuf bey teknik detaylarla ilgili görüşüyor. 19/08',
            ],
        ],
        'projects' => [
            [
                'name' => 'AK JEO ENERJİ pW-TR1002 RES',
                'type' => 'RES',
                'power' => '20 MW',
                'excel_row' => 186,
            ],
        ],
    ],
    [
        'name' => 'RAMA ENERJİ YATIRIMLARI A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'ÖMER AVNİ MAH.İNÖNÜ CAD.50/16 BEYOĞLU',
            'district' => 'Beyoğlu',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'ramaenerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 240 28 20',
            ],
            [
                'type' => 'email',
                'value' => 'info@ramaenerji.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Yaren Polat',
                'role' => 'decision_maker',
                'network' => 'Eski dökümanlarda bu proje için förüşülen yetkili',
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 544 760 49 26',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Projeleri şu an beklemede. Ancak ihale sürecine dahiliz. İstanbul ziyareti planlayıp yüz yüze de görüşeceğiz. (17.08.2026 arandı bilgi talep edildi. Yetkili kışılerin ofis dışında olduğu bilhsii ile mail iletilmesi istendi.) (21.08.2026 Aykut Bey ile iletişime geçildi. hali hazırda bir GES kurulumu tamamlanmak üzereymiş 2.GES projeleri için henüz hukuki süreçleri bekliyorlarmış teklif toplma sürecine daha var dendi. )',
            ],
        ],
        'projects' => [
            [
                'name' => 'AMASYA HES YARDIMCI KAYNAK',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '10 MW',
                'excel_row' => 187,
            ],
        ],
    ],
    [
        'name' => 'REİS RS ENERJİ ELEKTRİK ÜRETİM SAN.TİC.A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => null,
        'channels' => [
            [
                'type' => 'website',
                'value' => 'reisotomotiv.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'ÖMER NACİ ÇEBİ omer.cebi@reisotomotiv.com.tr',
                'role' => 'technical_contact',
                'network' => 'Enerji yatırımları koordinatörü',
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'omer.cebi@reisotomotiv.com.tr',
                    ],
                    [
                        'type' => 'phone',
                        'value' => '+90 312 219 21 99',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'YANLIŞ OLABİLİR',
            ],
            [
                'on' => '2026-09-04',
                'channel' => 'visit',
                'subject' => null,
                'text' => 'Ziyaret programına alındı(04/09)',
            ],
        ],
        'projects' => [
            [
                'name' => 'TOKAT ZİLE GES',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '3MW',
                'excel_row' => 188,
            ],
            [
                'name' => 'KIRKLARELİ VİZE RES VE EDT',
                'status' => 'IDK',
                'type' => 'RES EDT',
                'power' => '64 MW',
                'regulatory_note' => 'ÇED Olumlu 31.12.2024',
                'excel_row' => 189,
            ],
        ],
    ],
    [
        'name' => 'Reis RS Enerji Elektrik Üretim Sanayi ve Ticaret Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bulv. Nextlevel No: 3/A İç Kapı No: 128 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'reisotomotiv.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'ÖMER NACİ ÇEBİ omer.cebi@reisotomotiv.com.tr',
                'role' => 'technical_contact',
                'network' => 'Enerji yatırımları koordinatörü',
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'omer.cebi@reisotomotiv.com.tr',
                    ],
                    [
                        'type' => 'phone',
                        'value' => '+90 312 219 21 99',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Pınarhisar RES EDİRNE / LALAPAŞA',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '105,4 MWm / 105,4 MWe 111,2 MWh / 111,2 MWe',
                'excel_row' => 190,
            ],
        ],
    ],
    [
        'name' => 'Reyhan Yağ Teks. San. Ve Tic. Ltd. Şti.',
        'city' => 'Şanlıurfa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'SAKARKAYA MAHALLESİ SAKARYA CADDESİ NO:304 AKHİSAR / MANİSA / TÜRKİYE',
            'district' => 'Akhisar',
            'city' => 'Manisa',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.reyhanyag.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 414 290 15 18',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 516 165 35 45',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'REYHAN3 ve REYHAN4 URFA/ HALİLİYE',
                'type' => 'GES',
                'power' => '(1.3572 MWm / 0,96 MWe) 0.6864 MWm / 0,50 MWe)',
                'excel_row' => 191,
            ],
        ],
    ],
    [
        'name' => 'RG Enerji İnşaat Sanayi ve Ticaret Anonim Şirketi',
        'city' => 'Batman',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Regnum Sky Tower İşçi Blokları Mahallesi Muhsin Yazıcıoğlu Caddesi No: 57/42 Çankaya/Ankara Pınarbaşı Mah. 2201 Sok. No:17 İç Kapı No: 2 Merkez /BATMAN',
            'district' => 'Merkez',
            'city' => 'Batman',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.rgenerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 390 07 72',
            ],
            [
                'type' => 'email',
                'value' => 'info@hmdgrup.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 388 47 57',
            ],
            [
                'type' => 'email',
                'value' => 'oropei@hotmail.com',
            ],
            [
                'type' => 'email',
                'value' => 'zbaskin@hmdgrup.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'Batman adresi çed raporundan alınmıştır',
            ],
        ],
        'projects' => [
            [
                'name' => 'G3-Aksaray-1-6 GES AKSARAY / MERKEZ',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '14,85 MWm / 10,00 MWe',
                'excel_row' => 192,
            ],
        ],
    ],
    [
        'name' => 'RNC Avrasya Enerji Tedarik Depolama Ticaret A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Levent Mah. Büyükdere Cad. No: 168 / 1 Beşiktaş / İSTANBUL / TURKIYE',
            'district' => 'Beşiktaş',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 276 01 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@rncenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Nilgün Hanım',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 530 386 96 29',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-26',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Proje aksaray merkezde. (26.08.2026 arandı enerji birimi taşınma aşamasında bu hafta ulaşım sağlanamıyor dendi. Mail iletilmesi istendi info@renecore.com.tr adresi verildi.)',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje uşak banaz\'da.',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje afyonkarahisar HOCALAR,SİNCANLI(SİNANPAŞA)\'da.',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'proje tekirdağ saray\'da.',
            ],
            [
                'on' => '2026-08-26',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Proje karaman ayrancıda. (26.08.2026 arandı enerji birimi taşınma aşamasında bu hafta ulaşım sağlanamıyor dendi. Mail iletilmesi istendi info@renecore.com.tr adresi verildi.) (Gelen DEPOLAMA RES GES dökümanında işveren firma SUDURAĞI GES ENERJİ ÜRETİM ANONİM ŞİRKETİ 02164727630 OSMAN HULUSİ TOPRAK, MUHAMMET MESUT TOPRAK, İBRAHİM ERDEN )',
            ],
        ],
        'projects' => [
            [
                'name' => 'SULTANHANI ELEKTRİK DEPOLAMA TESİSİ (100 MWe / 100 MWh) GÜNEŞ ENERJİ SANTRALİ (100 MWm / 100 MWe) (178,29 ha)',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 193,
            ],
            [
                'name' => 'SİNANPAŞA ELEKTRİK DEPOLAMA TESİSİ (100 MWe/100 MWh) RÜZGÂR ENERJİ SANTRALİ (29 ADET TÜRBİN: 100 MWm / 100 MWe)',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 194,
            ],
            [
                'name' => 'ÇUKURYURT ELEKTRİK DEPOLAMA TESİSİ (100 MWe / 100 MWh) RÜZGÂR ENERJİ SANTRALİ (32 ADET TÜRBİN: 100 MWm / 100 MWe)',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 195,
            ],
            [
                'name' => 'SİNANPAŞA ELEKTRİK DEPOLAMA TESİSİ (100 MWe/100 MWh) RÜZGÂR ENERJİ SANTRALİ (29 ADET TÜRBİN: 100 MWm / 100 MWe)',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 196,
            ],
            [
                'name' => 'SUDURAĞI GÜNEŞ ENERJİ SANTRALİ (100 MWm / 100 MWe) VE ELEKTRİK DEPOLAMA TESİSİ (100 MWe / 100 MWh) (173,24 ha)',
                'type' => 'GES EDT',
                'power' => '100 MW',
                'excel_row' => 197,
            ],
        ],
    ],
    [
        'name' => 'Rol Enerji Elektrik Üretim ve Ticaret Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kısıklı Mah. Alemdağ Cad. No: 29 İç Kapı No: 1 Üsküdar / İSTANBUL',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 224 441 24 41',
            ],
            [
                'type' => 'email',
                'value' => 'rolenerji@gmail.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 367 40 27',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Rol GES KAHRAMANMARAŞ / GÖKSUN',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,00 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 198,
            ],
        ],
    ],
    [
        'name' => 'RT ENERJİ TURİZM SAN.VE TİC.A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'ROYAL SEGINUS HOTEL KEMERAĞZI MAH. YAŞAR SABUTAY BUL. NO:70 AKSU/ANTALYA/ TURKIYE',
            'district' => 'Aksu',
            'city' => 'Antalya',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.rtenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 242 352 12 06',
            ],
            [
                'type' => 'email',
                'value' => 'info@rtenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'SERGEN RES (145 MWm / 145 MWe, 29 ADET TÜRBİN) PROJESİ',
                'type' => 'RES',
                'power' => '145 MW',
                'excel_row' => 199,
            ],
        ],
    ],
    [
        'name' => 'SAFE ENERJİ A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İŞÇİ BLOKLARI MAH.MUHSİN YAZICIOĞLU CAD.NO:57 NO:11 ÇANKAYA:',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 284 03 36',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ahmet Teyfik Paksu',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 442 97 35',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => 'köşe firmalara sorulacak.(19/08) (28.08.2026 0312 442 97 35 görüşüldü. Projeler geliştirilme aşamasında olduğunu EPC teklif toplama yapmadıklarını proje için danışmanlık yaptıklarını beliritildi.mail olarak info@novitasenerji.com adresi verildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'MARDİN MİDYAT GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10MW',
                'excel_row' => 200,
            ],
            [
                'name' => 'SİVAS KANGAL GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '90MW',
                'excel_row' => 201,
            ],
        ],
    ],
    [
        'name' => 'Safe Enerji Anonim Şirketi Balpınar Enerji Üretim Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bulv. A Blok No: 9A İç Kapı No: 562 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 549 442 97 35',
            ],
            [
                'type' => 'email',
                'value' => 'enerjibirimi@outlook.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ahmet Teyfik Paksu',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 312 442 97 35',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'Lisans Balpınar Enerji şirketine devrediliyor. Safe ve Balpınar konum olarak aynı adreste, 1000 yatırımlar holding bünyesindeki Meta mobilite şirketi satın alıyor. (HÇ',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'BALPINAR ENERJİ ÜRETİM ANONİM ŞİRKETİ DEPOLAMA RES GES çalışmasında üretim lisanslı',
            ],
        ],
        'projects' => [
            [
                'name' => 'Balpınar DGES MARDİN / MAZIDAĞI',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '13,728 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 202,
            ],
        ],
    ],
    [
        'name' => 'Safir Yenilenebilir Enerji Ticaret Limited Şirketi',
        'city' => 'Diyarbakır',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Fırat Mahallesi, 553. Sokak, Tanlar Şehri Teras, B-Blok 2/38 Kayapınar / Diyarbakır',
            'district' => 'Kayapınar',
            'city' => 'Diyarbakır',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 394 78 15',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 531 949 55 55',
            ],
            [
                'type' => 'email',
                'value' => 'oropei@hotmail.com',
            ],
            [
                'type' => 'email',
                'value' => 'suaybaltun44@gmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Safir GES ŞANLIURFA / VİRANŞEHİR',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '20,80 MWm / 16,00 MWe 16,00 MWh / 16,00 MWe',
                'excel_row' => 203,
            ],
        ],
    ],
    [
        'name' => 'SANKO HOLDİNG A.Ş.',
        'city' => 'Gaziantep',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İBRAHİMLİ MAH.SANKO SOK.SANKO HOLDİNG YÖNETİM BİNASI NO:12 A ŞEHİTKAMİL',
            'district' => 'Şehitkamil',
            'city' => 'Gaziantep',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'sanko.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 342 211 30 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'NECMİ GÖĞÜŞ AYDIN YALÇIN merve.hanci@sankoenerji.com.tr 05547036809',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'email',
                        'value' => 'merve.hanci@sankoenerji.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-25',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'Bu hafta iletişime geçilecek (25.08.2026 merve hanım ile görüşüldü. Projelerinin iptal edildiğini ve farklı projeleri olmadığını ve yakın vadede planlalnan projeleri olmadığını belirtti.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'AFYON EVCİLER GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '20 MW',
                'excel_row' => 204,
            ],
        ],
    ],
    [
        'name' => 'Sanvar İnşaat Elektrik Ener. Malz. Turizm San. Tic. Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Balgat Mah. Mevlana Bulv. No:139 A/28 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 533 743 03 78',
            ],
            [
                'type' => 'email',
                'value' => 'iletisim@sanvargroup.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Nizam Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 554 806 90 64',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'Mardin\'de bulunan biyogaz santrallerine 4.5 mw hibrit ges yapacaklar. 2 proje 3.1mw+ 1.4mw şeklinde ayrı başvuru yaptılar. 3.1 mw imar süreci daha başlamadı, 1.4mw gesin lisansı alındı.Dicle EDAŞ onay sürecinde, vali ile sıkıntı çekiyorlar. Çed yok daha. iş başlamadı sıkıntı çekiliyormuş',
            ],
        ],
        'projects' => [
            [
                'name' => 'Mardin Biogaz Santrali',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '1.4 MW',
                'excel_row' => 205,
            ],
        ],
    ],
    [
        'name' => 'SARAY GERİ DÖNÜŞÜM VE ENTEGRE TESİSLERİ SAN. TİC. LTD. ŞTİ.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Saray, Fatih Sultan Mehmet Blv No:317, 06980 Kahramankazan/Ankara',
            'district' => null,
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 802 02 09',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Abdullah Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 554 463 36 69',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'abdullah@asammuhendıslık.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'visit',
                'subject' => null,
                'text' => 'ASAM MÜHENDİSLİKTEN ABDULLAH BEY ZİYARET EDİLDİ. TEKLİF TOPLAMA SÜRECİ ŞUBATTA TOPLANACAK.Saha ölçümleri ve haritalandırma süreçleri Kayram Mühendislik tarafından bitirilerek arazi kurulumuna hazır hale getirildi .asam müh epc firması olarak gözüküyor',
            ],
        ],
        'projects' => [
            [
                'name' => 'Proje Konumu Kırıkkale Merkez GÜNEŞ ENERJİ SANTRALİ (5 MWm, 10,13 ha)',
                'status' => 'LİSANS',
                'type' => 'GES',
                'power' => '5 MWe',
                'excel_row' => 206,
            ],
        ],
    ],
    [
        'name' => 'SARAY RES ENERJİ ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kültür Mah. Nisbetiye Cad. Akmerkez No:56 İç Kapı No:6 Beşiktaş / İSTANBUL',
            'district' => 'Beşiktaş',
            'city' => 'İstanbul',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Osman Hulusi Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 472 76 30',
                    ],
                ],
            ],
            [
                'name' => 'Muhammet Mesut Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'İbrahim Erden',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'traktek ile aynı adres(yeni taşınmışlar',
            ],
        ],
        'projects' => [
            [
                'name' => 'SARAY EDT RES',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '53,00 MWm / 50,00 MWe 50,00 MWh / 50,00 MWe',
                'regulatory_note' => 'ÇED Olumlu 31.07.2025',
                'excel_row' => 207,
            ],
        ],
    ],
    [
        'name' => 'SCORPİON ELEKTRİK ENERJİSİ DANIŞMANLIK ANONİM ŞİRKETİ',
        'city' => 'Şanlıurfa',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'YENİŞEHİR MAH. 238 SK. ERTUĞRUL A BLK /12 A HALİLİYE / ŞANLIURFA',
            'district' => 'Haliliye',
            'city' => 'Şanlıurfa',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Fırat Ural',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 507 659 54 08',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ŞANLIURFA/ BOZOVA NARSAİT-FU GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '14,235 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 208,
            ],
        ],
    ],
    [
        'name' => 'SMO HARİTA MÜHENDİSLİK',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'PINAR MAH.BOSTANLIK SOK.NO:3 MERAM AŞAĞI ÖVEÇLER MAH.1324.CAD.NO:55/7 ÇANKAYA',
            'district' => 'Meram',
            'city' => 'Konya',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'smoharita.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 332 320 40 10',
            ],
            [
                'type' => 'email',
                'value' => 'smo@smoharita.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'Görüşüldü ancak SYS enerji ile çalışıyorlar.',
            ],
        ],
        'projects' => [
            [
                'name' => 'KONYA GES VE EDT',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10 MW',
                'excel_row' => 209,
            ],
        ],
    ],
    [
        'name' => 'SOLAKOĞLU GRUP',
        'city' => 'Erzurum',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'LALAPAŞA MAH.1.KURTDERESİ SOK.ŞEHRİSTAN KONUTLARI A1 BLOK NO:1 A/3 YAKUTİYE',
            'district' => 'Yakutiye',
            'city' => 'Erzurum',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'solakoglugroup.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 442 235 03 52',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Cihan Abuşoğlu',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'phone',
                'subject' => null,
                'text' => 'Projeyle alakalı telefonda görüşüldü.Malzemeyi kendileri tedarik edeceklerini söylediler. Konuyla ilgili tekrar bu hafta görüşeceğiz. (17.08.2026 cihan Bey ile görüşüldü Biren Enerji firmasından Halil Bey veya Eren Bey ile görüşülmesi istendi 05335447830 ile görüşüldü cuma günü tekrar iletişime geçmemi istediler.) (25.08.2026 Biren Enerji ile iletişime geçildi projenin 1 yıl sonra için planlaması var dendi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ERZURUM GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '15 Mwe',
                'excel_row' => 210,
            ],
        ],
    ],
    [
        'name' => 'SPON ENERJİ ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Maslak, AOS 55. Sk No:42 No: 2 A Blok D: 252 (Kat: 11 D: 02, 34398 Sarıyer/İstanbul',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 944 52 66',
            ],
            [
                'type' => 'email',
                'value' => 'info@novitasenerji.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 549 442 97 35',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-31',
                'channel' => 'email',
                'subject' => null,
                'text' => '(31.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'BAYBURT GÜNEŞ ENERJİ SANTRALİ (87,859 MWm/75 MWe-112,39 ha) VE ELEKTRİK DEPOLAMA TESİSİ (75 MWe/75 MWh)',
                'type' => 'GES EDT',
                'power' => '87,859 MWm/75 MWe-112,39ha 75 MWe/75 MWh',
                'regulatory_note' => 'çed olumlu tarihi 07.01.2025',
                'excel_row' => 211,
            ],
            [
                'name' => 'Tepeköy Güneş Enerji Santrali (30 MWm/20 MWe-39,999 ha) ve Elektrik Depolama Tesisi (20 MWe/20 MWh)',
                'type' => 'GES EDT',
                'power' => '87,859 MWm/75 MWe-112,39ha 75 MWe/75 MWh',
                'regulatory_note' => 'çed olumlu tarihi 26.10.2024',
                'excel_row' => 212,
            ],
            [
                'name' => 'Van Depolamalı Güneş Enerji Santrali (90 MWm / 64 MWe - 127,65 ha) ve Elektrik Depolama Tesisi (64 MWh / 64 MWe - 0,50 ha)',
                'type' => 'GES EDT',
                'power' => '87,859 MWm/75 MWe-112,39ha 75 MWe/75 MWh',
                'regulatory_note' => 'çed olumlu tarihi 12.10.2024',
                'excel_row' => 213,
            ],
        ],
    ],
    [
        'name' => 'SUNAR MISIR ENTEGRE TESİSLERİ SANAYİİ VE TİCARET A.Ş.',
        'city' => 'Adana',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Turhan Cemal Beriker Bulvarı Yolgeçen Mah. No:565 01355 Seyhan / Adana',
            'district' => 'Seyhan',
            'city' => 'Adana',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.sunarmisir.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 322 441 01 65',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 539 552 45 78',
            ],
            [
                'type' => 'email',
                'value' => 'info@sunarmisir.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Doğukan Çayırcı',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 067 33 03',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'dogukan.cayirci@sunarmisir.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => '(28.08.2026 mail atıldı)(04.09.2026 da doğukan bey ile ietişime geçildi. Fabrika tarafına batarya sistemi kurulmak isteniyor. Araştırılıp dönüş yapılacak)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ESKİŞEHİR/ALPU SUNAR ESKİŞEHİR GES ALAN REVİZYONU (8,0004 MWm/ 6 MWe/ 8,0004 MWp- 8,99 ha)',
                'type' => 'GES',
                'power' => '6 MW',
                'regulatory_note' => 'çed olumlu tarihi 06.08.2025',
                'excel_row' => 214,
            ],
        ],
    ],
    [
        'name' => 'SYCS İNŞAAT ÇİMENTO SANAYİ VE TİCARET ANONİM ŞİRKETİ',
        'city' => 'Elazığ',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'PINARLI KÖYÜ NO:70 GEDEBÜK',
            'district' => null,
            'city' => 'Elazığ',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'sezacimento.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 424 522 11 10',
            ],
            [
                'type' => 'email',
                'value' => 'sezacimento@sycs.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 553 652 58 44',
            ],
            [
                'type' => 'email',
                'value' => 'bulent.seyyar@sezainsaat.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Miray Aydın (0530 348 17 23)',
                'role' => 'technical_contact',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'Halil Güneş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 537 474 65 04',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => '(17.08.2026 Miray Hanım ile görüşüldü. Yakın zaman içerisnde firmalardan teklif isteneceğini belirtti buna istinaden teklif gönderme talebi ve firma tanıtım için mail iletildi.)(25.08.2026 miray hanımla iletişime geçildi. Mail ulaşmış projenin lisansına başvuru yapılmamış lisans çıktıktan sonra teklif toplayacaklarının bilgisi verildi.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'KIRKLARELİ PINARHİSAR RES',
                'status' => 'LİSANS',
                'type' => 'RES',
                'power' => '21 MW',
                'regulatory_note' => 'çed olumlu tarihi 30.06.2026',
                'excel_row' => 215,
            ],
            [
                'name' => 'ELAZIĞ/BASKİL DOĞANCIK DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '10,0004 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 216,
            ],
        ],
    ],
    [
        'name' => 'TALARY ENERJİ',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Çınarlı Mah. Ozan Abay Caddesi Egeperla B Kule No:10/65 Konak / İZMİR - Konutkent Mah. 3028 Cd. West Gate Residence No:2A/59 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 232 520 83 97',
            ],
            [
                'type' => 'email',
                'value' => 'info@talaryenerji.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'email',
                'subject' => null,
                'text' => 'mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'regulatory_note' => 'çed olumlu tarihi 09.09.2024',
                'excel_row' => 217,
            ],
        ],
    ],
    [
        'name' => 'Taykar Enerji Üretim Pazarlama Ticaret Anonim Şirketi Uşak 1 Enerji Üretim Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mah. Dumlupınar Bulv. Nextlevel No:3A iç Kapı No:82 Çankaya / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Uşak-1 GES UŞAK / MERKEZ',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '40,00 MWm / 40,00 MWe 40,00 MWh / 40,00 MWe',
                'excel_row' => 218,
            ],
        ],
    ],
    [
        'name' => 'TCDD',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'authority',
        'address' => [
            'line1' => 'TCDD Genel Müdürlüğü Hacı Bayram Mahallesi Hipodrom Caddesi No:3 06050 Altındağ/ANKARA',
            'district' => 'Altındağ',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.tcdd.gov.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 520 00 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Bünyamin Polat',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Firma notu',
                'text' => 'T.C. DEVLET DEMİRYOLLARI İŞLETMESİ GENEL MÜDÜRLÜĞÜ',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '(zuhal hanım)',
            ],
        ],
        'projects' => [
            [
                'power' => '210 MW',
                'excel_row' => 219,
            ],
        ],
    ],
    [
        'name' => 'TEMO ELEKTRİK ENERJİ ÜRETİM PAZARLAMA SAN.A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 2,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kızılırmak Mahallesi, Dumlupınar Bulvarı, Next Level No: 3A İç Kapı 82 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 496 40 96',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 533 329 64 73',
            ],
            [
                'type' => 'email',
                'value' => 'ced@egesa.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'EGESA İLE ORTAKLIĞI VAR.EPC FİRMASI (19/08)',
            ],
        ],
        'projects' => [
            [
                'name' => 'SİNOP GERZE GES VE EDT Çattepe Rüzgâr Enerji Santrali (RES)',
                'status' => 'ÖNLİSANS',
                'type' => 'RES EDT',
                'power' => '50 MW',
                'regulatory_note' => 'çed olumlu tarihi 19.02.2026',
                'excel_row' => 220,
            ],
        ],
    ],
    [
        'name' => 'TERMAL SERAMİK SANAYİ VE TİCARET A.Ş.',
        'city' => 'Bilecik',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Bilecik Devlet Karayolu 4.Km. 11600 Söğüt/Bilecik/TURKEY',
            'district' => 'Söğüt',
            'city' => 'Bilecik',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.termalseramik.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'info@termalseramik.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ahmet Altaş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 228 361 55 00',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-28',
                'channel' => 'email',
                'subject' => null,
                'text' => 'SON DURUM İÇİN GÖRÜŞMELER DEVAM EDİYOR. ZİYARET EDİLECEK. (28.08.2026 Mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'Proje ili Ankara',
                'status' => '5.1.h',
                'type' => 'GES',
                'power' => '20 MW',
                'regulatory_note' => 'çed olumlu Tarihi 19.11.2025',
                'excel_row' => 221,
            ],
        ],
    ],
    [
        'name' => 'Tersan Tersanecilik Sanayi ve Ticaret A.Ş',
        'city' => 'Yalova',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Tavşanlı Beldesi Fatih Mah. Boğaziçi Cad. No:30 İç Kapı No:4 / Altınova Yalova Türkiye',
            'district' => 'Altınova',
            'city' => 'Yalova',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://tersanshipyard.com/tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 226 465 62 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@tersan.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'info@tersanshipyard.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'MANİSA/SOMA GÜNEŞ ENERJİ SANTRALİ (3,9 MW, 5 MWp, 75,29 ha, 8.492 adet panel ve 13 invertör)',
                'type' => 'GES',
                'power' => '3.9MW',
                'excel_row' => 222,
            ],
        ],
    ],
    [
        'name' => 'Tesla Internationel Enerji Anonim Şirketi',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'MUTLUKENT MAH. TUNCA SK. NO:14 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 344 231 91 11',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 736 94 99',
            ],
            [
                'type' => 'email',
                'value' => 'zeynepneslihanyilmaz@hotmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Sincer DRES HATAY / BELEN',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '30,00 MWm / 30,00 MWe 30,00 MWh / 30,00 MWe',
                'regulatory_note' => 'çed olumlu Tarihi 19.11.2025',
                'excel_row' => 223,
            ],
        ],
    ],
    [
        'name' => 'TG ENERJİ İNŞAAT SAN. VE TİC. LTD. ŞTİ.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Çayyolu Mah. 2681. Sokak No:12 Irmakkent Sitesi Çankaya/Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://tg-enerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 380 72 72',
            ],
            [
                'type' => 'email',
                'value' => 'info@tg-enerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Yavuz Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 542 719 89 06',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-31',
                'channel' => 'email',
                'subject' => null,
                'text' => '(31.08.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'NİĞDE/MERKEZ',
                'type' => 'GES EDT',
                'power' => '100MW/ 100 MWH',
                'regulatory_note' => 'çed olumlu Tarihi 19.11.2025',
                'excel_row' => 224,
            ],
        ],
    ],
    [
        'name' => 'TOKGÖZ GRUP',
        'city' => 'Kayseri',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Cırgalan Mah. Engir Gölü Küme Evler No:255 Kocasinan/Kayseri tokgozbeton@hs01.kep.tr',
            'district' => 'Kocasinan',
            'city' => 'Kayseri',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'tokgozgrup.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 352 330 00 25',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 282 48 81',
            ],
            [
                'type' => 'email',
                'value' => 'm.uludag@tokgozgrup.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-21',
                'channel' => 'email',
                'subject' => null,
                'text' => 'telefon,tanıtım maili ve köşe noktalara sorulacak. (21.08.2026 farklı şahıs numarası)',
            ],
        ],
        'projects' => [
            [
                'name' => 'EMET GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '9 MW',
                'excel_row' => 225,
            ],
        ],
    ],
    [
        'name' => 'Torpal Enerji Anonim Şirketi',
        'city' => 'Ankara',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Mustafa Kemal Mahallesi 2118. Cadde Maidan İş ve Yaşam Merkezi No:4 D Blok Kat:2 No:2 Çankaya / Ankara',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://ulusoyenerji.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 577 50 41',
            ],
            [
                'type' => 'email',
                'value' => 'info@ulusoyenerji.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 538 845 17 36',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'Ulusoy Enerji Firması iştiraki',
            ],
        ],
        'projects' => [
            [
                'name' => 'Karaman-4 GES KARAMAN / MERKEZ',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '15,00 MWm / 15,00 MWe 15,00 MWh / 15,00 MWe',
                'excel_row' => 226,
            ],
        ],
    ],
    [
        'name' => 'TRAKTEK ENERJİ TEDARİK DEPOLAMA TİCARET ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'LEVENT MAH. BÜYÜKDERE CAD. NO: 168/1, BEŞİKTAŞ, İSTANBUL',
            'district' => 'Beşiktaş',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 212 276 01 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@traktekenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Osman Hulusi Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 472 76 30',
                    ],
                ],
            ],
            [
                'name' => 'Muhammet Mesut Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'İbrahim Erden',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-01',
                'channel' => 'email',
                'subject' => null,
                'text' => 'ÇED OLUMLU. (01.09.2026 mail atıldı.)',
            ],
            [
                'on' => '2026-09-01',
                'channel' => 'email',
                'subject' => null,
                'text' => '(01.09.2026 mail atıldı.)',
            ],
            [
                'on' => '2026-09-01',
                'channel' => 'email',
                'subject' => null,
                'text' => '(01.09.2026 mail atıldı.)(çed raporunda info@renecore.com mail adresi var)',
            ],
            [
                'on' => '2026-09-01',
                'channel' => 'email',
                'subject' => null,
                'text' => 'Mucur GES Enerji Üretim Anonim Şirketi (01.09.2026 mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'EDİRNE/ ENEZ ENEZ ELEKTRİK DEPOLAMA TESİSİ (50 MWe / 50 MWh) RÜZGÂR ENERJİ SANTRALİ (RES) (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '50 MW ( 16 Türbin)',
                'excel_row' => 227,
            ],
            [
                'name' => 'UŞAK/MERKEZ BOZKÖY ELEKTRİK DEPOLAMA TESİSİ (50 MWe / 50 MWh) GÜNEŞ ENERJİ SANTRALİ (50 MWm / 50 MWe) (74,96 ha)',
                'type' => 'GES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 228,
            ],
            [
                'name' => 'KÜTAHYA/SİMAV SİMAV ELEKTRİK DEPOLAMA TESİSİ (50 MWe/50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'type' => 'RES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 229,
            ],
            [
                'name' => 'MANİSA/DEMİRDCİ SİMAV ELEKTRİK DEPOLAMA TESİSİ (50 MWe/50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'type' => 'RES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 230,
            ],
            [
                'name' => 'BİLECİK/ BOZÜYÜK BOZÜYÜK ELEKTRİK DEPOLAMA TESİSİ (50 MWe/50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'type' => 'RES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 231,
            ],
            [
                'name' => 'KÜTAHYA/DOMANİÇ BOZÜYÜK ELEKTRİK DEPOLAMA TESİSİ (50 MWe/50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'type' => 'RES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 232,
            ],
            [
                'name' => 'KIRKLARELİ/MERKEZ KUZULU ELEKTRİK DEPOLAMA TESİSİ (50 MWe / 50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'status' => 'LİSANS',
                'type' => 'RES EDT',
                'power' => '50 MWe 53 MWm /50 Mwe 50 MWh',
                'regulatory_note' => 'çed olumlu tarihi 03.06.2025',
                'excel_row' => 233,
            ],
            [
                'name' => 'ERZİNCAN/KEMALİYE KEMALİYE ELEKTRİK DEPOLAMA TESİSİ (50 MWe / 50 MWh) RÜZGÂR ENERJİ SANTRALİ (16 ADET TÜRBİN: 50 MWm / 50 MWe)',
                'type' => 'RES EDT',
                'power' => '50 MW 50 MWh',
                'excel_row' => 234,
            ],
            [
                'name' => 'Mucur EDT GES KIRŞEHİR / MUCUR',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '50 MWe',
                'excel_row' => 235,
            ],
        ],
    ],
    [
        'name' => 'TÜMAD Madencilik San. ve Tic. A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Barbaros Mahallesi, Buğday Sokak No: 9, Kavaklıdere, 06680 Çankaya / Ankara / TURKIYE',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.tumad.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 455 16 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@tumad.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Lisanssız Rüzgar Enerji Santrali Kapasite Artışı (İlave 4,26 MWm/4,26 MWe kapasiteden 2 adet türbin -Toplam 8,52 MWm/8,52 MWe)',
                'type' => 'RES',
                'power' => '4,6 MW',
                'regulatory_note' => 'çed olumlu tarihi 26.07.2026',
                'excel_row' => 236,
            ],
        ],
    ],
    [
        'name' => 'TÜRK TELEKOM',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'TEKLİF DEPARTMANINDA BİLGİLER',
            ],
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => '5.ayda ihale yapılmış. Daha kazanan firma açıklanmamış. Alan firma Cevahir Yapı ve Huawei birlikte almış.',
            ],
        ],
        'projects' => [
            [
                'name' => 'MALATYA DARENDE GES',
                'status' => 'ÇM',
                'type' => 'GES',
                'power' => '400 MW',
                'excel_row' => 237,
            ],
        ],
    ],
    [
        'name' => 'Türkiye Petrolleri Anonim Ortaklığı Genel Müdürlüğü',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'authority',
        'address' => [
            'line1' => 'Söğütözü Mahallesi Nizami Gencevi Caddesi No:10 06510 Çankaya/ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 207 20 00',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 532 308 17 08',
            ],
            [
                'type' => 'email',
                'value' => 'sozer@tpao.gov.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Erkan Öndaş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 533 476 48 80',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-06-15',
                'channel' => 'other',
                'subject' => null,
                'text' => '(15.06.2026 zuhal hanım, çağrı mektubu bekliyorlar görüşme talep edildi.)',
            ],
        ],
        'projects' => [
            [
                'power' => '5,5 mw',
                'excel_row' => 238,
            ],
        ],
    ],
    [
        'name' => 'ULUSOY ENERJİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => null,
        'channels' => [],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-18',
                'channel' => 'other',
                'subject' => null,
                'text' => 'yusuf bey ile sıcak takip ediliyor. Teklif verilen iş için hedef fiyat verecekler. Görüşmeyi bekliyoruz.(18/08) haftaya ilgili kişi buraya geliyor görüşülecek toplantı yapılacak.yusuf bey (20/08)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ULUSOY BURDUR GES',
                'type' => 'GES',
                'excel_row' => 239,
            ],
        ],
    ],
    [
        'name' => 'ULUSOY UN SAN. VE TİC. A.Ş.',
        'city' => 'Samsun',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Denizevleri Mah. Alaçam Cad. Ulusoy Un San. ve Tic. A.Ş. Blok No:42 İç Kapı No:1 55200 Atakum/Samsun',
            'district' => 'Alaçam',
            'city' => 'Samsun',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.ulusoyun.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '444 5 534',
            ],
            [
                'type' => 'phone',
                'value' => '+90 362 266 90 90',
            ],
            [
                'type' => 'email',
                'value' => 'info@ulusoyun.com.tr',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 541 209 81 09',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-29',
                'channel' => 'email',
                'subject' => null,
                'text' => '(29.08.2026 tarihinde 31.08.2026 tarihi için mail atıldı.)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ULUSOY UN BOYABAT GÜNEŞ ENERJİSİ SANTRALİ SİNOP/BOYABAT',
                'type' => 'GES',
                'power' => '1.515 MW',
                'excel_row' => 240,
            ],
        ],
    ],
    [
        'name' => 'UNIT International SA',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'Nispetiye Caddesi Akmerkez E3 Blok Kat:13 34337 Etiler - Istanbul / TURKIYE',
            'district' => null,
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.unit.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 319 19 00',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Ender Ateş',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 549 798 33 18',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-19',
                'channel' => 'other',
                'subject' => null,
                'text' => 'yusuf bey gizlilik sözleşmesini gönderdi. Görüşme talebi onayı gelir ise bu hafta online toplantı yapılacak.19/08 katılımı sağlanmadı daha sonraki projelerle ilgilenilecek.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Solar PV',
                'type' => 'GES',
                'power' => '1200 MW',
                'excel_row' => 241,
            ],
        ],
    ],
    [
        'name' => 'ÜRGÜP GES ENERJİ ÜRETİM ANONİM ŞİRKETİ',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Kültür Mah. Nisbetiye Cad. Akmerkez No:56 İç Kapı No:6 Beşiktaş / İSTANBUL',
            'district' => 'Beşiktaş',
            'city' => 'İstanbul',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Osman Hulusi Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'phone',
                        'value' => '+90 216 472 76 30',
                    ],
                ],
            ],
            [
                'name' => 'Muhammet Mesut Toprak',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
            [
                'name' => 'İbrahim Erden',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => 'Kartvizit notu',
                'text' => 'traktek ile aynı adres(yeni taşınmışlar',
            ],
        ],
        'projects' => [
            [
                'name' => 'ÜRGÜP EDT GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '50,00 MWm / 50,00 MWe 100,00 MWh / 100,00 MWe',
                'excel_row' => 242,
            ],
        ],
    ],
    [
        'name' => 'VAKKO TEKSTİL ve HAZIR GİYİM SANAYİ İŞLETMLERİ A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 1,
        'role' => 'employer',
        'address' => [
            'line1' => 'ALTUNİZADE MH. KUŞBAKIŞI CD. NO: 35 ÜSKÜDAR / İSTANBUL ÜSKÜDAR / İSTANBUL / TURKIYE',
            'district' => 'Üsküdar',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.vakko.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 554 07 00',
            ],
            [
                'type' => 'email',
                'value' => 'yatirimci@vakko.com.tr',
            ],
            [
                'type' => 'email',
                'value' => 'iletisim@vakko.com.tr',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Gökhan Gündüz',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 555 980 80 89',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'gokhan.gunduz@vakko.com.tr',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-17',
                'channel' => 'email',
                'subject' => null,
                'text' => '(17.08.2026 ve 20.08.2026 tarihlerinde mail iletildi.) TEKLİF İSTEĞİ GELDİ. 26/08 AKŞAM SON TARİH. Teklif gönderildi.',
            ],
        ],
        'projects' => [
            [
                'name' => 'UŞAK ULUBEY GES',
                'type' => 'GES',
                'excel_row' => 243,
            ],
        ],
    ],
    [
        'name' => 'VEGA RÜZGAR ENERJİSİ ELEKTRİK ÜRETİM A.Ş.',
        'city' => 'İzmir',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'BARBAROS MAH. ÇİĞDEM SK. AĞAOĞLU MY OFFİCE NO:1 İÇ KAPI NO:16 ATAŞEHİR / İSTANBUL',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 232 712 71 03',
            ],
            [
                'type' => 'email',
                'value' => 'info@vegaenerji.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 232 483 43 48',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 505 641 50 43',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'other',
                'subject' => null,
                'text' => 'itirazlar ve hukuki süreç var. Son durumu sor takip et listeye ona göre ekle',
            ],
        ],
        'projects' => [
            [
                'name' => 'İZMİR/ÇEŞME',
                'type' => 'GES',
                'power' => '9.9897 MW',
                'excel_row' => 244,
            ],
        ],
    ],
    [
        'name' => 'Vega Solar Enerji Sistemleri Ticaret Anonim Şirketi',
        'city' => 'İstanbul',
        'network' => 'Telefon ve mail çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'İnkılap Mah. Dr. Adnan Büyükdeniz Cad. Kelif Plaza 3. Blok No: 2 İç Kapı No: 1 Ümraniye / İSTANBUL',
            'district' => 'Ümraniye',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'mobile',
                'value' => '+90 539 422 64 66',
            ],
            [
                'type' => 'email',
                'value' => 'yusuf.akbas@360enerji.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'Akbağ EDT GES MARDİN / ARTUKLU',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,50 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 245,
            ],
        ],
    ],
    [
        'name' => 'Vizyoneks Bilgi Teknolojileri A.Ş.',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Küçükbakkalköy Mahallesi Vedat Günyol Caddesi Sarı Lale Sokak No:3 Ataşehir/İstanbul',
            'district' => 'Ataşehir',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.vizyoneks.com.tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 216 577 84 00',
            ],
            [
                'type' => 'email',
                'value' => 'info@vizyoneks.com.tr',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-08-27',
                'channel' => 'email',
                'subject' => null,
                'text' => '27.08.2026 mail atıldı.',
            ],
        ],
        'projects' => [
            [
                'name' => 'Tekirdağ Hibrit GES',
                'type' => 'GES',
                'power' => '15 MW',
                'excel_row' => 246,
            ],
        ],
    ],
    [
        'name' => 'WINDSUN ENERJİ ÜRETİMİ İLETİM DAĞITIMI TOPTAN VE PERAKENDE SATIŞ İNŞAAT MADEN TARIM SANAYİ HAYVANCILIK PİYASA İŞLETİM İTHALAT VE İHRACAT LİMİTED ŞİRKETİ',
        'city' => 'Elazığ',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'CUMHURİYET MAH. 155 SK. NO:24/1 MERKEZ / ELAZIĞ',
            'district' => 'Merkez',
            'city' => 'Elazığ',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Selahattin Yıldız',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 703 27 00',
                    ],
                ],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ELAZIĞ/AĞIN WINDSUN AĞIN GES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '12,37 MWm / 10,00 MWe 10,00 MWh / 10,00 MWe',
                'excel_row' => 247,
            ],
        ],
    ],
    [
        'name' => 'YAVUZ Yapı İnş. San. ve Tic. A.Ş.',
        'city' => 'Ankara',
        'network' => 'Telefon çed raporundan alındı',
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Beytepe Mah. 5397 Sk. A1 / 2 Çankaya ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 468 28 44',
            ],
            [
                'type' => 'phone',
                'value' => '+90 312 426 41 40',
            ],
            [
                'type' => 'email',
                'value' => 'info@yavuzyapias.com',
            ],
            [
                'type' => 'mobile',
                'value' => '+90 554 395 00 77',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'ANKARA/MAMAK',
                'type' => 'GES EDT',
                'power' => '26MWm/ 20MWh',
                'excel_row' => 248,
            ],
        ],
    ],
    [
        'name' => 'YAVUZSAN OTOMOTİV SANAYİ VE TİCARET LİMİTED ŞİRKETİ',
        'city' => 'Konya',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => '3. Organize Sanayi Böl. Kuddusi Cad. No:16 Selçuklu/Konya',
            'district' => 'Selçuklu',
            'city' => 'Konya',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'https://www.yavuzsan.com/tr',
            ],
            [
                'type' => 'phone',
                'value' => '+90 332 239 22 00',
            ],
            [
                'type' => 'email',
                'value' => 'bilgi@yavuzsan.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 332 239 22 07',
            ],
            [
                'type' => 'email',
                'value' => 'cm.mekes@hotmail.com',
            ],
        ],
        'contacts' => [],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KONYA/SELÇUKLU Güneş Enerjisi Santrali Kapasite Artışı (Kurulu Gücü: 6,319 Mwm 4,014 Mwe, 5,63 Ha)',
                'type' => 'GES',
                'power' => '6,319 MWm / 4.014 MW',
                'excel_row' => 249,
            ],
        ],
    ],
    [
        'name' => 'YBT Enerji Elektronik İnşaat San. Ve Tic. A.Ş.',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'Söğütözü Mah. 2177 Cad. No: 10/B - 108 ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [
            [
                'type' => 'phone',
                'value' => '+90 312 220 07 09',
            ],
            [
                'type' => 'email',
                'value' => 'info@ybtenerji.com',
            ],
        ],
        'contacts' => [
            [
                'name' => 'Teoman Bey',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [
                    [
                        'type' => 'mobile',
                        'value' => '+90 532 583 12 83',
                    ],
                    [
                        'type' => 'phone',
                        'value' => '+90 224 225 63 16',
                    ],
                    [
                        'type' => 'email',
                        'value' => 'munsaluzun@obelyamuhendislik.com',
                    ],
                ],
            ],
        ],
        'notes' => [
            [
                'on' => '2026-08-31',
                'channel' => 'email',
                'subject' => null,
                'text' => '(31.08.2026 mail atıldı.)',
            ],
            [
                'on' => '2026-08-31',
                'channel' => 'email',
                'subject' => null,
                'text' => '(31.08.2026 mail atıldı)',
            ],
        ],
        'projects' => [
            [
                'name' => 'ANKARA/ GÖLBAŞI EMİRLER DEPOLAMALI GÜNEŞ ENERJİ SANTRALİ (34 MWm/34 MWp/21 MWe/21 MWh/18,9 ha)',
                'type' => 'GES EDT',
                'power' => '34MW 21MWh',
                'excel_row' => 250,
            ],
            [
                'name' => 'ANKARA/POLATLI KOCAHACILI DEPOLAMALI GÜNEŞ ENERJİ SANTRALİ(33 MWm/33 MWp/20 MWe/20 MWh-30 ha)',
                'type' => 'GES EDT',
                'power' => '33MW 20MWh',
                'excel_row' => 251,
            ],
            [
                'name' => 'MUŞ/KORKUT KÜMBET DEPOLAMALI GÜNEŞ ENERJİ SANTRALİ(44 MWm/44 MWp/27 MWe/27 MWh/40 ha)',
                'type' => 'GES EDT',
                'power' => '44MW 27MWh',
                'excel_row' => 252,
            ],
            [
                'name' => 'ÇANKIRI ÇERKES G3 ÇANKIRI 2-3 GÜNEŞ ENERJİ SANTRALİ (17 MWm-17 MWp-10 MWe-15,2 Ha)',
                'type' => 'GES',
                'power' => '17MW',
                'excel_row' => 253,
            ],
            [
                'name' => 'ESKİŞEHİR ÇİFTELER G3 ESKİŞEHİR 1-2 GÜNEŞ ENERJİ SANTRALİ (25,5 MWm/25,5 MWp/15 MWe-22,5 ha)',
                'type' => 'GES',
                'power' => '25,5MW',
                'excel_row' => 254,
            ],
            [
                'name' => 'KIRKLARELİ/LÜLEBURGAZ SARICAALİ DEPOLAMALI GÜNEŞ ENERJİ SANTRALİ (33 MWm/33 MWp/20 MWe/20 MWh/30,1 ha)',
                'type' => 'GES EDT',
                'power' => '33MW 20MWh',
                'excel_row' => 255,
            ],
        ],
    ],
    [
        'name' => 'YILDIRIM HOLDİNG',
        'city' => 'İstanbul',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'YILDIRIM Tower, Maslak Mahallesi, Tasyoncasi Sok. No:1C B2 Blok, 34485 Sariyer / Istanbul',
            'district' => 'Sarıyer',
            'city' => 'İstanbul',
        ],
        'channels' => [
            [
                'type' => 'website',
                'value' => 'www.yildirimgroup.com',
            ],
            [
                'type' => 'phone',
                'value' => '+90 212 290 30 80',
            ],
            [
                'type' => 'email',
                'value' => 'info@yildirimgroup.com',
            ],
        ],
        'contacts' => [],
        'notes' => [
            [
                'on' => '2026-09-14',
                'channel' => 'visit',
                'subject' => null,
                'text' => '(Mustafa Güneş ile ziyaret. Hibrit yapılacak mustafa beyden numara alınacak)',
            ],
        ],
        'projects' => [],
    ],
    [
        'name' => 'ZEY YENİLENEBİLİR ELEKTRİK ÜRETİM VE DEPOLAMA ANONİM ŞİRKETİ',
        'city' => 'Ankara',
        'network' => null,
        'priority' => 3,
        'role' => 'employer',
        'address' => [
            'line1' => 'KIZILIRMAK MAH. DUMLUPINAR BLV. NEXTLEVEL 82/3 A ÇANKAYA / ANKARA',
            'district' => 'Çankaya',
            'city' => 'Ankara',
        ],
        'channels' => [],
        'contacts' => [
            [
                'name' => 'Ali Gümüşsoy',
                'role' => 'decision_maker',
                'network' => null,
                'channels' => [],
            ],
        ],
        'notes' => [],
        'projects' => [
            [
                'name' => 'KIRIKKALE/KESKİN AG MARS-3 DGES',
                'status' => 'LİSANS',
                'type' => 'GES EDT',
                'power' => '24,83 MWm / 20,00 MWe 20,00 MWh / 20,00 MWe',
                'excel_row' => 257,
            ],
        ],
    ],
];
