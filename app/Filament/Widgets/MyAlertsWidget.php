<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Models\Notification\BusinessAlert;
use App\Models\Personnel\Personnel;
use App\Query\Notification\BusinessAlertQueries;
use App\Services\Authorization\RoleResolver;
use App\Services\Notification\BusinessAlertService;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;

/**
 * Pano: yaklasan / gecmis son tarih uyarilarim (D-82). Sahibi "Gordum"
 * der; system_admin ve auditor herkesin acik uyarisini gorur.
 */
class MyAlertsWidget extends TableWidget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return SchemaReadiness::hasBatch('B11A');
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $personnelId = (int) ($user?->getAuthIdentifier() ?? 0);
        $seesAll = $user instanceof Personnel
            && (app(RoleResolver::class)->hasFullAccess($user) || app(RoleResolver::class)->isAuditor($user));

        return $table
            ->heading(__('business_alert.widget.heading'))
            ->description(__($seesAll ? 'business_alert.widget.description_all' : 'business_alert.widget.description'))
            ->query(fn () => app(BusinessAlertQueries::class)->openForPanel($personnelId, $seesAll))
            ->emptyStateHeading(__('business_alert.widget.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('severity')
                    ->label(__('business_alert.fields.severity'))
                    ->badge(),
                TextColumn::make('title_key')
                    ->label(__('business_alert.fields.title'))
                    ->formatStateUsing(fn (BusinessAlert $record): string => $record->title())
                    ->description(fn (BusinessAlert $record): ?string => $record->project?->name)
                    ->wrap(),
                TextColumn::make('due_at')
                    ->label(__('business_alert.fields.due_at'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('owner.full_name')
                    ->label(__('business_alert.fields.owner'))
                    ->placeholder('-')
                    ->visible($seesAll),
                TextColumn::make('state')
                    ->label(__('business_alert.fields.state'))
                    ->badge(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('business_alert.actions.open'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (BusinessAlert $record): ?string => $record->url)
                    ->visible(fn (BusinessAlert $record): bool => filled($record->url)),
                Action::make('acknowledge')
                    ->label(__('business_alert.actions.acknowledge'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('gray')
                    ->visible(fn (BusinessAlert $record): bool => Gate::allows('acknowledge', $record))
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
}
