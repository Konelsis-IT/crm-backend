<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Exports\Jobs\KonelsisExportCompletion;
use App\Filament\Support\TableConventions;
use App\Infrastructure\Console\SchemaChangeGuard;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Audit\ActorContext;
use App\Services\Platform\FeatureRegistry;
use App\Support\DisplayTime;
use App\Support\UploadLimits;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Exports\Jobs\ExportCompletion;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hareketi kimin yaptigi bilgisi istek/is basina tutulur.
        $this->app->scoped(ActorContext::class);

        // Sosyal medya sorumlulugu istek/is basina bir kez cozulur (B31, D-106):
        // politika ayni istekte defalarca sorar, sonuc ornekte bellekte tutulur.
        $this->app->scoped(SocialResponsibilityQueries::class);

        // Ozellik anahtarlari istek/is basina bir kez okunur (B39, D-128).
        $this->app->scoped(FeatureRegistry::class);

        // Excel disa aktarimi (D-110): dosya dogrudan iner, "dosya hazir"
        // bildirimi yalniz aktarilamayan satir varsa cikar.
        $this->app->bind(ExportCompletion::class, KonelsisExportCompletion::class);
    }

    public function boot(): void
    {
        // Uygulama semayi kendi basina degistiremez (M01 cikis kriteri).
        Event::listen(CommandStarting::class, SchemaChangeGuard::class);
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Kapali ozelligin kayit turleri butun yetki kontrollerinde reddedilir
        // (D-128): kaynak menusu, alt sekmeler, baglantilar ve dosya uclari
        // birlikte kapanir. Aciksa karar politikaya birakilir (null).
        Gate::before(fn (mixed $user, string $ability, array $arguments): ?bool => app(FeatureRegistry::class)->deniesAbility($arguments) ? false : null);

        $this->configureUploads();
        $this->configureFilamentDefaults();
    }

    /**
     * Gecici yukleme ust siniri (D-127): Livewire varsayilani 12 MB tum
     * yukleme alanlarini keserdi. Genel tavan belge siniridir (1 GB); her
     * alan kendi siniri ile daraltilir (fotograf 4-8 MB, sohbet 20 MB...).
     * Buyuk dosya uzun surebildiginden yukleme imzasi da uzatilir.
     */
    private function configureUploads(): void
    {
        config([
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.UploadLimits::documentMaxKb()],
            'livewire.temporary_file_upload.max_upload_time' => UploadLimits::uploadMinutes(),
        ]);
    }

    /**
     * Panel geneli bilesen varsayilanlari (10 Eylul 2026, kullanici karari):
     * - Tarih alanlari tek tiptir (16 Eylul 2026 kurali): tarayicinin yerel
     *   tarih girisi, gun.ay.yil bicimi, kisa (1/6) genislik. DateTimePicker
     *   kullanilmaz; tek tek `->native(false)` yazilmaz.
     * - Olustur / duzenle / goruntule modallari 6xl acilir: 12 sutunlu alan
     *   izgarasinda (FieldGrid) kisa alanlar okunur kalsin (D-80).
     * - "Olustur & yeni olustur" dugmesi sistem genelinde kapalidir (16 Eylul
     *   2026 kullanici karari); hicbir kaynak veya eylem onu yeniden acmaz.
     */
    private function configureFilamentDefaults(): void
    {
        // Kayitlar UTC; ekranda kurum saati (22 Eylul 2026 kullanici talimati, DisplayTime).
        FilamentTimezone::set(DisplayTime::zone());
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native()->displayFormat('d.m.Y'));
        TimePicker::configureUsing(fn (TimePicker $picker) => $picker->native());
        CreateRecord::disableCreateAnother();
        CreateAction::configureUsing(fn (CreateAction $action) => $action->modalWidth(Width::SixExtraLarge)->createAnother(false));
        EditAction::configureUsing(fn (EditAction $action) => $action->modalWidth(Width::SixExtraLarge));
        ViewAction::configureUsing(fn (ViewAction $action) => $action->modalWidth(Width::SixExtraLarge));

        // Tablo kurallari (D-125): satir tiklamasi detaya gider, Goruntule / Ac
        // dugmesi yok, satir eylemleri simge + ipucu, bagli kayit tiklanabilir
        // ve simgeli, personel adinda kisi simgesi.
        TableConventions::register();
    }
}
