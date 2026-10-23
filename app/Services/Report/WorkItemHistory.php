<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkItemStatus;
use App\Models\Activity\PersonnelActivity;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Support\ActivityLabels;
use App\Support\DisplayTime;
use Illuminate\Support\Str;

/**
 * Isin gecmisi (D-248; kullanici 23 Ekim 2026: "isler hakkinda tamamlandiysa
 * durumu degistirildiyse bunun kim tarafindan yapildigi yine isin icinde bir
 * gecmis alani koyalim ... ilgili iste personeller neler yaptigini gormemiz
 * lazim. adimlar kaybolmamali"). Kaynak Personel Hareketleri'dir
 * (`work_item.*`, yalniz eklenir, silinmez); burada yalniz okunur ve okuma
 * penceresinin Gecmis sekmesi icin bicimlenir. Isin yazisi zaten ustte
 * oldugundan olusturma adiminda tekrar gosterilmez.
 */
final class WorkItemHistory
{
    /** Olusturma adiminda gosterilen alanlar (yazi ve aciklama ustte zaten var). */
    private const CREATED_FIELDS = ['durum', 'sorumlu', 'diger_sorumlular', 'tum_ekip', 'tarih', 'termin', 'saat', 'oncelik', 'kimden_bekleniyor', 'talep_eden'];

    /** Hic gosterilmeyen alanlar: teknik ya da ekranda olmayan (D-245 is grubu). */
    private const HIDDEN_FIELDS = ['kod', 'eski_no', 'is_grubu'];

    /** Uzun yazi alanlari kisaltilir. */
    private const TEXT_FIELDS = ['baslik', 'not'];

    /** Satir sirasi (JSON kolonu anahtar sirasini korumaz); listede olmayan sona. */
    private const FIELD_ORDER = [
        'durum', 'kimden_bekleniyor', 'sorumlu', 'diger_sorumlular', 'tum_ekip', 'tarih', 'termin', 'saat', 'oncelik', 'kritik',
        'departman', 'proje', 'ana_is', 'kategori', 'talep_eden', 'bagli_kayit', 'ek_bagli_kayitlar', 'baslik', 'not', 'kaynak', 'rapor_no',
    ];

    /** Isi olusturana bildirilen degisiklikler (D-248): durum, baslangic, termin, efor. */
    public const CREATOR_FIELDS = ['durum', 'tarih', 'termin', 'saat'];

    public function __construct(private readonly WorkItemQueries $queries) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function entries(WorkItem $item): array
    {
        $zone = DisplayTime::zone();
        $today = DisplayTime::today()->format('Y-m-d');
        $yesterday = DisplayTime::today()->subDay()->format('Y-m-d');
        $entries = [];

        foreach ($this->queries->historyFor((int) $item->getKey()) as $activity) {
            $at = $activity->occurred_at?->copy()->timezone($zone);

            if ($at === null) {
                continue;
            }

            $day = $at->format('Y-m-d');
            $name = $activity->actorName();

            $entries[] = [
                'id' => (int) $activity->getKey(),
                'day' => $day,
                'day_label' => match ($day) {
                    $today => (string) __('work_item.history.today'),
                    $yesterday => (string) __('work_item.history.yesterday'),
                    default => $at->locale(app()->getLocale())->translatedFormat('j F Y, l'),
                },
                'time' => $at->format('H:i'),
                'at' => $at->format('d.m.Y H:i'),
                'actor' => ['name' => $name, 'initials' => WorkItemPresenter::initials($name), 'system' => $activity->personnel === null],
                ...$this->describe($activity),
            ];
        }

        return $entries;
    }

    /**
     * Bildirim satiri: "Durum: Planlandı → Tamamlandı", "Efor: 3 saat".
     */
    public static function changeText(string $field, mixed $value): string
    {
        $label = self::label($field);

        if (is_array($value) && array_key_exists('onceki', $value) && array_key_exists('yeni', $value)) {
            return $label.': '.(self::value($field, $value['onceki']) ?? '–').' → '.(self::value($field, $value['yeni']) ?? '–');
        }

        return $label.': '.(self::value($field, $value) ?? '–');
    }

