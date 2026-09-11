<?php

namespace Tests\Feature;

use App\Models\Personnel\Personnel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M01 kapsamiyla ayni tutulur. Proje kurali: kodlama ajanlari ve CI bu test
 * paketini calistirmaz (docs/ai/skills). Sema korumasi, DBA ilgili bayraklari
 * ayri bir test veritabani icin acmadikca migrate:fresh calistirilmasini
 * engeller.
 */
class FilamentLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_can_be_displayed_in_turkish(): void
    {
        $this->get('/admin/login?locale=tr')
            ->assertOk()
            ->assertSessionHas('locale', 'tr')
            ->assertSee('TR')
            ->assertSee('EN');
    }

    public function test_the_login_page_can_be_displayed_in_english(): void
    {
        $this->get('/admin/login?locale=en')
            ->assertOk()
            ->assertSessionHas('locale', 'en')
            ->assertSee('TR')
            ->assertSee('EN');
    }

    public function test_the_bootstrap_personnel_is_seeded_idempotently(): void
    {
        config()->set('konelsis.bootstrap_admin.email', 'yonetici@example.test');
        config()->set('konelsis.bootstrap_admin.password', 'bootstrap-parola-123');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Personnel::query()->where('normalized_email', 'yonetici@example.test')->count());
    }
}
