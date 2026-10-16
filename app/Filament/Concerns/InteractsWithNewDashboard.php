<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Enums\Approval\ApprovalDecisionKind;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Filament\Support\NewDashboardConfig;
use App\Filament\Support\PersonnelSelect;
use App\Models\Approval\ApprovalRequest;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Query\Approval\ApprovalQueries;
use App\Query\Notification\WarningTargetQueries;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Ui\NewDashboardQueries;
use App\Services\Approval\ApprovalRequestService;
use App\Services\Notification\UrgentWarningService;
use App\Services\Notification\WarningTarget;
use App\Support\Dashboard\DepartmentDashboards;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Departman panosunun (React, D-207 / D-208 / D-212) yonetici islemleri: UI
 * Deneme > Yeni pano ve Genel bakis ayni eylemleri kullanir. React dugmeleri
 * `$wire.mountAction(ad, argumanlar)` ile acar, pencereler Filament'indir;
 * basaridan sonra yeni veri `nd-config` olayiyla React'e gider.
 *
 * - ndApprove: "Onay ver" (onay talebi; aktif adimdaki onayci).
 * - ndWarnNow / ndWarnCustom: "Hemen uyar" (sabit ifade) / "Ozel bildirim".
 * - ndCallDepartment / ndAlertDepartment: "Departmani cagir" / "Uyari gonder".
 *
 * Kullanan sayfa kisinin panolarini ve basligi verir.
 */
trait InteractsWithNewDashboard
{
    /**
     * Sayfanin gosterdigi panolar (DepartmentDashboards sabitleri).
     *
     * @return list<string>
     */
    abstract protected function newDashboardPanels(): array;

    protected function newDashboardTitle(): ?string
    {
        return null;
    }

