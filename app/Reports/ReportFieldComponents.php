<?php

declare(strict_types=1);

namespace App\Reports;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;

/**
 * ReportField tanimindan Filament form bileseni ve goruntuleme girdisi
 * uretir (D-86). Form bilesenleri `payload` durum yolundaki bir bolume
 * konur; girdiler `payload.{alan}` adiyla dogrudan kayittan okur.
 */
final class ReportFieldComponents
{
    public const PAYLOAD = 'payload';

    public static function form(ReportTemplate $template, ReportField $field): Component
    {
        $component = match ($field->type) {
            ReportField::TEXT => TextInput::make($field->name)->maxLength(200),
            ReportField::LONG_TEXT => Textarea::make($field->name)->rows($field->rows)->maxLength(8000),
            ReportField::INTEGER => self::numeric($field, integer: true),
            ReportField::DECIMAL, ReportField::PERCENT => self::numeric($field, integer: false),
            ReportField::RATING => Select::make($field->name)->options(self::ratingOptions())->native(false),
            ReportField::CHOICE => Select::make($field->name)
                ->options(self::choiceOptions($template, $field))
                ->native(false),
            ReportField::KEY_VALUE => KeyValue::make($field->name)
                ->keyLabel(__('report.fields.metric_key'))
                ->valueLabel(__('report.fields.metric_value'))
                ->addActionLabel(__('report.actions.add_measurement'))
                ->reorderable(false),
            ReportField::BOOLEAN => Toggle::make($field->name),
            ReportField::DATE => DatePicker::make($field->name),
            default => TextInput::make($field->name),
        };

        $component->label($template->label($field->name));

        if ($field->required && method_exists($component, 'required')) {
            $component->required();
        }

        if (($help = $template->help($field->name)) !== null && method_exists($component, 'helperText')) {
            $component->helperText($help);
        }

        if ($field->span !== null) {
            $component->columnSpan($field->span);
        }

        return $component;
    }

    public static function entry(ReportTemplate $template, ReportField $field): Component
    {
        $name = self::PAYLOAD.'.'.$field->name;

        $entry = match ($field->type) {
            ReportField::LONG_TEXT => TextEntry::make($name)->placeholder('-')->columnSpanFull(),
            ReportField::INTEGER, ReportField::DECIMAL, ReportField::PERCENT => TextEntry::make($name)
                ->placeholder('-')
                ->formatStateUsing(fn ($state): ?string => self::formatNumber($state, $field->suffix)),
            ReportField::RATING => TextEntry::make($name)
                ->placeholder('-')
                ->badge()
                ->formatStateUsing(fn ($state): ?string => self::ratingLabel($state))
                ->color(fn ($state): string => self::ratingColor($state)),
            ReportField::CHOICE => TextEntry::make($name)
                ->placeholder('-')
                ->badge()
                ->color('gray')
                ->formatStateUsing(fn ($state): ?string => filled($state) ? $template->optionLabel($field->name, (string) $state) : null),
            ReportField::KEY_VALUE => KeyValueEntry::make($name)
                ->keyLabel(__('report.fields.metric_key'))
                ->valueLabel(__('report.fields.metric_value'))
                ->columnSpanFull(),
            ReportField::BOOLEAN => IconEntry::make($name)->boolean(),
            ReportField::DATE => TextEntry::make($name)->placeholder('-')->date('d.m.Y'),
            default => TextEntry::make($name)->placeholder('-'),
        };

        $entry->label($template->label($field->name));

        if ($field->span !== null) {
            $entry->columnSpan($field->span);
        }

        return $entry;
    }

    /**
     * @return array<int, string>
     */
    public static function ratingOptions(): array
    {
        $options = [];

        for ($score = 1; $score <= 5; $score++) {
            $options[$score] = self::ratingLabel($score) ?? (string) $score;
        }

        return $options;
    }

    public static function ratingLabel(mixed $state): ?string
    {
        if (! is_numeric($state)) {
            return null;
        }

        $score = (int) $state;
        $key = 'report.rating.'.$score;
        $label = __($key);

        return $label === $key ? (string) $score : $label;
    }

    public static function ratingColor(mixed $state): string
    {
        if (! is_numeric($state)) {
            return 'gray';
        }

        return match (true) {
            (int) $state <= 2 => 'danger',
            (int) $state === 3 => 'warning',
            default => 'success',
        };
    }

    public static function formatNumber(mixed $state, ?string $suffix = null): ?string
    {
        if ($state === null || $state === '' || ! is_numeric($state)) {
            return null;
        }

        $value = (float) $state;
        $decimals = floor($value) == $value ? 0 : 2;

        return number_format($value, $decimals, ',', '.').($suffix !== null ? ' '.$suffix : '');
    }

    /**
     * @return array<string, string>
     */
    private static function choiceOptions(ReportTemplate $template, ReportField $field): array
    {
        $options = [];

        foreach ($field->options as $value) {
            $options[$value] = $template->optionLabel($field->name, $value);
        }

        return $options;
    }

    private static function numeric(ReportField $field, bool $integer): TextInput
    {
        $input = TextInput::make($field->name)->numeric();

        if ($integer) {
            $input->integer();
        } else {
            $input->step(0.01);
        }

        if ($field->min !== null) {
            $input->minValue($field->min);
        }

        if ($field->max !== null) {
            $input->maxValue($field->max);
        }

        if ($field->suffix !== null) {
            $input->suffix($field->suffix);
        }

        return $input;
    }
}
