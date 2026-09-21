<?php

declare(strict_types=1);

/**
 * Pazar haritasi (Firma_Harita_Takip.xlsx, Genel_Harita sayfasi; son degisiklik
 * 11 Agustos 2026; 21 Eylul 2026 kullanici talimatiyla aktarildi, D-107).
 * MarketMapSeeder bu diziyi okur.
 *
 * Uretici: haritanin her sutunu bir kategori; firma satirlari ada gore
 * birlestirildi (ayni firma birden fazla kategoride ise tek kayit, birden fazla
 * faaliyet satiri). Alt basliklar (Avrupa / Cin / Yerli / Ithal / Yabanci)
 * koken, kategori faaliyet satiri (proje tipi + faaliyet alani + alt faaliyet
 * alani) ve taraf tipi olur. Kisi hucreleri "Ad: telefon" olarak ayristirildi;
 * "aranmayacak" gorusme notuna, "samimi" / referans notlari kisinin network
 * alanina yazildi. 'match' dolu ise firma sistemde o adla zaten vardir.
 * 'excel' yalniz izlenebilirlik icindir.
 *
 * Bu dosya elle duzenlenebilir; yeniden uretilirse elle yapilan duzeltmeler
 * kaybolur.
 */

return [
    'source' => 'Firma_Harita_Takip.xlsx / Genel_Harita',
    'noted_on' => '2026-08-11',
    'tender_sources' => [
        [
            'code' => 'EKAP',
            'name_tr' => 'EKAP (Kamu İhale Kurumu)',
            'name_en' => 'EKAP (Public Procurement Authority)',
            'source_type' => 'public_procurement',
            'base_url' => null,
            'excel' => 'AF3',
        ],
        [
            'code' => 'PROJE_HABER',
            'name_tr' => 'Proje Haber',
            'name_en' => 'Proje Haber (news)',
            'source_type' => 'other',
            'base_url' => null,
            'excel' => 'AF4',
        ],
        [
            'code' => 'ISDB',
            'name_tr' => 'İslam Kalkınma Bankası',
            'name_en' => 'Islamic Development Bank',
            'source_type' => 'international_finance',
            'base_url' => 'https://www.isdb.org',
            'excel' => 'AF5',
        ],
        [
            'code' => 'EBRD',
            'name_tr' => 'EBRD (Avrupa İmar ve Kalkınma Bankası)',
            'name_en' => 'EBRD (European Bank for Reconstruction and Development)',
            'source_type' => 'international_finance',
            'base_url' => 'https://www.ebrd.com',
            'excel' => 'AF6',
        ],
        [
            'code' => 'YATIRIMLAR_DERGISI',
            'name_tr' => 'Yatırımlar Dergisi',
            'name_en' => 'Yatirimlar Dergisi (magazine)',
            'source_type' => 'other',
            'base_url' => null,
            'excel' => 'AF7',
        ],
        [
            'code' => 'YEM_DER',
            'name_tr' => 'YEM DER',
            'name_en' => 'YEM DER',
            'source_type' => 'other',
            'base_url' => null,
            'excel' => 'AF8',
        ],
        [
            'code' => 'ANBA_HABER',
            'name_tr' => 'ANBA Haber',
            'name_en' => 'ANBA Haber (news)',
            'source_type' => 'other',
            'base_url' => null,
            'excel' => 'AF9',
        ],
        [
            'code' => 'ECED',
            'name_tr' => 'e-ÇED',
            'name_en' => 'e-EIA',
            'source_type' => 'other',
            'base_url' => null,
            'excel' => 'AF10',
        ],
    ],
    'parties' => [
        [
            'name' => 'GE',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A4 (HES türbin / jeneratör)',
                'V7 (RES türbin)',
            ],
        ],
        [
            'name' => 'GLOBAL HYDRO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [
                [
                    'subject' => 'Pazar haritası notu',
                    'text' => 'Excel notu: "suat abiden alınacak"',
                    'next_action' => 'Suat abiden alınacak',
                    'contact' => null,
                ],
            ],
            'excel' => [
                'A5 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'GUGLER',
            'match' => 'GUGLER SU TRÜBÜNLERİ',
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kutan Demirci',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 536 259 36 19',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'A6 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'WASSER CRAFT',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A7 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'KOCHENDOERFERR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A8 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'LITOSTROJ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A9 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'POWERMACHINE',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A10 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'VOITH HYDRO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
                [
                    'project_type' => null,
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_AUTOMATION',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Muzaffer Çelebi',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 643 81 64',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'A11 (HES türbin / jeneratör)',
                'T10 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'ANDRITZ Hydro Grup',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'europe',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
                [
                    'project_type' => null,
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_AUTOMATION',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A12 (HES türbin / jeneratör)',
                'A29 (HES türbin / jeneratör)',
                'T11 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'HARBİN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'china',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A14 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'HYDRO TU',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'china',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A15 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'ORIENT HUADONG',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'china',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A16 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'MARBEYAZ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Elif Toraman',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 531 847 14 81',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'A18 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'HİDRO POWER',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Murat Bal',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 498 10 10',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'A19 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'TEMSAN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A20 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'ÖZGÜR MOTOR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Zeki Şeker',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 664 14 32',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'A22 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'TURAN MOTOR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A23 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'EMS MOTOR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A24 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'LEROY SOMER',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A26 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'MARELLİ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A27 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'TDBS',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_GENERATOR',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'A28 (HES türbin / jeneratör)',
            ],
        ],
        [
            'name' => 'AKARSU PROJE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'C3 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'ARK HES',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Tayfun Akutlu',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 491 30 48',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C4 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'ATAK MÜH.',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Emre Balcı',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 556 78 09',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C5 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'CE YAPI',
            'match' => null,
            'roles' => [
                'consultant',
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Serkan Korkmaz',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 226 78 80',
                        ],
                    ],
                ],
            ],
            'notes' => [
                [
                    'subject' => 'Aranmayacak',
                    'text' => 'Serkan Korkmaz aranmayacak. Excel notu: "aranmayacak arkadaşı"',
                    'next_action' => null,
                    'contact' => 'Serkan Korkmaz',
                ],
            ],
            'excel' => [
                'C6 (HES proje firmaları)',
                'AK27 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'DOLSAR',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Burhan Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 722 45 32',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C7 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'ERGES',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Sinan Gülsoy',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 341 37 84',
                        ],
                    ],
                ],
            ],
            'notes' => [
                [
                    'subject' => 'Aranmayacak',
                    'text' => 'Sinan Gülsoy aranmayacak. Excel notu: "aranmayacak mustafa bey"',
                    'next_action' => null,
                    'contact' => 'Sinan Gülsoy',
                ],
            ],
            'excel' => [
                'C8 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'HİDRO YAPI',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Melih Kondakçı',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 426 90 38',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C9 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'HİDRO DİZAYN',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Denizhan Bütün',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 683 14 31',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C10 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'PETEK PROJE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Tuğtekin Aykut',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 353 97 97',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C11 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'PROENCO',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Önder Akçakoyun',
                    'network' => 'mustafa beyin selamı',
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 226 78 72',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C12 (HES proje firmaları)',
                'C22 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'SİBA',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ömer Alimoğlu',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 624 09 14',
                        ],
                    ],
                ],
            ],
            'notes' => [
                [
                    'subject' => 'Aranmayacak',
                    'text' => 'Ömer Alimoğlu aranmayacak. Excel notu: "arama hüseyin bey"',
                    'next_action' => null,
                    'contact' => 'Ömer Alimoğlu',
                ],
            ],
            'excel' => [
                'C13 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'SU YAPI',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Sezai Ersoy',
                    'network' => null,
                    'channels' => [],
                ],
            ],
            'notes' => [
                [
                    'subject' => 'Pazar haritası notu',
                    'text' => 'Sezai Ersoy: ofis aranacak.',
                    'next_action' => 'Ofis aranacak',
                    'contact' => 'Sezai Ersoy',
                ],
            ],
            'excel' => [
                'C14 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'TEMEL-SU',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ahmet Bozkurt',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 562 51 82',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C15 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'YOL-SU',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Altan Okay',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 717 30 93',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C16 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'MTB ENERJİ',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Muammer Ayberk',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 277 49 54',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C17 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'MGB',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'C18 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'ARTI UÇ MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'inactive',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [
                [
                    'subject' => 'Pazar haritası notu',
                    'text' => 'Excel notu: "kapandı" (firma kapanmış).',
                    'next_action' => null,
                    'contact' => null,
                ],
            ],
            'excel' => [
                'C19 (HES proje firmaları)',
            ],
        ],
        [
            'name' => 'ARB ENERJİ',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'hes',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ali Rıza Beşer',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 211 50 49',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'C20 (HES proje firmaları)',
                'Y5 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'HESİAD',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'C24 (HES proje firmaları)',
                'AH16 (Dernekler)',
            ],
        ],
        [
            'name' => 'HOPEWIND',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ahmet Yıldırım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 554 120 16 67',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E3 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'GROWATT',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'İdris',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 534 899 24 14',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E4 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'CHINT',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Mustafa Tekdaş',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '0530 244 20 208',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E5 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'HUAWEİ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ERC OZAN',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 918 47 08',
                        ],
                    ],
                ],
                [
                    'name' => 'AHMET ABİ ALFA HUAWEI',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 604 02 47',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E6 (GES inverter / panel)',
                'L3 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'TBEA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E7 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SUNGROW',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Candaş Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 555 226 25 56',
                        ],
                    ],
                ],
                [
                    'name' => 'Emrah Sunspectra',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 554 937 26 07',
                        ],
                    ],
                ],
                [
                    'name' => 'Selen İnsight',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 507 497 29 83',
                        ],
                    ],
                ],
                [
                    'name' => 'Emrah Bey Sunspectra',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 554 937 26 07',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E8 (GES inverter / panel)',
                'L7 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'KSTAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kvk Enerji Berk',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 501 347 33 19',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E9 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'GOODWE',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'İdris',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 476 53 69',
                        ],
                    ],
                ],
                [
                    'name' => 'Engel Tastan',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 545 346 58 50',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E10 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SOLINVED',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Pelin Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 545 644 42 51',
                        ],
                    ],
                ],
                [
                    'name' => 'Anıl Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 552 243 06 77',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E11 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SIGENERGY',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_INVERTER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Selina İnci',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 507 137 04 55',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E12 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'KIVANÇ ENERJI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Burak Sarıtaş',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 531 887 17 40',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E14 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'DAXLER',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Emre Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 797 69 10',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E15 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SCHMID-PEKİNTAŞ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kamil Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 543 835 45 39',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E16 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'ALFA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ahmet Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 604 02 47',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E17 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'KALYON',
            'match' => null,
            'roles' => [
                'supplier',
                'employer',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ahmet Tengiz',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 534 457 20 02',
                        ],
                    ],
                ],
                [
                    'name' => 'HALİS TURK',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 554 456 03 73',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E18 (GES inverter / panel)',
                'AK8 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ANKARA SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Hakan Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 539 844 04 84',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E19 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'ZES SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E20 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'GTC GÜNEŞ SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E21 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SMART GES',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E22 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'CW ENERJİ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Emine Hanım',
                    'network' => null,
                    'channels' => [],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E23 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'HT-SAAE',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Umut KARLATLI',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 534 665 18 20',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E24 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'PLURAWATT',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E25 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'RHOFA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E26 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'ELIN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kemal Ertugra',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 911 39 49',
                        ],
                    ],
                ],
                [
                    'name' => 'Özgür Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 246 88 07',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E27 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'HSA ENERJİ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ecem Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 531 393 94 12',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E28 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'TALESUN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'domestic',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Pınar Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 543 806 91 19',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E29 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'HANWA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E31 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'FİRST SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E32 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'JINKO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E33 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'JA SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Fırat Muminoğlu',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 546 656 56 11',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'E34 (GES inverter / panel)',
                'E36 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'DMEGC',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => 'foreign',
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_PV_PANEL',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'E35 (GES inverter / panel)',
            ],
        ],
        [
            'name' => 'SOLARİAN',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'J3 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'NOVİTAS ENERJİ',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Hasan Gürsakal',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 765 31 21',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J4 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'PRATİKUS',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Abdurrahman Merzifonluoğlu',
                    'network' => 'mustafa beyin selamı',
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 247 94 87',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J5 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'İSTRİCH',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Cüneyt Çimen',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 505 711 63 87',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J6 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'SUSOL',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Okan Uykan',
                    'network' => 'hüsein bey samimi',
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 506 867 59 39',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J7 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'ADLERA GRUP',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'PAULİNE SEYFERT',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 544 717 89 78',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J8 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'HMD',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Ahmet Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 576 26 54',
                        ],
                    ],
                ],
                [
                    'name' => 'Sedat',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 552 220 24 57',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J11 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'GAP GALVANİZ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Seda Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 537 233 37 19',
                        ],
                        [
                            'type' => 'mobile',
                            'value' => '+90 505 413 27 14',
                        ],
                    ],
                ],
                [
                    'name' => 'Zeki Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 061 55 10',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J12 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'PROOFX',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'J13 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'KIRAÇ METAL',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Yağmur Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 546 904 99 85',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J14 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'ISOTEC',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Erkan Öztürk',
                    'network' => 'hüseyin bey verdi',
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 543 535 12 66',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J15 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'ERL',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Aycan Özer',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 302 74 07',
                        ],
                    ],
                ],
                [
                    'name' => 'Onur',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 505 650 70 55',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J16 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'MCT METAL',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Yunus Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 608 74 70',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J17 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'KARTAL GES',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Buket Urus',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 555 179 78 94',
                        ],
                    ],
                ],
                [
                    'name' => 'Okan',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 730 90 17',
                        ],
                    ],
                ],
                [
                    'name' => 'Adnan Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 414 32 55',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J18 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'SOLARFİX',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Eyüp Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 541 670 06 63',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J19 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'SOLARRED',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kadir',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 454 77 30',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J20 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'MEGA SOLAR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'J21 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'STA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Yiğit',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 545 284 60 52',
                        ],
                    ],
                ],
                [
                    'name' => 'Ferhat',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 538 598 76 94',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J22 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'VERGO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Canan Ergin',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 814 98 19',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J24 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'BAŞARI ENERJİ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'ges',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_MOUNTING',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Selver Hanım',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 537 955 77 77',
                        ],
                    ],
                ],
                [
                    'name' => 'Erdim Erdoğan',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 537 300 68 03',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'J26 (GES proje firmaları ve danışmanlar)',
            ],
        ],
        [
            'name' => 'BYD',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Pelin',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 545 644 42 51',
                        ],
                    ],
                ],
                [
                    'name' => 'Anıl Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 552 243 06 77',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'L4 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'CATL',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L5 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'HYXIPOWER',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L6 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'HITACHI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L8 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'NARADA POWER',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L9 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'HIITIO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L10 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'POWIN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L11 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'EXERGONIX',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L12 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'EVE ENERGY',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L13 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'POMEGA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L14 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'YEO TEKNOLOJI ENERJI VE ENDUSTRI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'SEDEF KARAÇAM',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 544 966 03 32',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'L15 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'INOVAT',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L16 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'BATRON',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L17 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'LG ENERGY SOLUTION',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L18 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'SAMSUNG SDI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'L19 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'CRRC',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'RİCH ZHANG',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'phone',
                            'value' => '+86 731 28493490',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'L20 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'ENVISION',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'bes',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_BATTERY',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'KURES ABLIZ',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 644 76 79',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'L21 (Depolama firmaları)',
            ],
        ],
        [
            'name' => 'ELTAŞ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'İlkay Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 455 78 96',
                        ],
                    ],
                ],
                [
                    'name' => 'Gökhan',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 538 545 07 16',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'T3 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'BEST',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Umut Bey',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 962 76 29',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'T4 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'ASTOR',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Dilara Yurtdışı',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 536 773 16 79',
                        ],
                    ],
                ],
                [
                    'name' => 'Merve',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 536 773 15 73',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'T5 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'EREN TRAFO',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'T6 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'MAKSAN',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Kübra',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 075 35 10',
                        ],
                    ],
                ],
                [
                    'name' => 'Burak',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 531 261 86 40',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'T7 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'SÖNMEZ',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TRANSFORMER',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'T8 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'BIRCI ENERJI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_AUTOMATION',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'T13 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'RELTEK ENERJI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_AUTOMATION',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'T14 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'LOCUS ENERJI',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_AUTOMATION',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'T15 (Trafo / otomasyon)',
            ],
        ],
        [
            'name' => 'ENERCON',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V3 (RES türbin)',
            ],
        ],
        [
            'name' => 'NORDEX',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V4 (RES türbin)',
            ],
        ],
        [
            'name' => 'VESTAS',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V5 (RES türbin)',
            ],
        ],
        [
            'name' => 'GOLDWIND',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'MİNGQİ GUO',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 538 216 68 55',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'V6 (RES türbin)',
            ],
        ],
        [
            'name' => 'SİNOVEL',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V8 (RES türbin)',
            ],
        ],
        [
            'name' => 'NEGMİCON',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V9 (RES türbin)',
            ],
        ],
        [
            'name' => 'SIEMENS GAMESA',
            'match' => null,
            'roles' => [
                'supplier',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'EQUIPMENT',
                    'sub' => 'EQUIPMENT_TURBINE',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'V10 (RES türbin)',
            ],
        ],
        [
            'name' => 'FSE MÜHENDSİLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Salih',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 541 224 48 36',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'Y3 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'AVR MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'Y4 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'GY ENERJİ',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Göknur Atalay',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 513 63 68',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'Y6 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'EN-SU',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'Y7 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'AKENSA',
            'match' => 'Akensa Enerji Mühendislik Danışmanlık Sanayi ve Ticaret Limited Şirketi Çal Enerji Üretim Anonim Şirketi',
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Yaşar Öztürk',
                    'network' => 'samimi',
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 694 97 45',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'Y8 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'CERES',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => 'res',
                    'area' => 'ENGINEERING',
                    'sub' => 'ENGINEERING_DESIGN',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'Y9 (RES proje firmaları)',
            ],
        ],
        [
            'name' => 'İLLER BANKASI',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [
                [
                    'name' => 'Aslı Harmanlık Olgun',
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
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 507 927 94 77',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AA3 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'TEDAS',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA4 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'TEIAS',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA5 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'DSI',
            'match' => 'DEVLET SU İŞLERİ GENEL MÜDÜRLÜĞÜ DSİ',
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA6 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'DSİ BARAJLAR HES',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA7 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'DSİ İÇMESUYU ATIKSU DAİRE BAŞKANLIĞI',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA8 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'ENERJI GENEL MÜDÜRLÜĞÜ',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA9 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'EPDK',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA10 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'EUAS',
            'match' => null,
            'roles' => [
                'authority',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AA11 (Kamu kurumları)',
            ],
        ],
        [
            'name' => 'MÜSİAD',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH3 (Dernekler)',
            ],
        ],
        [
            'name' => 'TUREB',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH4 (Dernekler)',
            ],
        ],
        [
            'name' => 'ASİYAD',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH5 (Dernekler)',
            ],
        ],
        [
            'name' => 'GÜNDER',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH6 (Dernekler)',
            ],
        ],
        [
            'name' => 'GENSED',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH7 (Dernekler)',
            ],
        ],
        [
            'name' => 'DEİK',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH8 (Dernekler)',
            ],
        ],
        [
            'name' => 'TİM',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH9 (Dernekler)',
            ],
        ],
        [
            'name' => 'EKO AVRASYA',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH10 (Dernekler)',
            ],
        ],
        [
            'name' => 'TÇMB',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH11 (Dernekler)',
            ],
        ],
        [
            'name' => 'KOSGEB',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH12 (Dernekler)',
            ],
        ],
        [
            'name' => 'TKDKİ',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH13 (Dernekler)',
            ],
        ],
        [
            'name' => 'TOBB',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH14 (Dernekler)',
            ],
        ],
        [
            'name' => 'OSTİM ENERJİ KÜMELENMESİ',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH15 (Dernekler)',
            ],
        ],
        [
            'name' => 'ENERJİ DEPOLAMA DERNEĞİ',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AH17 (Dernekler)',
            ],
        ],
        [
            'name' => 'TÜSİAV',
            'match' => null,
            'roles' => [
                'association',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [],
            'contacts' => [
                [
                    'name' => 'Volkan Karabulut',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 247 75 88',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AH18 (Dernekler)',
            ],
        ],
        [
            'name' => 'LİMAK',
            'match' => 'LİMAK İNŞAAT',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'CAN DEĞİRMENCİ',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 640 94 50',
                        ],
                    ],
                ],
                [
                    'name' => 'BARIŞ BUDAK',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 690 44 23',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK3 (Büyük firmalar)',
            ],
        ],
        [
            // Haritadaki "CENGIZ" yatirim grubudur: CENGIZ HOLDING (21 Eylul 2026
            // kullanici karari); CENGIZ INSAAT ayri taraftir.
            'name' => 'CENGİZ HOLDİNG',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK4 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'KOLİN',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK5 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'RÖNESANS',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'MEHMET AKMAN',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 505 925 26 49',
                        ],
                    ],
                ],
                [
                    'name' => 'ÖMER TURANCI',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 544 292 29 19',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK6 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ÖZALTIN',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK7 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ALARKO',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK9 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GÜRBAĞ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ATİLLA BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 471 00 05',
                        ],
                    ],
                ],
                [
                    'name' => 'FATİH BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 469 36 27',
                        ],
                    ],
                ],
                [
                    'name' => 'AHMET BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 469 36 03',
                        ],
                    ],
                ],
                [
                    'name' => 'SERDAR',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 469 36 04',
                        ],
                    ],
                ],
                [
                    'name' => 'İLYAS BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 257 66 46',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK10 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'MNG',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK11 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'BAYBURT GRUP',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK12 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'AYDINER',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK13 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GÜLSAN',
            'match' => 'GÜLSAN HOLDİNG',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ALİ FİDANCI',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 553 74 23',
                        ],
                    ],
                ],
                [
                    'name' => 'ÖZGÜR İSTEKLİ',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 537 811 40 23',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK14 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ONUR TAAHHUT',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK15 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'EKSİM ENERJİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'İbrahim Bülbül',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 553 338 48 08',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK16 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'İÇDAS',
            'match' => 'İÇDAŞ ÇELİK ENERJİ TERSANE VE ULAŞIM A.Ş.',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ULVİ GUNDUZ',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 237 70 39',
                        ],
                    ],
                ],
                [
                    'name' => 'TARIK ÖZCAN',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 536 593 80 75',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK17 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GÜRİŞ / GÜRMAT / ULU',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ARDA ŞEKER',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 693 33 17',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK18 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ÇALIK HOLDİNG',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK19 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'M. GÜNEŞ İNŞAAT',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK20 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ANADOLU GRUP',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK21 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'AKFEN',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK22 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'SANKO',
            'match' => 'SANKO HOLDİNG A.Ş.',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Aydın Yalçın',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 531 955 85 49',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK23 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'AĞAOĞLU',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK24 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'SUR YAPI',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK25 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'İREM İNŞAAT',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'HAMZA AĞIRMAN',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 545 947 14 61',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK26 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'MAKİNEL',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK28 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'FERNAS',
            'match' => 'FERNAS ŞİRKETLER GRUBU',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Alper Esatoğlu',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 538 255 82 25',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK29 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'RES ANATOLIA',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'KAĞAN GİLİK',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 618 62 06',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK30 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'DOĞUŞ İNŞAAT',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK31 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'BAŞKANLAR ENERJİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK32 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ENERJİSA',
            'match' => 'Enerjisa Enerji Üretim Anonim Şirketi',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'EMRE ERCAN',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 602 81 04',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK33 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'YILDIZLAR GRUP',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'AHMET BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 310 04 82',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK34 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ESA GRUP ENERJİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK35 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'CGN ENERJİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK36 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GELGİT',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK37 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'AMR ENERJİ ÜRETİM PAZARLAMA TİCARET ANONİM ŞİRKETİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK38 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'BİOTREND',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK39 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'CB ELEKTRİK',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK40 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ZORLU',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK41 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'BORUSAN',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK42 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'DİMER GRUP',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'İHSAN OLCAY ERTUĞRUL',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 533 154 92 35',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK43 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GÖKZİRVE',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK44 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'RT ENERJİ',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK45 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'KARAYEL ELEKTRİK',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK46 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'TÜRKERLER',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK47 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'SANCAK - SE SANTRAL',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK48 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'GALATA WİND',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AK49 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'FİBA ENERJİ',
            'match' => 'FİBA YENİLEBİLİR ENERJİ HOLDİNG A.Ş.',
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'RIZA ERSAMUT',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 549 474 30 99',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK50 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'ASELSAN',
            'match' => null,
            'roles' => [
                'employer',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'INVESTMENT',
                    'sub' => 'INVESTMENT_GROUP',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ALİ BULENT KAPCI',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 280 85 68',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AK51 (Büyük firmalar)',
            ],
        ],
        [
            'name' => 'MERKEZ ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'FIRAT BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 541 231 46 42',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN2 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ARÇEV',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'İstanbul',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN3 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ÇEVMED',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'YAVUZ BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 507 718 18 06',
                        ],
                    ],
                ],
                [
                    'name' => 'BARIŞ BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 555 997 21 04',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN4 (ÇED firmaları)',
                'AN33 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ENVISAN ÇED DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => null,
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN5 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'GESA ÇED',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'İzmir',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN6 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ENVA ÇEVRE DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Şaziment Hanıkm',
                    'network' => null,
                    'channels' => [],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN7 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ANKARA ÇEVRE DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN8 (ÇED firmaları)',
                'AN32 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'PPM KİRLİLİK ÖNLEME',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN9 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'GREENTASK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'IŞIL ÇAKIR',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 530 491 26 81',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN10 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'MOMENT ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'LEYLA HANIM',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 506 599 40 57',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN11 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'MERS ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'ÖMER BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 539 602 00 65',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN12 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ETNA ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'OLCAY BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 554 812 92 75',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN14 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ONLİNE ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'Makbule Hanım',
                    'network' => null,
                    'channels' => [],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN15 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ÇEVRE BOYUT',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN16 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'MERİÇ MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN17 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'AKYA PROJE DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN18 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'AKTİF ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'EKREM BEY',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 270 17 11',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN19 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'PRD ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN20 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ZHM PROJE DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN21 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'MİTTO DANIŞMANLIK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN22 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ANKAÇED',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN23 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ENOVA GRUP ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN24 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'RH MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN25 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'YILDIZLAR MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN26 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ALMER ÇEVRE DENETİM',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN27 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'EMS ÇEVRE MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN28 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'FİLİZİ MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN29 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'YALÇIN PROJE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN30 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ASAM MÜHENDİSLİK',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN31 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'AKTEL ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN34 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'NARTUS ÇEVRE',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN35 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'JURA MADEN',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN36 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'ENÇEV',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [],
            'notes' => [],
            'excel' => [
                'AN37 (ÇED firmaları)',
            ],
        ],
        [
            'name' => 'PROCED',
            'match' => null,
            'roles' => [
                'consultant',
            ],
            'origin' => null,
            'status' => 'prospect',
            'city' => 'Ankara',
            'activities' => [
                [
                    'project_type' => null,
                    'area' => 'ENVIRONMENT',
                    'sub' => 'ENVIRONMENT_EIA',
                ],
            ],
            'contacts' => [
                [
                    'name' => 'VOLKAN KARABULUT',
                    'network' => null,
                    'channels' => [
                        [
                            'type' => 'mobile',
                            'value' => '+90 532 247 75 88',
                        ],
                    ],
                ],
            ],
            'notes' => [],
            'excel' => [
                'AN38 (ÇED firmaları)',
            ],
        ],
    ],
];
