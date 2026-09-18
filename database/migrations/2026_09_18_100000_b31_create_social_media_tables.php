<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migrations\KonelsisMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B31 - Sosyal Medya modulu (kullanici karari, 18 Eylul 2026, D-106): icerik
 * hazirlama, onay, planlama, paylasim takibi, rakip/kurum hesaplari ve platform
 * istatistikleri. Kanonik B15 "Sosyal medya" (09 veri sozlugu 04) tasariminin
 * SM01 dilimidir.
 *
 * Tablolar (olusturma sirasiyla):
 *  - `social_profiles`             : icerigi ayri tutulan kendi hesaplarimiz
 *                                    (kurumsal hesap, yonetici hesabi).
 *  - `social_profile_links`        : hesabin platform baglantilari (hesap + platform tek).
 *  - `social_categories`           : icerik kategorileri; `color` sabit sekiz palet adindan biri.
 *  - `social_contents`             : icerik kaydi (fotograf, video, kisa metin, uzun
 *                                    metin, blog); plan tarihi, bes durum, karar, paylasim
 *                                    ve acil onay izleri; sayaclar (`media_count`,
 *                                    `comment_count`, `like_count`, `dislike_count`) ile
 *                                    `reminder_key` ve `urgent_*` sistem kolonlaridir,
 *                                    servis satir surumunu artirmadan yazar.
 *  - `social_content_platforms`    : icerigin hedef platformlari ve paylasim baglantisi.
 *  - `social_content_media`        : gorsel/video satirlari. Bir "medya grubu" kok satir
 *                                    (`parent_media_id` bos) ile ondan turetilen surumlerdir;
 *                                    grupta tek satir `is_selected` olur (servis kurali).
 *                                    `usage` = gallery | inline (inline: metin editoru gorseli).
 *                                    `crop_*` kok ozgun gorsele gore 0..1 normalize kirpma,
 *                                    `poster_file_object_id` video kapak karesi,
 *                                    `preview_file_object_id` tembel uretilen 1280 px onizleme.
 *                                    Galeriden cikarma `removed_at` ile yapilir; dosya yerinde kalir.
 *                                    `file_object_id` tekil DEGILDIR (ayni dosya paylasilabilir).
 *  - `social_comments`             : yorum ve yanit (tek gorsel seviye); `media_id` isaretin
 *                                    cizildigi ANDA goruntulenen medya satiridir, `anchor_*`
 *                                    o satirin piksel kutusuna gore 0..1 normalize edilir.
 *  - `social_reactions`            : kisi basina tek tepki (like | dislike | none).
 *  - `social_content_revisions`    : metin degistiginde ONCEKI baslik/aciklama/govde (yalniz ekleme).
 *  - `social_special_days`         : ozel gunler (`year` bos = her yil, `profile_id` bos = iki hesap).
 *  - `social_watch_accounts`       : rakip firma, rakip yonetici ve resmi kurum hesaplari.
 *  - `social_watch_links`          : izlenen hesabin platform baglantilari.
 *  - `social_metric_entries`       : donemlik platform istatistigi (elle ya da rapor dosyasiyla).
 *  - `social_responsible_positions`: modulden sorumlu gorevler (pozisyon); satir kaldirilmaz,
 *                                    `status` ile pasife alinir.
 *
 * DIKKAT: `social_responsible_positions` grubun SON tablosudur ve
 * SchemaReadiness imzasidir ('B31'). Bu tablo en sonda kalmali: up() yarida
 * kalirsa imza olusmaz ve ekranlar acilmaz. Yeni tablo eklenecekse bu tablodan
 * ONCE eklenir.
 *
 * Kanonik B15'ten farklar: platform baglantilari/webhook/senkron calismalari,
 * kampanyalar, icerik surum-ceviri agaci, yayin hedefleri ve onay motoru bagi bu
 * dilimde yok. `functional_area_id` kolonu yok (B04 kurulmadi). Durum gecmisi
 * ayri tabloda tutulmaz; tek kaynak Personel Hareketleri'dir. Hicbir tabloda
 * silme akisi yoktur: icerik arsive kalkar, medya `removed_at` alir, ayar
 * kayitlari pasife cekilir. "Bir satir kendini gosteremez" kurallari
 * (`parent_media_id`, `parent_comment_id`) serviste korunur; MySQL 8,
 * AUTO_INCREMENT kolonuna bakan CHECK'i reddeder.
 *
 * Not: `usage` MySQL'de ayrilmis sozcuktur. Sorgu kurucusu kolonu ters tirnakla
 * yazar; ham SQL yazan her yer de `usage` bicimini kullanmalidir.
 *
 * On kosul: B00/B01 (personnel), B03 (positions), B06 (file_objects). Ucu de
 * zorunlu on kosul oldugu icin FK'ler kosulsuz eklenir.
 *
 * Uygulama sonrasi (DBA/kullanici calistirir, ajanlar calistirmaz):
 *  1. db:seed --class=SocialProfileSeeder
 *  2. db:seed --class=SocialSpecialDaySeeder
 *  3. db:seed --class=ReferenceTypeRegistrySeeder   (social_* kayit turleri)
 *  4. shield:generate --all --panel=admin --ignore-existing-policies --option=permissions
 *  5. db:seed --class=RoleMatrixSeeder
 *  6. filament:assets
 * Uygulanana kadar "Sosyal Medya" menusu, pano widget'i ve gunluk hatirlatma
 * komutu pasif kalir.
 */
