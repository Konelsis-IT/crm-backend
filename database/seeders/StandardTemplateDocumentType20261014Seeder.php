<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Document\DocumentDiscipline;
use App\Enums\Reference\ClassificationCode;
use App\Models\Document\DocumentType;
use App\Models\Reference\RetentionPolicy;
use App\Models\Reference\SecurityClassification;
use App\Services\Audit\ActorContext;
use App\Services\Document\DocumentTypeService;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * "SAB Standart Sablon" dokuman turu (D-208, 14 Ekim 2026 kullanici talimati:
 * "Tum departmanlar dashboardda indirebilecegi standart sablonlarina
 * erisebilmelidir. Tek tusla indirebilmeli veya yan sekmede acabilmelidir.").
 *
 * Departmanlarin bos formlari ve sablonlari Dokuman yonetiminde bu turle
 * yuklenir; Sahip birim departmandir, bos birakilan sablon butun departmanlar
 * icindir. Panolarin "Sablonlar" alani bu belgeleri gosterir. Gizlilik "Sirket
 * ici". D-165: yalniz tur yoksa eklenir; var olan tur degistirilmez. Kurulumda
 * DEPLOY_SEEDERS ile calisir.
 */
final class StandardTemplateDocumentType20261014Seeder extends ProtectedSeeder
{
    private const CODE = 'SAB';

    private const NAME = 'Standart Şablon';

    public function run(): void
    {
        $internalId = (int) SecurityClassification::query()->where('code', ClassificationCode::Internal)->value('id');
        $retentionId = (int) RetentionPolicy::query()->where('code', 'RET-DOC-CONTROLLED')->value('id');

        if ($internalId === 0 || $retentionId === 0) {
            $this->command?->warn('Guvenlik sinifi / saklama politikasi yok; Standart Sablon turu sonraki kuruluma kaldi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $service = app(DocumentTypeService::class);
        $created = false;

        $this->row('document-type:'.self::CODE, static function () use ($service, $internalId, $retentionId, &$created): Model {
            $existing = DocumentType::query()->where('code', self::CODE)->first();

            if ($existing !== null) {
                return $existing;
            }

            $type = $service->create([
                'code' => self::CODE,
                'name' => self::NAME,
                'discipline' => DocumentDiscipline::General->value,
                'numbering_prefix' => self::CODE,
                'is_controlled' => true,
                'default_classification_id' => $internalId,
                'default_retention_policy_id' => $retentionId,
                'status' => 'active',
            ]);
            $created = true;

            return $type;
        });

        $this->command?->info($created ? 'Standart Sablon (SAB) dokuman turu eklendi.' : 'Standart Sablon (SAB) dokuman turu zaten var.');
    }
}
