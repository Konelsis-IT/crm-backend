<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B55 - Her teklif surumunun kendi teklif durumu (D-195, 12 Ekim 2026
 * kullanici karari: "Her revizyonda yeni surum gelsin, oncekinin durumu da
 * kaybedilmesin. Yani her revizyon'un durumu kendine ait olsun").
 *
 * `proposal_versions.offer_status` (Verilecek / Verilen / Onaylandi / Kacan
 * firsat). `proposals.offer_status` guncel surumun durumunun kopyasi olarak
 * kalir (listeler, sekmeler, panolar ayni alani okur; servis ikisini birlikte
 * yazar).
 *
 * Doldurma: guncel surum teklifin bugunku durumunu alir; ancak teklif Verilen
 * gorunup guncel surumu hic gonderilmemisse (gonderilmis surumden sonra acilan
 * yeni surum) guncel surum ve teklif Verilecek olur. Onceki surumler
 * gonderildiyse (gonderim tarihi ya da Gonderildi durumu) Verilen, degilse
 * Verilecek olur. Bos teklif durumu bos kalir. On kosul: B29, B43.
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const STATUSES = ['to_be_submitted', 'submitted', 'approved', 'lost'];

    public function up(): void
    {
        if (Schema::hasColumn('proposal_versions', 'offer_status')) {
            return;
        }

        Schema::table('proposal_versions', function (Blueprint $table): void {
            $this->status($table, 'offer_status')->nullable()->after('status');
            $table->index(['proposal_id', 'offer_status'], 'ix_proposal_versions_offer_status');
        });

        $list = implode(', ', array_map(static fn (string $value): string => "'".$value."'", self::STATUSES));
        $this->check('proposal_versions', 'ck_proposal_versions_offer_status_enum', "`offer_status` IS NULL OR `offer_status` IN ({$list})");

        $this->backfill();
    }

    private function backfill(): void
    {
        $current = DB::table('proposals')->whereNotNull('current_version_id')->pluck('offer_status', 'current_version_id');

        foreach ($current as $versionId => $status) {
            DB::table('proposal_versions')->where('id', (int) $versionId)->update(['offer_status' => $status]);
        }

        // Gonderilmis bir surumden sonra acilip henuz gonderilmemis guncel surum
        // Verilecek'tir; onceki surum Verilen kalir, teklifin kopyasi guncel
        // surumu izler (D-195: "her revizyon'un durumu kendine ait olsun").
        // Onaylandi ve Kacan firsat teklifin tamami icin verilmis karardir; dokunulmaz.
        $unsent = DB::table('proposals as p')
            ->join('proposal_versions as v', 'v.id', '=', 'p.current_version_id')
            ->where('p.offer_status', 'submitted')
            ->whereNull('v.submitted_at')
            ->where('v.status', '!=', 'submitted')
            ->get(['p.id as proposal_id', 'v.id as version_id']);

        foreach ($unsent as $row) {
            DB::table('proposal_versions')->where('id', (int) $row->version_id)->update(['offer_status' => 'to_be_submitted']);
            DB::table('proposals')->where('id', (int) $row->proposal_id)->update(['offer_status' => 'to_be_submitted']);
        }

        $currentIds = $current->keys()->map(static fn ($id): int => (int) $id)->all();

        DB::table('proposal_versions')
            ->whereNull('offer_status')
            ->when($currentIds !== [], fn ($query) => $query->whereNotIn('id', $currentIds))
            ->where(fn ($query) => $query->whereNotNull('submitted_at')->orWhere('status', 'submitted'))
            ->update(['offer_status' => 'submitted']);

        DB::table('proposal_versions')
            ->whereNull('offer_status')
            ->when($currentIds !== [], fn ($query) => $query->whereNotIn('id', $currentIds))
            ->update(['offer_status' => 'to_be_submitted']);
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasColumn('proposal_versions', 'offer_status')) {
            return;
        }

        if ($this->isMySql()) {
            DB::statement('ALTER TABLE `proposal_versions` DROP CHECK `ck_proposal_versions_offer_status_enum`');
        }

        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->dropIndex('ix_proposal_versions_offer_status');
            $table->dropColumn('offer_status');
        });
    }
};
