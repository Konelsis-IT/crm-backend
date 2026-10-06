<?php

declare(strict_types=1);

namespace App\Support\Acquisition;

use App\Enums\Acquisition\ChecklistAnswer;
use App\Enums\Acquisition\LicenseStatus;
use App\Enums\Acquisition\ProjectScopeType;
use BackedEnum;

/**
 * Teklif oncesi idari ve teknik uygunluk kontrol listeleri (B43, D-155; 5 Ekim
 * 2026 kullanicinin paylastigi "Teklif Oncesi Checklistler" dosyasi).
 *
 * - GES listesi: 7 agirlikli ana madde (35 / 5 / 10 / 5 / 15 / 15 / 15) ve
 *   opsiyonel 8. madde (ENH / baglanti guzergahi, sicakliga girmez). RES, HES,
 *   BESS ve ENH/EIH de bu listeye bakar.
 * - TM listesi (154 kV trafo merkezi): 5 ana madde, dosyada agirlik yok;
 *   kullanici karariyla esit (20'ser).
 * - Potansiyel iste iki tur birlikte seciliyse iki liste de doldurulur;
 *   sicaklik iki listenin ortalamasidir.
 *
 * Her ana maddenin alti 3 sorudur; cevap Evet / Hayir / Bilinmiyor. Olumlu
 * cevap "Evet"tir; sorusu olumsuz kurulmus maddelerde (ilave yatirim sarti,
 * irtifak / kamulastirma ihtiyaci, eksik / revizyon bekleyen konu) "Hayir".
 *
 * Teklif sicakligi (0-100) = her ana madde icin agirlik x ((olumlu cevap +
 * yuklu belge) / (soru sayisi + 1)) toplami: uc soru ve maddenin belgesi esit
 * pay tasir (D-159). Bilinmiyor ve bos cevap olumlu sayilmaz ("–": ekranda
 * ikisi aynidir, D-157).
 *
 * Kurallar (kullanici talimati):
 * - GES 1.3 "Gecerlilik suresi devam ediyor mu?" Hayir ise potansiyel isin
 *   teklif tipi dogrudan Butcesel olur ve kullaniciya soylenir.
 * - Cagri mektubu (GES 1) belgesi proje lisansli (Onlisans / Lisans) degilse
 *   zorunludur; lisansliysa zorunlu degildir ama yuklenebilir. Diger ana
 *   maddelerin belgesi istege baglidir.
 * - Lisansli projede (D-157, 5 Ekim 2026: "Onlisans / Lisans sectigimde Cagri
 *   mektubu agirlik yine ayni gorunuyor") Cagri mektubu maddesi opsiyoneldir:
 *   sicakliga girmez, %35'lik payi kalan maddelere agirliklari oraninda dagilir
 *   (weights()), 1.3 kurali da uygulanmaz.
 *
 * KonelsisAI (sonraki adim, kullanici talimati: "ai destegi geldiginde belgeden
 * otomatik cekecektir"): cevaplar bugun elle secilir. AI geldiginde her ana
 * maddeye yuklenen belge (or. Cagri mektubu, imar onayi) okunup alt sorularin
 * cevabi ve dayanak cumlesi onerilecek; oneri kullanicinin onayiyla bu
 * tablodaki cevaba yazilacak (cevabin kaynagi "ai" olarak ayrica tutulacak,
 * B43'te kolon yok). Ekranda "KonelsisAI yakinda" etiketi bu yeri isaret eder.
 */
final class ChecklistTemplates
{
    public const GES = 'ges';

    public const TM = 'tm';

    /** Cagri mektubu ana maddesi (GES): lisanssiz projede belge zorunlu. */
    public const CALL_LETTER_ITEM = '1';

    /** Gecerlilik sorusu (GES 1.3): "Hayir" ise teklif tipi Butcesel olur. */
    public const VALIDITY_QUESTION = '1.3';