    /**
     * Adimin turu, fiili ve ayrintisi.
     *
     * @return array{kind: string, text: string, lines: list<array<string, string|null>>, quote: string|null, files: list<string>}
     */
    private function describe(PersonnelActivity $activity): array
    {
        $changes = is_array($activity->changes) ? $activity->changes : [];
        $operation = Str::after((string) $activity->action_code, 'work_item.');

        [$kind, $changes, $quote, $files] = match (true) {
            // Normal kritikligin rozeti yok; olusturmada da yazilmaz.
            $operation === 'created' => ['created', array_filter(array_intersect_key($changes, array_flip(self::CREATED_FIELDS)), static fn (mixed $value, string $field): bool => ! ($field === 'oncelik' && $value === WorkItemPriority::Normal->getLabel()), ARRAY_FILTER_USE_BOTH), null, []],
            $operation === 'status_changed' => ['status', array_diff_key($changes, ['not' => true]), $changes['not'] ?? null, []],
            $operation === 'archived' => ['archive', [], null, []],
            $operation === 'restored' => ['restore', [], null, []],
            $operation === 'added_late' => ['late', $changes, null, []],
            $operation === 'urgent_warned' => ['warn', [], null, []],
            array_key_exists('not_eklendi', $changes) => [filled($changes['kaynak'] ?? null) ? 'note' : 'reply', [], (string) $changes['not_eklendi'], []],
            array_key_exists('belge_eklendi', $changes) => ['file', [], null, self::names($changes['belge_eklendi'])],
            array_key_exists('belge_kaldirildi', $changes) => ['file_removed', [], null, self::names($changes['belge_kaldirildi'])],
            array_diff_key($changes, ['oncelik' => true, 'kritik' => true]) === [] && $changes !== [] => ['priority', $changes, null, []],
            // Duzenlemede yalniz durum (ve bekleme sebebi / efor) degistiyse durum adimidir.
            array_key_exists('durum', $changes) && array_diff_key($changes, ['durum' => true, 'kimden_bekleniyor' => true, 'saat' => true]) === [] => ['status', $changes, null, []],
            default => ['edit', $changes, null, []],
        };

        $verb = __('work_item.history.verbs.'.$kind);

        return [
            'kind' => $kind,
            'text' => is_string($verb) && $verb !== 'work_item.history.verbs.'.$kind ? $verb : mb_strtolower(ActivityLabels::action($activity->action_code)),
            'lines' => $this->lines($changes),
            'quote' => filled($quote) ? Str::limit(trim((string) $quote), 600, '…') : null,
            'files' => $files,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return list<array<string, string|null>>
     */
    private function lines(array $changes): array
    {
        $lines = [];
        $rank = array_flip(self::FIELD_ORDER);
        uksort($changes, static fn (int|string $a, int|string $b): int => ($rank[$a] ?? 99) <=> ($rank[$b] ?? 99));

        foreach ($changes as $field => $value) {
            $field = (string) $field;

            if (in_array($field, self::HIDDEN_FIELDS, true)) {
                continue;
            }

            $pair = is_array($value) && array_key_exists('onceki', $value) && array_key_exists('yeni', $value);
            $from = $pair ? self::value($field, $value['onceki']) : null;
            $to = self::value($field, $pair ? $value['yeni'] : $value);

            if ($pair && $from === $to) {
                continue;
            }

            if (! $pair && $to === null) {
                continue;
            }

            $lines[] = [
                'field' => $field,
                'label' => self::label($field),
                'from' => $from,
                'to' => $to,
                // Durum rozet renkleri (kq-s-*): kayitta etiket var, deger buradan bulunur.
                'from_tone' => $field === 'durum' ? self::statusValue($from) : null,
                'to_tone' => $field === 'durum' ? self::statusValue($to) : null,
            ];
        }

        return $lines;
    }

    private static function label(string $field): string
    {
        $key = 'work_item.history.fields.'.$field;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : ActivityLabels::field($field);
    }

    private static function value(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_bool($value)) {
            return (string) __($value ? 'work_item.history.yes' : 'work_item.history.no');
        }

        if ($field === 'saat' && is_numeric($value)) {
            return (string) __('work_item.history.hours', ['n' => str_replace('.', ',', (string) (float) $value)]);
        }

        if (is_array($value)) {
            return implode(', ', array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : (string) json_encode($part, JSON_UNESCAPED_UNICODE), $value));
        }

        $text = (string) $value;

        // Baslangic gun olarak tutulur; saat 00:00 ise yazilmaz.
        if ($field === 'tarih') {
            $text = (string) preg_replace('/ 00:00$/', '', $text);
        }

        return in_array($field, self::TEXT_FIELDS, true) ? Str::limit(Str::squish($text), 240, '…') : $text;
    }

    private static function statusValue(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        foreach (WorkItemStatus::cases() as $status) {
            if ((string) $status->getLabel() === $label) {
                return $status->value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', explode(', ', (string) $value)), static fn (string $name): bool => $name !== ''));
    }
}
