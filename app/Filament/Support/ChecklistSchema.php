<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\Acquisition\ChecklistAnswer;
use App\Enums\Acquisition\LicenseStatus;
use App\Enums\Acquisition\OfferType;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Platform\Feature;
use App\Filament\Forms\Components\ChecklistBoard;
use App\Filament\Infolists\Components\ChecklistBoardEntry;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseChecklistAnswer;
use App\Models\Acquisition\BusinessCaseDocument;
use App\Services\Platform\FeatureFlags;
use App\Services\Platform\SchemaReadiness;
use App\Support\Acquisition\ChecklistTemplates;
use App\Support\UploadLimits;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Potansiyel isin teklif oncesi kontrol listesi ekranlari (B43, D-155; ekran
 * D-157, 5 Ekim 2026 kullanici talimati ve "Teklif Oncesi Checklistler" dosyasi).
 *
 * - Sihirbazin potansiyel is adiminda tek bolum: React tahtasi (ChecklistBoard).
 *   Her soru tiklandikca – / ✓ / ✗ olur; kalp ve yuzde aninda guncellenir; ana
 *   madde basina kucuk belge dugmesi vardir. Proje durumu (Lisanssiz 5.1-C /
 *   5.1-H / Onlisans / Lisans) Siniflandirma bolumundeki acilir listedir
 *   (BusinessCaseWizard::caseFields); lisansli projede Cagri mektubu opsiyoneldir.
 * - Kaydet / Ileri penceresi (summaryHtml, ConfirmsChecklist): hangi soruya ne
 *   cevap verildi, sicaklik (basari ihtimali), cevaplanan soru, zorunlu belge,
 *   teklife gecerken durum ve eksikleri doldurmaya tesvik eden satir.
 * - Cevaplar bugun elle secilir; "KonelsisAI yakinda" etiketi belgeden otomatik
 *   doldurmanin yerini gosterir (plan: ChecklistTemplates).
 * - Potansiyel is detayinda ayni tahtanin salt okunur hali (viewBoard, D-158).
 * - Ek belgeler (Belgeler ozelligi): coklu yukleme + kayitli belgeler; bolum
 *   Siniflandirma'nin altinda, alanlar tam satir degil (D-157).
 *
 * Sicaklik gostergesi kucuk HTML'dir (heatHtml); kalp atisi ve EKG cizgisi
 * konelsis.css'te (.kc-heat), tahta ve ozet konelsis-checklist.css'te.
 */
final class ChecklistSchema
{
    /** Gecici yukleme dizini (DocumentService dosyayi buradan alir). */
    public const UPLOAD_DIRECTORY = 'document-uploads-tmp';

    private const SYMBOLS = ['yes' => '✓', 'no' => '✗', 'none' => '–'];

    /** Kontrol listesi bu ortamda acik mi (B43 + ozellik). */
    public static function enabled(): bool
    {
        return SchemaReadiness::hasBatch('B43') && FeatureFlags::enabled(Feature::BusinessCaseChecklist);
    }

    /** Potansiyel is genel belgeleri acik mi (B43 + ozellik). */
    public static function documentsEnabled(): bool
    {
        return SchemaReadiness::hasBatch('B43') && FeatureFlags::enabled(Feature::BusinessCaseDocuments);
    }

