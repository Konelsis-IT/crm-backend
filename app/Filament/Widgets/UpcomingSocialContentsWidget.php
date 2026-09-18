<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SocialMedia\SocialReminderStage;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Support\SocialAppConfig;
use App\Models\Personnel\Personnel;
use App\Models\SocialMedia\SocialContent;
use App\Query\SocialMedia\SocialContentQueries;
use App\Query\SocialMedia\SocialResponsibilityQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

/**
 * Pano: paylasim gunu gelen / yaklasan sosyal medya icerikleri (B31, D-106;
 * kullanici istegi madde 7: "bugun paylasilacak, yarin paylasilacak, paylasim
 * gunu yaklasiyor seklinde temiz bir listeleme ... yaninda kucuk bir buton ile
 * paylasimin detayina gidilecek").
 *
 * - Gorunurluk: B31 + bayrak + personel + viewAny yetkisi VE (tam erisim ya da
 *   sorumlu gorevde olmak ya da kendi yaklasan icerigi bulunmak).
 * - Sorumlular ve tam erisimliler butun icerikleri, digerleri yalniz kendi
 *   hazirladiklarini gorur; bu ayrim SocialContentQueries::upcomingFor()
 *   icindedir. Burada Eloquent zinciri yoktur.
 * - Asama (Bugun / Yarin / Yaklasiyor / Gecikti) SocialContentQueries::stageOf()
 *   ile, yani modulun tek "bugun" kaynagindan (SocialClock) hesaplanir.
 * - Tek satir eylemi "Ac": Sosyal Medya sayfasini `icerik` parametresiyle acar.
 */
class UpcomingSocialContentsWidget extends TableWidget
{
    protected static ?int $sort = 30;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        if (! SchemaReadiness::hasBatch('B31') || ! FeatureFlags::enabled('social_media.admin_ui')) {
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

        $seesAll = self::seesAll($user);

        return $table
            ->heading(__('social_content.widget.heading'))
            ->description(__($seesAll ? 'social_content.widget.description_all' : 'social_content.widget.description'))
            // Siralama sorgudadir: plan tarihi, plan saati (en yakin ustte).
            ->query(fn () => app(SocialContentQueries::class)->upcomingFor($user))
            ->emptyStateHeading(__('social_content.widget.empty'))
            ->emptyStateDescription(__('social_content.widget.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('stage')
                    ->label(__('social_content.widget.columns.stage'))
                    ->state(fn (SocialContent $record): ?SocialReminderStage => app(SocialContentQueries::class)->stageOf($record))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->label(__('social_content.widget.columns.title'))
                    ->weight('semibold')
                    ->description(fn (SocialContent $record): ?string => $record->content_type?->getLabel())
                    ->wrap(),
                TextColumn::make('profile.name')
                    ->label(__('social_content.widget.columns.profile'))
                    ->placeholder('-'),
                TextColumn::make('platforms.platform')
                    ->label(__('social_content.widget.columns.platforms'))
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),
                TextColumn::make('planned_on')
                    ->label(__('social_content.widget.columns.planned_on'))
                    ->date('d.m.Y')
                    ->description(fn (SocialContent $record): ?string => filled($record->planned_time)
                        ? __('social_content.widget.at_time', ['time' => $record->planned_time])
                        : null),
                TextColumn::make('status')
                    ->label(__('social_content.widget.columns.status'))
                    ->badge(),
                TextColumn::make('createdBy.full_name')
                    ->label(__('social_content.widget.columns.creator'))
                    ->placeholder(__('activity.system'))
                    ->visible($seesAll),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('social_content.widget.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->button()
                    ->outlined()
                    ->size(Size::Small)
                    ->url(fn (SocialContent $record): string => SocialContentResource::getUrl('index', [
                        SocialAppConfig::PARAM_CONTENT => $record->getKey(),
                    ])),
            ]);
    }

    /** Butun icerikleri gorenler: tam erisim ya da sorumlu gorevdeki personel. */
    private static function seesAll(Personnel $personnel): bool
    {
        return app(RoleResolver::class)->hasFullAccess($personnel)
            || app(SocialResponsibilityQueries::class)->isResponsible((int) $personnel->getKey());
    }
}
