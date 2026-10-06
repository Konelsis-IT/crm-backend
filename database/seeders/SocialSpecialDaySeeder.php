<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Shared\ActiveStatus;
use App\Models\SocialMedia\SocialSpecialDay;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use App\Services\SocialMedia\SocialSpecialDayService;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Database\Eloquent\Model;

/**
 * Sosyal medya ozel gunleri (B31, D-106): YALNIZ tarihi sabit resmi ulusal
 * gunler. Her yil tekrarlar (`year` bos) ve iki hesap icin de gecerlidir
 * (`profile_id` bos).
 *
 * Bu liste herkese acik referans verisidir, uydurulmus sirket verisi degildir
 * (D-105). Tarihi her yil degisen gunler (dini bayramlar, Anneler Gunu vb.),
 * sektor gunleri ve sirkete ozel gunler buraya YAZILMAZ; kullanici modulun
 * Ayarlar bolumunden girer.
 *
 * Ad + ay + gun ile aranir; var olan satira dokunmaz (pasife alinmis ya da
 * notu degistirilmis gun yeniden calistirmada ezilmez). Onkosul: B31
 * migration'i uygulanmis olmali; degilse sessizce atlanir. Yalniz yetkili
 * DBA/DevOps sureci calistirir.
 *
 * D-165: islenen her gun satiri seed arsivine duser ve bir daha islenmez
 * (canlida silinen gun geri gelmez).
 */
class SocialSpecialDaySeeder extends ProtectedSeeder
{
    /**
     * ay, gun, ad.
     *
     * @var list<array{0: int, 1: int, 2: string}>
     */
    private const DAYS = [
        [1, 1, 'Yılbaşı'],
        [4, 23, 'Ulusal Egemenlik ve Çocuk Bayramı'],
        [5, 1, 'Emek ve Dayanışma Günü'],
        [5, 19, 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı'],
        [7, 15, 'Demokrasi ve Millî Birlik Günü'],
        [8, 30, 'Zafer Bayramı'],
        [10, 29, 'Cumhuriyet Bayramı'],
        [11, 10, 'Atatürk\'ü Anma Günü'],
    ];

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B31')) {
            $this->command?->warn('B31 (sosyal medya) uygulanmamis; ozel gunler atlandi.');

            return;
        }

        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $service = app(SocialSpecialDayService::class);
        $created = 0;

        foreach (self::DAYS as [$month, $day, $name]) {
            $this->row(sprintf('special-day:%02d-%02d|%s', $month, $day, $name), static function () use ($service, $month, $day, $name, &$created): Model {
                $existing = SocialSpecialDay::query()
                    ->where('name', $name)
                    ->where('month', $month)
                    ->where('day', $day)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $specialDay = $service->create([
                    'name' => $name,
                    'month' => $month,
                    'day' => $day,
                    'year' => null,
                    'profile_id' => null,
                    'note' => null,
                    'status' => ActiveStatus::Active->value,
                ]);
                $created++;

                return $specialDay;
            });
        }

        $this->command?->info(sprintf('%d ozel gun eklendi.', $created));
    }
}