    public function ndApproveAction(): Action
    {
        return Action::make('ndApprove')
            ->label(__('new_dashboard.app.actions.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->modalHeading(__('new_dashboard.app.actions.approve'))
            ->modalDescription(fn (array $arguments): string => (string) ($this->approvalRequest($arguments)?->subject_label ?? ''))
            ->modalSubmitActionLabel(__('approval_request.actions.approve'))
            ->modalWidth(Width::Large)
            ->visible(fn (array $arguments): bool => $this->approvalRequest($arguments) instanceof ApprovalRequest)
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Textarea::make('comment')
                        ->label(__('approval_request.fields.comment'))
                        ->rows(3)
                        ->maxLength(1000),
                ])))
            ->action(function (array $data, array $arguments): void {
                $request = $this->approvalRequest($arguments);

                if (! $request instanceof ApprovalRequest) {
                    return;
                }

                try {
                    app(ApprovalRequestService::class)->decide($request, ApprovalDecisionKind::Approved, $data['comment'] ?? null);
                    DomainNotifications::success(__('approval_request.messages.decided'));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $this->refreshDashboard();
            });
    }

    public function ndWarnNowAction(): Action
    {
        return Action::make('ndWarnNow')
            ->label(__('new_dashboard.app.actions.warn_now'))
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('warning')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedBellAlert)
            ->modalHeading(__('new_dashboard.app.actions.warn_now'))
            ->modalDescription(fn (array $arguments): string => $this->warnDescription($arguments))
            ->modalSubmitActionLabel(__('new_dashboard.app.actions.warn_submit'))
            ->visible(fn (array $arguments): bool => $this->warningTarget($arguments) instanceof WarningTarget)
            ->action(function (array $arguments): void {
                $target = $this->warningTarget($arguments);

                if (! $target instanceof WarningTarget) {
                    return;
                }

                try {
                    // Departmana gonderme izni yoksa yalniz ilgili kisiler uyarilir.
                    $units = $this->warnings()->canWarn($this->viewer(), true) ? null : [];
                    $sent = $this->warnings()->warn($target, null, null, $units);
                    DomainNotifications::success(__('new_dashboard.app.messages.warned', ['count' => $sent]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $this->refreshDashboard();
            });
    }

    public function ndWarnCustomAction(): Action
    {
        return Action::make('ndWarnCustom')
            ->label(__('new_dashboard.app.actions.warn_custom'))
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalIcon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->modalHeading(__('new_dashboard.app.actions.warn_custom'))
            ->modalDescription(fn (array $arguments): string => (string) ($this->warningTarget($arguments)?->title ?? ''))
            ->modalSubmitActionLabel(__('new_dashboard.app.actions.send'))
            ->modalWidth(Width::TwoExtraLarge)
            ->visible(fn (array $arguments): bool => $this->warningTarget($arguments) instanceof WarningTarget)
            ->fillForm(function (array $arguments): array {
                $target = $this->warningTarget($arguments);

                return [
                    'personnel_ids' => $target?->personnelIds ?? [],
                    'unit_ids' => $target?->unitIds ?? [],
                ];
            })
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Select::make('personnel_ids')
                        ->label(__('new_dashboard.app.fields.personnel'))
                        ->tap(PersonnelSelect::all())
                        ->multiple()
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => $this->warnings()->canWarn($this->viewer())),
                    Select::make('unit_ids')
                        ->label(__('new_dashboard.app.fields.departments'))
                        ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                        ->multiple()
                        ->searchable()
                        ->native(false)
                        ->visible(fn (): bool => $this->warnings()->canWarn($this->viewer(), true)),
                    Textarea::make('message')
                        ->label(__('new_dashboard.app.fields.message'))
                        ->placeholder(__('new_dashboard.app.help.message'))
                        ->rows(4)
                        ->required()
                        ->maxLength(1000),
                ])))
            ->action(function (array $data, array $arguments): void {
                $target = $this->warningTarget($arguments);

                if (! $target instanceof WarningTarget) {
                    return;
                }

                try {
                    $sent = $this->warnings()->warn(
                        $target,
                        (string) ($data['message'] ?? ''),
                        array_values(array_map('intval', (array) ($data['personnel_ids'] ?? []))),
                        array_values(array_map('intval', (array) ($data['unit_ids'] ?? []))),
                    );
                    DomainNotifications::success(__('new_dashboard.app.messages.warned', ['count' => $sent]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }

                $this->refreshDashboard();
            });
    }

    public function ndCallDepartmentAction(): Action
    {
        return Action::make('ndCallDepartment')
            ->label(__('new_dashboard.app.actions.call_department'))
            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
            ->color('warning')
            ->modalIcon(Heroicon::OutlinedPhoneArrowUpRight)
            ->modalHeading(fn (array $arguments): string => __('new_dashboard.app.actions.call_department_named', ['department' => $this->unit($arguments)?->name ?? '-']))
            ->modalDescription(fn (array $arguments): string => __('new_dashboard.app.help.call_department', [
                'department' => $this->unit($arguments)?->name ?? '-',
                'count' => $this->memberCount($arguments),
            ]))
            ->modalSubmitActionLabel(__('new_dashboard.app.actions.call_submit'))
            ->modalWidth(Width::Large)
            ->visible(fn (array $arguments): bool => $this->managesFromDashboard() && $this->unit($arguments) instanceof OrgUnit && $this->warnings()->canWarn($this->viewer(), true))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Textarea::make('note')
                        ->label(__('new_dashboard.app.fields.note'))
                        ->rows(3)
                        ->maxLength(500),
                ])))
            ->action(function (array $data, array $arguments): void {
                $unit = $this->unit($arguments);

                if (! $unit instanceof OrgUnit) {
                    return;
                }

                try {
                    $sent = $this->warnings()->callDepartment($unit, $data['note'] ?? null);
                    DomainNotifications::success(__('new_dashboard.app.messages.called', ['count' => $sent, 'department' => $unit->name]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    public function ndAlertDepartmentAction(): Action
    {
        return Action::make('ndAlertDepartment')
            ->label(__('new_dashboard.app.actions.alert_department'))
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('warning')
            ->modalIcon(Heroicon::OutlinedBellAlert)
            ->modalHeading(fn (array $arguments): string => __('new_dashboard.app.actions.alert_department_named', ['department' => $this->laneFacts($arguments)['unit']?->name ?? '-']))
            ->modalDescription(fn (array $arguments): string => $this->alertDescription($arguments))
            ->modalSubmitActionLabel(__('new_dashboard.app.actions.warn_submit'))
            ->modalWidth(Width::Large)
            ->visible(fn (array $arguments): bool => $this->managesFromDashboard() && ($this->laneFacts($arguments)['unit'] ?? null) instanceof OrgUnit && $this->warnings()->canWarn($this->viewer(), true))
            ->schema(fn (Schema $schema): Schema => $schema
                ->columns(FieldGrid::MODAL_COLUMNS)
                ->components(FieldGrid::modal([
                    Textarea::make('note')
                        ->label(__('new_dashboard.app.fields.note'))
                        ->rows(3)
                        ->maxLength(500),
                ])))
            ->action(function (array $data, array $arguments): void {
                $facts = $this->laneFacts($arguments);
                $unit = $facts['unit'] ?? null;

                if (! $unit instanceof OrgUnit) {
                    return;
                }

                try {
                    $sent = $this->warnings()->alertDepartment($unit, $facts['label'], $facts['count'], $facts['oldest'], $facts['url'], $data['note'] ?? null);
                    DomainNotifications::success(__('new_dashboard.app.messages.alerted', ['count' => $sent, 'department' => $unit->name]));
                } catch (AbstractException $exception) {
                    DomainNotifications::failure($exception);
                }
            });
    }

    /** Islemden sonra panonun verisi React'e yeniden gider (wire:ignore koku yenilenmez). */
    private function refreshDashboard(): void
    {
        $this->dispatch('nd-config', config: $this->newDashboardConfig());
    }

    /**
     * Sayfanin panolari ve basligiyla pano verisi.
     *
     * @return array<string, mixed>
     */
    protected function newDashboardConfig(): array
    {
        return app(NewDashboardConfig::class)->make($this->newDashboardPanels(), $this->newDashboardTitle());
    }

    /** @param  array<string, mixed>  $arguments */
    private function approvalRequest(array $arguments): ?ApprovalRequest
    {
        $id = (int) ($arguments['request'] ?? 0);
        $viewer = $this->viewer();

        if ($id <= 0 || ! $viewer instanceof Personnel) {
            return null;
        }

        $request = app(NewDashboardQueries::class)->approvalRequests([$id])->first();

        return $request instanceof ApprovalRequest && app(ApprovalQueries::class)->canDecide($request, (int) $viewer->getKey()) ? $request : null;
    }

    /**
     * Hedef kayit; kisi kaydi goremiyorsa ya da hic kimseye uyari gonderemiyorsa null.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function warningTarget(array $arguments): ?WarningTarget
    {
        $type = (string) ($arguments['type'] ?? '');
        $id = (int) ($arguments['id'] ?? 0);
        $viewer = $this->viewer();

        if (! $this->managesFromDashboard() || ! in_array($type, WarningTargetQueries::TYPES, true) || $id <= 0 || ! $this->warnings()->canWarn($viewer)) {
            return null;
        }

        $queries = app(WarningTargetQueries::class);
        $record = $queries->record($type, $id);

        if ($record === null || (Gate::getPolicyFor($record) !== null && ! Gate::allows('view', $record))) {
            return null;
        }

        return $queries->target($type, $record);
    }

    /** @param  array<string, mixed>  $arguments */
    private function warnDescription(array $arguments): string
    {
        $target = $this->warningTarget($arguments);

        if (! $target instanceof WarningTarget) {
            return '';
        }

        $queries = app(WarningTargetQueries::class);
        $names = $queries->names($target->personnelIds, $this->warnings()->canWarn($this->viewer(), true) ? $target->unitIds : []);
        $who = array_merge($names['personnel'], array_map(fn (string $unit): string => __('new_dashboard.app.help.department_named', ['department' => $unit]), $names['units']));

        return __('new_dashboard.app.help.warn_now', [
            'subject' => $target->title,
            'who' => $who === [] ? '-' : implode(', ', $who),
            'message' => __('urgent_warning.item.body_fixed', ['sender' => (string) ($this->viewer()?->full_name ?? '')]),
        ]);
    }

    /** @param  array<string, mixed>  $arguments */
    private function alertDescription(array $arguments): string
    {
        $facts = $this->laneFacts($arguments);
        $unit = $facts['unit'] ?? null;

        if (! $unit instanceof OrgUnit) {
            return '';
        }

        return __('new_dashboard.app.help.alert_department', [
            'department' => $unit->name,
            'members' => app(WarningTargetQueries::class)->memberCount((int) $unit->getKey()),
            'topic' => $facts['label'],
            'count' => $facts['count'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{label: string, count: int, oldest: ?int, url: ?string, unit: ?OrgUnit}|array{}
     */
    private function laneFacts(array $arguments): array
    {
        $tab = (string) ($arguments['tab'] ?? '');
        $lane = (string) ($arguments['lane'] ?? '');

        if (! in_array($tab, ['executive', 'purchasing'], true) || $lane === '') {
            return [];
        }

        return app(NewDashboardConfig::class)->laneFacts($tab, $lane) ?? [];
    }

    /** @param  array<string, mixed>  $arguments */
    private function unit(array $arguments): ?OrgUnit
    {
        return app(OrganizationQueries::class)->unit((int) ($arguments['unit'] ?? 0));
    }

    /** @param  array<string, mixed>  $arguments */
    private function memberCount(array $arguments): int
    {
        $unit = $this->unit($arguments);

        return $unit instanceof OrgUnit ? app(WarningTargetQueries::class)->memberCount((int) $unit->getKey()) : 0;
    }

    /**
     * Hemen uyar, Ozel bildirim, Departmani cagir ve Uyari gonder yalniz Yonetici
     * panelinden (D-210); Onay ver her panelde onay sirasindaki kisiye.
     */
    private function managesFromDashboard(): bool
    {
        return in_array(DepartmentDashboards::EXECUTIVE, $this->newDashboardPanels(), true);
    }

    private function warnings(): UrgentWarningService
    {
        return app(UrgentWarningService::class);
    }

    private function viewer(): ?Personnel
    {
        $user = auth()->user();

        return $user instanceof Personnel ? $user : null;
    }
}
