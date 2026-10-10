<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ProjectScopeType;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Project\Project;
use App\Models\Project\ProjectScope;
use App\Services\Project\ProjectScopeService;
use App\Support\Acquisition\ScopeTypes;
use App\Support\Money;
use App\Support\Projects\ProjectNames;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Projenin "Proje tipi" secimi ve tip basina olculeri (B48, D-174). Tasarim
 * potansiyel is / teklifle ortaktir: ayni tip katalogu, tipin kendi simgesi ve
 * rengiyle coklu secim dugmeleri (D-163), secilen her tip icin kendi kucuk
 * bolumu (ProposalScopeSchema gibi). Isterler ayridir: teklifte birim fiyat /
 * maliyet / satis, projede olculer (MWp, MW, MWe / MWh, km, adet), sozlesme
 * tutari ve butce (ProjectScopeService::TYPE_FIELDS).
 *
 * Her sey `projects.scope_types` ozelligine ve B48'e baglidir; kapaliyken
 * alanlar gorunmez ve kaydedilmez.
 */
final class ProjectScopeSchema
{
    /** Miktar alanlarinin birimi (kart ve ozet icin). */
    private const UNITS = [
        'capacity_mwp' => 'MWp',
        'capacity_mw' => 'MW',
        'power_mwe' => 'MWe',
        'energy_mwh' => 'MWh',
        'length_km' => 'km',
    ];

    /** @var list<string> Para alanlari. */
    private const MONEY = ['contract_amount', 'budget_amount'];

    /**
     * Olusturma / duzenleme formundaki bolum: tip dugmeleri ve secili tiplerin
     * yarim genislikteki bolumleri.
     */
    public function formSection(): Section
    {
        $sections = [];

        foreach (ProjectScopeType::cases() as $type) {
            $sections[] = Section::make(__('project_scope.sections.'.$type->value))
                ->key('project-scope-'.$type->value)
                ->icon($type->getIcon())
                ->iconColor($type->getColor())
                ->compact()
                ->secondary()
                // Iki bolum yan yana; ic izgara yarim bolume gore (D-157).
                ->columnSpan(FieldGrid::HALF)
                ->columns(FieldGrid::HALF_COLUMNS)
                ->visible(fn (Get $get): bool => in_array($type->value, self::selected($get), true))
                ->components(FieldGrid::fields($this->typeFields($type), halfSection: true));
        }

        return Section::make(__('project.sections.scope'))
            ->key('project-scope')
            ->icon(Heroicon::OutlinedCube)
            ->columns(FieldGrid::COLUMNS)
            ->visible(fn (): bool => ProjectNames::scopeTypesEnabled())
            ->components([
                // D-163: tipler kendi simgesi ve rengiyle secim dugmesi (coklu secim).
                ToggleButtons::make('scope_types')
                    ->label(__('project.fields.scope_types'))
                    ->helperText(__('project.help.scope_types'))
                    // D-177: Otomasyon / Process yalniz B50 ve ozellik acikken secilir;
                    // projede kayitli tip secenekte kalir.
                    ->options(fn (?Model $record): array => self::typeOptions($record))
                    ->enum(ProjectScopeType::class)
                    ->multiple()
                    ->columns(['default' => 2, 'md' => 3, 'xl' => 6])
                    ->live()
                    ->dehydrated(fn (): bool => ProjectNames::scopeTypesEnabled())
                    ->columnSpan(FieldGrid::FULL),
                ...$sections,
            ]);
    }

    /**
     * Duzenleme formu verisi: scope_types + scopes.{tip}.{alan}.
     *
     * @return array{scope_types: list<string>, scopes: array<string, array<string, string|null>>}|array{}
     */
    public static function formData(Project $project): array
    {
        if (! ProjectNames::scopeTypesEnabled()) {
            return [];
        }

        return app(ProjectScopeService::class)->formData($project);
    }

    /** Proje kartindaki tip rozetleri. */
    public function typesEntry(Project $project): TextEntry
    {
        return TextEntry::make('project_scope_types')
            ->label(__('project.fields.scope_types'))
            ->state($this->types($project))
            ->formatStateUsing(static fn (mixed $state): string => $state instanceof HasLabel ? (string) $state->getLabel() : (string) $state)
            ->color(static fn (mixed $state): string => $state instanceof HasColor ? (string) $state->getColor() : 'gray')
            ->icon(static fn (mixed $state): ?Heroicon => $state instanceof ProjectScopeType ? $state->getIcon() : null)
            ->badge()
            ->placeholder(__('project_scope.empty'))
            ->visible(fn (): bool => ProjectNames::scopeTypesEnabled());
    }

