<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions;

use App\Filament\Clusters\Settings;
use App\Filament\Resources\ApprovalPolicyVersions\Pages\EditApprovalPolicyVersion;
use App\Filament\Resources\ApprovalPolicyVersions\Pages\ListApprovalPolicyVersions;
use App\Filament\Resources\ApprovalPolicyVersions\Pages\ViewApprovalPolicyVersion;
use App\Filament\Resources\ApprovalPolicyVersions\RelationManagers\StepsRelationManager;
use App\Filament\Resources\ApprovalPolicyVersions\Schemas\PolicyVersionForm;
use App\Models\Approval\ApprovalPolicyVersion;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Politika surumu (menude gorunmez): surumun ayarlari ve adimlari.
 * Politika kartindaki "Adimlari ac" buraya gelir.
 */
class ApprovalPolicyVersionResource extends Resource
{
    protected static ?string $model = ApprovalPolicyVersion::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 141;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'version_no';

    public static function getModelLabel(): string
    {
        return __('approval_policy_version.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('approval_policy_version.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('approvals.admin_ui')
            && SchemaReadiness::hasBatch('B07')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return PolicyVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy.code')
                    ->label(__('approval_policy.label')),
                TextColumn::make('version_no')
                    ->label(__('approval_policy_version.fields.version_no')),
                TextColumn::make('mode')
                    ->label(__('approval_policy_version.fields.mode'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('approval_policy_version.fields.status'))
                    ->badge(),
                TextColumn::make('published_at')
                    ->label(__('approval_policy_version.fields.published_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (ApprovalPolicyVersion $record): bool => $record->isDraft()),
            ])
            ->toolbarActions([])
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalPolicyVersions::route('/'),
            'view' => ViewApprovalPolicyVersion::route('/{record}'),
            'edit' => EditApprovalPolicyVersion::route('/{record}/edit'),
        ];
    }
}
