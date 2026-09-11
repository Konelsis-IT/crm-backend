<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Project\StageInstanceStatus;
use App\Enums\Project\WorkstreamStatus;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Models\Project\Project;
use App\Query\Project\ProjectStepReadiness;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;

/**
 * Proje calisma alani (ViewProject::content) icin Filament-native sema
 * parcalari: proje karti (simgeli, baglantili alanlar; tiklanabilir adres),
 * "su an / sirada / eksik" uyarisi, beklenti kontrol listesi, saha adresi
 * ozeti ve onay kapisi ozeti. Adim yapisi ProjectWizard'dadir; veri
 * App\Query\Project\ProjectStepReadiness'ten okunur.
 */
final class ProjectWorkspace
{
    public function __construct(private readonly ProjectStepReadiness $readiness) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function steps(Project $project): array
    {
        return $this->readiness->forProject($project);
    }

    public function stepIcon(string $groupCode): Heroicon
    {
        return match (strtoupper($groupCode)) {
            'PROJECT' => Heroicon::OutlinedClipboardDocumentCheck,
            'PROCUREMENT' => Heroicon::OutlinedShoppingCart,
            'ACCOUNTING' => Heroicon::OutlinedBanknotes,
            'LOGISTICS' => Heroicon::OutlinedTruck,
            'FIELD' => Heroicon::OutlinedWrenchScrewdriver,
            'SOFTWARE' => Heroicon::OutlinedCpuChip,
            default => Heroicon::OutlinedSquares2x2,
        };
    }

    /**
     * Adim rengi: odak = primary, tamamlanan = success, blokaj = danger,
     * suren = info, hazir = warning, henuz degil = gray.
     *
     * @param  array<string, mixed>  $step
     */
    public function stepColor(array $step): string
    {
        if ($step['is_current']) {
            return 'primary';
        }

        return match ($step['status']) {
            WorkstreamStatus::Completed, WorkstreamStatus::Waived => 'success',
            WorkstreamStatus::Blocked => 'danger',
            WorkstreamStatus::Active, WorkstreamStatus::Review => 'info',
            WorkstreamStatus::Ready => 'warning',
            default => 'gray',
        };
    }

    /**
     * @param  array<string, mixed>  $step
     */
    public function stepBadge(array $step): string
    {
        if ($step['is_current']) {
            return __('project.steps.current');
        }

        return match ($step['status']) {
            WorkstreamStatus::Completed, WorkstreamStatus::Waived => __('project.steps.done'),
            default => __('project.steps.upcoming'),
        };
    }

