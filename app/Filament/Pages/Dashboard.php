<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Notification\AnnouncementAudience;
use App\Enums\Notification\AnnouncementPriority;
use App\Exceptions\AbstractException;
use App\Filament\Support\DomainNotifications;
use App\Filament\Support\FieldGrid;
use App\Models\Personnel\Personnel;
use App\Query\Authorization\RoleQueries;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Personnel\PersonnelQueries;
use App\Services\Notification\AnnouncementService;
use App\Services\Notification\AudienceResolver;
use App\Services\Platform\SchemaReadiness;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Pano (D-82): ustte "Bildirim gonder" eylemi — gonderenin izinli oldugu
 * kitleye (ekibi, departman, rol, secili kisiler, herkes) Filament zili
 * bildirimi. Sag ust kullanici menusundeki "Bildirim gonder" de buraya
 * `?bildirim=gonder` ile gelir ve pencereyi dogrudan acar.
 */
class Dashboard extends BaseDashboard
{
    public const SEND_ACTION = 'send_notification';

    public function mount(): void
    {
        if (request()->query('bildirim') === 'gonder' && $this->canSendNotification()) {
            $this->mountAction(self::SEND_ACTION);
        }
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make(self::SEND_ACTION)
                ->label(__('announcement.actions.send'))
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('primary')
                ->visible(fn (): bool => $this->canSendNotification())
                ->modalHeading(__('announcement.actions.send'))
                ->modalDescription(__('announcement.help.send'))
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalSubmitActionLabel(__('announcement.actions.submit'))
                ->schema(fn (Schema $schema): Schema => $schema
                    ->columns(FieldGrid::MODAL_COLUMNS)
                    ->components(FieldGrid::modal([
                        Select::make('audience_kind')
                            ->label(__('announcement.fields.audience_kind'))
                            ->options($this->audienceOptions())
                            ->required()
                            ->live()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('priority')
                            ->label(__('announcement.fields.priority'))
                            ->options(AnnouncementPriority::options())
                            ->default(AnnouncementPriority::Normal->value)
                            ->required()
                            ->native(false)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        Select::make('org_unit_id')
                            ->label(__('announcement.fields.department'))
                            ->options(fn (): array => app(OrganizationQueries::class)->orgUnitOptions())
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Department->value)
                            ->visible(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Department->value)
                            ->columnSpan(FieldGrid::FULL),
                        Select::make('role_id')
                            ->label(__('announcement.fields.role'))
                            ->options(fn (): array => app(RoleQueries::class)->roleOptions())
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Role->value)
                            ->visible(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Role->value)
                            ->columnSpan(FieldGrid::FULL),
                        Select::make('audience_ids')
                            ->label(__('announcement.fields.personnel'))
                            ->options(fn (): array => app(PersonnelQueries::class)->personnelOptions())
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->required(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Personnel->value)
                            ->visible(fn (Get $get): bool => $get('audience_kind') === AnnouncementAudience::Personnel->value)
                            ->columnSpan(FieldGrid::FULL),
                        TextInput::make('title')
                            ->label(__('announcement.fields.title'))
                            ->required()
                            ->maxLength(200)
                            ->columnSpan(FieldGrid::FULL),
                        Textarea::make('body')
                            ->label(__('announcement.fields.body'))
                            ->required()
                            ->rows(5)
                            ->maxLength(4000),
                        TextInput::make('action_url')
                            ->label(__('announcement.fields.action_url'))
                            ->helperText(__('announcement.help.action_url'))
                            ->url()
                            ->maxLength(2000)
                            ->columnSpan(FieldGrid::FULL),
                    ])))
                ->action(function (array $data): void {
                    $kind = (string) ($data['audience_kind'] ?? '');
                    $audienceId = match ($kind) {
                        AnnouncementAudience::Department->value => $data['org_unit_id'] ?? null,
                        AnnouncementAudience::Role->value => $data['role_id'] ?? null,
                        default => null,
                    };

                    try {
                        $announcement = app(AnnouncementService::class)->send([
                            'audience_kind' => $kind,
                            'audience_id' => $audienceId,
                            'audience_ids' => $data['audience_ids'] ?? [],
                            'priority' => $data['priority'] ?? null,
                            'title' => $data['title'] ?? '',
                            'body' => $data['body'] ?? '',
                            'action_url' => $data['action_url'] ?? null,
                        ]);

                        DomainNotifications::success(__('announcement.messages.sent', ['count' => $announcement->recipient_count]));
                    } catch (AbstractException $exception) {
                        DomainNotifications::failure($exception);
                    }
                }),
        ];
    }

    public function getColumns(): int | array
    {
        return ['default' => 1, 'lg' => 2];
    }

    /**
     * @return array<string, string>
     */
    private function audienceOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof Personnel) {
            return [];
        }

        $options = [];

        foreach (app(AudienceResolver::class)->permittedKinds($user) as $kind) {
            $options[$kind->value] = $kind->getLabel();
        }

        return $options;
    }

    private function canSendNotification(): bool
    {
        $user = auth()->user();

        return SchemaReadiness::hasBatch('B11A')
            && $user instanceof Personnel
            && app(AudienceResolver::class)->permittedKinds($user) !== [];
    }
}
