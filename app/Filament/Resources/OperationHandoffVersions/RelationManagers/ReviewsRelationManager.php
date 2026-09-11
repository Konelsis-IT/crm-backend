<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperationHandoffVersions\RelationManagers;

use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Acquisition\HandoffReview;
use App\Services\Acquisition\HandoffReviewService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCheckBadge;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('handoff_review.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('handoff_review.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('comment')
                            ->label(__('handoff_review.fields.comment'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('handoff_review.relation.title'))
            ->recordTitleAttribute('decision')
            ->columns([
                TextColumn::make('reviewer.full_name')
                    ->label(__('handoff_review.fields.reviewer')),
                TextColumn::make('decision')
                    ->label(__('handoff_review.fields.decision'))
                    ->badge(),
                TextColumn::make('comment')
                    ->label(__('handoff_review.fields.comment'))
                    ->limit(60)
                    ->placeholder('-'),
                TextColumn::make('decided_at')
                    ->label(__('handoff_review.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                
            ])
            ->toolbarActions([])
            ->defaultSort('decided_at', 'desc')
            ->emptyStateHeading(__('handoff_review.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }
}