    /**
     * Proje karti: kapak gorseli, ad, rozetler ve simgeli/baglantili alanlar.
     * Musteri ve proje yoneticisi kendi kartlarina gider; adres tiklaninca
     * $siteAction (modal) acilir.
     *
     * @param  list<Action>  $headerActions
     */
    public function headerCard(Project $project, array $headerActions = [], ?Action $siteAction = null): Component
    {
        $coverUrl = $project->coverPhoto?->previewUrl();
        $code = $project->businessCode?->formatted_code ?? '-';
        $customer = $project->customerParty;
        $manager = $project->projectManager;
        $focus = $project->primaryFocusWorkstream?->group?->localizedName();

        $address = TextEntry::make('site_address')
            ->label(__('project.fields.site_address'))
            ->state($project->siteAddressLine() ?? __('project.steps.site_missing'))
            ->icon(Heroicon::OutlinedMapPin)
            ->iconColor('danger')
            ->weight(FontWeight::Medium)
            ->columnSpanFull();

        if ($siteAction !== null) {
            $address
                ->action($siteAction)
                ->helperText(__('project.help.site_click'));
        }

        $entries = [
            TextEntry::make('customer_party')
                ->label(__('project.fields.customer_party'))
                ->state($customer?->display_name ?? '-')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->iconColor('primary')
                ->color($customer !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($customer !== null && Gate::allows('view', $customer) ? PartyResource::getUrl('view', ['record' => $customer]) : null),
            TextEntry::make('project_manager')
                ->label(__('project.fields.project_manager'))
                ->state($manager?->full_name ?? '-')
                ->icon(Heroicon::OutlinedUserCircle)
                ->iconColor('primary')
                ->color($manager !== null ? 'primary' : 'gray')
                ->weight(FontWeight::SemiBold)
                ->url($manager !== null && Gate::allows('view', $manager) ? PersonnelResource::getUrl('view', ['record' => $manager]) : null),
            TextEntry::make('contract_value')
                ->label(__('project.fields.contract_value'))
                ->state($this->money($project->contract_value_snapshot, $project->currency_code))
                ->icon(Heroicon::OutlinedBanknotes)
                ->iconColor('success')
                ->weight(FontWeight::SemiBold),
            TextEntry::make('planned_dates')
                ->label(__('project.fields.planned_dates'))
                ->state(($project->planned_start_on?->format('d.m.Y') ?? '-').' → '.($project->planned_finish_on?->format('d.m.Y') ?? '-'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->iconColor('warning'),
            TextEntry::make('actual_dates')
                ->label(__('project.fields.actual_dates'))
                ->state(($project->actual_start_on?->format('d.m.Y') ?? '-').' → '.($project->actual_finish_on?->format('d.m.Y') ?? '-'))
                ->icon(Heroicon::OutlinedClock)
                ->iconColor('gray'),
            TextEntry::make('current_focus')
                ->label(__('project.fields.current_focus'))
                ->state($focus ?? __('project.steps.none'))
                ->badge()
                ->color($focus !== null ? 'primary' : 'gray')
                ->icon(Heroicon::OutlinedPlayCircle),
            $address,
        ];

        $badges = [
            Text::make($code)->badge()->color('gray')->icon(Heroicon::OutlinedRocketLaunch),
            Text::make($project->status->getLabel())->badge()->color($project->status->getColor()),
            Text::make($project->origin?->getLabel() ?? '-')->badge()->color($project->origin?->getColor() ?? 'gray'),
            Text::make($project->criticality_profile->getLabel())->badge()->color('warning'),
        ];

        $media = $coverUrl !== null
            ? Image::make($coverUrl, (string) $project->name)->imageHeight('12rem')
            : Icon::make(Heroicon::OutlinedPhoto)->color('gray');

        return Section::make(__('project.sections.header'))
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->compact()
            ->headerActions($headerActions)
            ->components([
                Grid::make(['default' => 1, 'lg' => 4])
                    ->components([
                        Group::make([$media])->columnSpan(1),
                        Group::make([
                            Text::make((string) $project->name)->size(TextSize::Large)->weight(FontWeight::Bold),
                            Flex::make($badges),
                            Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries),
                            TextEntry::make('description')
                                ->label(__('project.fields.description'))
                                ->state((string) $project->description)
                                ->icon(Heroicon::OutlinedDocumentText)
                                ->iconColor('gray')
                                ->hidden(blank($project->description)),
                        ])->columnSpan(['default' => 1, 'lg' => 3]),
                    ]),
            ]);
    }

    /**
     * Adim cubugu (Proje adimlari): her workstream bir kart; odak, tamamlanan
     * ve sirada olanlar renkle ayrilir. Beklenti ayrintisi her adimin
     * sekmesindeki "Bu adimda beklenenler" listesindedir.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    public function stepper(Project $project, array $steps): Component
    {
        $cards = [];

        foreach ($steps as $index => $step) {
            $color = $this->stepColor($step);
            $status = $step['status'] instanceof WorkstreamStatus ? $step['status']->getLabel() : (string) $step['status'];

            $cards[] = Section::make(__('project.tabs.step', ['order' => $index + 1, 'name' => $step['group_name']]))
                ->icon($this->stepIcon($step['group_code']))
                ->iconColor($color)
                ->description(__('project.steps.status_line', ['status' => $status]))
                ->compact()
                ->components([
                    Text::make($this->stepBadge($step))->badge()->color($color),
                    Text::make(__('project.steps.summary', ['met' => $step['mandatory_met'], 'total' => $step['mandatory_total']]))
                        ->color($step['is_ready'] ? 'success' : 'gray')
                        ->icon($step['is_ready'] ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedClock),
                ]);
        }

        return Section::make(__('project.sections.stepper'))
            ->icon(Heroicon::OutlinedForward)
            ->description(__('project.steps.other_departments'))
            ->components([
                Grid::make(['default' => 1, 'md' => 3, 'xl' => 6])->components($cards),
            ]);
    }

    /**
     * "Su an buradayiz / sirada ne var / ne eksik" uyarisi.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    public function stepCallout(Project $project, array $steps): Component
    {
        $current = null;

        foreach ($steps as $step) {
            if ($step['is_current']) {
                $current = $step;
                break;
            }
        }

        if ($current === null) {
            return Callout::make(__('project.steps.none'))->warning();
        }

        $next = $this->readiness->next($project);
        $missing = array_values(array_map(
            static fn (array $item): string => $item['name'],
            array_filter($current['items'], static fn (array $item): bool => $item['mandatory'] && ! $item['met']),
        ));

        $heading = __('project.steps.callout_current', ['step' => $current['group_name']]);
        $lines = [];

        if ($next !== null) {
            $lines[] = __('project.steps.callout_next', ['step' => $next->group?->localizedName() ?? '-']);
        } else {
            $lines[] = __('project.steps.callout_last');
        }

        if ($missing === []) {
            $lines[] = __('project.steps.callout_ready');
            $callout = Callout::make($heading)->success();
        } else {
            $lines[] = __('project.steps.callout_missing', ['count' => count($missing), 'items' => implode(', ', $missing)]);
            $callout = Callout::make($heading)->warning();
        }

        return $callout->description(implode(' ', $lines));
    }

    /** Saha adresi ozeti (Lojistik adiminda). */
    public function siteAddressSection(Project $project): Component
    {
        $lines = [
            __('project.fields.site_address').': '.($project->siteAddressLine() ?? '-'),
            __('project.fields.site_country').': '.($project->siteCountry?->name_tr ?? $project->site_country_code ?? '-'),
            __('project.fields.site_latitude').' / '.__('project.fields.site_longitude').': '
                .($project->site_latitude !== null ? $project->site_latitude.' / '.$project->site_longitude : '-'),
            __('project.fields.site_note').': '.($project->site_note ?? '-'),
        ];

        return Section::make(__('project.sections.site'))
            ->icon(Heroicon::OutlinedMapPin)
            ->compact()
            ->collapsible()
            ->components(array_map(static fn (string $line): Text => Text::make($line), $lines));
    }

    /** Onay kapisi ozeti: kod, ad ve durum rozetleri. */
    public function gatesSummary(Project $project): Component
    {
        $lines = [];

        foreach ($project->stageInstances()->with('stageNode')->get() as $instance) {
            $status = $instance->status;
            $color = match ($status) {
                StageInstanceStatus::Passed, StageInstanceStatus::ConditionallyPassed => 'success',
                StageInstanceStatus::Rejected => 'danger',
                StageInstanceStatus::Preparing, StageInstanceStatus::ReadyForReview, StageInstanceStatus::ApprovalPending, StageInstanceStatus::Reopened => 'info',
                default => 'gray',
            };
            $node = $instance->stageNode;
            $name = app()->getLocale() === 'en' ? ($node?->name_en ?? '') : ($node?->name_tr ?? '');

            $lines[] = Text::make(($node?->stage_code ?? '?').' · '.$name.' — '.$status->getLabel())
                ->badge()
                ->color($color);
        }

        if ($lines === []) {
            $lines[] = Text::make('-')->color('gray');
        }

        return Section::make(__('project.sections.gates'))
            ->icon(Heroicon::OutlinedFlag)
            ->compact()
            ->components([Flex::make($lines)]);
    }

    private function money(mixed $amount, ?string $currency): string
    {
        if ($amount === null) {
            return '-';
        }

        return Number::format((float) $amount, precision: 2, locale: 'tr').' '.($currency ?? '');
    }
}
