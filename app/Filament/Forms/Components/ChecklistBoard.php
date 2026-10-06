<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Enums\Acquisition\ChecklistAnswer;
use App\Enums\Acquisition\LicenseStatus;
use App\Enums\Acquisition\OfferType;
use App\Filament\Support\ChecklistSchema;
use App\Models\Acquisition\BusinessCase;
use App\Support\Acquisition\ChecklistTemplates;
use App\Support\UploadLimits;
use BackedEnum;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Teklif oncesi kontrol listesi tahtasi (D-157, 5 Ekim 2026 kullanici talimati:
 * "Tiklayinca tik, tekrar tiklayinca carpi, hic secmezsen - (bilinmiyor) durumu
 * olsun. Ayni kontrol matrisindeki gibi ... istedigim tasarim Filament ile
 * olmadiginda React ile yapabilirsin").
 *
 * Filament'in kendi alanlari bu gorunumu vermiyor: ToggleButtons uc dugmeyi yan
 * yana cizer (tiklama dongusu yok, satir buyur), madde basina FileUpload
 * buyuk bir birakma alani acar, sicaklik her tiklamada sunucuya gidip gelir.
 * Bu yuzden tahta React'tir (resources/js/acquisition/checklist-board.js,
 * stil konelsis-checklist.css); durum yine bu Filament alanindadir:
 *
 *   checklist.{sablon}.q1_3      'yes' | 'no' | null ("–" = bilinmiyor)
 *   checklist.{sablon}.doc_1     madde belgesi (Livewire gecici yuklemesi)
 *
 * React cevaplari Livewire durumuna ertelenmis yazar ($wire.$set(..., false));
 * kaydetme istegiyle sunucuya gider. Yapilandirma (secili listeler, proje
 * durumuna gore agirliklar, kayitli belgeler) her cizimde formun o anki
 * durumundan kurulur ve kok ogenin data-config ozelligiyle tahtaya gecer.
 * Kaydederken gecici yuklemeler belge servisinin bekledigi gecici dosyaya
 * cevrilir (doc_1 = yol, doc_1_name = ozgun ad).
 */
