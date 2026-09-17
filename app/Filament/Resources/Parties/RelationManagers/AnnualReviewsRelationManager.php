<?php

declare(strict_types=1);

namespace App\Filament\Resources\Parties\RelationManagers;

use App\Enums\Party\AnnualReviewOutcome;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Party\PartyAnnualReview;
use App\Services\Party\PartyAnnualReviewService;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AnnualReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'annualReviews';

    protected static string | BackedEnum | null $icon = Heroicon::OutlinedStar;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('party_annual_review.relation.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('party_annual_review.sections.main'))
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                        TextInput::make('review_year')
                            ->label(__('party_annual_review.fields.review_year'))
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->required(),
                        Select::make('reviewer_employee_id')
                            ->label(__('party_annual_review.fields.reviewer'))
                            ->relationship('reviewer', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('outcome')
                            ->label(__('party_annual_review.fields.outcome'))
                            ->options(AnnualReviewOutcome::class)
                            ->default(AnnualReviewOutcome::Pending->value)
                            ->required()
                            ->native(false),
                        TextInput::make('score')
                            ->label(__('party_annual_review.fields.score'))
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->maxValue(100),
                        DatePicker::make('reviewed_at')
                            ->label(__('party_annual_review.fields.reviewed_at'))
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('next_review_on')
                            ->label(__('party_annual_review.fields.next_review_on'))
                            ->displayFormat('d.m.Y'),
                        Textarea::make('summary')
                            ->label(__('party_annual_review.fields.summary'))
                            ->columnSpanFull(),
                        Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('party_annual_review.label'))
            ->heading(__('party_annual_review.relation.title'))
            ->recordTitleAttribute('review_year')
            ->columns([
                TextColumn::make('review_year')
                    ->label(__('party_annual_review.fields.review_year'))
                    ->sortable(),
                TextColumn::make('reviewer.full_name')
                    ->label(__('party_annual_review.fields.reviewer')),
                TextColumn::make('outcome')
                    ->label(__('party_annual_review.fields.outcome'))
                    ->badge(),
                TextColumn::make('score')
                    ->label(__('party_annual_review.fields.score'))
                    ->placeholder('-'),
                TextColumn::make('next_review_on')
                    ->label(__('party_annual_review.fields.next_review_on'))
                    ->date('d.m.Y')
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): Model {
                        $data['party_id'] = $this->getOwnerRecord()->getKey();

                        try {
                            return app(PartyAnnualReviewService::class)->create($data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (PartyAnnualReview $record, array $data): Model {
                        try {
                            return app(PartyAnnualReviewService::class)->update($record, $data);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            throw new Halt;
                        }
                    }),
                DeleteAction::make()
                    ->using(function (PartyAnnualReview $record): bool {
                        try {
                            return app(PartyAnnualReviewService::class)->delete($record);
                        } catch (AbstractException $exception) {
                            DomainNotifications::failure($exception);

                            return false;
                        }
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('review_year', 'desc')
            ->emptyStateHeading(__('party_annual_review.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedStar);
    }
}