return new class extends KonelsisMigration
{
    /** App\Enums\SocialMedia\SocialPlatform degerleri. */
    private const PLATFORMS = ['instagram', 'facebook', 'linkedin', 'x', 'youtube', 'tiktok', 'website'];

    /** App\Enums\Shared\ActiveStatus degerleri. */
    private const ACTIVE_STATUSES = ['active', 'inactive'];

    /** App\Enums\SocialMedia\SocialContentStatus degerleri. */
    private const CONTENT_STATUSES = ['pending', 'approved', 'rejected', 'revision_requested', 'archived'];

    /** Rozet ve kategori paleti (App\Models\SocialMedia\SocialCategory::COLORS). */
    private const PALETTE = ['red', 'amber', 'emerald', 'sky', 'violet', 'stone', 'rose', 'teal'];

    public function up(): void
    {
        Schema::create('social_profiles', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'code', 32);
            $table->string('name', 160);
            $this->status($table, 'kind');
            $table->unsignedBigInteger('owner_personnel_id')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('code', 'uk_social_profiles_code');
            $table->index(['status', 'sort_order'], 'ix_social_profiles_status_order');
            $table->foreign('owner_personnel_id', 'fk_social_profiles_owner')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_profiles', 'kind', ['corporate', 'executive']);
        $this->enumCheck('social_profiles', 'status', self::ACTIVE_STATUSES);
        $this->personnelForeignKeys('social_profiles');

        Schema::create('social_profile_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $this->status($table, 'platform');
            $table->string('url', 500);
            $table->string('handle', 120)->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['profile_id', 'platform'], 'uk_social_profile_links_platform');
            $table->foreign('profile_id', 'fk_social_profile_links_profile')
                ->references('id')->on('social_profiles')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_profile_links', 'platform', self::PLATFORMS);
        $this->personnelForeignKeys('social_profile_links');

        Schema::create('social_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('color', 16)->default('red');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('name', 'uk_social_categories_name');
            $table->index(['status', 'sort_order'], 'ix_social_categories_status_order');
        });
        $this->enumCheck('social_categories', 'color', self::PALETTE);
        $this->enumCheck('social_categories', 'status', self::ACTIVE_STATUSES);
        $this->personnelForeignKeys('social_categories');

        Schema::create('social_contents', function (Blueprint $table): void {
            $table->id();
            $this->code($table, 'content_no', 32);
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $this->status($table, 'content_type');
            $table->string('title', 200);
            $table->text('caption')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $this->status($table, 'image_format')->nullable();
            $table->string('video_url', 500)->nullable();

            $table->date('planned_on')->nullable();
            $this->asciiChar($table, 'planned_time', 5)->nullable();

            $this->status($table)->default('pending');
            $this->status($table, 'status_before_archive')->nullable();
            $table->text('decision_note')->nullable();
            $table->unsignedBigInteger('decided_by_personnel_id')->nullable();
            $this->ts($table, 'decided_at')->nullable();

            $this->ts($table, 'published_at')->nullable();
            $table->unsignedBigInteger('published_by_personnel_id')->nullable();
            $table->text('publish_note')->nullable();

            $this->ts($table, 'urgent_requested_at')->nullable();
            $table->unsignedBigInteger('urgent_requested_by_personnel_id')->nullable();
            $this->ascii($table, 'reminder_key', 40)->nullable();

            $table->unsignedSmallInteger('media_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('dislike_count')->default(0);

            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('content_no', 'uk_social_contents_no');
            $table->index(['profile_id', 'status', 'planned_on'], 'ix_social_contents_feed');
            $table->index('planned_on', 'ix_social_contents_planned');
            $table->index('created_by_personnel_id', 'ix_social_contents_creator');
            $table->index(['profile_id', 'published_at'], 'ix_social_contents_published');

            foreach ([
                'profile_id' => ['social_profiles', 'profile'],
                'category_id' => ['social_categories', 'category'],
                'decided_by_personnel_id' => ['personnel', 'decider'],
                'published_by_personnel_id' => ['personnel', 'publisher'],
                'urgent_requested_by_personnel_id' => ['personnel', 'urgent_requester'],
            ] as $column => [$references, $suffix]) {
                $table->foreign($column, $this->shorten("fk_social_contents_{$suffix}"))
                    ->references('id')->on($references)->restrictOnDelete()->restrictOnUpdate();
            }
        });
        $this->enumCheck('social_contents', 'content_type', ['photo', 'video', 'short_text', 'long_text', 'blog']);
        $this->enumCheck('social_contents', 'image_format', ['original', 'square', 'portrait', 'story', 'landscape']);
        $this->enumCheck('social_contents', 'status', self::CONTENT_STATUSES);
        // Arsiv oncesi durum "archived" olamaz: arsivdeki icerik yeniden arsive kalkmaz.
        $this->enumCheck('social_contents', 'status_before_archive', ['pending', 'approved', 'rejected', 'revision_requested']);
        $this->personnelForeignKeys('social_contents');

        Schema::create('social_content_platforms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $this->status($table, 'platform');
            $table->string('published_url', 500)->nullable();
            $this->auditCreated($table);

            $table->unique(['content_id', 'platform'], 'uk_social_content_platforms');
            $table->index(['platform', 'content_id'], 'ix_social_content_platforms_platform');
            $table->foreign('content_id', 'fk_social_content_platforms_content')
                ->references('id')->on('social_contents')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_content_platforms', 'platform', self::PLATFORMS);
        $this->personnelForeignKeys('social_content_platforms');

        Schema::create('social_content_media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('file_object_id');
            $this->status($table, 'kind');
            $this->status($table, 'usage')->default('gallery');
            $table->unsignedBigInteger('parent_media_id')->nullable();
            $this->status($table, 'variant')->default('original');
            $table->string('variant_label', 80)->nullable();
            $table->boolean('is_selected')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('caption', 300)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->decimal('crop_x', 7, 6)->nullable();
            $table->decimal('crop_y', 7, 6)->nullable();
            $table->decimal('crop_w', 7, 6)->nullable();
            $table->decimal('crop_h', 7, 6)->nullable();
            $table->unsignedBigInteger('poster_file_object_id')->nullable();
            $table->unsignedBigInteger('preview_file_object_id')->nullable();
            $this->ts($table, 'removed_at')->nullable();
            $table->unsignedBigInteger('removed_by_personnel_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['content_id', 'usage', 'removed_at', 'sort_order'], 'ix_social_content_media_gallery');
            $table->index('parent_media_id', 'ix_social_content_media_parent');
            $table->index(['kind', 'file_object_id'], 'ix_social_content_media_kind_file');

            foreach ([
                'content_id' => ['social_contents', 'content'],
                'file_object_id' => ['file_objects', 'file'],
                'parent_media_id' => ['social_content_media', 'parent'],
                'poster_file_object_id' => ['file_objects', 'poster'],
                'preview_file_object_id' => ['file_objects', 'preview'],
                'removed_by_personnel_id' => ['personnel', 'remover'],
            ] as $column => [$references, $suffix]) {
                $table->foreign($column, $this->shorten("fk_social_content_media_{$suffix}"))
                    ->references('id')->on($references)->restrictOnDelete()->restrictOnUpdate();
            }
        });
        $this->enumCheck('social_content_media', 'kind', ['image', 'video']);
        $this->enumCheck('social_content_media', 'usage', ['gallery', 'inline']);
        $this->enumCheck('social_content_media', 'variant', ['original', 'square', 'portrait', 'story', 'landscape', 'resized']);
        $this->personnelForeignKeys('social_content_media');

        Schema::create('social_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('parent_comment_id')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->text('body');
            $this->status($table, 'anchor_shape')->nullable();
            $table->decimal('anchor_x', 7, 6)->nullable();
            $table->decimal('anchor_y', 7, 6)->nullable();
            $table->decimal('anchor_w', 7, 6)->nullable();
            $table->decimal('anchor_h', 7, 6)->nullable();
            $this->ts($table, 'resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_personnel_id')->nullable();
            $this->auditCreated($table);

            $table->index(['content_id', 'parent_comment_id', 'id'], 'ix_social_comments_thread');
            $table->index(['media_id', 'resolved_at'], 'ix_social_comments_marks');

            foreach ([
                'content_id' => ['social_contents', 'content'],
                'parent_comment_id' => ['social_comments', 'parent'],
                'media_id' => ['social_content_media', 'media'],
                'resolved_by_personnel_id' => ['personnel', 'resolver'],
            ] as $column => [$references, $suffix]) {
                $table->foreign($column, $this->shorten("fk_social_comments_{$suffix}"))
                    ->references('id')->on($references)->restrictOnDelete()->restrictOnUpdate();
            }
        });
        $this->enumCheck('social_comments', 'anchor_shape', ['point', 'rect']);
        $this->check(
            'social_comments',
            'ck_social_comments_anchor',
            '`anchor_shape` IS NULL OR (`media_id` IS NOT NULL AND `anchor_x` BETWEEN 0 AND 1 AND `anchor_y` BETWEEN 0 AND 1)',
        );
        $this->personnelForeignKeys('social_comments');

        Schema::create('social_reactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('personnel_id');
            $this->status($table, 'reaction');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['content_id', 'personnel_id'], 'uk_social_reactions_person');
            $table->index(['content_id', 'reaction'], 'ix_social_reactions_content_reaction');
            $table->foreign('content_id', 'fk_social_reactions_content')
                ->references('id')->on('social_contents')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('personnel_id', 'fk_social_reactions_personnel')
                ->references('id')->on('personnel')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_reactions', 'reaction', ['like', 'dislike', 'none']);
        $this->personnelForeignKeys('social_reactions');

        Schema::create('social_content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('content_id');
            $table->unsignedInteger('revision_no');
            $table->string('title', 200);
            $table->text('caption')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $this->auditCreated($table);

            $table->unique(['content_id', 'revision_no'], 'uk_social_content_revisions_no');
            $table->foreign('content_id', 'fk_social_content_revisions_content')
                ->references('id')->on('social_contents')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->personnelForeignKeys('social_content_revisions');

        Schema::create('social_special_days', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('note', 300)->nullable();
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['month', 'day'], 'ix_social_special_days_calendar');
            $table->foreign('profile_id', 'fk_social_special_days_profile')
                ->references('id')->on('social_profiles')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_special_days', 'status', self::ACTIVE_STATUSES);
        $this->check(
            'social_special_days',
            'ck_social_special_days_date',
            '`month` BETWEEN 1 AND 12 AND `day` BETWEEN 1 AND 31',
        );
        $this->personnelForeignKeys('social_special_days');

        Schema::create('social_watch_accounts', function (Blueprint $table): void {
            $table->id();
            $this->status($table, 'kind');
            $table->string('name', 160);
            $table->string('subtitle', 160)->nullable();
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->index(['kind', 'status', 'sort_order'], 'ix_social_watch_accounts_kind');
        });
        $this->enumCheck('social_watch_accounts', 'kind', ['competitor_company', 'competitor_executive', 'official_institution']);
        $this->enumCheck('social_watch_accounts', 'status', self::ACTIVE_STATUSES);
        $this->personnelForeignKeys('social_watch_accounts');

        Schema::create('social_watch_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('watch_account_id');
            $this->status($table, 'platform');
            $table->string('url', 500);
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['watch_account_id', 'platform'], 'uk_social_watch_links_platform');
            $table->foreign('watch_account_id', 'fk_social_watch_links_account')
                ->references('id')->on('social_watch_accounts')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_watch_links', 'platform', self::PLATFORMS);
        $this->personnelForeignKeys('social_watch_links');

        Schema::create('social_metric_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $this->status($table, 'platform');
            $table->date('period_start_on');
            $table->date('period_end_on');
            $table->unsignedBigInteger('followers')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();
            $table->unsignedBigInteger('impressions')->nullable();
            $table->unsignedBigInteger('reach')->nullable();
            $table->unsignedBigInteger('engagements')->nullable();
            $table->unsignedBigInteger('profile_visits')->nullable();
            $table->unsignedBigInteger('link_clicks')->nullable();
            $table->unsignedBigInteger('video_views')->nullable();
            $table->text('note')->nullable();
            $this->status($table, 'source')->default('manual');
            $table->unsignedBigInteger('file_object_id')->nullable();
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique(['profile_id', 'platform', 'period_start_on', 'period_end_on'], 'uk_social_metric_entries_period');
            $table->foreign('profile_id', 'fk_social_metric_entries_profile')
                ->references('id')->on('social_profiles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('file_object_id', 'fk_social_metric_entries_report_file')
                ->references('id')->on('file_objects')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_metric_entries', 'platform', self::PLATFORMS);
        $this->enumCheck('social_metric_entries', 'source', ['manual', 'upload']);
        $this->check(
            'social_metric_entries',
            'ck_social_metric_entries_period',
            '`period_end_on` >= `period_start_on`',
        );
        $this->personnelForeignKeys('social_metric_entries');

        // SON TABLO: SchemaReadiness 'B31' imzasi. Bu tablo en sonda kalmali.
        Schema::create('social_responsible_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('position_id');
            $this->status($table)->default('active');
            $this->auditCreated($table);
            $this->auditUpdated($table);

            $table->unique('position_id', 'uk_social_responsible_positions_position');
            $table->foreign('position_id', 'fk_social_responsible_positions_position')
                ->references('id')->on('positions')->restrictOnDelete()->restrictOnUpdate();
        });
        $this->enumCheck('social_responsible_positions', 'status', self::ACTIVE_STATUSES);
        $this->personnelForeignKeys('social_responsible_positions');
    }

    public function down(): void
    {
        $this->assertDestructiveAllowed();

        // Olusturmanin tersi sirayla: once cocuk tablolar, en son kok tablolar.
        foreach ([
            'social_responsible_positions' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_metric_entries' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_watch_links' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_watch_accounts' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_special_days' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_content_revisions' => ['created_by_personnel_id'],
            'social_reactions' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_comments' => ['created_by_personnel_id'],
            'social_content_media' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_content_platforms' => ['created_by_personnel_id'],
            'social_contents' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_categories' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_profile_links' => ['created_by_personnel_id', 'updated_by_personnel_id'],
            'social_profiles' => ['created_by_personnel_id', 'updated_by_personnel_id'],
        ] as $tableName => $auditColumns) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $blueprint) use ($tableName, $auditColumns): void {
                    foreach ($auditColumns as $column) {
                        $blueprint->dropForeign($this->fkName($tableName, $column));
                    }
                });
            }

            Schema::dropIfExists($tableName);
        }
    }
};