    /**
     * Sablon => ana madde => tanim. `negative`: "Hayir"in olumlu oldugu sorular.
     *
     * @var array<string, array<string, array{weight: int, questions: list<string>, optional?: bool, document_required?: bool, negative?: list<string>}>>
     */
    private const TEMPLATES = [
        self::GES => [
            '1' => ['weight' => 35, 'questions' => ['1.1', '1.2', '1.3'], 'document_required' => true],
            '2' => ['weight' => 5, 'questions' => ['2.1', '2.2', '2.3'], 'negative' => ['2.3']],
            '3' => ['weight' => 10, 'questions' => ['3.1', '3.2', '3.3']],
            '4' => ['weight' => 5, 'questions' => ['4.1', '4.2', '4.3']],
            '5' => ['weight' => 15, 'questions' => ['5.1', '5.2', '5.3'], 'negative' => ['5.3']],
            '6' => ['weight' => 15, 'questions' => ['6.1', '6.2', '6.3']],
            '7' => ['weight' => 15, 'questions' => ['7.1', '7.2', '7.3']],
            '8' => ['weight' => 0, 'questions' => ['8.1', '8.2', '8.3'], 'optional' => true],
        ],
        self::TM => [
            '1' => ['weight' => 20, 'questions' => ['1.1', '1.2', '1.3']],
            '2' => ['weight' => 20, 'questions' => ['2.1', '2.2', '2.3']],
            '3' => ['weight' => 20, 'questions' => ['3.1', '3.2', '3.3'], 'negative' => ['3.3']],
            '4' => ['weight' => 20, 'questions' => ['4.1', '4.2', '4.3']],
            '5' => ['weight' => 20, 'questions' => ['5.1', '5.2', '5.3']],
        ],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * Secili proje tiplerinin baktigi listeler (sirali, tekil).
     *
     * @param  iterable<mixed>  $scopeTypes  enum ya da deger
     * @return list<string>
     */
    public static function forScopeTypes(iterable $scopeTypes): array
    {
        $templates = [];

        foreach ($scopeTypes as $type) {
            $case = $type instanceof ProjectScopeType ? $type : ProjectScopeType::tryFrom($type instanceof BackedEnum ? (string) $type->value : (string) $type);

            if ($case !== null) {
                $templates[$case->checklistTemplate()] = true;
            }
        }

        return array_values(array_filter(self::codes(), static fn (string $code): bool => isset($templates[$code])));
    }

    /**
     * @return array<string, array{weight: int, questions: list<string>, optional: bool, document_required: bool, negative: list<string>}>
     */
    public static function items(string $template): array
    {
        $items = [];

        foreach (self::TEMPLATES[$template] ?? [] as $code => $item) {
            $items[$code] = [
                'weight' => $item['weight'],
                'questions' => $item['questions'],
                'optional' => $item['optional'] ?? false,
                'document_required' => $item['document_required'] ?? false,
                'negative' => $item['negative'] ?? [],
            ];
        }

        return $items;
    }

    /**
     * Ana madde kodlari metin olarak ("1", "2"...; PHP sayisal dizi anahtarini tamsayi yapar).
     *
     * @return list<string>
     */
    public static function itemCodes(string $template): array
    {
        return array_map('strval', array_keys(self::TEMPLATES[$template] ?? []));
    }

    public static function exists(string $template, string $code): bool
    {
        if (isset(self::TEMPLATES[$template][$code])) {
            return true;
        }

        foreach (self::TEMPLATES[$template] ?? [] as $item) {
            if (in_array($code, $item['questions'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ana madde ya da soru metni (lang/checklist.php). Sorular `questions`
     * altinda "q1_1" anahtariyla durur: "1.1" ceviri yolunda ic ice diziye donerdi.
     */
    public static function label(string $template, string $code): string
    {
        return str_contains($code, '.')
            ? (string) __('checklist.questions.'.$template.'.'.self::questionKey($code))
            : (string) __('checklist.items.'.$template.'.'.$code);
    }

    /** Lisansli projede (Onlisans / Lisans) Cagri mektubu maddesi opsiyoneldir (D-157). */
    public static function licenseExempt(string $template, string $item, ?LicenseStatus $license): bool
    {
        return $template === self::GES && $item === self::CALL_LETTER_ITEM && ($license?->isLicensed() ?? false);
    }

    /** Ana madde bu proje durumunda sicakliga giriyor mu (opsiyonel madde ve lisansli projede Cagri mektubu girmez). */
    public static function counts(string $template, string $item, ?LicenseStatus $license): bool
    {
        $definition = self::TEMPLATES[$template][$item] ?? null;

        if ($definition === null || ($definition['optional'] ?? false) || $definition['weight'] <= 0) {
            return false;
        }

        return ! self::licenseExempt($template, $item, $license);
    }

    /**
     * Sicakliga giren maddelerin agirliklari, toplami 100'e olceklenmis: lisansli
     * projede Cagri mektubunun payi kalan maddelere agirliklari oraninda dagilir.
     *
     * @return array<string, float> ana madde => agirlik
     */
    public static function weights(string $template, ?LicenseStatus $license): array
    {
        $weights = [];

        foreach (self::TEMPLATES[$template] ?? [] as $code => $definition) {
            $item = (string) $code;

            if (self::counts($template, $item, $license)) {
                $weights[$item] = (float) $definition['weight'];
            }
        }

        $total = array_sum($weights);

        if ($total <= 0) {
            return [];
        }

        return array_map(static fn (float $weight): float => $weight * 100 / $total, $weights);
    }

    /**
     * Ekranda yazan tam sayi agirliklar; toplam yine 100 (en buyuk kalan yontemi).
     *
     * @return array<string, int> ana madde => yuzde
     */
    public static function displayWeights(string $template, ?LicenseStatus $license): array
    {
        $exact = self::weights($template, $license);
        $shown = array_map(static fn (float $weight): int => (int) floor($weight), $exact);
        $left = 100 - array_sum($shown);
        $remainders = [];

        foreach ($exact as $item => $weight) {
            $remainders[$item] = $weight - floor($weight);
        }

        arsort($remainders);

        foreach (array_keys($remainders) as $item) {
            if ($left <= 0) {
                break;
            }

            $shown[$item]++;
            $left--;
        }

        return $shown;
    }

    /**
     * Sicakliga giren sorularin cevap durumu: cevaplanan (Evet / Hayir) ve toplam.
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, mixed>>  $answers  sablon => soru kodu => cevap
     * @return array{answered: int, total: int}
     */
    public static function progress(array $templates, array $answers, ?LicenseStatus $license): array
    {
        $answered = 0;
        $total = 0;

        foreach ($templates as $template) {
            foreach (self::TEMPLATES[$template] ?? [] as $code => $definition) {
                if (! self::counts($template, (string) $code, $license)) {
                    continue;
                }

                foreach ($definition['questions'] as $question) {
                    $total++;
                    $answer = self::answer($answers[$template][$question] ?? null);
                    $answered += ($answer === ChecklistAnswer::Yes || $answer === ChecklistAnswer::No) ? 1 : 0;
                }
            }
        }

        return ['answered' => $answered, 'total' => $total];
    }

    /**
     * Sicakliga giren ana maddelerin belge durumu: yuklu ve toplam (D-159).
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, bool>>  $documents  sablon => ana madde => belge var mi
     * @return array{present: int, total: int}
     */
    public static function documentProgress(array $templates, array $documents, ?LicenseStatus $license): array
    {
        $present = 0;
        $total = 0;

        foreach ($templates as $template) {
            foreach (self::itemCodes($template) as $item) {
                if (! self::counts($template, $item, $license)) {
                    continue;
                }

                $total++;
                $present += ($documents[$template][$item] ?? false) ? 1 : 0;
            }
        }

        return ['present' => $present, 'total' => $total];
    }

    /**
     * Acik ("–") sorularin hepsi olumlu cikar ve eksik belgeler yuklenirse
     * ulasilacak sicaklik (personeli eksikleri doldurmaya tesvik eden ozet satiri icin).
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, mixed>>  $answers
     */
    public static function potentialHeat(array $templates, array $answers, ?LicenseStatus $license): ?int
    {
        $filled = [];
        $documents = [];

        foreach ($templates as $template) {
            foreach (self::TEMPLATES[$template] ?? [] as $code => $definition) {
                $documents[$template][(string) $code] = true;

                foreach ($definition['questions'] as $question) {
                    $answer = self::answer($answers[$template][$question] ?? null);
                    $open = $answer === null || $answer === ChecklistAnswer::Unknown;
                    $filled[$template][$question] = $open
                        ? (self::isNegative($template, $question) ? ChecklistAnswer::No : ChecklistAnswer::Yes)
                        : $answer;
                }
            }
        }

        return self::heat($templates, $filled, $license, $documents);
    }

    /** Form durumundaki anahtar: "1.3" -> "q1_3" (noktali yol ic ice diziye donmesin). */
    public static function questionKey(string $question): string
    {
        return 'q'.str_replace('.', '_', $question);
    }

    /** Ana maddenin belge alani anahtari: "1" -> "doc_1". */
    public static function documentKey(string $item): string
    {
        return 'doc_'.$item;
    }

    public static function isNegative(string $template, string $question): bool
    {
        foreach (self::TEMPLATES[$template] ?? [] as $item) {
            if (in_array($question, $item['negative'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    public static function answer(mixed $value): ?ChecklistAnswer
    {
        if ($value instanceof ChecklistAnswer) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_string($value) && $value !== '' ? ChecklistAnswer::tryFrom($value) : null;
    }

    public static function isFavourable(string $template, string $question, mixed $answer): bool
    {
        $answer = self::answer($answer);

        if ($answer === null || $answer === ChecklistAnswer::Unknown) {
            return false;
        }

        return self::isNegative($template, $question) ? $answer === ChecklistAnswer::No : $answer === ChecklistAnswer::Yes;
    }

    /**
     * Teklif sicakligi 0-100; liste yoksa null. Birden fazla liste varsa ortalama.
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, mixed>>  $answers  sablon => soru kodu => cevap
     * @param  array<string, array<string, bool>>  $documents  sablon => ana madde => belge var mi (D-159)
     */
    public static function heat(array $templates, array $answers, ?LicenseStatus $license = null, array $documents = []): ?int
    {
        if ($templates === []) {
            return null;
        }

        $total = 0.0;

        foreach ($templates as $template) {
            $total += self::templateHeat($template, $answers[$template] ?? [], $license, $documents[$template] ?? []);
        }

        return max(0, min(100, (int) round($total / count($templates))));
    }

    /**
     * Ana maddenin olumlu orani (0-1); opsiyonel maddede de hesaplanir.
     *
     * D-159 (6 Ekim 2026 kullanici talimati: "Belge yuklenmesi de, ornegin 1 icin
     * Cagri mektubu belgesi demek, yani o da bir agirlik oluyor ... belge
     * yuklenmesi de tum seceneklerde bir agirliktir"): maddenin uc sorusu ve
     * belgesi esit pay tasir; olumlu soru ve yuklu belge sayisi / (soru + 1).
     *
     * @param  array<string, mixed>  $answers  soru kodu => cevap
     */
    public static function itemShare(string $template, string $item, array $answers, bool $hasDocument = false): float
    {
        $questions = self::TEMPLATES[$template][$item]['questions'] ?? [];

        if ($questions === []) {
            return 0.0;
        }

        $favourable = count(array_filter($questions, static fn (string $question): bool => self::isFavourable($template, $question, $answers[$question] ?? null)));

        return ($favourable + ($hasDocument ? 1 : 0)) / (count($questions) + 1);
    }

    /**
     * GES 1.3 "Hayir" mi (teklif tipi Butcesel olur). Lisansli projede Cagri
     * mektubu opsiyonel oldugu icin kural uygulanmaz (D-157).
     *
     * @param  array<string, array<string, mixed>>  $answers
     */
    public static function forcesBudgetary(array $answers, ?LicenseStatus $license = null): bool
    {
        return ! self::licenseExempt(self::GES, self::CALL_LETTER_ITEM, $license)
            && self::answer($answers[self::GES][self::VALIDITY_QUESTION] ?? null) === ChecklistAnswer::No;
    }

    /** Ana maddenin belgesi bu proje durumunda zorunlu mu. */
    public static function documentRequired(string $template, string $item, ?LicenseStatus $license): bool
    {
        $required = (bool) (self::TEMPLATES[$template][$item]['document_required'] ?? false);

        return $required && ! ($license?->isLicensed() ?? false);
    }

    /**
     * Kaydetmede gosterilen eksikler: proje durumu, "–" (cevapsiz ya da
     * Bilinmiyor) sorular, belgesi yuklenmemis ana maddeler (zorunlu olan
     * ayrica yazar, D-159). Opsiyonel madde (lisansli projede Cagri mektubu
     * dahil) listelenmez.
     *
     * @param  list<string>  $templates
     * @param  array<string, array<string, mixed>>  $answers  sablon => soru kodu => cevap
     * @param  array<string, array<string, bool>>  $documents  sablon => ana madde => belge var mi
     * @return list<string>
     */
    public static function missing(array $templates, array $answers, array $documents, ?LicenseStatus $license): array
    {
        if ($templates === []) {
            return [];
        }

        $lines = [];

        if ($license === null && in_array(self::GES, $templates, true)) {
            $lines[] = (string) __('checklist.incomplete.license_missing');
        }

        foreach ($templates as $template) {
            $prefix = count($templates) > 1 ? mb_strtoupper($template).' ' : '';

            foreach (self::items($template) as $code => $definition) {
                $item = (string) $code;

                if (! self::counts($template, $item, $license)) {
                    continue;
                }

                foreach ($definition['questions'] as $question) {
                    $answer = self::answer($answers[$template][$question] ?? null);

                    if ($answer === null || $answer === ChecklistAnswer::Unknown) {
                        $lines[] = (string) __('checklist.incomplete.answer_missing', [
                            'code' => $prefix.$question,
                            'question' => self::label($template, $question),
                        ]);
                    }
                }

                // D-159: belge de maddenin bir payidir; yuklenmemis her belge listelenir.
                if (! ($documents[$template][$item] ?? false)) {
                    $lines[] = (string) __(self::documentRequired($template, $item, $license) ? 'checklist.incomplete.document_missing_required' : 'checklist.incomplete.document_missing', [
                        'code' => $prefix.$item,
                        'item' => self::label($template, $item),
                    ]);
                }
            }
        }

        return $lines;
    }

    /** Sicaklik derecesi: cold (< 25), warm (< 50), hot (< 75), burning. */
    public static function heatLevel(?int $heat): ?string
    {
        return match (true) {
            $heat === null => null,
            $heat < 25 => 'cold',
            $heat < 50 => 'warm',
            $heat < 75 => 'hot',
            default => 'burning',
        };
    }

    /** Filament rengi (liste rozeti). */
    public static function heatColor(?int $heat): string
    {
        return match (self::heatLevel($heat)) {
            'cold' => 'info',
            'warm' => 'warning',
            'hot', 'burning' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @param  array<string, mixed>  $answers  soru kodu => cevap
     * @param  array<string, bool>  $documents  ana madde => belge var mi
     */
    private static function templateHeat(string $template, array $answers, ?LicenseStatus $license, array $documents = []): float
    {
        $score = 0.0;

        foreach (self::weights($template, $license) as $item => $weight) {
            $score += $weight * self::itemShare($template, (string) $item, $answers, (bool) ($documents[(string) $item] ?? false));
        }

        return $score;
    }
}
