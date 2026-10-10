<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Notification\AlertState;
use App\Enums\Platform\Feature;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Models\Notification\BusinessAlert;
use App\Models\Personnel\Personnel;
use App\Query\Notification\BusinessAlertQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Notification\BusinessAlertService;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\DisplayTime;
use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

/**
 * Pano: yaklasan / gecmis son tarih uyarilarim (D-82). Sahibi "Gordum"
 * der; system_admin ve auditor herkesin acik uyarisini gorur.
 *
 * D-146 (30 Eylul 2026 kullanici tasarimi): her uyari bir kart. Solda
 * yakinliga gore simge (3 gun ve alti kirmizi unlem, 4-6 gun turuncu saat,
 * daha uzak gri tur simgesi), ortada baslik ve proje, sagda tarih ve kalan
 * gun ayni renkte. Ilk dort kart; "Tümünü gör" hepsini acar. Karta tiklayinca
 * uyarinin kaydi acilir.
 */
class MyAlertsWidget extends TableWidget
{
    private const COLLAPSED = 4;

    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    public bool $showAll = false;

    public static function canView(): bool
    {
        return FeatureFlags::enabled(Feature::DashboardAlerts)
            && FeatureFlags::enabled(Feature::BusinessAlerts)
            && SchemaReadiness::hasBatch('B11A');
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $personnelId = (int) ($user?->getAuthIdentifier() ?? 0);
        $seesAll = $user instanceof Personnel
            && (app(RoleResolver::class)->hasFullAccess($user) || app(RoleResolver::class)->isAuditor($user));
        $queries = app(BusinessAlertQueries::class);
        $total = $queries->openCountForPanel($personnelId, $seesAll);

        return $table
            ->heading(__('business_alert.widget.heading'))
            ->query(fn () => $queries->upcomingForPanel($personnelId, $seesAll, $this->showAll ? null : self::COLLAPSED))
            ->headerActions([
                Action::make('toggle_all')
                    ->label(__($this->showAll ? 'business_alert.widget.show_less' : 'business_alert.widget.show_all'))
                    ->link()
                    ->color('primary')
                    ->visible($total > self::COLLAPSED)
                    ->action(function (): void {
                        $this->showAll = ! $this->showAll;
                        // Tablo istek basinda kuruldu; yeni sinirla yeniden kurulur.
                        $this->resetTable();
                    }),
            ])
            ->contentGrid(['default' => 1])
            ->paginated($this->showAll ? [10, 25] : false)
            ->emptyStateHeading(__('business_alert.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->recordUrl(fn (BusinessAlert $record): ?string => filled($record->url) ? $record->url : null)
            ->columns([
                Split::make([
                    // Ekran okuyucu seviye adini okur.
                    IconColumn::make('kind')
                        ->state(fn (BusinessAlert $record): string => (string) ($record->severity?->getLabel() ?? ''))
                        ->icon(fn (BusinessAlert $record): Heroicon => $this->icon($record))
                        ->color(fn (BusinessAlert $record): string => $this->tone($record))
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('title_key')
                            ->formatStateUsing(fn (BusinessAlert $record): string => $record->title())
                            ->weight(FontWeight::SemiBold)
                            ->wrap(),
                        TextColumn::make('subject')
                            ->state(fn (BusinessAlert $record): ?string => $this->subject($record, $seesAll))
                            ->size(TextSize::Small)
                            ->color('gray'),
                    ]),
                    Stack::make([
                        TextColumn::make('due_at')
                            ->formatStateUsing(fn (BusinessAlert $record): string => $this->dueDay($record))
                            ->weight(FontWeight::SemiBold)
                            ->color(fn (BusinessAlert $record): string => $this->tone($record)),
                        TextColumn::make('remaining')
                            ->state(fn (BusinessAlert $record): string => $this->remaining($record))
                            ->size(TextSize::Small)
                            ->color(fn (BusinessAlert $record): string => $this->tone($record)),
                    ])->alignment(Alignment::End)->grow(false),
                ]),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label(__('business_alert.actions.acknowledge'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (BusinessAlert $record): bool => $record->state === AlertState::Open && Gate::allows('acknowledge', $record))
                    ->action(function (BusinessAlert $record): void {
                        try {
                            app(BusinessAlertService::class)->acknowledge($record);
                            DomainNotifications::success(__('business_alert.messages.acknowledged'));
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);
                        }
                    }),
            ]);
    }

    /** Kurum gunune gore kalan gun (gecmis icin negatif). */
    private function days(BusinessAlert $record): ?int
    {
        return $record->due_at !== null
            ? DisplayTime::daysFromToday($record->due_at->copy()->timezone(DisplayTime::zone()))
            : null;
    }

    /** Renk: 3 gun ve alti kirmizi, 4-6 gun turuncu, daha uzak notr. */
    private function tone(BusinessAlert $record): string
    {
        $days = $this->days($record);

        return match (true) {
            $days === null => 'gray',
            $days <= 3 => 'danger',
            $days <= 6 => 'warning',
            default => 'gray',
        };
    }

    private function icon(BusinessAlert $record): Heroicon
    {
        return match ($this->tone($record)) {
            'danger' => Heroicon::ExclamationCircle,
            'warning' => Heroicon::OutlinedClock,
            default => match (true) {
                str_starts_with((string) $record->trigger_code, 'deadline.contract') => Heroicon::OutlinedDocumentText,
                $record->trigger_code === 'deadline.tender' => Heroicon::OutlinedMegaphone,
                $record->trigger_code === 'deadline.certification' => Heroicon::OutlinedAcademicCap,
                default => Heroicon::OutlinedCalendarDays,
            },
        };
    }

    /** Alt satir: proje; yoksa (herkesi gorene) sorumlu. Goruldu ise eklenir. */
    private function subject(BusinessAlert $record, bool $seesAll): ?string
    {
        $parts = array_filter([
            $record->project?->display_name ??($seesAll ? $record->owner?->full_name : null),
            $record->state === AlertState::Acknowledged ? $record->state->getLabel() : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function dueDay(BusinessAlert $record): string
    {
        return $record->due_at !== null
            ? $record->due_at->copy()->timezone(DisplayTime::zone())->locale(app()->getLocale())->translatedFormat('d M Y')
            : '-';
    }

    /** "Bugün", "Yarın", "5 gün kaldı" ya da "2 gün geçti". */
    private function remaining(BusinessAlert $record): string
    {
        $days = $this->days($record);

        return match (true) {
            $days === null => '-',
            $days === 0 => __('app.days.today'),
            $days === 1 => __('app.days.tomorrow'),
            $days > 1 => __('business_alert.widget.days_left', ['count' => $days]),
            default => __('business_alert.widget.days_over', ['count' => abs($days)]),
        };
    }
}
