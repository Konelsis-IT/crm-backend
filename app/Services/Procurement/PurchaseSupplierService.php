<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Party\PartyKind;
use App\Enums\Party\PartyStatus;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\RecordNotFoundException;
use App\Models\Party\Party;
use App\Models\Procurement\PurchaseSupplier;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Party\PartyService;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satin alma tedarikci listesi (D-232, B58). Firma bir taraf kaydidir
 * (PartyService yazar); bu servis uyeligi, hizmet alanlarini, notu ve arsivi
 * yazar.
 *
 * - Yeni tedarikci yalniz tedarikcidir (`purchase_only`): Taraflar'da gorunmez
 *   (kullanici 21 Ekim 2026: "oraya eklemeye ... çalışma").
 * - "Ayni firma" (kullanici: "2 alanda da aynı firmanın kaydı olacak. Firma tek
 *   olacak"): tedarikci kaydi Taraflar'daki firmayla birlestirilir
 *   (PartyMergeService; kisiler, adresler, kanallar ve satin alma kayitlari
 *   tasinir), uyelik o firmada kalir ve firma iki listede de gorunur.
 * - "Farkli firma": cift bir daha onerilmez.
 */
final class PurchaseSupplierService extends AbstractService
{
    protected string $model = PurchaseSupplier::class;

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly PartyService $parties,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $party  PartyService alanlari (organization_profile, communication_points...)
     * @param  array{note?: ?string, service_area_ids?: list<int|string>}  $supplier
     */
    public function createSupplier(array $party, array $supplier): Party
    {
        return $this->transactions->run(function () use ($party, $supplier): Party {
            /** @var Party $record */
            $record = $this->parties->create([
                ...$party,
                'party_kind' => PartyKind::Organization->value,
                'status' => PartyStatus::Active->value,
            ]);

            /** @var PurchaseSupplier $membership */
            $membership = parent::create([
                'party_id' => $record->getKey(),
                'purchase_only' => true,
                'note' => self::note($supplier['note'] ?? null),
            ]);

            $this->syncAreas($membership, $supplier['service_area_ids'] ?? []);

            return $record;
        });
    }

    /**
     * @param  array<string, mixed>  $party
     * @param  array{note?: ?string, service_area_ids?: list<int|string>}  $supplier
     */
    public function updateSupplier(Party $record, array $party, array $supplier): Party
    {
        return $this->transactions->run(function () use ($record, $party, $supplier): Party {
            /** @var Party $updated */
            $updated = $this->parties->update($record, $party);
            $membership = $this->membership($updated);

            if (array_key_exists('note', $supplier)) {
                $membership->forceFill(['note' => self::note($supplier['note'])]);
                $this->saveWithoutVersion($membership);
            }

            if (array_key_exists('service_area_ids', $supplier)) {
                $this->syncAreas($membership, (array) $supplier['service_area_ids']);
            }

            return $updated;
        });
    }

    public function archive(PurchaseSupplier $supplier): PurchaseSupplier
    {
        return $this->transactions->run(function () use ($supplier): PurchaseSupplier {
            /** @var PurchaseSupplier $locked */
            $locked = $this->lockForUpdate($supplier);

            if ($locked->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $locked->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($locked);
            $this->recordActivity($locked, 'archived');

            return $locked;
        });
    }

    public function restore(PurchaseSupplier $supplier): PurchaseSupplier
    {
        return $this->transactions->run(function () use ($supplier): PurchaseSupplier {
            /** @var PurchaseSupplier $locked */
            $locked = $this->lockForUpdate($supplier);

            if (! $locked->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $locked->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($locked);
            $this->recordActivity($locked, 'restored');

            return $locked;
        });
    }

    /**
     * Taraflar'daki bir firmayi (aktarim) tedarikci listesine ekler: yalniz uyelik,
     * hizmet alanlari ve not; firmanin kendi kayitlari degismez, alan kaldirilmaz.
     */
    public function attachExisting(Party $party, array $supplier): PurchaseSupplier
    {
        return $this->transactions->run(function () use ($party, $supplier): PurchaseSupplier {
            $membership = PurchaseSupplier::query()->where('party_id', $party->getKey())->first();

            if (! $membership instanceof PurchaseSupplier) {
                /** @var PurchaseSupplier $membership */
                $membership = parent::create([
                    'party_id' => $party->getKey(),
                    'purchase_only' => false,
                    'note' => self::note($supplier['note'] ?? null),
                ]);
            }

            $membership->serviceAreas()->syncWithoutDetaching(array_values(array_filter(array_map('intval', (array) ($supplier['service_area_ids'] ?? [])))));

            return $membership;
        });
    }

    /** @param  list<int|string>  $areaIds */
    private function syncAreas(PurchaseSupplier $membership, array $areaIds): void
    {
        $membership->serviceAreas()->sync(array_values(array_unique(array_filter(array_map('intval', $areaIds)))));
    }

    private function membership(Party $party): PurchaseSupplier
    {
        $membership = PurchaseSupplier::query()->where('party_id', $party->getKey())->first();

        if (! $membership instanceof PurchaseSupplier) {
            throw RecordNotFoundException::make();
        }

        return $membership;
    }

    private static function note(mixed $note): ?string
    {
        $text = trim((string) $note);

        return $text === '' ? null : mb_substr($text, 0, 4000);
    }

    /** Model::delete gerekmez; uyelik baglantisi yalniz "ayni firma" birlesmesinde kalkar. */
    public function delete(Model|int|string $record): bool
    {
        throw InvalidTransitionException::make();
    }
}