    /**
     * Potansiyel is adiminin kontrol listesi bolumu: React tahtasi (D-157).
     *
     * @return list<Component>
     */
    public function formSections(): array
    {
        if (! self::enabled()) {
            return [];
        }

        return [
            Section::make(__('checklist.section'))
                ->key('checklist')
                ->description(__('checklist.section_help'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->afterHeader([self::aiBadge(__('checklist.ai_soon'))])
                ->components([
                    ChecklistBoard::make('checklist'),
                ]),
        ];
    }

    /**
     * Ek belgeler (Belgeler ozelligi; D-157: "ekstra belgeler icinse Ek Belgeler
     * diyelim"): kayitli belgeler ve coklu yukleme. Yarim genislikteki
     * Siniflandirma bolumunun altinda durur; alanlar 4/6 (tam satir yok).
     */
    public function documentsSection(): ?Section
    {
        if (! self::documentsEnabled()) {
            return null;
        }

        return Section::make(__('business_case.sections.extra_documents'))
            ->key('case-documents')
            ->description(__('business_case.help.case_documents'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->collapsible()
            ->columns(FieldGrid::HALF_COLUMNS)
            ->components([
                TextEntry::make('case_documents_current')
                    ->label(__('business_case.fields.case_documents_current'))
                    ->state(fn (?Model $record): array => self::generalDocumentLines($record))
                    ->listWithLineBreaks()
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->iconColor('primary')
                    ->visible(fn (?Model $record): bool => self::generalDocumentLines($record) !== [])
                    ->dehydrated(false)
                    ->columnSpan(FieldGrid::HALF_LONG)
                    ->columnStart(1),
                FileUpload::make('case_document_files')
                    ->label(__('business_case.fields.case_documents'))
                    ->multiple()
                    ->disk('local')
                    ->directory(self::UPLOAD_DIRECTORY)
                    ->storeFileNamesIn('case_document_files_name')
                    ->maxSize(UploadLimits::documentMaxKb())
                    ->panelLayout('grid')
                    ->columnSpan(FieldGrid::HALF_LONG)
                    ->columnStart(1),
                Hidden::make('case_document_files_name'),
            ]);
    }

    /**
     * Duzenleme formu icin kayitli cevaplar: checklist.{sablon}.q1_3 => deger.
     *
     * @return array<string, array<string, string>>
     */
    public static function formData(BusinessCase $case): array
    {
        $data = [];

        foreach ($case->checklistAnswers as $row) {
            /** @var BusinessCaseChecklistAnswer $row */
            $answer = $row->answer;

            if ($answer instanceof ChecklistAnswer) {
                $data[(string) $row->template_code][ChecklistTemplates::questionKey((string) $row->item_code)] = $answer->value;
            }
        }

        return $data;
    }

    /**
     * Kontrol listesi bu formda acik ve en az bir liste secili mi (Kaydet /
     * Ileri penceresi yalniz o zaman acilir).
     *
     * @param  array<string, mixed>  $data
     */
    public static function hasTemplates(array $data): bool
    {
        return self::enabled() && ChecklistTemplates::forScopeTypes(self::typeValues($data['scope_types'] ?? [])) !== [];
    }

    /**
     * Eksikler (formdaki secim, cevaplar ve belgeler; kayitta zaten yuklu madde
     * belgeleri de sayilir).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public static function missingFromState(array $data, ?BusinessCase $record = null): array
    {
        if (! self::enabled()) {
            return [];
        }

        $templates = ChecklistTemplates::forScopeTypes(self::typeValues($data['scope_types'] ?? []));
        $checklist = is_array($data['checklist'] ?? null) ? $data['checklist'] : [];

        return ChecklistTemplates::missing($templates, self::answersFrom($checklist), self::documentPresence($templates, $checklist, $record), self::license($data['license_status'] ?? null));
    }

    /**
     * Madde belgeleri: formda yeni yukleme ya da kayitta belge var mi
     * (sablon => ana madde => bool). D-159: belge de maddenin bir payidir.
     *
     * @param  list<string>  $templates
     * @param  array<string, mixed>  $checklist
     * @return array<string, array<string, bool>>
     */
    private static function documentPresence(array $templates, array $checklist, ?BusinessCase $record): array
    {
        $documents = [];

        foreach ($templates as $template) {
            foreach (ChecklistTemplates::itemCodes($template) as $item) {
                $documents[$template][$item] = self::formHasItemDocument($checklist, $template, $item) || self::recordHasItemDocument($record, $template, $item);
            }
        }

        return $documents;
    }

    /**
     * Kaydet / Ileri penceresindeki ozet (D-157, 5 Ekim 2026 kullanici talimati:
     * "doldurulan bu alanlarin hangilerinde ne dolduruldu, kalp atis orani nedir,
     * basari orani, teklife gecerken guncel durum nedir ozeti gosterecek, hatta
     * personeli daha fazla bilgi doldurmaya tesvik edecek").
     *
     * @param  array<string, mixed>  $data  formun ham durumu
     */
    public static function summaryHtml(array $data, ?BusinessCase $record): HtmlString
    {
        $templates = ChecklistTemplates::forScopeTypes(self::typeValues($data['scope_types'] ?? []));
        $checklist = is_array($data['checklist'] ?? null) ? $data['checklist'] : [];
        $answers = self::answersFrom($checklist);
        $license = self::license($data['license_status'] ?? null);
        $documents = self::documentPresence($templates, $checklist, $record);
        $heat = ChecklistTemplates::heat($templates, $answers, $license, $documents);
        $progress = ChecklistTemplates::progress($templates, $answers, $license);
        $documentProgress = ChecklistTemplates::documentProgress($templates, $documents, $license);
        $potential = ChecklistTemplates::potentialHeat($templates, $answers, $license);
        $missing = self::missingFromState($data, $record);
        $required = 0;
        $present = 0;
        $tables = '';

        foreach ($templates as $template) {
            $shown = ChecklistTemplates::displayWeights($template, $license);
            $rows = '';

            foreach (ChecklistTemplates::items($template) as $code => $definition) {
                $item = (string) $code;
                $counts = ChecklistTemplates::counts($template, $item, $license);
                $marks = '';

                foreach ($definition['questions'] as $question) {
                    $answer = ChecklistTemplates::answer($answers[$template][$question] ?? null);
                    $value = match ($answer) {
                        ChecklistAnswer::Yes => 'yes',
                        ChecklistAnswer::No => 'no',
                        default => 'none',
                    };
                    $tone = $value === 'none' ? '' : (ChecklistTemplates::isFavourable($template, $question, $answer) ? ' is-good' : ' is-bad');
                    $marks .= sprintf(
                        '<span class="kc-sum-mark%s" title="%s">%s</span>',
                        $tone,
                        e($question.' '.ChecklistTemplates::label($template, $question).': '.__('checklist.board.answer_'.$value)),
                        self::SYMBOLS[$value],
                    );
                }

                $pending = self::formHasItemDocument($checklist, $template, $item);
                $stored = self::recordHasItemDocument($record, $template, $item);
                $isRequired = ChecklistTemplates::documentRequired($template, $item, $license);

                if ($isRequired) {
                    $required++;
                    $present += ($pending || $stored) ? 1 : 0;
                }

                $document = match (true) {
                    $pending => '<span class="kc-sum-doc-new">'.e(__('checklist.summary.document_pending')).'</span>',
                    $stored => '<span class="kc-sum-doc-ok">'.e(__('checklist.summary.document_present')).'</span>',
                    $isRequired => '<span class="kc-sum-doc-missing">'.e(__('checklist.summary.document_missing')).'</span>',
                    default => '<span class="kc-sum-doc-none">'.e(__('checklist.summary.document_none')).'</span>',
                };

                $weight = $counts
                    ? '%'.($shown[$item] ?? 0)
                    : e(ChecklistTemplates::licenseExempt($template, $item, $license) ? __('checklist.licensed_optional') : __('checklist.optional'));

                $rows .= sprintf(
                    '<tr%s><td class="kc-sum-no">%s</td><td>%s</td><td class="kc-sum-weight">%s</td><td><span class="kc-sum-marks">%s</span></td><td class="kc-sum-doc">%s</td></tr>',
                    $counts ? '' : ' class="is-optional"',
                    e($item),
                    e(ChecklistTemplates::label($template, $item)),
                    $weight,
                    $marks,
                    $document,
                );
            }

            $tables .= sprintf(
                '<section><h4>%s</h4><table class="kc-sum-table"><thead><tr><th></th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>%s</tbody></table></section>',
                e(__('checklist.templates.'.$template)),
                e(__('checklist.summary.col_item')),
                e(__('checklist.summary.col_weight')),
                e(__('checklist.summary.col_answers')),
                e(__('checklist.summary.col_document')),
                $rows,
            );
        }

        $open = $progress['total'] - $progress['answered'];
        $level = ChecklistTemplates::heatLevel($heat);
        $success = $heat === null ? __('checklist.heat_none') : __('checklist.heat_levels.'.$level).' (%'.$heat.')';
        $state = $missing === []
            ? '<span class="kc-sum-state is-ready">'.e(__('checklist.summary.ready')).'</span>'
            : '<span class="kc-sum-state is-open">'.e(__('checklist.summary.not_ready', ['count' => count($missing)])).'</span>';

        $facts = [
            [__('checklist.summary.success'), e($success)],
            [__('checklist.summary.answered'), e(__('checklist.summary.answered_value', $progress))],
            [__('checklist.summary.documents_all'), e(__('checklist.summary.documents_all_value', $documentProgress))],
            [__('checklist.summary.documents'), e($required === 0 ? __('checklist.summary.documents_none') : __('checklist.summary.documents_value', ['present' => $present, 'total' => $required]))],
            [__('checklist.summary.state'), $state.' '.e(self::contextLine($data, $license))],
        ];

        $top = '<div class="kc-sum-top">'.self::heatHtml($heat, large: true)->toHtml().'<dl class="kc-sum-facts">';

        foreach ($facts as [$term, $value]) {
            $top .= '<dt>'.e($term).'</dt><dd>'.$value.'</dd>';
        }

        $top .= '</dl></div>';

        $nudges = [];
        $openDocuments = $documentProgress['total'] - $documentProgress['present'];

        // D-159: eksik belgeler de sicakligi dusurur; tesvik satiri ikisini birlikte soyler.
        if ($open > 0 || $openDocuments > 0) {
            $key = match (true) {
                $open > 0 && $openDocuments > 0 => 'checklist.summary.nudge_both',
                $open > 0 => 'checklist.summary.nudge_questions',
                default => 'checklist.summary.nudge_docs',
            };
            $nudges[] = __($key, ['count' => $open, 'docs' => $openDocuments, 'heat' => $potential ?? 0]);
            $nudges[] = __('checklist.summary.nudge_tail');
        }

        if ($required > $present) {
            $nudges[] = __('checklist.summary.nudge_documents');
        }

        $nudge = '<p class="kc-sum-nudge">'.e($nudges === [] ? __('checklist.summary.all_done') : implode(' ', $nudges)).'</p>';

        $missingHtml = '';

        if ($missing !== []) {
            $missingHtml = '<section class="kc-sum-missing"><h4>'.e(__('checklist.summary.missing')).' ('.count($missing).')</h4><ul>';

            foreach ($missing as $line) {
                $missingHtml .= '<li>'.e($line).'</li>';
            }

            $missingHtml .= '</ul></section>';
        }

        return new HtmlString('<div class="kc-sum">'.$top.$nudge.$tables.$missingHtml.'</div>');
    }

    /**
     * Potansiyel is detayindaki kontrol listesi (D-158): duzenleme ekranindaki
     * React tahtasinin birebir salt okunur hali; sayfadaki tek teklif sicakligi
     * da budur (kart rozeti ve ayrinti kartindaki tekrarlar kaldirildi).
     */
    public function viewBoard(BusinessCase $case): ?Component
    {
        if (! self::enabled() || ChecklistTemplates::forScopeTypes($case->scopes->pluck('scope_type')->all()) === []) {
            return null;
        }

        return Section::make(__('checklist.section'))
            ->key('checklist-view')
            ->description(__('checklist.view_help'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->afterHeader([self::aiBadge(__('checklist.ai_soon'))])
            ->collapsible()
            ->components([
                ChecklistBoardEntry::make('checklist_board')->case($case),
            ]);
    }

    /**
     * Potansiyel is sayfasindaki Ek belgeler karti. D-158: madde belgeleri
     * kontrol listesi tahtasinda kendi maddesinin yaninda gorundugu icin burada
     * yalniz ek belgeler (ayni belge iki yerde gorunmesin).
     */
    public function documentsCard(BusinessCase $case): ?Component
    {
        if (! self::documentsEnabled() && ! self::enabled()) {
            return null;
        }

        $entries = [];

        foreach ($case->caseDocuments as $row) {
            /** @var BusinessCaseDocument $row */
            if ($row->item_code !== null) {
                continue;
            }

            if (! self::documentsEnabled()) {
                continue;
            }

            $info = DocumentLine::info($row->document);
            $label = $row->item_code !== null
                ? $row->item_code.'. '.ChecklistTemplates::label((string) $row->template_code, (string) $row->item_code)
                : __('business_case.fields.case_document');

            $entries[] = TextEntry::make('case_document_'.$row->getKey())
                ->label($label)
                ->state(DocumentLine::text($info, withTitle: $row->item_code === null))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->iconColor('primary')
                ->color('primary')
                ->url($info['url'] ?? null);
        }

        if ($entries === []) {
            return null;
        }

        return Section::make(__('business_case.sections.extra_documents'))
            ->key('case-documents-view')
            ->icon(Heroicon::OutlinedPaperClip)
            ->compact()
            ->collapsible()
            ->components([Grid::make(['default' => 1, 'md' => 2, 'xl' => 3])->components($entries)]);
    }

    /**
     * Teklif sicakligi gostergesi: kalp (seviyeye gore renk ve atis hizi),
     * yuzde, seviye adi ve serit. $small: listede yalniz kalp ve yuzde.
     * $large: buyuk kalp ve yaninda ayni hizda akan EKG cizgisi (tahta,
     * Kaydet / Ileri ozeti, potansiyel is karti). React tahtasi ayni isaretlemeyi
     * cizer (checklist-board.js Heat).
     */
    public static function heatHtml(?int $heat, bool $small = false, bool $large = false): HtmlString
    {
        $level = ChecklistTemplates::heatLevel($heat) ?? 'none';
        $label = $heat === null ? __('checklist.heat_none') : __('checklist.heat_levels.'.$level);
        $value = $heat === null ? '–' : '%'.$heat;
        $heart = svg('heroicon-s-heart')->toHtml();
        $title = e(__('checklist.heat').': '.$value.' · '.$label);

        if ($small) {
            return new HtmlString(sprintf(
                '<span class="kc-heat kc-heat--sm kc-heat--%s" title="%s"><span class="kc-heat__heart" aria-hidden="true">%s</span><span class="kc-heat__value">%s</span></span>',
                $level,
                $title,
                $heart,
                e($value),
            ));
        }

        $ecg = '';

        if ($large) {
            $points = '0,16 34,16 40,16 45,7 51,26 57,4 62,16 74,16 79,12 84,16 120,16';
            $ecg = '<svg class="kc-heat__ecg" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true"><polyline class="kc-heat__ecg-base" points="'.$points.'"></polyline><polyline class="kc-heat__ecg-trace" points="'.$points.'"></polyline></svg>';
        }

        return new HtmlString(sprintf(
            '<span class="kc-heat%s kc-heat--%s" style="--kc-heat-pct: %d%%" title="%s"><span class="kc-heat__heart" aria-hidden="true">%s</span><span class="kc-heat__body"><span class="kc-heat__top"><span class="kc-heat__value">%s</span><span class="kc-heat__label">%s · %s</span></span><span class="kc-heat__bar"><span class="kc-heat__rest"></span></span></span>%s</span>',
            $large ? ' kc-heat--lg' : '',
            $level,
            $heat ?? 0,
            $title,
            $heart,
            e($value),
            e(__('checklist.heat')),
            e($label),
            $ecg,
        ));
    }

    /** "KonelsisAI yakinda" etiketi. */
    public static function aiBadge(string $text): Text
    {
        return Text::make($text)
            ->badge()
            ->color('primary')
            ->icon(Heroicon::OutlinedSparkles);
    }

    /**
     * Sicaklik: formda kontrol listesi varsa cevaplardan canli, yoksa kayitli
     * deger (ozet kartinin okuyucusu `heat_score` verir). $read: Get ya da
     * "yol => deger" okuyucusu.
     */
    public static function heatFrom(callable $read, ?BusinessCase $record = null): ?int
    {
        $checklist = $read('checklist');

        if (is_array($checklist)) {
            $templates = ChecklistTemplates::forScopeTypes(self::typeValues($read('scope_types')));

            return ChecklistTemplates::heat(
                $templates,
                self::answersFrom($checklist),
                self::license($read('license_status')),
                self::documentPresence($templates, $checklist, $record),
            );
        }

        $stored = $read('heat_score');

        return is_numeric($stored) ? (int) $stored : null;
    }

    /**
     * Ana maddenin kayitli belgesi (baslik, revizyon, dosya, baglanti); yoksa null.
     *
     * @return array{title: string, revision: string|null, file: string|null, url: string|null}|null
     */
    public static function itemDocumentInfo(?Model $record, string $template, string $item): ?array
    {
        return DocumentLine::info(self::itemDocument($record, $template, $item)?->document);
    }

    /**
     * Form durumundaki cevaplar: sablon => soru kodu => cevap.
     *
     * @param  array<string, mixed>  $checklist
     * @return array<string, array<string, ChecklistAnswer|null>>
     */
    private static function answersFrom(array $checklist): array
    {
        $answers = [];

        foreach (ChecklistTemplates::codes() as $template) {
            foreach (ChecklistTemplates::items($template) as $definition) {
                foreach ($definition['questions'] as $question) {
                    $answers[$template][$question] = ChecklistTemplates::answer($checklist[$template][ChecklistTemplates::questionKey($question)] ?? null);
                }
            }
        }

        return $answers;
    }

    /**
     * "Teklife gecerken" satirinin baglami: teklif tipi, proje durumu, proje tipleri.
     *
     * @param  array<string, mixed>  $data
     */
    private static function contextLine(array $data, ?LicenseStatus $license): string
    {
        $parts = [];
        $offerType = $data['offer_type'] ?? null;
        $offerType = $offerType instanceof OfferType ? $offerType : OfferType::tryFrom((string) self::scalar($offerType));

        if ($offerType !== null) {
            $parts[] = __('business_case.fields.offer_type').': '.$offerType->getLabel();
        }

        if ($license !== null) {
            $parts[] = __('checklist.license_status').': '.$license->getLabel();
        }

        $types = array_filter(array_map(static fn (string $value): ?string => ProjectScopeType::tryFrom($value)?->getLabel(), self::typeValues($data['scope_types'] ?? [])));

        if ($types !== []) {
            $parts[] = __('business_case.fields.scope_types').': '.implode(', ', $types);
        }

        return implode(' · ', $parts);
    }

    /**
     * Formdaki proje tipleri (deger listesi).
     *
     * @return list<string>
     */
    private static function typeValues(mixed $values): array
    {
        return array_values(array_map(static fn (mixed $value): string => (string) self::scalar($value), (array) $values));
    }

    private static function scalar(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private static function license(mixed $value): ?LicenseStatus
    {
        if ($value instanceof LicenseStatus) {
            return $value;
        }

        return is_string($value) && $value !== '' ? LicenseStatus::tryFrom($value) : null;
    }

    /**
     * Formda bu madde icin yeni belge var mi (tahtanin gecici yuklemesi ya da
     * kaydetmede dosyaya cevrilmis yol).
     *
     * @param  array<string, mixed>  $checklist
     */
    private static function formHasItemDocument(array $checklist, string $template, string $item): bool
    {
        $value = $checklist[$template][ChecklistTemplates::documentKey($item)] ?? null;

        return $value instanceof TemporaryUploadedFile || (is_string($value) && $value !== '') || (is_array($value) && $value !== []);
    }

    private static function recordHasItemDocument(?BusinessCase $record, string $template, string $item): bool
    {
        return self::itemDocument($record, $template, $item) !== null;
    }

    private static function itemDocument(?Model $record, string $template, string $item): ?BusinessCaseDocument
    {
        if (! $record instanceof BusinessCase || ! SchemaReadiness::hasBatch('B43')) {
            return null;
        }

        /** @var BusinessCaseDocument|null $row */
        $row = $record->caseDocuments->first(static fn (BusinessCaseDocument $row): bool => $row->template_code === $template && (string) $row->item_code === $item);

        return $row;
    }

    /**
     * Kayittaki ek belgelerin satirlari.
     *
     * @return list<string>
     */
    private static function generalDocumentLines(?Model $record): array
    {
        if (! $record instanceof BusinessCase || ! SchemaReadiness::hasBatch('B43')) {
            return [];
        }

        $lines = [];

        foreach ($record->caseDocuments as $row) {
            /** @var BusinessCaseDocument $row */
            if ($row->item_code === null) {
                $lines[] = DocumentLine::text(DocumentLine::info($row->document), withTitle: true);
            }
        }

        return $lines;
    }
}
