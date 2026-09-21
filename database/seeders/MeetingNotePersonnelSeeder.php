<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Database\Seeder;

/**
 * Gorusen personeli bos gorusme notlarina Ersin Ozdemir yazilir (21 Eylul 2026
 * kullanici talimati: "personeli bos olanlara Ersin Ozdemir yaz").
 *
 * Firma takip listesi (RealPartySeeder), pazar haritasi (MarketMapSeeder) ve
 * haftalik ziyaret plani aktarimlarindan gelen notlarin gorusen personeli
 * bilinmiyordu. Guncelleme servis uzerinden gider: hareket kaydi dusulur,
 * B34 uygulanmissa gorusme plani satiri da ayni personele gecer. Yalniz bos
 * olanlar guncellenir; personeli dolu nota dokunulmaz. Personel sonradan
 * kendi notunu duzeltir.
 */
class MeetingNotePersonnelSeeder extends Seeder
{
    public const DEFAULT_PERSONNEL_EMAIL = 'ersin.ozdemir@konelsis.com';

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B28')) {
            $this->command?->warn('Gorusme notu tablosu (B28) yok; MeetingNotePersonnelSeeder atlandi.');

            return;
        }

        $personnelId = Personnel::query()->where('email', self::DEFAULT_PERSONNEL_EMAIL)->value('id');

        if ($personnelId === null) {
            $this->command?->warn(self::DEFAULT_PERSONNEL_EMAIL.' bulunamadi; gorusme notlarinin personeli atanmadi.');

            return;
        }

        $service = app(PartyMeetingNoteService::class);
        $updated = 0;

        PartyMeetingNote::query()
            ->whereNull('personnel_id')
            ->orderBy('id')
            ->chunkById(200, function ($notes) use ($service, $personnelId, &$updated): void {
                foreach ($notes as $note) {
                    $service->update($note, ['personnel_id' => (int) $personnelId]);
                    $updated++;
                }
            });

        $this->command?->info(sprintf('Gorusme notlari: %d notun gorusen personeli Ersin Ozdemir olarak yazildi.', $updated));
    }
}
