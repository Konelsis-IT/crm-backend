<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalPolicyVersions\Schemas;

use App\Enums\Approval\ApprovalMode;
use App\Enums\Approval\RiskLevel;
use App\Filament\Support\FieldGrid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Politika surumu formu: surum olusturma (politika kartindaki liste) ve
 * taslak surumu duzenleme ayni alanlari kullanir.
 */
final class PolicyVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('approval_policy_version.sections.flow'))
                ->description(__('approval_policy_version.help.flow'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->columns(FieldGrid::COLUMNS)
                ->components(FieldGrid::fields([
                    Select::make('mode')
                        ->label(__('approval_policy_version.fields.mode'))
                        ->helperText(__('approval_policy_version.help.mode'))
                        ->options(ApprovalMode::class)
                        ->default(ApprovalMode::Sequential->value)
                        ->required()
                        ->native(false)
                        ->live(),
                    TextInput::make('quorum_count')
                        ->label(__('approval_policy_version.fields.quorum_count'))
                        ->helperText(__('approval_policy_version.help.quorum_count'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->visible(fn (Get $get): bool => $get('mode') === ApprovalMode::Quorum->value)
                        ->required(fn (Get $get): bool => $get('mode') === ApprovalMode::Quorum->value),
                    Toggle::make('requires_maker_checker')
                        ->label(__('approval_policy_version.fields.requires_maker_checker'))
                        ->helperText(__('approval_policy_version.help.requires_maker_checker'))
                        ->default(true),
                    Toggle::make('reapproval_on_change')
                        ->label(__('approval_policy_version.fields.reapproval_on_change'))
                        ->helperText(__('approval_policy_version.help.reapproval_on_change'))
                        ->default(true),
                    TextInput::make('sla_minutes')
                        ->label(__('approval_policy_version.fields.sla_minutes'))
                        ->helperText(__('approval_policy_version.help.sla_minutes'))
                        ->numeric()
                        ->minValue(1)
                        ->suffix('dk'),
                    Select::make('risk_level')
                        ->label(__('approval_policy_version.fields.risk_level'))
                        ->options(RiskLevel::class)
                        ->native(false),
                ])),
            Section::make(__('approval_policy_version.sections.threshold'))
                ->description(__('approval_policy_version.help.threshold'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->columns(FieldGrid::COLUMNS)
                ->collapsible()
                ->collapsed()
                ->components(FieldGrid::fields([
                    TextInput::make('applies_min_amount')
                        ->label(__('approval_policy_version.fields.applies_min_amount'))
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('applies_max_amount')
                        ->label(__('approval_policy_version.fields.applies_max_amount'))
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('currency_code')
                        ->label(__('approval_policy_version.fields.currency_code'))
                        ->maxLength(3)
                        ->minLength(3)
                        ->placeholder('TRY'),
                ])),
            Section::make(__('approval_policy_version.sections.note'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->components([
                    Textarea::make('change_summary')
                        ->label(__('approval_policy_version.fields.change_summary'))
                        ->rows(2),
                    Hidden::make('row_version')->hiddenOn('create'),
                ]),
        ]);
    }
}
