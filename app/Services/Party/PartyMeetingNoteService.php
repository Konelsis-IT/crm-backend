<?php

declare(strict_types=1);

namespace App\Services\Party;

use App\Models\Party\PartyMeetingNote;
use App\Services\AbstractService;
use App\Services\Audit\ActorContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Gorusme notu servisi (B28, D-98).
 *
 * Tarih bos birakilirsa bugunun tarihi (UTC), gorusen personel bos
 * birakilirsa oturumdaki personel yazilir. Geri kalan islemler
 * AbstractService'ten gelir; liste en yeni gorusmeden baslar.
 */
final class PartyMeetingNoteService extends AbstractService
{
    protected string $model = PartyMeetingNote::class;

    /** @var list<string> */
    protected array $with = ['contact', 'personnel'];

    protected string $orderBy = 'noted_on';

    protected string $orderDirection = 'desc';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        if (blank($data['noted_on'] ?? null)) {
            $data['noted_on'] = Carbon::now('UTC')->toDateString();
        }

        if (blank($data['personnel_id'] ?? null)) {
            $data['personnel_id'] = app(ActorContext::class)->personnelId();
        }

        return parent::create($data);
    }
}
