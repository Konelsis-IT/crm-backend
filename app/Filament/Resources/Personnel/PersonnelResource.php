<?php

declare(strict_types=1);

namespace App\Filament\Resources\Personnel;

use App\Filament\NavigationGroup;
use App\Filament\Resources\Personnel\Pages\CreatePersonnelRecord;
use App\Filament\Resources\Personnel\Pages\EditPersonnelRecord;
use App\Filament\Resources\Personnel\Pages\ListPersonnelRecords;
use App\Filament\Resources\Personnel\Pages\ViewPersonnelRecord;
use App\Filament\Resources\Personnel\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\Personnel\RelationManagers\AssignmentHistoryRelationManager;
use App\Filament\Resources\Personnel\RelationManagers\PersonnelCertificationsRelationManager;
use App\Filament\Resources\Personnel\RelationManagers\PositionAssignmentsRelationManager;
use App\Filament\Resources\WorkRequests\RelationManagers\IncomingWorkRequestsRelationManager;
use App\Filament\Resources\WorkRequests\RelationManagers\RequestedWorkRequestsRelationManager;
use App\Filament\Resources\Reports\RelationManagers\SubjectReportsRelationManager;
use App\Filament\Resources\Personnel\RelationManagers\ReportingHistoryRelationManager;
use App\Filament\Resources\Personnel\RelationManagers\TrainingAttendancesRelationManager;
use App\Filament\Resources\Personnel\Schemas\PersonnelForm;
use App\Filament\Resources\Personnel\Schemas\PersonnelInfolist;
use App\Filament\Resources\Personnel\Tables\PersonnelTable;
use App\Models\Personnel\Personnel;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PersonnelResource extends Resource
{
    protected static ?string $model = Personnel::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | UnitEnum | null $navigationGroup = NavigationGroup::Administrative;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getModelLabel(): string
    {
        return __('personnel.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('personnel.plural');
    }

    public static function canAccess(): bool
    {
        return FeatureFlags::enabled('personnel.admin_ui')
            && SchemaReadiness::hasBatch('B02')
            && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return PersonnelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonnelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonnelTable::configure($table);
    }

    /**
     * Personel Hareketleri ayri bir menu degil, personelin altinda bir
     * listedir. Sertifika/egitim iliskileri yalniz B13 semasi
     * uygulandiginda gorunur.
     */
    public static function getRelations(): array
    {
        $relations = [
            ActivitiesRelationManager::class,
        ];

        if (SchemaReadiness::hasBatch('B13')) {
            $relations[] = PersonnelCertificationsRelationManager::class;
            $relations[] = TrainingAttendancesRelationManager::class;
        }

        if (SchemaReadiness::hasBatch('B25')) {
            $relations[] = AssignmentHistoryRelationManager::class;
        }

        if (SchemaReadiness::hasBatch('B03')) {
            $relations[] = ReportingHistoryRelationManager::class;
            $relations[] = PositionAssignmentsRelationManager::class;
        }

        if (SchemaReadiness::hasBatch('B10A')) {
            $relations[] = SubjectReportsRelationManager::class;
        }

        if (SchemaReadiness::hasBatch('B11B')) {
            $relations[] = IncomingWorkRequestsRelationManager::class;
            $relations[] = RequestedWorkRequestsRelationManager::class;
        }

        return $relations;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonnelRecords::route('/'),
            'create' => CreatePersonnelRecord::route('/create'),
            'view' => ViewPersonnelRecord::route('/{record}'),
            'edit' => EditPersonnelRecord::route('/{record}/edit'),
        ];
    }
}
