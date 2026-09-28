<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\Platform\Feature;
use App\Query\Platform\FeatureQueries;
use Throwable;

/**
 * Ozelliklerin acik / kapali durumu (B39, D-128). Istek (ve kuyruk isi)
 * basina bir kez okunur (scoped baglama, AppServiceProvider).
 *
 * - Durum yalniz `features` tablosundan gelir; .env ve arayuz ayari yoktur.
 * - Bir ozellik, kendisi ve butun ustleri aciksa aciktir.
 * - Tabloda satiri olmayan ozellik (B39 uygulanmadan once ya da esitleme
 *   yazamadiysa) katalogdaki varsayilan durumunu alir.
 * - Tablo katalogla esit degilse ilk okumada PlatformFeatureService esitler;
 *   esitleme basarisiz olursa (ayni anda baska istek yazdi vb.) okuma bozulmaz.
 */
final class FeatureRegistry
{
    /** @var array<string, bool>|null */
    private ?array $states = null;

    /** @var array<class-string, Feature>|null */
    private static ?array $modelMap = null;

    public function __construct(
        private readonly FeatureQueries $queries,
        private readonly PlatformFeatureService $features,
    ) {}

    public function enabled(Feature $feature): bool
    {
        $states = $this->states();

        for ($current = $feature; $current !== null; $current = $current->parent()) {
            if (! ($states[$current->value] ?? $current->defaultActive())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Yetki kontrolu kapali bir ozelligin kayit turune mi? (Gate::before)
     * Ilk arguman kayit ya da kayit sinifidir (Filament: viewAny sinif, digerleri kayit).
     *
     * @param  array<int|string, mixed>  $arguments
     */
    public function deniesAbility(array $arguments): bool
    {
        $subject = reset($arguments);
        $class = is_object($subject) ? $subject::class : (is_string($subject) ? $subject : null);
        $feature = $class === null ? null : (self::modelMap()[$class] ?? null);

        return $feature !== null && ! $this->enabled($feature);
    }

    /**
     * @return array<string, bool>
     */
    private function states(): array
    {
        if ($this->states !== null) {
            return $this->states;
        }

        if (! SchemaReadiness::hasBatch('B39')) {
            return $this->states = [];
        }

        try {
            $rows = $this->queries->all();

            if ($this->features->outOfSync($rows)) {
                try {
                    $this->features->syncCatalog($rows);
                } catch (Throwable $exception) {
                    // Ayni anda baska bir istek yazmis olabilir; tablo yeniden okunur.
                    // Hata yine de gunluge yazilir (sessiz kalmasin).
                    report($exception);
                }

                $rows = $this->queries->all();
            }

            return $this->states = $rows->map(fn ($row): bool => (bool) $row->is_active)->all();
        } catch (Throwable $exception) {
            report($exception);

            return $this->states = [];
        }
    }

    /**
     * @return array<class-string, Feature>
     */
    private static function modelMap(): array
    {
        if (self::$modelMap !== null) {
            return self::$modelMap;
        }

        $map = [];

        foreach (Feature::cases() as $feature) {
            foreach ($feature->models() as $class) {
                $map[$class] = $feature;
            }
        }

        return self::$modelMap = $map;
    }
}
