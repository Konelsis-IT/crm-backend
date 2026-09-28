<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B40 - Yila gore kodlar (D-132, 28 Eylul 2026 kullanici karari).
 *
 * "Teklif proje kodlarinin isimlendirmesi: TKLF-2026-0001, TKLF-2027-0012 ...
 * yil bilgisi otomatik gelecek. Is dosyasi ismi potansiyel isler olarak
 * degistirilecek ... kodu POTIS- ek'i ile baslasin: POTIS-2026-0001. Teklif'e
 * donusturuldugunde TKLF kodu gelir ... POTIS kodunu teklif adimina gecince de
 * koruyalim."
 *
 * - `business_code_sequences`: on ek (POTIS / TKLF / PRJ) ve yil basina sayac.
 *   Kod ayni islemde (transaction) artirilir; islem geri alinirsa numara da
 *   geri alinir, numara bosa gitmez.
 * - `business_codes.formatted_code` artik uretilmis (generated) kolon degil;
 *   servis yazar. `code_year` ve `year_sequence` eklendi. Tur listesine
 *   `potential` (potansiyel is, POTIS) eklendi.
 * - Veri donusumu: potansiyel islerin (is dosyalarinin) TKLF-n kodlari
 *   POTIS-YYYY-NNNN olur; proje kodlari PRJ-YYYY-NNNN; teklif numaralari
 *   (proposals.proposal_no) TKLF-YYYY-NNNN. Yil, kodun verildigi yil (kurum saati);
 *   sira, eski sira numarasi / olusturma sirasidir. Sayaclar son numarayla baslar.
 *
 * On kosul: B16 (business_codes, proposals).
 */
return new class extends KonelsisMigration
{
    private const PREFIXES = ['potential' => 'POTIS', 'project' => 'PRJ'];

    public function up(): void
    {
        Schema::create('business_code_sequences', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code_prefix', 8);
            $table->unsignedSmallInteger('code_year');
            $table->unsignedInteger('last_number')->default(0);
            $this->ts($table, 'updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['code_prefix', 'code_year'], 'uk_business_code_sequences_prefix_year');
        });

        // Uretilmis kolon duz kolona doner; MySQL mevcut degerleri korur.
        if ($this->isMySql()) {
            DB::statement('ALTER TABLE `business_codes` MODIFY `formatted_code` VARCHAR(24) NOT NULL');
        }

        Schema::table('business_codes', function (Blueprint $table): void {
            $table->unsignedSmallInteger('code_year')->nullable()->after('formatted_code');
            $table->unsignedInteger('year_sequence')->nullable()->after('code_year');
        });

        $this->dropCheck('business_codes', 'ck_business_codes_code_kind_enum');
        $this->convertBusinessCodes();
        $this->enumCheck('business_codes', 'code_kind', ['potential', 'offer', 'project']);
        $this->convertProposals();
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        $this->dropCheck('business_codes', 'ck_business_codes_code_kind_enum');

        foreach (DB::table('business_codes')->orderBy('id')->get() as $row) {
            $kind = $row->code_kind === 'potential' ? 'offer' : $row->code_kind;
            DB::table('business_codes')->where('id', $row->id)->update([
                'code_kind' => $kind,
                'formatted_code' => ($kind === 'project' ? 'PRJ-' : 'TKLF-').$row->sequence_no,
            ]);
        }

        $this->enumCheck('business_codes', 'code_kind', ['offer', 'project']);

        $counts = [];

        foreach (DB::table('proposals')->join('business_cases', 'business_cases.id', '=', 'proposals.business_case_id')->orderBy('proposals.id')->get(['proposals.id', 'business_cases.sequence_no']) as $row) {
            $index = $counts[$row->sequence_no] = ($counts[$row->sequence_no] ?? -1) + 1;
            DB::table('proposals')->where('id', $row->id)->update([
                'proposal_no' => 'TKLF-'.$row->sequence_no.($index === 0 ? '' : '-'.chr(ord('A') + $index)),
            ]);
        }

        Schema::table('business_codes', function (Blueprint $table): void {
            $table->dropColumn(['code_year', 'year_sequence']);
        });

        if ($this->isMySql()) {
            DB::statement("ALTER TABLE `business_codes` MODIFY `formatted_code` VARCHAR(24) AS (CONCAT(CASE `code_kind` WHEN 'offer' THEN 'TKLF-' WHEN 'project' THEN 'PRJ-' END, `sequence_no`)) STORED");
        }

        Schema::dropIfExists('business_code_sequences');
    }

    /** Potansiyel is (eski teklif kodu) ve proje kodlari: yil + yil icindeki sira. */
    private function convertBusinessCodes(): void
    {
        $counters = [];

        foreach (DB::table('business_codes')->orderBy('sequence_no')->orderBy('id')->get() as $row) {
            $kind = $row->code_kind === 'offer' ? 'potential' : (string) $row->code_kind;
            $prefix = self::PREFIXES[$kind] ?? 'PRJ';
            $year = $this->year($row->issued_at);
            $number = $counters[$prefix][$year] = ($counters[$prefix][$year] ?? 0) + 1;

            DB::table('business_codes')->where('id', $row->id)->update([
                'code_kind' => $kind,
                'code_year' => $year,
                'year_sequence' => $number,
                'formatted_code' => sprintf('%s-%d-%04d', $prefix, $year, $number),
            ]);
        }

        $this->storeCounters($counters);
    }

    /** Her teklif kendi TKLF numarasini alir (eskiden potansiyel isin kodunu tasiyordu). */
    private function convertProposals(): void
    {
        $counters = [];

        foreach (DB::table('proposals')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at']) as $row) {
            $year = $this->year($row->created_at);
            $number = $counters['TKLF'][$year] = ($counters['TKLF'][$year] ?? 0) + 1;

            DB::table('proposals')->where('id', $row->id)->update([
                'proposal_no' => sprintf('TKLF-%d-%04d', $year, $number),
            ]);
        }

        $this->storeCounters($counters);
    }

    /**
     * @param  array<string, array<int, int>>  $counters
     */
    private function storeCounters(array $counters): void
    {
        foreach ($counters as $prefix => $years) {
            foreach ($years as $year => $last) {
                DB::table('business_code_sequences')->insert([
                    'code_prefix' => $prefix,
                    'code_year' => $year,
                    'last_number' => $last,
                ]);
            }
        }
    }

    /** Kurum saatine gore yil (kayitlar UTC, D-114). */
    private function year(mixed $value): int
    {
        $timezone = (string) config('konelsis.organization.default_timezone', 'Europe/Istanbul');

        return $value === null
            ? (int) Carbon::now($timezone)->format('Y')
            : (int) Carbon::parse((string) $value, 'UTC')->setTimezone($timezone)->format('Y');
    }

    private function dropCheck(string $table, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP CHECK `%s`', $table, $this->shorten($name)));
    }
};
