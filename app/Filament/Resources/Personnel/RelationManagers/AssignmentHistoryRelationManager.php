<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel\RelationManagers;

use App\Filament\Support\RowDetail;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Organizasyon birimi/gorev gecmisi.
 *
 * Personelin kartinin altinda salt okunur bir listedir; ayri bir
 * olusturma/duzenleme formu yoktur, kayitlar PersonnelService tarafindan
 * otomatik uretilir. Dogrudan amir gecmisi burada degil
 * ReportingHistoryRelationManager'dadir (D-62).
 */
class AssignmentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'assignmentHistory';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedClock;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('assignment.relation.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assignment.relation.title'))
            ->description(__('assignment.relation.help'))
            ->columns([
                TextColumn::make('orgUnit.name')
                    ->label(__('assignment.fields.department'))
                    ->placeholder('-'),
                TextColumn::make('job_title')
                    ->label(__('assignment.fields.job_title'))
                    ->placeholder('-'),
                TextColumn::make('effective_from')
                    ->label(__('assignment.fields.effective_from'))
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label(__('assignment.fields.effective_to'))
                    ->date('d.m.Y')
                    ->placeholder(__('assignment.messages.ongoing')),
            ])
            ->headerActions([])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
            ])
            ->toolbarActions([])
            ->defaultSort('effective_from', 'desc')
            ->emptyStateHeading(__('assignment.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
