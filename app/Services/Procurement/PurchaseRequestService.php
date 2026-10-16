<?php

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Enums\Procurement\PurchaseNeedSource;
use App\Enums\Procurement\PurchaseNeedStatus;
use App\Enums\Procurement\PurchaseRequestStatus;
use App\Enums\Procurement\UsageArea;
use App\Exceptions\InvalidTransitionException;
use App\Exceptions\Procurement\PurchaseRequestIncompleteException;
use App\Exceptions\RecordArchivedException;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseRequest;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Numbering\YearlyCodeAllocator;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kalemli satin alma talebi (D-192, B53; kullanici 12 Ekim 2026: "Birden
 * fazla urun talebi olabilir. O yuzden kalemli bir yapi olmasi lazim").
 *
 * - Baslik: kullanim yeri (Sirket ici / Saha), Saha'da proje, termin, acil,
 *   gerekce (zorunlu), "var olan neden kullanilamadi", not. Kod
 *   SAT-YYYY-NNNN (YearlyCodeAllocator).
 * - Kalemler: urun / hizmet (Malzeme ve Hizmetler'den ya da adiyla),
 *   marka / model, ozellikler, miktar, birim, not. Her kalem bir
 *   `purchase_needs` satiridir (kaynak: Satin alma talebi) ve Satin
 *   alinacaklar'a duser; baslik alanlari kaleme de yazilir.
 * - Duzenlemede formdan kaldirilan kalem silinmez, iptal edilir (islenmemisse).
 * - Talebin durumu kalemlerinden hesaplanir (PurchaseNeedService); talep
 *   iptal edilince islenmemis kalemler de iptal olur.
 */
final class PurchaseRequestService extends AbstractService
{
    protected string $orderBy = 'id';

    protected string $orderDirection = 'desc';

    /** @var list<string> Baslik alanlari. */
    private const HEADER = ['usage_area', 'project_id', 'org_unit_id', 'needed_by', 'is_urgent', 'justification', 'existing_check', 'note'];

    /** @var list<string> Kalem alanlari (formdaki repeater satiri; `uom_id` D-194, PurchaseNeedService esitler). */
    private const LINE = ['catalog_item_id', 'requested_item_name', 'brand_model', 'specification', 'quantity', 'unit', 'uom_id', 'note'];

    /** @var list<string> Baslikten kaleme yazilan alanlar. */
    private const INHERITED = ['usage_area', 'project_id', 'org_unit_id', 'needed_by', 'is_urgent', 'justification', 'existing_check'];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
        private readonly YearlyCodeAllocator $codes,
        private readonly PurchaseNeedService $needs,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data  Baslik alanlari + `lines` (kalem dizileri).
     */
    public function create(array $data): Model
    {
        $lines = self::filledLines($data['lines'] ?? []);

        if ($lines === [] || blank($data['justification'] ?? null)) {
            throw PurchaseRequestIncompleteException::make();
        }

        return $this->transactions->run(function () use ($data, $lines): PurchaseRequest {
            $actor = $this->actorModel();
            $header = array_intersect_key($data, array_flip(self::HEADER));

            /** @var PurchaseRequest $request */
            $request = parent::create([
                ...$header,
                'request_no' => $this->codes->next('SAT')['formatted_code'],
                'requested_by_personnel_id' => $actor?->getKey(),
                'org_unit_id' => filled($header['org_unit_id'] ?? null) ? $header['org_unit_id'] : $actor?->getAttribute('org_unit_id'),
                'status' => PurchaseRequestStatus::Open->value,
            ]);

            foreach ($lines as $index => $line) {
                $this->createLine($request, $line, $index);
            }

            return $request->refresh();
        });
    }

    /**
     * Baslik ve kalemler. `lines` verilmisse: `id`'li kalem guncellenir, yeni
     * kalem eklenir, formdan kaldirilan islenmemis kalem iptal edilir.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return $this->transactions->run(function () use ($record, $data): PurchaseRequest {
            /** @var PurchaseRequest $request */
            $request = $this->lockForUpdate($record);
            $this->assertOpen($request);

            if (array_key_exists('justification', $data) && blank($data['justification'])) {
                throw PurchaseRequestIncompleteException::make();
            }

            /** @var PurchaseRequest $request */
            $request = parent::update($request, array_intersect_key($data, array_flip([...self::HEADER, 'row_version'])));

            if (array_key_exists('lines', $data)) {
                $this->syncLines($request, self::filledLines($data['lines']));
            } else {
                $this->pushHeaderToLines($request);
            }

            return $request->refresh();
        });
    }

    /** Talebi iptal eder: islenmemis kalemler de iptal olur; siparisi verilmis kalem varsa iptal edilemez. */
    public function cancel(PurchaseRequest $request, ?string $reason = null): PurchaseRequest
    {
        return $this->transactions->run(function () use ($request, $reason): PurchaseRequest {
            /** @var PurchaseRequest $locked */
            $locked = $this->lockForUpdate($request);
            $this->assertOpen($locked);

            $ordered = $locked->needs()->whereIn('status', [PurchaseNeedStatus::Ordered->value, PurchaseNeedStatus::Completed->value])->exists();

            if ($ordered) {
                throw InvalidTransitionException::make();
            }

            foreach ($locked->needs()->get() as $need) {
                /** @var PurchaseNeed $need */
                if (! $need->statusEnum()->isClosed() && ! $need->isArchived()) {
                    $this->needs->cancel($need, $reason);
                }
            }

            $locked->refresh()->forceFill(['status' => PurchaseRequestStatus::Cancelled->value]);
            $this->saveWithoutVersion($locked);
            $this->recordActivity($locked, 'cancelled', filled($reason) ? ['gerekce' => $reason] : []);

            return $locked;
        });
    }

    public function archive(Model|int|string $record): PurchaseRequest
    {
        return $this->transactions->run(function () use ($record): PurchaseRequest {
            /** @var PurchaseRequest $request */
            $request = $this->lockForUpdate($record);

            if ($request->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $request->forceFill(['archived_at' => Carbon::now('UTC')]);
            $this->saveWithoutVersion($request);
            $this->recordActivity($request, 'archived');

            return $request;
        });
    }

    public function restore(Model|int|string $record): PurchaseRequest
    {
        return $this->transactions->run(function () use ($record): PurchaseRequest {
            /** @var PurchaseRequest $request */
            $request = $this->lockForUpdate($record);

            if (! $request->isArchived()) {
                throw InvalidTransitionException::make();
            }

            $request->forceFill(['archived_at' => null]);
            $this->saveWithoutVersion($request);
            $this->recordActivity($request, 'restored');

            return $request;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, ?Model $record): array
    {
        unset($data['row_version'], $data['lines']);

        if (array_key_exists('usage_area', $data)) {
            $value = $data['usage_area'] instanceof UsageArea ? $data['usage_area']->value : (string) $data['usage_area'];
            $data['usage_area'] = UsageArea::tryFrom($value)?->value;
        }

        // Proje yalniz Saha kullaniminda anlamlidir.
        if (($data['usage_area'] ?? $record?->getAttribute('usage_area')?->value ?? null) === UsageArea::Office->value) {
            $data['project_id'] = null;
        }

        foreach (['project_id', 'org_unit_id', 'needed_by', 'justification', 'existing_check', 'note'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filled($data[$field]) ? (is_string($data[$field]) ? trim($data[$field]) : $data[$field]) : null;
            }
        }

        if (array_key_exists('is_urgent', $data)) {
            $data['is_urgent'] = (bool) $data['is_urgent'];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        return [
            'talep_no' => $record->getAttribute('request_no'),
            'kullanim_yeri' => $record->getAttribute('usage_area')?->value,
            'proje' => $record->getAttribute('project_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function createLine(PurchaseRequest $request, array $line, int $index): PurchaseNeed
    {
        /** @var PurchaseNeed $need */
        $need = $this->needs->create([
            ...array_intersect_key($line, array_flip(self::LINE)),
            ...$this->inherited($request),
            'source' => PurchaseNeedSource::OfficeRequest->value,
            'purchase_request_id' => $request->getKey(),
            'sort_order' => $index,
        ]);

        return $need;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(PurchaseRequest $request, array $lines): void
    {
        if ($lines === []) {
            throw PurchaseRequestIncompleteException::make();
        }

        $existing = $request->needs()->get()->keyBy(static fn (PurchaseNeed $need): int => (int) $need->getKey());
        $kept = [];

        foreach ($lines as $index => $line) {
            $id = filled($line['id'] ?? null) ? (int) $line['id'] : null;
            $need = $id !== null ? $existing->get($id) : null;

            if (! $need instanceof PurchaseNeed) {
                $this->createLine($request, $line, $index);

                continue;
            }

            $kept[] = $id;

            if ($need->statusEnum()->isClosed() || $need->isArchived()) {
                continue;
            }

            $values = $this->inherited($request);

            // Satin alma islemeye baslamadan kalemin kendisi de duzeltilebilir.
            if (in_array($need->statusEnum(), [PurchaseNeedStatus::Open, PurchaseNeedStatus::AwaitingProject], true)) {
                $values = [...array_intersect_key($line, array_flip(['brand_model', 'specification', 'quantity', 'unit', 'uom_id', 'note'])), ...$values];
            }

            $this->needs->update($need, $values);
            $need->refresh();

            if ((int) $need->getAttribute('sort_order') !== $index) {
                $need->forceFill(['sort_order' => $index]);
                $this->saveWithoutVersion($need);
            }
        }

        foreach ($existing as $id => $need) {
            if (! in_array($id, $kept, true) && in_array($need->statusEnum(), [PurchaseNeedStatus::Open, PurchaseNeedStatus::AwaitingProject], true)) {
                $this->needs->cancel($need, __('purchasing.request.line_removed'));
            }
        }
    }

    /** Baslik degisince islenmemis kalemlere yazilir. */
    private function pushHeaderToLines(PurchaseRequest $request): void
    {
        foreach ($request->needs()->get() as $need) {
            /** @var PurchaseNeed $need */
            if (! $need->statusEnum()->isClosed() && ! $need->isArchived()) {
                $this->needs->update($need, $this->inherited($request));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inherited(PurchaseRequest $request): array
    {
        $values = [];

        foreach (self::INHERITED as $field) {
            $value = $request->getAttribute($field);
            $values[$field] = $value instanceof \BackedEnum ? $value->value : ($value instanceof Carbon ? $value->toDateString() : $value);
        }

        return $values;
    }

    /**
     * Bos satirlar (urun secilmemis ve ad yazilmamis) atilir.
     *
     * @return list<array<string, mixed>>
     */
    private static function filledLines(mixed $lines): array
    {
        $filled = [];

        foreach (is_array($lines) ? $lines : [] as $line) {
            if (is_array($line) && (filled($line['catalog_item_id'] ?? null) || filled(trim((string) ($line['requested_item_name'] ?? ''))))) {
                $filled[] = $line;
            }
        }

        return $filled;
    }

    private function assertOpen(PurchaseRequest $request): void
    {
        if ($request->isArchived()) {
            throw RecordArchivedException::make();
        }

        if ($request->statusEnum()->isClosed()) {
            throw InvalidTransitionException::make();
        }
    }

    private function actorModel(): ?Personnel
    {
        $id = $this->actor->personnelId();

        // D-148: kimlige burunulmus oturumda gizli hesap kapsami atlanir.
        return $id !== null ? Personnel::query()->withoutGlobalScopes()->find($id) : null;
    }
}
