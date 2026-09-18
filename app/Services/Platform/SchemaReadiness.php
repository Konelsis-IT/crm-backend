<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Bir migration grubunun (docs/planning/16 §4) bu ortamda uygulanip
 * uygulanmadigini soyler. Ekranlar buna bakarak acilir.
 *
 * D-92 (16 Eylul 2026, kullanici karari): eskiden bu bilgi
 * `KONELSIS_APPLIED_SCHEMA_BATCH` ortam degiskenindeki virgullu listeden
 * okunuyordu ve her migration'dan sonra `.env` elle guncelleniyordu. Artik
 * dogrudan VERITABANI SEMASINA bakilir: her grubun bir "imzasi" vardir
 * (o grupla gelen bir tablo ya da kolon); imza varsa grup uygulanmistir.
 * Boylece migration calistirildigi anda ekranlar kendiliginden acilir,
 * `.env` duzenlenmez.
 *
 * Hangi grubun ne getirdigi docs/planning/16'da tutulur; buradaki liste o
 * belgenin calisan karsiligidir. Yeni grup eklerken buraya bir imza satiri
 * yazmak yeterlidir.
 *
 * Sonuc istek boyunca bellekte tutulur; veritabanina erisilemiyorsa
 * (kurulum oncesi, konsol komutlari) guvenli taraf secilir ve "uygulanmadi"
 * denir.
 */
final class SchemaReadiness
{
    /**
     * Grup kodu => imza. "tablo" ya da "tablo.kolon" bicimindedir.
     *
     * @var array<string, string>
     */
    private const SIGNATURES = [
        'B00' => 'personnel',                          // Cerceve: personel, oturum
        'B02' => 'competencies',                       // Yetkinlik
        'B03' => 'org_units',                          // Organizasyon ve pozisyon
        'B03A' => 'positions.role_id',                 // Pozisyon - rol bagi
        'B05' => 'roles',                              // RBAC
        'B06' => 'documents',                          // Dokuman yonetimi
        'B06A' => 'document_shares',                   // Dokuman yazma ve paylasim
        'B07' => 'approval_requests',                  // Onay motoru
        'B08' => 'personnel_activities',               // Personel hareketleri
        'B10A' => 'reports',                           // Raporlar
        'B11A' => 'announcements',                     // Duyuru ve is uyarilari
        'B11B' => 'work_requests',                     // Talepler
        'B11D' => 'work_requests.requires_approval',   // Onaya tabi talep
        'B12A' => 'conversations',                     // Kurum ici sohbet
        'B13' => 'certifications',                     // IK uzantilari
        'B13A' => 'certifications.valid_until',        // Sertifika gecerlilik tarihi
        'B16' => 'parties',                            // Taraf ve is alim
        'B17' => 'projects',                           // Proje orkestrasyonu
        'B17A' => 'project_supply_items',              // Proje calisma alani
        'B25' => 'personnel_assignments',              // Organizasyon/gorev gecmisi
        'B26' => 'personnel_titles',                   // Personel unvanlari
        'B27' => 'communication_points.contact_relationship_id', // Kisi + iletisim birlesimi
        'B28' => 'party_meeting_notes',                // Taraf network / ziyaret onceligi / gorusme notlari
        'B29' => 'business_case_scopes',               // Is dosyasi kapsamlari / teklif tipi / teklif belgeleri
        'B30' => 'business_case_scopes.hes_unit_cost', // HES kapsaminin kendi tutar alani
        'B31' => 'social_responsible_positions',       // Sosyal medya (grubun en son olusan tablosu)
    ];

    /** @var array<string, bool> */
    private static array $cache = [];

    /** @var list<string>|null */
    private static ?array $tables = null;

    public static function hasBatch(string $batch): bool
    {
        $code = strtoupper(trim($batch));

        return self::$cache[$code] ??= self::probe($code);
    }

    /**
     * Bu ortamda uygulanmis gruplar (tanilama ve raporlama icin).
     *
     * @return list<string>
     */
    public static function appliedBatches(): array
    {
        return array_values(array_filter(
            array_keys(self::SIGNATURES),
            static fn (string $code): bool => self::hasBatch($code),
        ));
    }

    /** Migration/seed sonrasi bellekteki sonucu tazeler. */
    public static function flush(): void
    {
        self::$cache = [];
        self::$tables = null;
    }

    private static function probe(string $code): bool
    {
        $signature = self::SIGNATURES[$code] ?? null;

        if ($signature === null) {
            return false;
        }

        if (! str_contains($signature, '.')) {
            return self::hasTable($signature);
        }

        [$table, $column] = explode('.', $signature, 2);

        if (! self::hasTable($table)) {
            return false;
        }

        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    private static function hasTable(string $table): bool
    {
        if (self::$tables === null) {
            try {
                self::$tables = array_map(
                    static fn (array $row): string => (string) ($row['name'] ?? ''),
                    Schema::getTables(),
                );
            } catch (Throwable) {
                self::$tables = [];

                return false;
            }
        }

        return in_array($table, self::$tables, true);
    }
}
