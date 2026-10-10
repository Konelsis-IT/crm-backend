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
 * "MLY Maliyet Listesi" dokuman turu (D-181, 9 Ekim 2026 kullanici talimati:
 * "Kapsam listesinin Excel olarak yuklendigi yerde hemen yaninda dokuman
 * olarak maliyet listesi de yuklenecek").
 *
 * Teklif kapsamindaki Maliyet listesi yuklemesi (B51) yeni belgeyi bu turde
 * acar. Maliyet bilgisi ticari sirdir: varsayilan gizlilik "Gizli" (TKM / TEK
 * gibi). D-165: yalniz tur yoksa eklenir; var olan tur degistirilmez, satir
 * seed arsivine duser ve bir daha islenmez (anahtar DocumentTypeSeeder ile
 * ayni bicimde: document-type:MLY). Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class CostListDocumentType20261009Seeder extends ProtectedSeeder
{
    private const CODE = 'MLY';

    private const NAME = 'Maliyet Listesi';

    public function run(): void
    {
        $confidentialId = (int) SecurityClassification::query()->where('code', ClassificationCode::Confidential)->value('id');
        $retentionId = (int) RetentionPolicy::query()->where('code', 'RET-DOC-CONTROLLED')->value('id');

        if ($confidentialId === 0 || $retentionId === 0) {
            $this->command?->warn('Guvenlik sinifi / saklama politikasi yok; Maliyet Listesi turu sonraki kuruluma kaldi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $service = app(DocumentTypeService::class);
        $created = false;

        $this->row('document-type:'.self::CODE, static function () use ($service, $confidentialId, $retentionId, &$created): Model {
            $existing = DocumentType::query()->where('code', self::CODE)->first();

            if ($existing !== null) {
                return $existing;
            }

            $type = $service->create([
                'code' => self::CODE,
                'name' => self::NAME,
                'discipline' => DocumentDiscipline::Commercial->value,
                'numbering_prefix' => self::CODE,
                'is_controlled' => true,
                'default_classification_id' => $confidentialId,
                'default_retention_policy_id' => $retentionId,
                'status' => 'active',
            ]);
            $created = true;

            return $type;
        });

        $this->command?->info($created ? 'Maliyet Listesi (MLY) dokuman turu eklendi.' : 'Maliyet Listesi (MLY) dokuman turu zaten var.');
    }
}
