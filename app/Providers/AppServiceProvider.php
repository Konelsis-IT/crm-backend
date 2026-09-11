<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Console\SchemaChangeGuard;
use App\Services\Audit\ActorContext;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Support\Enums\Width;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hareketi kimin yaptigi bilgisi istek/is basina tutulur.
        $this->app->scoped(ActorContext::class);
    }

    public function boot(): void
    {
        // Uygulama semayi kendi basina degistiremez (M01 cikis kriteri).
        Event::listen(CommandStarting::class, SchemaChangeGuard::class);
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->configureFilamentDefaults();
    }

    /**
     * Panel geneli bilesen varsayilanlari (10 Eylul 2026, kullanici karari):
     * - Tarih/saat alanlari tarayicinin yerel tarih girisidir (takvim acilir
     *   metin kutusu degil); tek tek `->native(false)` yazilmaz.
     * - Olustur / duzenle / goruntule modallari 6xl acilir: 12 sutunlu alan
     *   izgarasinda (FieldGrid) kisa alanlar okunur kalsin (D-80).
     */
    private function configureFilamentDefaults(): void
    {
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native());
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native());
        TimePicker::configureUsing(fn (TimePicker $picker) => $picker->native());
        CreateAction::configureUsing(fn (CreateAction $action) => $action->modalWidth(Width::SixExtraLarge));
        EditAction::configureUsing(fn (EditAction $action) => $action->modalWidth(Width::SixExtraLarge));
        ViewAction::configureUsing(fn (ViewAction $action) => $action->modalWidth(Width::SixExtraLarge));

    }
}
