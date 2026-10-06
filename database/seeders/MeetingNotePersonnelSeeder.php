<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Party\PartyMeetingNote;
use App\Models\Personnel\Personnel;
use App\Services\Party\PartyMeetingNoteService;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
 *
 * D-165: korumali seeder. Yalniz BU seed surecinde (ayni artisan
 * calistirmasinda) acilmis notlara dokunur: ilk kurulumda ya da yeni bir
 * aktarimin yeni notlarinda. Var olan (canlida duran) nota hic dokunulmaz;
 * SeedGuard da buna izin vermez. Her not bir satirdir
 * ('note:<firma normalize adi>|<tarih>|<not metni ozeti>'), islenince arsive
 * duser. Arsivdeki not atlanir.
 */
class MeetingNotePersonnelSeeder extends ProtectedSeeder
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

        // D-165: seed surecinin baslangici; bundan once acilmis not var olan veridir.
        if (! defined('LARAVEL_START')) {
            $this->command?->warn('Seed sureci baslangici bilinmiyor; MeetingNotePersonnelSeeder atlandi.');

            return;
        }

        $since = Carbon::createFromTimestamp((int) floor((float) LARAVEL_START), (string) config('app.timezone'));
        $service = app(PartyMeetingNoteService::class);
        $updated = 0;

        PartyMeetingNote::query()
            ->with('party')
            ->whereNull('personnel_id')
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->chunkById(200, function ($notes) use ($service, $personnelId, &$updated): void {
                foreach ($notes as $note) {
                    $this->row(self::noteKey($note), function () use ($service, $note, $personnelId, &$updated): ?PartyMeetingNote {
                        // Arsivdeki not degistirilemez (RecordArchivedException).
                        if ($note->isArchived()) {
                            return null;
                        }

                        /** @var PartyMeetingNote $saved */
                        $saved = $service->update($note, ['personnel_id' => (int) $personnelId]);
                        $updated++;

                        return $saved;
                    });
                }
            });

        $this->command?->info(sprintf('Gorusme notlari: %d notun gorusen personeli Ersin Ozdemir olarak yazildi.', $updated));
    }

    /** Ortamdan bagimsiz not anahtari: firma adi, not tarihi, metin ozeti (D-165). */
    public static function noteKey(PartyMeetingNote $note): string
    {
        $party = Str::of((string) ($note->party?->normalized_name ?? ''))->lower()->squish()->value();
        $text = Str::of((string) $note->note)->lower()->squish()->value();

        return sprintf('note:%s|%s|%s', $party, (string) $note->noted_on?->toDateString(), sha1($text));
    }
}
