<?php

declare(strict_types=1);

namespace App\Filament\Resources\MeetingPlans\Pages;

use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Support\MeetingPlanAppConfig;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Gorusme plani takvimi (B34, D-109): ay takvimi React ile cizilir ve sosyal
 * medya takviminin ortak bilesenini kullanir (resources/js/social/social-calendar.js;
 * 21 Eylul 2026 kullanici karari: "kod tekrari yapmayalim"). Gorunum yalniz
 * stil baglantisini ve kok ogeyi icerir; betikler bu sayfaya ozel BODY_END
 * kancasindan gelir (filament.meetings.scripts).
 */
class MeetingPlanCalendar extends Page
{
    protected static string $resource = MeetingPlanResource::class;

    // Filament 5'te $view statik DEGILDIR (BasePage::$view).
    protected string $view = 'filament.meetings.calendar';

    public function getTitle(): string | Htmlable
    {
        return __('meeting_plan.plural_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label(__('meeting_plan.actions.list'))
                ->icon(Heroicon::OutlinedListBullet)
                ->color('gray')
                ->url(MeetingPlanResource::getUrl('list')),
            Action::make('create')
                ->label(__('meeting_plan.actions.create'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn (): bool => MeetingPlanResource::canCreate())
                ->url(MeetingPlanResource::getUrl('create')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'config' => MeetingPlanAppConfig::make(),
        ];
    }
}
