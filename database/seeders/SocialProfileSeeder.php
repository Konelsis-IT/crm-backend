<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shared\ActiveStatus;
use App\Enums\SocialMedia\SocialProfileKind;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialProfile;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialProfileService;
use Illuminate\Database\Seeder;

/**
 * Sosyal medya hesaplari (B31, D-106): kullanicinin adlandirdigi iki GERCEK
 * hesap. Icerikler bu iki hesaba gore ayrilir:
 *
 *  - KONELSIS       Konelsis Resmi Hesap       (kurumsal)
 *  - HUSEYIN_GUNES  Huseyin Gunes Resmi Hesap  (yonetici; sahibi ilgili personel)
 *
 * Yalniz eksik hesabi ekler; var olan satira DOKUNMAZ (tanitim yazisi ve
 * platform baglantilari modulden duzenlenir, yeniden calistirmak onlari ezmez).
 * Platform baglantisi, rakip hesap, kategori gibi sirket verisi uydurulmaz
 * (D-105); kullanici modulden girer.
 *
 * Onkosul: B31 migration'i uygulanmis olmali; degilse sessizce atlanir.
 * Yonetici hesabinin sahibi RealPersonnelSeeder'dan sonra bulunabildigi icin
 * zincirde RoleMatrixSeeder'dan sonra calisir. Yalniz yetkili DBA/DevOps sureci
 * calistirir.
 */
class SocialProfileSeeder extends Seeder
{
    /**
     * kod, ad, tur, sahip e-postasi, sira.
     *
     * @var list<array{0: string, 1: string, 2: SocialProfileKind, 3: ?string, 4: int}>
     */
    private const PROFILES = [
        [SocialProfile::CODE_KONELSIS, 'Konelsis Resmi Hesap', SocialProfileKind::Corporate, null, 1],
        [SocialProfile::CODE_HUSEYIN_GUNES, 'Hüseyin Güneş Resmi Hesap', SocialProfileKind::Executive, SystemAccountSeeder::MANAGER_EMAIL, 2],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            $this->command?->warn('B31 (sosyal medya) uygulanmamis; sosyal medya hesaplari atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $service = app(SocialProfileService::class);
        $created = 0;

        foreach (self::PROFILES as [$code, $name, $kind, $ownerEmail, $sortOrder]) {
            if (SocialProfile::query()->where('code', $code)->exists()) {
                continue;
            }

            $service->create([
                'code' => $code,
                'name' => $name,
                'kind' => $kind->value,
                'owner_personnel_id' => $this->ownerId($ownerEmail),
                'bio' => null,
                'sort_order' => $sortOrder,
                'status' => ActiveStatus::Active->value,
            ]);
            $created++;
        }

        $this->command?->info(sprintf('%d sosyal medya hesabi eklendi.', $created));
    }

    /** Hesap sahibi personel; e-posta verilmediyse ya da kayit yoksa bos kalir. */
    private function ownerId(?string $email): ?int
    {
        $normalized = Personnel::normalizeEmail($email);

        if ($normalized === null) {
            return null;
        }

        $id = Personnel::query()->where('normalized_email', $normalized)->value('id');

        if ($id === null) {
            $this->command?->warn(sprintf('%s bulunamadi; hesap sahibi bos birakildi.', $email));

            return null;
        }

        return (int) $id;
    }
}
