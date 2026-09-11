<?php

declare(strict_types=1);

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Enums\Document\DocumentReviewDecision;
use App\Enums\Document\DocumentReviewType;
use App\Filament\Support\FieldGrid;
use App\Models\Document\DocumentReview;
use App\Services\Document\DocumentReviewService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Revizyonlara bagli kontrol/onay/kalite kararlari (M03 onay motoru
 * gelene kadar elle kaydedilir).
 */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedCheckBadge;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('document.relation.reviews.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('document.relation.reviews.title'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('document_revision_id')
                        ->label(__('document_revision.label'))
                        ->relationship(
                            'revision',
                            'revision_code',
                            modifyQueryUsing: fn ($query) => $query->where('document_id', $this->getOwnerRecord()->getKey()),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('reviewer_personnel_id')
                        ->label(__('document_review.fields.reviewer'))
                        ->relationship('reviewer', 'full_name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false),
                    Select::make('review_type')
                        ->label(__('document_review.fields.review_type'))
                        ->options(DocumentReviewType::class)
                        ->default(DocumentReviewType::Check->value)
                        ->required()
                        ->native(false),
                    Select::make('decision')
                        ->label(__('document_review.fields.decision'))
                        ->options(DocumentReviewDecision::class)
                        ->default(DocumentReviewDecision::Approved->value)
                        ->required()
                        ->native(false),
                    DateTimePicker::make('decided_at')
                        ->label(__('document_review.fields.decided_at'))
                        ->default(fn (): Carbon => Carbon::now())
                        ->required(),
                    Textarea::make('comment')
                        ->label(__('document_review.fields.comment'))
                        ->columnSpanFull(),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('document_review.label'))
            ->heading(__('document.relation.reviews.title'))
            ->recordTitleAttribute('decision')
            ->columns([
                TextColumn::make('revision.revision_code')
                    ->label(__('document_revision.label'))
                    ->placeholder('-'),
                TextColumn::make('reviewer.full_name')
                    ->label(__('document_review.fields.reviewer'))
                    ->placeholder('-'),
                TextColumn::make('review_type')
                    ->label(__('document_review.fields.review_type'))
                    ->badge(),
                TextColumn::make('decision')
                    ->label(__('document_review.fields.decision'))
                    ->badge(),
                TextColumn::make('decided_at')
                    ->label(__('document_review.fields.decided_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(fn (array $data): Model => app(DocumentReviewService::class)->create($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (DocumentReview $record, array $data): Model => app(DocumentReviewService::class)->update($record, $data)),
                DeleteAction::make()
                    ->using(fn (DocumentReview $record): bool => app(DocumentReviewService::class)->delete($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('decided_at', 'desc')
            ->emptyStateHeading(__('document.relation.reviews.empty'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge);
    }
}
