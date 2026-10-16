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
 * "STF Satin Alma Teklifi" dokuman turu (D-192, 12 Ekim 2026 kullanici
 * talimati: satin alma teklifinde "foto, pdf, word gibi dokumanlar" yuklenir
 * ve Konelsis AI ile okunur).
 *
 * Satin alma teklifine yuklenen her belge Dokuman yonetiminde bu turde acilir
 * (PurchaseQuotationService::attachDocuments). Tedarikci fiyatlari ticari
 * sirdir: varsayilan gizlilik "Gizli" (MLY gibi). D-165: yalniz tur yoksa
 * eklenir; var olan tur degistirilmez. Kurulumda DEPLOY_SEEDERS ile calisir.
 */
final class PurchaseQuotationDocumentType20261012Seeder extends ProtectedSeeder
{
    private const CODE = 'STF';

    private const NAME = 'Satın Alma Teklifi';

    public function run(): void
    {
        $confidentialId = (int) SecurityClassification::query()->where('code', ClassificationCode::Confidential)->value('id');
        $retentionId = (int) RetentionPolicy::query()->where('code', 'RET-DOC-CONTROLLED')->value('id');

        if ($confidentialId === 0 || $retentionId === 0) {
            $this->command?->warn('Guvenlik sinifi / saklama politikasi yok; Satin Alma Teklifi turu sonraki kuruluma kaldi.');

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

        $this->command?->info($created ? 'Satin Alma Teklifi (STF) dokuman turu eklendi.' : 'Satin Alma Teklifi (STF) dokuman turu zaten var.');
    }
}