class ChecklistBoard extends Field
{
    protected string $view = 'filament.forms.checklist-board';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);
        $this->hiddenLabel();
        $this->dehydrateStateUsing(static fn (mixed $state): array => self::storeUploads(is_array($state) ? $state : []));
    }

    /**
     * Tahtanin yapilandirmasi.
     *
     * @return array<string, mixed>
     */
    public function getBoardConfig(): array
    {
        return (array) $this->evaluate(function (Get $get, ?Model $record): array {
            $statePath = (string) $this->getStatePath();
            $parentPath = Str::contains($statePath, '.') ? Str::beforeLast($statePath, '.') : '';
            $state = $this->getState();
            $state = is_array($state) ? $state : [];

            return self::buildConfig(
                ChecklistTemplates::forScopeTypes(self::values($get('scope_types'))),
                self::license($get('license_status')),
                self::answers($state),
                self::pending($state),
                $record,
                [
                    'statePath' => $statePath,
                    'offerTypePath' => ($parentPath !== '' ? $parentPath.'.' : '').'offer_type',
                    'offerType' => self::value($get('offer_type')),
                    'disabled' => $this->isDisabled(),
                ],
            );
        });
    }

    /**
     * Potansiyel is detayindaki salt okunur tahtanin yapilandirmasi (D-158:
     * "Teklif oncesi kontrol listesi arayuzu birebir olarak salt okunur sekilde
     * potansiyel is detayinda gorulsun"). Cevaplar kayittan.
     *
     * @return array<string, mixed>
     */
    public static function readOnlyConfig(BusinessCase $case): array
    {
        $answers = array_fill_keys(ChecklistTemplates::codes(), []);

        foreach ($case->checklistAnswers as $row) {
            $answer = $row->answer;

            if ($answer === ChecklistAnswer::Yes || $answer === ChecklistAnswer::No) {
                $answers[(string) $row->template_code][ChecklistTemplates::questionKey((string) $row->item_code)] = $answer->value;
            }
        }

        return self::buildConfig(
            ChecklistTemplates::forScopeTypes($case->scopes->pluck('scope_type')->all()),
            $case->license_status,
            $answers,
            [],
            $case,
            ['readOnly' => true, 'disabled' => true],
        );
    }

    /**
     * Tahtanin ortak yapilandirmasi (form alani ve salt okunur detay).
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, string>>  $answers
     * @param  array<string, array<string, string>>  $pending
     * @param  array<string, mixed>  $extra  statePath, offerTypePath, offerType, disabled, readOnly
     * @return array<string, mixed>
     */
    public static function buildConfig(array $templates, ?LicenseStatus $license, array $answers, array $pending, ?Model $record, array $extra): array
    {
        return [
            'statePath' => null,
            'offerTypePath' => null,
            'offerType' => null,
            'disabled' => false,
            'readOnly' => false,
            ...$extra,
            'budgetary' => OfferType::Budgetary->value,
            'licensed' => $license?->isLicensed() ?? false,
            'maxBytes' => UploadLimits::documentMaxKb() * 1024,
            'templates' => array_map(static fn (string $template): array => self::templateConfig($template, $license, $record), $templates),
            'answers' => $answers,
            'pending' => $pending,
            'labels' => self::labels(),
        ];
    }

    /**
     * Kaydederken gecici yuklemeleri gecici dosyaya cevirir (belge servisi bu
     * yolu alip belge / revizyon acar).
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function storeUploads(array $state): array
    {
        foreach (ChecklistTemplates::codes() as $template) {
            if (! is_array($state[$template] ?? null)) {
                continue;
            }

            foreach (ChecklistTemplates::itemCodes($template) as $item) {
                $key = ChecklistTemplates::documentKey($item);
                $file = $state[$template][$key] ?? null;

                if ($file instanceof TemporaryUploadedFile) {
                    $state[$template][$key] = $file->store(ChecklistSchema::UPLOAD_DIRECTORY, 'local');
                    $state[$template][$key.'_name'] = $file->getClientOriginalName();
                } elseif (! is_string($file) || $file === '') {
                    unset($state[$template][$key], $state[$template][$key.'_name']);
                }
            }
        }

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    private static function templateConfig(string $template, ?LicenseStatus $license, ?Model $record): array
    {
        $exact = ChecklistTemplates::weights($template, $license);
        $shown = ChecklistTemplates::displayWeights($template, $license);
        $items = [];

        foreach (ChecklistTemplates::items($template) as $code => $definition) {
            $item = (string) $code;
            $exempt = ChecklistTemplates::licenseExempt($template, $item, $license);
            $document = $record instanceof BusinessCase ? ChecklistSchema::itemDocumentInfo($record, $template, $item) : null;

            $items[] = [
                'code' => $item,
                'label' => ChecklistTemplates::label($template, $item),
                'counts' => ChecklistTemplates::counts($template, $item, $license),
                'exact' => $exact[$item] ?? 0,
                'weight' => $shown[$item] ?? null,
                'optional' => $definition['optional'],
                'exempt' => $exempt,
                'documentKey' => ChecklistTemplates::documentKey($item),
                'documentRequired' => ChecklistTemplates::documentRequired($template, $item, $license),
                'document' => $document === null ? null : [
                    'name' => $document['file'] ?? $document['title'],
                    'revision' => $document['revision'],
                    'url' => $document['url'],
                ],
                'questions' => array_map(static fn (string $question): array => [
                    'code' => $question,
                    'key' => ChecklistTemplates::questionKey($question),
                    'label' => ChecklistTemplates::label($template, $question),
                    'negative' => ChecklistTemplates::isNegative($template, $question),
                    // GES 1.3 "Hayir" -> teklif tipi Butcesel (lisansli projede uygulanmaz).
                    'budgetary' => $template === ChecklistTemplates::GES && $question === ChecklistTemplates::VALIDITY_QUESTION && ! $exempt,
                ], $definition['questions']),
            ];
        }

        return [
            'code' => $template,
            'title' => (string) __('checklist.templates.'.$template),
            'items' => $items,
        ];
    }

    /**
     * Formdaki cevaplar: sablon => q1_3 => 'yes' | 'no' (Bilinmiyor ve bos "–").
     *
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, string>>
     */
    private static function answers(array $state): array
    {
        $answers = [];

        foreach (ChecklistTemplates::codes() as $template) {
            $answers[$template] = [];

            foreach (ChecklistTemplates::items($template) as $definition) {
                foreach ($definition['questions'] as $question) {
                    $key = ChecklistTemplates::questionKey($question);
                    $answer = ChecklistTemplates::answer($state[$template][$key] ?? null);

                    if ($answer === ChecklistAnswer::Yes || $answer === ChecklistAnswer::No) {
                        $answers[$template][$key] = $answer->value;
                    }
                }
            }
        }

        return $answers;
    }

    /**
     * Kaydedilmeyi bekleyen madde yuklemeleri: sablon => ana madde => dosya adi.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, array<string, string>>
     */
    private static function pending(array $state): array
    {
        $pending = [];

        foreach (ChecklistTemplates::codes() as $template) {
            $pending[$template] = [];

            foreach (ChecklistTemplates::itemCodes($template) as $item) {
                $file = $state[$template][ChecklistTemplates::documentKey($item)] ?? null;

                if ($file instanceof TemporaryUploadedFile) {
                    $pending[$template][$item] = $file->getClientOriginalName();
                }
            }
        }

        return $pending;
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        $labels = [];

        foreach ((array) __('checklist.board') as $key => $text) {
            $labels[(string) $key] = (string) $text;
        }

        foreach ((array) __('checklist.heat_levels') as $key => $text) {
            $labels['level_'.$key] = (string) $text;
        }

        return [
            ...$labels,
            'heat' => (string) __('checklist.heat'),
            'heat_none' => (string) __('checklist.heat_none'),
            'choose_type_first' => (string) __('checklist.choose_type_first'),
            'optional' => (string) __('checklist.optional'),
            'licensed_optional' => (string) __('checklist.licensed_optional'),
            'document_required' => (string) __('checklist.document_required'),
            'negative_hint' => (string) __('checklist.negative_hint'),
            'budgetary_message' => (string) __('checklist.messages.budgetary'),
            'ai_soon' => (string) __('checklist.ai_soon'),
        ];
    }

    private static function license(mixed $value): ?LicenseStatus
    {
        if ($value instanceof LicenseStatus) {
            return $value;
        }

        return is_string($value) && $value !== '' ? LicenseStatus::tryFrom($value) : null;
    }

    /**
     * @return list<string>
     */
    private static function values(mixed $values): array
    {
        return array_values(array_map(static fn (mixed $value): string => (string) self::value($value), (array) $values));
    }

    private static function value(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
