<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkRequests\RelationManagers;

use App\Filament\Resources\WorkRequests\Pages\CreateWorkRequest;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Models\Party\Party;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\Project;
use App\Models\WorkRequest\WorkRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Ilgili kaydin altindaki talepler (kullanici karari, 12 Eylul 2026): personel,
 * departman, proje, urun/bilesen ve musteri (taraf) kartlarinda "Talepler"
 * gorunur. Alt siniflar iliski adini ve basligi verir; tablo talep listesiyle
 * aynidir. "Talep ac" bu kayit on secili olarak olusturma sayfasina goturur.
 */
abstract class WorkRequestsRelationManager extends RelationManager
{
    protected static string | BackedEnum | null $icon = Heroicon::OutlinedInboxArrowDown;

    /** lang: work_request.relation.{key} / help_{key} */
    protected static string $titleKey = 'related';

    /** "Talep ac" dugmesi bu listede gosterilsin mi? */
    protected static bool $allowsCreate = true;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('work_request.relation.'.static::$titleKey);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return WorkRequestResource::canAccess();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();

        return WorkRequestResource::table($table)
            ->heading(__('work_request.relation.'.static::$titleKey))
            ->description(__('work_request.relation.help_'.static::$titleKey))
            ->headerActions([
                Action::make('open_request')
                    ->label(__('work_request.actions.create'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->visible(fn (): bool => static::$allowsCreate
                        && self::prefillParams($owner) !== []
                        && auth()->user()?->can('create', WorkRequest::class))
                    ->url(fn (): string => WorkRequestResource::getUrl('create', self::prefillParams($owner))),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('work_request.actions.open'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (WorkRequest $record): string => WorkRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('work_request.relation.empty'))
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown);
    }

    /**
     * Sahip kayda gore olusturma sayfasi sorgu parametreleri.
     *
     * @return array<string, int>
     */
    private static function prefillParams(Model $owner): array
    {
        $id = (int) $owner->getKey();

        return match (true) {
            $owner instanceof Personnel => ['hedef_personel' => $id],
            $owner instanceof OrgUnit => [CreateWorkRequest::QUERY_TARGET_UNIT => $id],
            $owner instanceof Project => ['proje' => $id],
            $owner instanceof Party => ['musteri' => $id],
            $owner instanceof ComponentDefinition => ['bilesen' => $id],
            default => [],
        };
    }
}
