<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\QuickActions\QuickAction;
use App\Filament\Support\QuickActions\QuickActionCatalog;
use App\Models\Personnel\Personnel;
use App\Services\Personnel\PersonnelQuickActionService;
use App\Services\Platform\SchemaReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Hizli islemlerim (D-122): ust cubuktaki "Hizli islemler" dugmesinin
 * kisiye ozel icerigi. Sol menude yoktur; yalniz dugmedeki "Hizli islem
 * ekle" ile acilir. Sabit islemler gosterilir ama degistirilemez.
 */
class QuickActionSettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBolt;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'hizli-islemler';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user() instanceof Personnel;
    }

    public function getTitle(): string | Htmlable
    {
        return __('quick_action.settings.title');
    }

    public function getSubheading(): ?string
    {
        return __('quick_action.settings.subheading');
    }

    public function mount(): void
    {
        $this->form->fill([
            'codes' => app(QuickActionCatalog::class)->personalCodes((int) auth()->id()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $catalog = app(QuickActionCatalog::class);
        $ready = SchemaReadiness::hasBatch('B38');

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('quick_action.settings.fixed'))
                    ->description(__('quick_action.settings.fixed_help'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->compact()
                    ->schema([
                        TextEntry::make('fixed')
                            ->label(__('quick_action.settings.fixed'))
                            ->hiddenLabel()
                            ->state(array_map(fn (QuickAction $action): string => $action->label, $catalog->fixed()))
                            ->badge()
                            ->color('primary'),
                    ]),
                Section::make(__('quick_action.settings.personal'))
                    ->description($ready
                        ? __('quick_action.settings.personal_help', ['max' => QuickActionCatalog::MAX_PERSONAL])
                        : __('quick_action.settings.pending_schema'))
                    ->icon(Heroicon::OutlinedBolt)
                    ->compact()
                    ->schema([
                        CheckboxList::make('codes')
                            ->label(__('quick_action.settings.choices'))
                            ->options(fn (): array => $catalog->selectableOptions())
                            ->maxItems(QuickActionCatalog::MAX_PERSONAL)
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->disabled(! $ready),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('quick_action.settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s'])
                            ->visible(fn (): bool => SchemaReadiness::hasBatch('B38')),
                    ])
                        ->alignment(Alignment::Start)
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $catalog = app(QuickActionCatalog::class);

        app(PersonnelQuickActionService::class)->sync(
            (int) auth()->id(),
            $catalog->sanitize((array) ($state['codes'] ?? [])),
        );

        Notification::make()
            ->title(__('quick_action.settings.saved'))
            ->success()
            ->send();

        $this->form->fill(['codes' => $catalog->personalCodes((int) auth()->id())]);
    }
}
