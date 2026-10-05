<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Platform\Feature;
use App\Enums\SocialMedia\SocialReminderStage;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Support\PlatformLogo;
use App\Filament\Support\SocialAppConfig;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Models\SocialMedia\SocialContentPlatform;
use App\Query\SocialMedia\SocialContentQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

/**
 * Pano: paylasim gunu gelen / yaklasan sosyal medya icerikleri (B31, D-106).
 *
 * - Gorunurluk: B31 + bayrak + personel + viewAny yetkisi VE (tam erisim ya da
 *   sorumlu gorevde olmak ya da kendi yaklasan icerigi bulunmak).
 * - Sorumlular ve tam erisimliler butun icerikleri, digerleri yalniz kendi
 *   hazirladiklarini gorur; bu ayrim SocialContentQueries::upcomingFor()
 *   icindedir. Burada Eloquent zinciri yoktur.
 * - D-146 (30 Eylul 2026 kullanici tasarimi): solda gun ve ay kutucugu, platform
 *   isaretleri, baslik, sagda "Bugün 12:00 / Yarın 10:00 / 3 gün sonra";
 *   geciken paylasimin solunda kirmizi serit. En yakin 5 paylasim; "Tümünü
 *   gör" Sosyal Medya ekranini acar, satira tiklayinca icerik acilir.
 */
class UpcomingSocialContentsWidget extends TableWidget
{
    private const LIMIT = 5;

    protected static ?int $sort = 30;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! SchemaReadiness::hasBatch('B31') || ! FeatureFlags::enabled(Feature::DashboardSocial) || ! FeatureFlags::enabled(Feature::SocialMedia)) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof Personnel || ! Gate::allows('viewAny', SocialContent::class)) {
            return false;
        }

        return self::seesAll($user)
            || app(SocialContentQueries::class)->hasOwnUpcoming((int) $user->getKey());
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        // canView() zaten personel sartini arar; tip guvencesi icin yinelenir.
        abort_unless($user instanceof Personnel, 403);

        return $table
            ->heading(__('social_content.widget.heading'))
            ->query(fn () => app(SocialContentQueries::class)->upcomingFor($user, self::LIMIT))
            ->headerActions([
                Action::make('show_all')
                    ->label(__('social_content.widget.show_all'))
                    ->link()
                    ->color('primary')
                    ->url(SocialContentResource::getUrl('index')),
            ])
            ->paginated(false)
            ->recordUrl(fn (SocialContent $record): string => SocialContentResource::getUrl('index', [
                SocialAppConfig::PARAM_CONTENT => $record->getKey(),
            ]))
            ->recordClasses(fn (SocialContent $record): ?string => $this->stage($record) === SocialReminderStage::Missed ? 'kc-tone-danger' : null)
            ->emptyStateHeading(__('social_content.widget.empty'))
            ->emptyStateDescription(__('social_content.widget.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('day')
                            ->state(fn (SocialContent $record): string => $this->day($record)?->format('d') ?? '–')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextColumn::make('month')
                            ->state(fn (SocialContent $record): string => $this->upper((string) $this->day($record)?->translatedFormat('M')))
                            ->size(TextSize::ExtraSmall)
                            ->color('gray'),
                    ])->grow(false)->extraAttributes(['class' => 'kc-date-tile']),
                    ImageColumn::make('logos')
                        ->state(fn (SocialContent $record): array => $record->platforms
                            ->map(fn (SocialContentPlatform $platform): string => PlatformLogo::url($platform->platform))
                            ->unique()
                            ->values()
                            ->all())
                        ->imageSize(20)
                        ->grow(false),
                    TextColumn::make('title')
                        ->weight(FontWeight::Medium)
                        ->wrap(),
                    TextColumn::make('when')
                        ->state(fn (SocialContent $record): string => $this->when($record))
                        ->size(TextSize::ExtraSmall)
                        ->color(fn (SocialContent $record): string => $this->stage($record) === SocialReminderStage::Missed ? 'danger' : 'gray')
                        ->grow(false)
                        ->alignEnd(),
                ]),
            ]);
    }

    private function stage(SocialContent $record): ?SocialReminderStage
    {
        return app(SocialContentQueries::class)->stageOf($record);
    }

    private function day(SocialContent $record): ?CarbonImmutable
    {
        return $record->planned_on !== null
            ? CarbonImmutable::parse($record->planned_on->format('Y-m-d'), DisplayTime::zone())->locale(app()->getLocale())
            : null;
    }

    /** Turkcede i -> İ, ı -> I ("Eki" -> "EKİ"). */
    private function upper(string $text): string
    {
        return mb_strtoupper(app()->getLocale() === 'tr' ? strtr($text, ['i' => 'İ', 'ı' => 'I']) : $text);
    }

    /** "Bugün 12:00", "Yarın 10:00", "3 gün sonra" ya da "2 gün gecikti". */
    private function when(SocialContent $record): string
    {
        if ($record->planned_on === null) {
            return '–';
        }

        $days = DisplayTime::daysFromToday($record->planned_on);
        $time = filled($record->planned_time) ? substr((string) $record->planned_time, 0, 5) : '';

        return trim(match (true) {
            $days < 0 => __('social_content.widget.when.missed', ['count' => abs($days)]),
            $days === 0 => __('social_content.widget.when.today', ['time' => $time]),
            $days === 1 => __('social_content.widget.when.tomorrow', ['time' => $time]),
            default => __('social_content.widget.when.in_days', ['count' => $days]),
        });
    }

    /** Butun icerikleri gorenler: tam erisim ya da sorumlu gorevdeki personel. */
    private static function seesAll(Personnel $personnel): bool
    {
        return app(RoleResolver::class)->hasFullAccess($personnel)
            || app(SocialResponsibilityQueries::class)->isResponsible((int) $personnel->getKey());
    }
}
