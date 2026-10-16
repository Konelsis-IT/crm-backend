<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Personnel\PositionStatus;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Models\Personnel\PositionAssignment;
use App\Services\Audit\ActorContext;
use App\Services\Platform\SchemaReadiness;
use Database\Seeders\Support\ProtectedSeeder;
use Database\Seeders\Support\SeedPositionRoles;
use Illuminate\Support\Facades\DB;

/**
 * Teklif departmaninin uyeleri (D-218; kullanici 15 Ekim 2026: "Yusuf Bey
 * teklif grup müdürü olduğu için hem iş geliştirme hem teklif departmanında
 * çalışan bir personeldir. Mukaddes ve Haydar bey'de hem teklif hem iş
 * geliştirme departmanında çalışan personellerdir.").
 *
 * Kisilerin kendi departmani (Is Gelistirme) ve gorevi degismez; Teklif
 * departmaninda acilan gorevlere ek gorev (is_primary = 0) olarak atanirlar.
 * Uygulama departman uyeligini ek gorevlerden de okur (OrganizationQueries).
 * Kisiler e-postayla bulunur. D-165: yalniz eksik olan eklenir; var olan
 * gorev, atama ve rol degistirilmez; departmana yonetici atanmaz. Kurulumda
 * DEPLOY_SEEDERS ile calisir.
 */
final class ProposalDepartmentMembers20261015Seeder extends ProtectedSeeder
{
    private const UNIT = 'TEKLIF';

    /** Teklif departmaninin gorevleri. */
    private const POSITIONS = [
        ['code' => 'TGM', 'title' => 'Teklif grup müdürü', 'level' => 2, 'headcount' => 1],
        ['code' => 'TS', 'title' => 'Teklif sorumlusu', 'level' => 0, 'headcount' => 2],
    ];

    /** E-posta => Teklif departmanindaki ek gorevi. */
    private const MEMBERS = [
        'yusuf.fil@konelsis.com' => 'TGM',
        'mukaddes.tekik@konelsis.com' => 'TS',
        'haydar.cakmak@konelsis.com' => 'TS',
    ];

    private const VALID_FROM = '2026-10-15';

    public function run(): void
    {
        if (! SchemaReadiness::hasBatch('B03')) {
            $this->command?->warn('Organizasyon tablolari yok; Teklif departmani uyeleri sonraki kuruluma kaldi.');

            return;
        }

        $unit = OrgUnit::query()->where('code', self::UNIT)->first();

        if (! $unit instanceof OrgUnit) {
            $this->command?->warn('TEKLIF departmani yok; ek gorevler eklenmedi.');

            return;
        }

        $positions = [];

        foreach (self::POSITIONS as $row) {
            $positions[$row['code']] = $this->row('position:'.self::UNIT.'|'.$row['code'], static fn (): Position => Position::query()->firstOrCreate(
                ['org_unit_id' => $unit->getKey(), 'code' => $row['code']],
                [
                    'title' => $row['title'],
                    'managerial_level' => $row['level'],
                    'headcount' => $row['headcount'],
                    'status' => PositionStatus::Active,
                    'valid_from' => self::VALID_FROM,
                ],
            ));
        }

        $added = 0;

        foreach (self::MEMBERS as $email => $code) {
            $this->row('position-assignment:'.$email.'|'.self::UNIT.'|'.$code, function () use ($email, $code, $positions, &$added): ?PositionAssignment {
                $personnel = Personnel::query()->where('normalized_email', $email)->first();
                $position = $positions[$code] ?? Position::query()->whereHas('orgUnit', fn ($query) => $query->where('code', self::UNIT))->where('code', $code)->first();

                if (! $personnel instanceof Personnel || ! $position instanceof Position) {
                    $this->command?->warn(sprintf('%s ya da %s gorevi yok; atlandi.', $email, $code));

                    return null;
                }

                $existing = PositionAssignment::query()
                    ->where('personnel_id', $personnel->getKey())
                    ->where('position_id', $position->getKey())
                    ->whereNull('valid_until')
                    ->first();

                if ($existing instanceof PositionAssignment) {
                    return $existing;
                }

                $added++;

                return PositionAssignment::query()->create([
                    'personnel_id' => $personnel->getKey(),
                    'position_id' => $position->getKey(),
                    'is_primary' => false,
                    'allocation_pct' => 100,
                    'valid_from' => self::VALID_FROM,
                ]);
            });
        }

        // Rol esitlemesi hareket kaydi yazar; hareket kaydi islem icinde olmalidir.
        $admin = SystemAccountSeeder::actor();

        if ($admin !== null) {
            app(ActorContext::class)->actAsPersonnel((int) $admin->getKey());
        }

        $roles = DB::transaction(static fn (): array => SeedPositionRoles::sync());

        $this->command?->info(sprintf('ProposalDepartmentMembers20261015Seeder: Teklif departmanina %d ek gorev; %d rol verildi.', $added, $roles['granted']));
    }
}
