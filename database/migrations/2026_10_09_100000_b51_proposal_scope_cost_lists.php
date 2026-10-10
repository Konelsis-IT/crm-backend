<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B51 - Teklif kapsaminda maliyet listesi (D-181, 9 Ekim 2026 kullanici
 * talimati: "Kapsam listesinin Excel olarak yuklendigi yerde hemen yaninda
 * dokuman olarak maliyet listesi de yuklenecek. Bu maliyet listesi alani ile
 * satin almaya ve projeye hangi urunlerin alinacagi ve maliyetleri gidecek").
 *
 * `proposal_version_scope_documents`: teklif surumunun bir proje kapsamina
 * (proposal_version_scopes satiri) bagli belge revizyonlari. Bugun tek rol
 * `cost_list` (Maliyet listesi, dokuman turu MLY); bir kapsamda birden fazla
 * maliyet listesi olabilir (D-176). Kapsam listesi (KPS) gibi her surum
 * baktigi revizyonu saklar: yeni surum ayni revizyonu yeni satirla baglar,
 * belge kopyalanmaz (D-158); ayni adli dosya ayni belgenin yeni revizyonudur.
 * Ayni kapsamda bir revizyon bir rolde bir kez (uk_..._scope_revision_role).
 * Kapsam ve revizyon silinemez (RESTRICT); kapsam kaldirilinca servis once bu
 * satirlari siler. Satirlar degismez (yalniz created_*).
 *
 * Maliyet satirlarinin ayristirilmasi (Excel -> kalem) bu grupta yoktur;
 * kullanici ornek dosyayi gonderince ayrica planlanir.
 *
 * Veri tasima yok. Uygulama sonrasi CostListDocumentType20261009Seeder
 * (DEPLOY_SEEDERS) "MLY Maliyet Listesi" dokuman turunu ekler.
 * On kosul: B06 (dokumanlar), B43 (teklif kapsamlari).
 */
return new class extends KonelsisMigration
{
    /** @var list<string> */
    private const ROLES = ['cost_list'];

    public function up(): void
    {
        if (Schema::hasTable('proposal_version_scope_documents')) {
            return;
        }

        Schema::create('proposal_version_scope_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('proposal_version_scope_id');
            $table->unsignedBigInteger('document_revision_id');
            $this->status($table, 'document_role');
            $table->smallInteger('sort_order')->default(0);
            $this->auditCreated($table);

            $table->unique(['proposal_version_scope_id', 'document_revision_id', 'document_role'], 'uk_proposal_version_scope_documents_scope_revision_role');
            $table->index('document_revision_id', 'ix_proposal_version_scope_documents_revision');
            $table->foreign('proposal_version_scope_id', 'fk_proposal_version_scope_documents_scope')
                ->references('id')->on('proposal_version_scopes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('document_revision_id', 'fk_proposal_version_scope_documents_revision')
                ->references('id')->on('document_revisions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('proposal_version_scope_documents', 'document_role', self::ROLES);
        $this->personnelForeignKeys('proposal_version_scope_documents');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        if (! Schema::hasTable('proposal_version_scope_documents')) {
            return;
        }

        Schema::table('proposal_version_scope_documents', function (Blueprint $blueprint): void {
            $blueprint->dropForeign('fk_proposal_version_scope_documents_scope');
            $blueprint->dropForeign('fk_proposal_version_scope_documents_revision');
            $blueprint->dropForeign($this->fkName('proposal_version_scope_documents', 'created_by_personnel_id'));
        });

        Schema::dropIfExists('proposal_version_scope_documents');
    }
};
