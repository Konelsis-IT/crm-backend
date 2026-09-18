<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Document\DocumentDiscipline;
use App\Enums\Reference\ClassificationCode;
use App\Models\Document\DocumentType;
use App\Models\Personnel\Personnel;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentTypeService;
use Illuminate\Database\Seeder;

/**
 * Dokuman turleri: referans verisi (16 §4 B24).
 *
 * Onceki surumde bu liste kurgusal dokumanlarla birlikte gelen ornek DMS
 * seeder'inin icindeydi. 16 Eylul 2026 kullanici karariyla ornek veriler
 * zincirden cikarildi, turler gercek referans verisi oldugu icin ayri bir
 * seeder'a alindi (D-90); ornek seeder 18 Eylul 2026'da silindi (D-105).
 */
class DocumentTypeSeeder extends Seeder
{
    /**
     * kod, ad, disiplin, gizli mi.
     *
     * @var list<array{0: string, 1: string, 2: DocumentDiscipline, 3: bool}>
     */
    private const TYPES = [
        ['ELE', 'Elektrik Tek Hat Şeması', DocumentDiscipline::Electrical, false],
        ['OTM', 'SCADA/PLC Dokümantasyonu', DocumentDiscipline::Automation, false],
        ['DVR', 'Devreye Alma Prosedürü', DocumentDiscipline::Engineering, false],
        ['KAL', 'Kalite Kontrol Planı', DocumentDiscipline::Quality, false],
        ['ISG', 'İş Sağlığı ve Güvenliği Talimatı', DocumentDiscipline::Hse, false],
        ['TEK', 'Ticari Teklif Dosyası', DocumentDiscipline::Commercial, true],
        ['SOZ', 'Sözleşme', DocumentDiscipline::Legal, true],
        // B29 (D-101, 16 Eylul 2026): is dosyasi sihirbazinin belgeleri.
        ['KPS', 'Kapsam Listesi', DocumentDiscipline::Commercial, false],
        ['BEK', 'Firma Beklentileri', DocumentDiscipline::Commercial, false],
        ['TKM', 'Teklif Mektubu', DocumentDiscipline::Commercial, true],
        ['REF', 'Referanslar Belgesi', DocumentDiscipline::Commercial, false],
        ['KAT', 'Genel Katalog', DocumentDiscipline::Commercial, false],
    ];

    public function run(): void
    {
        $internalId = (int) SecurityClassification::query()->where('code', ClassificationCode::Internal)->value('id');
        $confidentialId = (int) SecurityClassification::query()->where('code', ClassificationCode::Confidential)->value('id');
        $retentionId = (int) RetentionPolicy::query()->where('code', 'RET-DOC-CONTROLLED')->value('id');

        if ($internalId === 0 || $confidentialId === 0 || $retentionId === 0) {
            $this->command?->warn('Guvenlik sinifi / saklama politikasi yok; dokuman turleri atlandi (once ReferenceDataSeeder).');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $service = app(DocumentTypeService::class);
        $created = 0;

        foreach (self::TYPES as [$code, $name, $discipline, $confidential]) {
            if (DocumentType::query()->where('code', $code)->exists()) {
                continue;
            }

            $service->create([
                'code' => $code,
                'name' => $name,
                'discipline' => $discipline->value,
                'numbering_prefix' => $code,
                'is_controlled' => true,
                'default_classification_id' => $confidential ? $confidentialId : $internalId,
                'default_retention_policy_id' => $retentionId,
                'status' => 'active',
            ]);
            $created++;
        }

        $this->command?->info(sprintf('%d dokuman turu eklendi.', $created));
    }
}
