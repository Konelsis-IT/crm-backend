<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Reference\ClassificationCode;
use App\Enums\Reference\LegalEntityKind;
use App\Enums\Reference\LegalEntityStatus;
use App\Enums\Reference\RetentionDisposition;
use App\Enums\Reference\RetentionTriggerKind;
use App\Enums\Reference\UomDimension;
use App\Enums\Shared\ActiveStatus;
use App\Models\Reference\BusinessCalendar;
use App\Models\Reference\BusinessCalendarWeekday;
use App\Models\Reference\Country;
use App\Models\Reference\Currency;
use App\Models\Reference\LegalEntity;
use App\Models\Reference\Organization;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Models\Reference\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Verified public reference data (ISO codes, units, classifications) plus
 * the single organization and its main legal entity. Retention policies are
 * seeded inactive until legal/finance confirm the periods (decision D-38).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCountries();
        $this->seedCurrencies();
        $this->seedUnitsOfMeasure();
        $this->seedSecurityClassifications();
        $this->seedRetentionPolicies();
        $this->seedOrganization();
    }

    private function seedCountries(): void
    {
        $countries = [
            ['TR', 'TUR', 'Türkiye', 'Türkiye', 'Europe/Istanbul'],
            ['DE', 'DEU', 'Almanya', 'Germany', 'Europe/Berlin'],
            ['GB', 'GBR', 'Birleşik Krallık', 'United Kingdom', 'Europe/London'],
            ['US', 'USA', 'Amerika Birleşik Devletleri', 'United States', null],
            ['FR', 'FRA', 'Fransa', 'France', 'Europe/Paris'],
            ['IT', 'ITA', 'İtalya', 'Italy', 'Europe/Rome'],
            ['ES', 'ESP', 'İspanya', 'Spain', null],
            ['NL', 'NLD', 'Hollanda', 'Netherlands', 'Europe/Amsterdam'],
            ['BE', 'BEL', 'Belçika', 'Belgium', 'Europe/Brussels'],
            ['CH', 'CHE', 'İsviçre', 'Switzerland', 'Europe/Zurich'],
            ['AT', 'AUT', 'Avusturya', 'Austria', 'Europe/Vienna'],
            ['PL', 'POL', 'Polonya', 'Poland', 'Europe/Warsaw'],
            ['RO', 'ROU', 'Romanya', 'Romania', 'Europe/Bucharest'],
            ['BG', 'BGR', 'Bulgaristan', 'Bulgaria', 'Europe/Sofia'],
            ['GR', 'GRC', 'Yunanistan', 'Greece', 'Europe/Athens'],
            ['SE', 'SWE', 'İsveç', 'Sweden', 'Europe/Stockholm'],
            ['NO', 'NOR', 'Norveç', 'Norway', 'Europe/Oslo'],
            ['DK', 'DNK', 'Danimarka', 'Denmark', 'Europe/Copenhagen'],
            ['FI', 'FIN', 'Finlandiya', 'Finland', 'Europe/Helsinki'],
            ['UA', 'UKR', 'Ukrayna', 'Ukraine', 'Europe/Kyiv'],
            ['RU', 'RUS', 'Rusya', 'Russia', null],
            ['GE', 'GEO', 'Gürcistan', 'Georgia', 'Asia/Tbilisi'],
            ['AZ', 'AZE', 'Azerbaycan', 'Azerbaijan', 'Asia/Baku'],
            ['KZ', 'KAZ', 'Kazakistan', 'Kazakhstan', null],
            ['UZ', 'UZB', 'Özbekistan', 'Uzbekistan', 'Asia/Tashkent'],
            ['KG', 'KGZ', 'Kırgızistan', 'Kyrgyzstan', 'Asia/Bishkek'],
            ['TM', 'TKM', 'Türkmenistan', 'Turkmenistan', 'Asia/Ashgabat'],
            ['TJ', 'TJK', 'Tacikistan', 'Tajikistan', 'Asia/Dushanbe'],
            ['IQ', 'IRQ', 'Irak', 'Iraq', 'Asia/Baghdad'],
            ['IR', 'IRN', 'İran', 'Iran', 'Asia/Tehran'],
            ['SA', 'SAU', 'Suudi Arabistan', 'Saudi Arabia', 'Asia/Riyadh'],
            ['AE', 'ARE', 'Birleşik Arap Emirlikleri', 'United Arab Emirates', 'Asia/Dubai'],
            ['QA', 'QAT', 'Katar', 'Qatar', 'Asia/Qatar'],
            ['KW', 'KWT', 'Kuveyt', 'Kuwait', 'Asia/Kuwait'],
            ['OM', 'OMN', 'Umman', 'Oman', 'Asia/Muscat'],
            ['BH', 'BHR', 'Bahreyn', 'Bahrain', 'Asia/Bahrain'],
            ['JO', 'JOR', 'Ürdün', 'Jordan', 'Asia/Amman'],
            ['EG', 'EGY', 'Mısır', 'Egypt', 'Africa/Cairo'],
            ['LY', 'LBY', 'Libya', 'Libya', 'Africa/Tripoli'],
            ['TN', 'TUN', 'Tunus', 'Tunisia', 'Africa/Tunis'],
            ['DZ', 'DZA', 'Cezayir', 'Algeria', 'Africa/Algiers'],
            ['MA', 'MAR', 'Fas', 'Morocco', 'Africa/Casablanca'],
            ['NG', 'NGA', 'Nijerya', 'Nigeria', 'Africa/Lagos'],
            ['GH', 'GHA', 'Gana', 'Ghana', 'Africa/Accra'],
            ['KE', 'KEN', 'Kenya', 'Kenya', 'Africa/Nairobi'],
            ['ET', 'ETH', 'Etiyopya', 'Ethiopia', 'Africa/Addis_Ababa'],
            ['TZ', 'TZA', 'Tanzanya', 'Tanzania', 'Africa/Dar_es_Salaam'],
            ['SN', 'SEN', 'Senegal', 'Senegal', 'Africa/Dakar'],
            ['ZA', 'ZAF', 'Güney Afrika', 'South Africa', 'Africa/Johannesburg'],
            ['PK', 'PAK', 'Pakistan', 'Pakistan', 'Asia/Karachi'],
            ['IN', 'IND', 'Hindistan', 'India', 'Asia/Kolkata'],
            ['BD', 'BGD', 'Bangladeş', 'Bangladesh', 'Asia/Dhaka'],
            ['ID', 'IDN', 'Endonezya', 'Indonesia', null],
            ['MY', 'MYS', 'Malezya', 'Malaysia', 'Asia/Kuala_Lumpur'],
            ['JP', 'JPN', 'Japonya', 'Japan', 'Asia/Tokyo'],
            ['KR', 'KOR', 'Güney Kore', 'South Korea', 'Asia/Seoul'],
            ['CN', 'CHN', 'Çin', 'China', 'Asia/Shanghai'],
            ['AU', 'AUS', 'Avustralya', 'Australia', null],
            ['CA', 'CAN', 'Kanada', 'Canada', null],
            ['BR', 'BRA', 'Brezilya', 'Brazil', null],
            ['MX', 'MEX', 'Meksika', 'Mexico', null],
        ];

        foreach ($countries as [$code, $iso3, $nameTr, $nameEn, $timezone]) {
            Country::query()->updateOrCreate(
                ['code' => $code],
                ['iso3_code' => $iso3, 'name_tr' => $nameTr, 'name_en' => $nameEn, 'default_timezone' => $timezone, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['TRY', 'Türk Lirası', 'Turkish Lira', 2],
            ['USD', 'ABD Doları', 'US Dollar', 2],
            ['EUR', 'Euro', 'Euro', 2],
            ['GBP', 'İngiliz Sterlini', 'Pound Sterling', 2],
            ['CHF', 'İsviçre Frangı', 'Swiss Franc', 2],
            ['SAR', 'Suudi Riyali', 'Saudi Riyal', 2],
            ['AED', 'BAE Dirhemi', 'UAE Dirham', 2],
            ['QAR', 'Katar Riyali', 'Qatari Riyal', 2],
            ['KWD', 'Kuveyt Dinarı', 'Kuwaiti Dinar', 3],
            ['OMR', 'Umman Riyali', 'Omani Rial', 3],
            ['BHD', 'Bahreyn Dinarı', 'Bahraini Dinar', 3],
            ['JOD', 'Ürdün Dinarı', 'Jordanian Dinar', 3],
            ['IQD', 'Irak Dinarı', 'Iraqi Dinar', 3],
            ['EGP', 'Mısır Lirası', 'Egyptian Pound', 2],
            ['LYD', 'Libya Dinarı', 'Libyan Dinar', 3],
            ['TND', 'Tunus Dinarı', 'Tunisian Dinar', 3],
            ['DZD', 'Cezayir Dinarı', 'Algerian Dinar', 2],
            ['MAD', 'Fas Dirhemi', 'Moroccan Dirham', 2],
            ['NGN', 'Nijerya Nairası', 'Nigerian Naira', 2],
            ['ZAR', 'Güney Afrika Randı', 'South African Rand', 2],
            ['KZT', 'Kazak Tengesi', 'Kazakhstani Tenge', 2],
            ['UZS', 'Özbek Somu', 'Uzbekistani Som', 2],
            ['AZN', 'Azerbaycan Manatı', 'Azerbaijani Manat', 2],
            ['GEL', 'Gürcistan Larisi', 'Georgian Lari', 2],
            ['RUB', 'Rus Rublesi', 'Russian Ruble', 2],
            ['UAH', 'Ukrayna Grivnası', 'Ukrainian Hryvnia', 2],
            ['PLN', 'Polonya Zlotisi', 'Polish Zloty', 2],
            ['RON', 'Rumen Leyi', 'Romanian Leu', 2],
            ['BGN', 'Bulgar Levası', 'Bulgarian Lev', 2],
            ['SEK', 'İsveç Kronu', 'Swedish Krona', 2],
            ['NOK', 'Norveç Kronu', 'Norwegian Krone', 2],
            ['DKK', 'Danimarka Kronu', 'Danish Krone', 2],
            ['JPY', 'Japon Yeni', 'Japanese Yen', 0],
            ['KRW', 'Güney Kore Wonu', 'South Korean Won', 0],
            ['CNY', 'Çin Yuanı', 'Chinese Yuan', 2],
            ['INR', 'Hindistan Rupisi', 'Indian Rupee', 2],
            ['PKR', 'Pakistan Rupisi', 'Pakistani Rupee', 2],
            ['MYR', 'Malezya Ringgiti', 'Malaysian Ringgit', 2],
            ['IDR', 'Endonezya Rupiahı', 'Indonesian Rupiah', 2],
            ['CAD', 'Kanada Doları', 'Canadian Dollar', 2],
            ['AUD', 'Avustralya Doları', 'Australian Dollar', 2],
            ['BRL', 'Brezilya Reali', 'Brazilian Real', 2],
            ['MXN', 'Meksika Pesosu', 'Mexican Peso', 2],
        ];

        foreach ($currencies as [$code, $nameTr, $nameEn, $decimals]) {
            Currency::query()->updateOrCreate(
                ['code' => $code],
                ['name_tr' => $nameTr, 'name_en' => $nameEn, 'decimal_places' => $decimals, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedUnitsOfMeasure(): void
    {
        // [dimension, code, symbol, name_tr, name_en, base code (null = base), factor to base]
        $units = [
            [UomDimension::Count, 'pcs', 'ad', 'Adet', 'Piece', null, null],
            [UomDimension::Count, 'set', 'set', 'Takım', 'Set', null, null],
            [UomDimension::Count, 'lot', 'lot', 'Parti', 'Lot', null, null],
            [UomDimension::Length, 'm', 'm', 'Metre', 'Metre', null, null],
            [UomDimension::Length, 'mm', 'mm', 'Milimetre', 'Millimetre', 'm', '0.001'],
            [UomDimension::Length, 'cm', 'cm', 'Santimetre', 'Centimetre', 'm', '0.01'],
            [UomDimension::Length, 'km', 'km', 'Kilometre', 'Kilometre', 'm', '1000'],
            [UomDimension::Area, 'm2', 'm²', 'Metrekare', 'Square metre', null, null],
            [UomDimension::Area, 'ha', 'ha', 'Hektar', 'Hectare', 'm2', '10000'],
            [UomDimension::Volume, 'm3', 'm³', 'Metreküp', 'Cubic metre', null, null],
            [UomDimension::Volume, 'l', 'L', 'Litre', 'Litre', 'm3', '0.001'],
            [UomDimension::Mass, 'kg', 'kg', 'Kilogram', 'Kilogram', null, null],
            [UomDimension::Mass, 'g', 'g', 'Gram', 'Gram', 'kg', '0.001'],
            [UomDimension::Mass, 't', 't', 'Ton', 'Tonne', 'kg', '1000'],
            [UomDimension::Time, 'h', 'sa', 'Saat', 'Hour', null, null],
            [UomDimension::Time, 'min', 'dk', 'Dakika', 'Minute', 'h', '0.016666666667'],
            [UomDimension::Time, 'day', 'gün', 'Gün', 'Day', 'h', '24'],
            [UomDimension::Energy, 'kWh', 'kWh', 'Kilovat-saat', 'Kilowatt-hour', null, null],
            [UomDimension::Energy, 'MWh', 'MWh', 'Megavat-saat', 'Megawatt-hour', 'kWh', '1000'],
            [UomDimension::Energy, 'GWh', 'GWh', 'Gigavat-saat', 'Gigawatt-hour', 'kWh', '1000000'],
            [UomDimension::Power, 'kW', 'kW', 'Kilovat', 'Kilowatt', null, null],
            [UomDimension::Power, 'W', 'W', 'Vat', 'Watt', 'kW', '0.001'],
            [UomDimension::Power, 'MW', 'MW', 'Megavat', 'Megawatt', 'kW', '1000'],
            [UomDimension::Power, 'kWp', 'kWp', 'Kilovat-pik', 'Kilowatt-peak', null, null],
            [UomDimension::Power, 'MWp', 'MWp', 'Megavat-pik', 'Megawatt-peak', 'kWp', '1000'],
            [UomDimension::Voltage, 'V', 'V', 'Volt', 'Volt', null, null],
            [UomDimension::Voltage, 'kV', 'kV', 'Kilovolt', 'Kilovolt', 'V', '1000'],
            [UomDimension::Current, 'A', 'A', 'Amper', 'Ampere', null, null],
            [UomDimension::Current, 'kA', 'kA', 'Kiloamper', 'Kiloampere', 'A', '1000'],
            [UomDimension::Temperature, 'C', '°C', 'Santigrat derece', 'Degree Celsius', null, null],
        ];

        $ids = [];

        foreach ($units as [$dimension, $code, $symbol, $nameTr, $nameEn, $baseCode, $factor]) {
            $unit = UnitOfMeasure::query()->updateOrCreate(
                ['dimension' => $dimension, 'code' => $code],
                [
                    'symbol' => $symbol,
                    'name_tr' => $nameTr,
                    'name_en' => $nameEn,
                    'base_unit_id' => $baseCode === null ? null : ($ids[$dimension->value.'|'.$baseCode] ?? null),
                    'to_base_factor' => $factor,
                    'status' => ActiveStatus::Active,
                ],
            );

            $ids[$dimension->value.'|'.$code] = $unit->id;
        }
    }

    private function seedSecurityClassifications(): void
    {
        $rows = [
            [ClassificationCode::Public, 0, 'Kamuya açık', 'Public', true],
            [ClassificationCode::Internal, 1, 'Şirket içi', 'Internal', true],
            [ClassificationCode::Confidential, 2, 'Gizli', 'Confidential', true],
            [ClassificationCode::Restricted, 3, 'Kısıtlı', 'Restricted', false],
        ];

        foreach ($rows as [$code, $rank, $nameTr, $nameEn, $externalAllowed]) {
            SecurityClassification::query()->updateOrCreate(
                ['code' => $code],
                ['rank' => $rank, 'name_tr' => $nameTr, 'name_en' => $nameEn, 'external_analysis_allowed' => $externalAllowed, 'status' => ActiveStatus::Active],
            );
        }
    }

    private function seedRetentionPolicies(): void
    {
        // docs/planning/15 §4 — inactive until decision D-38 confirms the legal periods.
        $policies = [
            ['RET-REF', 'Referans verisi', 'Reference data', null, RetentionTriggerKind::Manual, RetentionDisposition::Review],
            ['RET-AUDIT', 'Audit ve olay kayıtları', 'Audit and event records', 3650, RetentionTriggerKind::CreatedAt, RetentionDisposition::ColdArchive],
            ['RET-HR-CORE', 'Personel çekirdek kayıtları', 'Personnel core records', 3650, RetentionTriggerKind::Separation, RetentionDisposition::Anonymize],
            ['RET-HR-PRIVATE', 'Personel özel profili', 'Personnel private profile', 3650, RetentionTriggerKind::Separation, RetentionDisposition::Purge],
            ['RET-CANDIDATE', 'Aday verisi', 'Candidate data', 180, RetentionTriggerKind::ClosedAt, RetentionDisposition::Purge],
            ['RET-REPORT', 'Rapor gönderimleri', 'Report submissions', 1825, RetentionTriggerKind::ArchivedAt, RetentionDisposition::ColdArchive],
            ['RET-NOTIF', 'Bildirimler', 'Notifications', 365, RetentionTriggerKind::ClosedAt, RetentionDisposition::Purge],
            ['RET-TASK', 'Görevler', 'Tasks', 1095, RetentionTriggerKind::ClosedAt, RetentionDisposition::ColdArchive],
            ['RET-DOC-CONTROLLED', 'Kontrollü dokümanlar', 'Controlled documents', 3650, RetentionTriggerKind::ProjectClose, RetentionDisposition::ColdArchive],
            ['RET-FILE-QUARANTINE', 'Karantinadaki dosyalar', 'Quarantined files', 30, RetentionTriggerKind::CreatedAt, RetentionDisposition::Purge],
            ['RET-CHAT', 'Konuşmalar', 'Conversations', 1095, RetentionTriggerKind::ArchivedAt, RetentionDisposition::Anonymize],
            ['RET-EMAIL', 'Gelen e-postalar', 'Inbound e-mail', 365, RetentionTriggerKind::CreatedAt, RetentionDisposition::Purge],
            ['RET-SOCIAL-RAW', 'Sosyal platform ham payload', 'Social raw payloads', 30, RetentionTriggerKind::CreatedAt, RetentionDisposition::Purge],
            ['RET-INTEGRATION', 'Entegrasyon teslim kayıtları', 'Integration delivery records', 365, RetentionTriggerKind::CreatedAt, RetentionDisposition::Purge],
            ['RET-SESSION', 'Oturum ve framework bildirimleri', 'Sessions and framework notifications', 90, RetentionTriggerKind::CreatedAt, RetentionDisposition::Purge],
        ];

        foreach ($policies as [$code, $nameTr, $nameEn, $days, $trigger, $disposition]) {
            RetentionPolicy::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name_tr' => $nameTr,
                    'name_en' => $nameEn,
                    'retention_days' => $days,
                    'trigger_kind' => $trigger,
                    'disposition' => $disposition,
                    'legal_basis' => 'Hukuk/finans doğrulaması bekliyor (D-38).',
                    'status' => ActiveStatus::Inactive,
                ],
            );
        }
    }

    private function seedOrganization(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['code' => (string) config('konelsis.organization.code', 'KONELSIS')],
            [
                'name_tr' => (string) config('konelsis.organization.name_tr', 'Konelsis'),
                'name_en' => (string) config('konelsis.organization.name_en', 'Konelsis'),
                'default_locale' => (string) config('konelsis.organization.default_locale', 'tr'),
                'default_timezone' => (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul'),
                'default_currency_code' => (string) config('konelsis.organization.default_currency', 'TRY'),
                'status' => ActiveStatus::Active,
            ],
        );

        $legalEntity = LegalEntity::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => (string) config('konelsis.legal_entity.code', 'KONELSIS_MAIN')],
            [
                'legal_name' => (string) config('konelsis.legal_entity.legal_name'),
                'short_name' => (string) config('konelsis.legal_entity.short_name', 'Konelsis'),
                'country_code' => (string) config('konelsis.legal_entity.country', 'TR'),
                'currency_code' => (string) config('konelsis.legal_entity.currency', 'TRY'),
                'timezone' => (string) config('konelsis.legal_entity.timezone', 'Europe/Istanbul'),
                'entity_kind' => LegalEntityKind::Parent,
                'status' => LegalEntityStatus::Active,
                'valid_from' => now()->toDateString(),
            ],
        );

        $calendar = BusinessCalendar::query()->firstOrCreate(
            ['code' => 'TR_OFFICE'],
            [
                'name_tr' => 'Türkiye ofis takvimi',
                'name_en' => 'Türkiye office calendar',
                'legal_entity_id' => $legalEntity->id,
                'country_code' => 'TR',
                'timezone' => 'Europe/Istanbul',
                'is_default' => true,
                'status' => ActiveStatus::Active,
            ],
        );

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            BusinessCalendarWeekday::query()->firstOrCreate(
                ['business_calendar_id' => $calendar->id, 'iso_weekday' => $weekday],
                ['work_start' => '09:00:00', 'work_end' => '18:00:00'],
            );
        }
    }
}
