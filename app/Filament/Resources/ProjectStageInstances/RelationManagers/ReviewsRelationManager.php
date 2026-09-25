<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProjectStageInstances\RelationManagers;

use App\Filament\Support\RowDetail;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Project\StageReview;
use App\Services\Project\StageReviewService;
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
        return __('stage_review.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stage_review.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        Textarea::make('comment')
                            ->label(__('stage_review.fields.comment'))
                            ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('stage_review.relation.title'))
            ->recordTitleAttribute('decision')
            ->columns([
                TextColumn::make('reviewer.full_name')
                    ->label(__('stage_review.fields.reviewer')),
                TextColumn::make('decision')
                    ->label(__('stage_review.fields.decision'))
                    ->badge(),
                TextColumn::make('conditions')
                    ->label(__('stage_review.fields.conditions'))
                    ->limit(40)
                    ->placeholder('-'),
                TextColumn::make('comment')
                    ->label(__('stage_review.fields.comment'))
                    ->limit(40)
                    ->placeholder('-'),
                TextColumn::make('decided_at')
                    ->label(__('stage_review.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                
            ])
            ->recordActions([
                // Satira tiklamak ayrinti penceresini acar (D-125); dugme gorunmez.
                RowDetail::action(),
                
            ])
            ->toolbarActions([])
            ->defaultSort('decided_at', 'desc')
            ->emptyStateHeading(__('stage_review.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }
}
