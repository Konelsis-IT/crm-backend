<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B06A - DMS: sistemde yazilan belge ve paylasim baglantisi (karar D-75).
 *
 * Kullanici talebi (10 Eylul 2026): belge bilgileri doldurulup belgenin asli
 * sisteme yuklenecek; dosyasi olmayan belgeler icin sistemde ayrintili bir
 * belge yazma arayuzu olacak; belge detayinda "Paylas" dugmesi olacak ve
 * baglanti simdilik yetkisiz erisime acik olacak (yetki/sifre sonraki tur).
 *
 * - `document_revisions.content_kind`: `upload` (dosya) | `authored` (sistemde
 *   yazildi). `body_html` yazilan icerigi tasir; icerik hash'i govdeden uretilir.
 * - `document_shares`: dokuman basina paylasim baglantilari. `token` tahmin
 *   edilemez rastgele anahtardir ve URL'de yalniz o gorunur (kimlik gosterilmez).
 *   Erisim sayaci ve son erisim ani izlenir; iptal edilen baglanti kapanir.
 *   Sifre/yetki kolonlari bilinclice henuz yok (02 SS11).
 */
return new class extends KonelsisMigration
{
    public function up(): void
    {
        Schema::table('document_revisions', function (Blueprint $table): void {
            $this->status($table, 'content_kind')->default('upload')->after('status');
            $table->longText('body_html')->nullable()->after('change_summary');
        });
        $this->enumCheck('document_revisions', 'content_kind', ['upload', 'authored']);

        Schema::create('document_shares', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $this->ascii($table, 'token', 64);
            $table->string('label', 120)->nullable();
            $table->boolean('allow_download')->default(true);
            $this->ts($table, 'expires_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $this->ts($table, 'last_accessed_at')->nullable();
            $this->status($table)->default('active');
            $this->ts($table, 'revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by_personnel_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('token', 'uk_document_shares_token');
            $table->index(['document_id', 'status'], 'ix_document_shares_document_status');
            $table->foreign('document_id', 'fk_document_shares_document')
                ->references('id')->on('documents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('revoked_by_personnel_id', 'fk_document_shares_revoker')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('document_shares', 'status', ['active', 'revoked', 'expired']);
        $this->personnelForeignKeys('document_shares');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        Schema::table('document_shares', function (Blueprint $blueprint): void {
            foreach (['created_by_personnel_id', 'updated_by_personnel_id'] as $column) {
                $blueprint->dropForeign($this->fkName('document_shares', $column));
            }
        });

        Schema::dropIfExists('document_shares');

        Schema::table('document_revisions', function (Blueprint $table): void {
            $table->dropColumn(['content_kind', 'body_html']);
        });
    }
};
