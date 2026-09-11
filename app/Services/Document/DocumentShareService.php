<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\DocumentShareStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Document\DocumentShare;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Audit\ActorContext;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dokuman paylasim baglantisi servisi (D-75).
 *
 * create: token uretilir (48 karakter, tahmin edilemez). revoke: baglanti
 * kapatilir. recordAccess: anonim erisim sayaci; kimlik dogrulamasi olmayan
 * paylasim sayfasindan cagrilir, Personel Hareketleri'ne yazilmaz (kisi yok).
 */
final class DocumentShareService extends AbstractService
{
    protected string $orderBy = 'created_at';

    protected string $orderDirection = 'desc';

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly ActorContext $actor,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return parent::create([
            'document_id' => (int) ($data['document_id'] ?? 0),
            'token' => Str::random(48),
            'label' => $data['label'] ?? null,
            'allow_download' => (bool) ($data['allow_download'] ?? true),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => DocumentShareStatus::Active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model|int|string $record, array $data): Model
    {
        return parent::update($record, array_intersect_key($data, array_flip(['label', 'allow_download', 'expires_at', 'row_version'])));
    }

    public function revoke(Model|int|string $record): DocumentShare
    {
        return $this->transactions->run(function () use ($record): DocumentShare {
            /** @var DocumentShare $share */
            $share = $this->lockForUpdate($record);

            if ($share->status !== DocumentShareStatus::Active) {
                throw InvalidTransitionException::make(['from' => $share->status->getLabel(), 'to' => DocumentShareStatus::Revoked->getLabel()]);
            }

            $share->forceFill([
                'status' => DocumentShareStatus::Revoked,
                'revoked_at' => Carbon::now('UTC'),
                'revoked_by_personnel_id' => $this->actor->personnelId(),
            ])->save();

            $this->recordActivity($share, 'revoked', ['etiket' => $share->label]);

            return $share;
        });
    }

    /** Anonim erisim: sayac ve son erisim ani. Suresi dolmussa durum kapatilir. */
    public function recordAccess(DocumentShare $share): DocumentShare
    {
        return $this->transactions->run(function () use ($share): DocumentShare {
            /** @var DocumentShare $locked */
            $locked = $this->lockForUpdate($share);

            if ($locked->status === DocumentShareStatus::Active && $locked->expires_at !== null && $locked->expires_at->isPast()) {
                $locked->forceFill(['status' => DocumentShareStatus::Expired])->save();

                return $locked;
            }

            $locked->forceFill([
                'access_count' => (int) $locked->access_count + 1,
                'last_accessed_at' => Carbon::now('UTC'),
            ])->save();

            return $locked;
        });
    }
}
