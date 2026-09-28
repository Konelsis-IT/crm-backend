<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicies;

use App\Enums\Approval\ApprovalPolicyStatus;
use App\Filament\Clusters\Settings;
use App\Filament\Resources\ApprovalPolicies\Pages\CreateApprovalPolicy;
use App\Filament\Resources\ApprovalPolicies\Pages\EditApprovalPolicy;
use App\Filament\Resources\ApprovalPolicies\Pages\ListApprovalPolicies;
use App\Filament\Resources\ApprovalPolicies\Pages\ViewApprovalPolicy;
use App\Filament\Resources\ApprovalPolicies\RelationManagers\VersionsRelationManager;
use App\Filament\Support\FieldGrid;
use App\Models\Approval\ApprovalPolicy;
use App\Services\Approval\Subjects\ApprovalSubjectRegistry;
use App\Enums\Platform\Feature;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Onay politikalari (12 SS2.1): Ayarlar kumesinde. Surumler ve adimlar
 * politika kartinin altindaki listelerden yonetilir.
 */
class ApprovalPolicyResource extends Resource
{
    protected static ?string $model = ApprovalPolicy::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 140;

    protected static ?string $recordTitleAttribute = 'name_tr';

    public static function getModelLabel(): string
    {
        return __('approval_policy.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('approval_policy.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled(Feature::ApprovalPolicies)
            && SchemaReadiness::hasBatch('B07')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('approval_policy.sections.main'))
                ->description(__('approval_policy.help.main'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    // Kod arayuzde yok (D-130): ApprovalPolicyService Turkce addan uretir.
                    Select::make('subject_type')
                        ->label(__('approval_policy.fields.subject_type'))
                        ->helperText(__('approval_policy.help.subject_type'))
                        ->options(fn (): array => app(ApprovalSubjectRegistry::class)->options())
                        ->required()
                        ->native(false)
                        ->disabled(fn (?ApprovalPolicy $record): bool => $record?->current_version_id !== null),
                    TextInput::make('name_tr')
                        ->label(__('approval_policy.fields.name_tr'))
                        ->required()
                        ->maxLength(255),
                    Select::make('status')
                        ->label(__('approval_policy.fields.status'))
                        ->options(ApprovalPolicyStatus::class)
                        ->default(ApprovalPolicyStatus::Draft->value)
                        ->required()
                        ->native(false)
                        ->visibleOn('edit'),
                    Hidden::make('row_version')->hiddenOn('create'),
                ])),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_tr')
                    ->label(__('approval_policy.fields.name_tr'))
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->limit(50),
                TextColumn::make('subject_type')
                    ->label(__('approval_policy.fields.subject_type'))
                    ->formatStateUsing(fn (string $state): string => __('approval_policy.subject_types.'.$state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('currentVersion.version_no')
                    ->label(__('approval_policy.fields.current_version'))
                    ->placeholder('-')
                    ->badge()
                    ->color('info'),
                TextColumn::make('currentVersion.mode')
                    ->label(__('approval_policy_version.fields.mode'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('approval_policy.fields.status'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('approval_policy.fields.status'))
                    ->options(ApprovalPolicyStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('name_tr');
    }

    public static function getRelations(): array
    {
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalPolicies::route('/'),
            'create' => CreateApprovalPolicy::route('/create'),
            'view' => ViewApprovalPolicy::route('/{record}'),
            'edit' => EditApprovalPolicy::route('/{record}/edit'),
        ];
    }
}
