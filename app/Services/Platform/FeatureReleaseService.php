<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\Platform\Feature;
use App\Exceptions\Platform\InvalidReleaseVersionException;
use App\Exceptions\Platform\ReleaseAlreadyPublishedException;
use App\Exceptions\Platform\ReleaseDowngradeException;
use App\Models\Platform\FeatureRelease;
use App\Query\Platform\FeatureReleaseQueries;
use App\Services\AbstractService;
use App\Services\Audit\ActivityRecorder;
use App\Services\Support\OptimisticLock;
use App\Services\Support\TransactionRunner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Surum yayini (B42, D-151, 2 Ekim 2026 kullanici karari): kod canliya
 * dogrudan gider; yeni ozellikler surumleri yayinlanana kadar gorunmez.
 * Yayin bir satir ekler; o surume kadar olan ozellikler (anahtari aciksa)
 * gorunur olur. D-153 (3 Ekim 2026): yayin, onceki yayindan bu yana gelen
 * surumlerin ozelliklerini de acar (`is_active = 1`; ilk yayinda yalniz o
 * surumunkileri). Satirlar degistirilmez; geri almak icin eski surum acik
 * onayla yeniden yayinlanir (ozellik acilmaz). Komut satirindan
 * (`konelsis:release`) cagrilir.
 */
final class FeatureReleaseService extends AbstractService
{
    protected string $model = FeatureRelease::class;

    private ?string $previousVersion = null;

    /** @var list<string> Son yayinda acilan ozellik kodlari. */
    private array $opened = [];

    public function __construct(
        TransactionRunner $transactions,
        OptimisticLock $lock,
        ActivityRecorder $activities,
        private readonly FeatureReleaseQueries $releases,
        private readonly PlatformFeatureService $features,
    ) {
        parent::__construct($transactions, $lock, $activities);
    }

    /**
     * Son publish() cagrisinda acilan ozellikler (D-153).
     *
     * @return list<string>
     */
    public function openedFeatures(): array
    {
        return $this->opened;
    }

    public function publish(string $version, ?string $note = null, bool $rollback = false): FeatureRelease
    {
        $version = trim($version);

        if (preg_match('/^\d+\.\d+(\.\d+)?$/', $version) !== 1) {
            throw InvalidReleaseVersionException::make(['version' => $version]);
        }

        $current = $this->releases->currentVersion();

        if ($current !== null && version_compare($version, $current, '==')) {
            throw ReleaseAlreadyPublishedException::make(['version' => $version]);
        }

        if ($current !== null && version_compare($version, $current, '<') && ! $rollback) {
            throw ReleaseDowngradeException::make(['version' => $version, 'current' => $current]);
        }

        $this->previousVersion = $current;
        $forward = $current === null || version_compare($version, $current, '>');

        return $this->transactions->run(function () use ($version, $note, $current, $forward): FeatureRelease {
            // Sira gelen guncellemenin ozellikleri acilir (D-153); geri almada acilan olmaz,
            // daha yeni surumlerin ozellikleri surum suzgeciyle yeniden gizlenir.
            $this->opened = $forward ? $this->features->openReleased($current, $version) : [];

            /** @var FeatureRelease $release */
            $release = parent::create([
                'version' => $version,
                'published_at' => Carbon::now('UTC'),
                'note' => filled($note) ? Str::limit(trim((string) $note), 500, '') : null,
            ]);

            return $release;
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function createdChanges(Model $record): array
    {
        $opened = array_map(
            static fn (string $code): string => Feature::tryFrom($code)?->title() ?? $code,
            $this->opened,
        );

        return array_filter([
            'surum' => $record->getAttribute('version'),
            'onceki_surum' => $this->previousVersion,
            'acilan_ozellikler' => implode(', ', $opened),
            'not' => $record->getAttribute('note'),
        ], fn (mixed $value): bool => filled($value));
    }
}