    /**
     * Proje sayfasindaki olcu karti: her tip kendi simgesiyle kucuk bolumde,
     * dolu degerler etiketli. Hicbir tipte deger yoksa kart cikmaz (rozetler
     * proje kartinda).
     */
    public function recordCard(Project $project): ?Component
    {
        if (! ProjectNames::scopeTypesEnabled()) {
            return null;
        }

        $blocks = [];

        foreach ($project->scopes as $scope) {
            /** @var ProjectScope $scope */
            $type = $scope->scope_type;
            $value = $type instanceof BackedEnum ? (string) $type->value : (string) $type;
            $entries = [];

            foreach ([...ProjectScopeService::TYPE_FIELDS[$value] ?? [], ...ProjectScopeService::COMMON_FIELDS] as $field) {
                $raw = $scope->getAttribute($field);

                if ($raw === null || $raw === '') {
                    continue;
                }

                $entries[] = TextEntry::make('project_scope_'.$value.'_'.$field)
                    ->label(self::label($value, $field))
                    ->state(self::format($field, $raw, $project->currency_code))
                    ->weight(in_array($field, self::MONEY, true) ? FontWeight::SemiBold : FontWeight::Medium)
                    ->color($field === 'contract_amount' ? 'success' : null);
            }

            if ($entries === []) {
                continue;
            }

            $blocks[] = Section::make($type instanceof ProjectScopeType ? (string) $type->getLabel() : $value)
                ->key('project-scope-card-'.$value)
                ->icon($type instanceof ProjectScopeType ? $type->getIcon() : Heroicon::OutlinedCube)
                ->iconColor($type instanceof ProjectScopeType ? $type->getColor() : 'gray')
                ->compact()
                ->secondary()
                ->columns(['default' => 2, 'md' => 3])
                ->components($entries);
        }

        if ($blocks === []) {
            return null;
        }

        return Section::make(__('project.sections.scope'))
            ->key('project-scope-card')
            ->icon(Heroicon::OutlinedCube)
            ->compact()
            ->components($blocks);
    }

    /**
     * @return list<ProjectScopeType>
     */
    private function types(Project $project): array
    {
        if (! ProjectNames::scopeTypesEnabled()) {
            return [];
        }

        $types = [];

        foreach ($project->scopes as $scope) {
            $type = $scope->scope_type;

            if ($type instanceof ProjectScopeType) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Tipin alanlari (ekrandaki sira).
     *
     * @return list<TextInput|MoneyInput>
     */
    private function typeFields(ProjectScopeType $type): array
    {
        $fields = [];

        foreach (ProjectScopeService::TYPE_FIELDS[$type->value] ?? [] as $field) {
            // D-180: tutarlar MoneyInput (Turkce maske, projenin para birimi simgesi).
            if (in_array($field, self::MONEY, true)) {
                $fields[] = MoneyInput::make('scopes.'.$type->value.'.'.$field)
                    ->label(self::label($type->value, $field));

                continue;
            }

            $input = TextInput::make('scopes.'.$type->value.'.'.$field)
                ->label(self::label($type->value, $field))
                ->numeric()
                ->minValue(0);

            $fields[] = $field === 'unit_count'
                ? $input->integer()->step(1)->maxValue(65535)
                : $input->step('0.001');
        }

        $fields[] = TextInput::make('scopes.'.$type->value.'.note')
            ->label(__('project_scope.fields.note'))
            ->maxLength(255);

        return $fields;
    }

    /**
     * Secilebilir tipler (ScopeTypes) ve duzenlenen projenin kayitli tipleri.
     *
     * @return array<string, string>
     */
    private static function typeOptions(?Model $record): array
    {
        $options = ScopeTypes::options();

        foreach ($record instanceof Project ? $record->scopes : [] as $scope) {
            $type = $scope->scope_type;

            if ($type instanceof ProjectScopeType) {
                $options[$type->value] ??= (string) $type->getLabel();
            }
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private static function selected(Get $get): array
    {
        return ProjectScopeService::normaliseTypes((array) ($get('scope_types') ?? []));
    }

    private static function label(string $type, string $field): string
    {
        if ($field === 'unit_count') {
            return (string) __('project_scope.unit_count.'.$type);
        }

        return (string) __('project_scope.fields.'.$field);
    }

    private static function format(string $field, mixed $raw, ?string $currency): string
    {
        $number = is_numeric($raw) ? (float) $raw : null;

        if ($number === null) {
            return (string) $raw;
        }

        if (in_array($field, self::MONEY, true)) {
            return Money::format($number, $currency);
        }

        if ($field === 'unit_count') {
            return Number::format($number, precision: 0, locale: 'tr');
        }

        return Number::format($number, maxPrecision: 3, locale: 'tr').' '.(self::UNITS[$field] ?? '');
    }
}
