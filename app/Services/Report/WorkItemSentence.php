<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\Report\WorkDiscipline;
use App\Enums\Report\WorkItemLinkKind;
use App\Enums\Report\WorkItemPriority;
use App\Enums\Report\WorkWaitingKind;
use App\Models\Personnel\Personnel;
use App\Models\Report\WorkItem;
use App\Query\Report\WorkItemQueries;
use App\Services\Platform\SchemaReadiness;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Isin "Akilli is ekle" okunusu (D-235; kullanici 22 Ekim 2026: "Is
 * olusturulurken yazilan text ve badge yapisiyla yine ayni sekilde isler
 * sekmesinde ... listeli sekilde yazilsin"; "bu iş oluşturma text kısmında
 * renkli şekilde badge'li şekilde eklediğimiz ... alanların okunuş biçimi de
 * ilgili iş kartına panodan tıkladığımızda aynı şekilde okunabilir olmalıdır").
 *
 * Tek kaynak: isin alanlarindan metin + rozet dizisi (segments). Is panosu
 * (React) ve duzenleme penceresi bu diziyi okur; Isler listesi ayni diziyi
 * html() ile cizer. Rozet turleri ve renkleri work-quick.js / konelsis-work.css
 * ile aynidir (kq-t-*). Metin baslik (ve not) olarak saklandigi icin rozetler
 * her zaman alanlarin guncel halinden gelir; baska ekrandan degisen alan da
 * dogru okunur.
 */
final class WorkItemSentence
{
    /** Bagli kayit turu -> rozet turu. */
    public const LINK_TYPES = [
        'proposal' => 'proposal',
        'business_case' => 'case',
        'tender_notice' => 'tender',
        'document' => 'document',
        'work_request' => 'request',
        'meeting_plan' => 'meeting',
        'purchase_need' => 'need',
        'purchase_order' => 'order',
        'catalog_item' => 'catalog',
        'supply_item' => 'supply',
    ];

    /** Rozet turu -> Heroicon (Isler listesinde sunucu cizimi). */
    private const ICONS = [
        'person' => 'heroicon-o-user',
        'unit' => 'heroicon-o-building-office',
        'team' => 'heroicon-o-user-group',
        'project' => 'heroicon-o-briefcase',
        'proposal' => 'heroicon-o-document-text',
        'case' => 'heroicon-o-folder',
        'tender' => 'heroicon-o-megaphone',
        'document' => 'heroicon-o-paper-clip',
        'request' => 'heroicon-o-inbox-arrow-down',
        'meeting' => 'heroicon-o-chat-bubble-left-right',
        'need' => 'heroicon-o-shopping-cart',
        'order' => 'heroicon-o-clipboard-document-check',
        'catalog' => 'heroicon-o-cube',
        'supply' => 'heroicon-o-truck',
        'status' => 'heroicon-o-signal',
        'start' => 'heroicon-o-calendar',
        'due' => 'heroicon-o-clock',
        'hours' => 'heroicon-o-bolt',
        'priority' => 'heroicon-o-flag',
        'discipline' => 'heroicon-o-tag',
        'category' => 'heroicon-o-rectangle-stack',
        'requester' => 'heroicon-o-hand-raised',
        'waiting' => 'heroicon-o-pause-circle',
    ];

    public function __construct(private readonly WorkItemPresenter $presenter) {}

    /**
     * Metindeki yerine konabilen rozet turleri (adi yazinin icinde gecer).
     * Alan rozetleri (tarih, efor, kritiklik, bekletici...) ve sabit durum /
     * Sorumlu metne gomulmez.
     */
    private const INLINE_TYPES = ['person', 'unit', 'project', 'proposal', 'case', 'tender', 'document', 'request', 'meeting', 'need', 'order', 'catalog', 'supply'];

    /** Okunusta son satira (durum ve Sorumlu'nun ardina) giden alan rozetleri, sirasiyla (D-247). */
    private const LINE_TWO_TYPES = ['start', 'due', 'hours', 'priority'];

    /**
     * Akilli is ekle'deki okunus (D-235; kullanici 22 Ekim 2026: "Ilgili detay
     * ilgili ifadeye eklensin. 2. satira atanmasina gerek yok. Olusturulurken gibi
     * gorunecektir kayit edildiginde de ve listelenirken de"): adi metinde gecen
     * rozet o ifadenin yerinde, digerleri yazinin sonunda; son satirda sabit
     * durum ve Sorumlu. Baslik noktayla bitiyorsa aciklamanin ilk satiri ayni
     * paragrafta devam eder (ilk cumle baslik, sonrakiler aciklama).
     *
     * @return list<array<string, mixed>>
     */
    public function segments(WorkItem $item): array
    {
        return $this->build($item, false);
    }

    /**
     * @param  bool  $lineTwo  D-247: tarih, efor ve kritiklik son satirda, durum ve Sorumlu'nun ardinda
     * @return list<array<string, mixed>>
     */
    private function build(WorkItem $item, bool $lineTwo): array
    {
        [$pinned, $pool] = $this->splitBadges($this->badges($item));

        if ($lineTwo) {
            $pinned = [...$pinned, ...array_values(array_filter($pool, static fn (array $badge): bool => in_array($badge['type'], self::LINE_TWO_TYPES, true)))];
            $pool = array_values(array_filter($pool, static fn (array $badge): bool => ! in_array($badge['type'], self::LINE_TWO_TYPES, true)));
        }

        [$first, $noteLines] = $this->textLines($item);
        $paragraphs = [$this->inline($first, $pool)];

        foreach ($noteLines as $line) {
            $paragraphs[] = $this->inline($line, $pool);
        }

        $last = array_key_last($paragraphs);

        foreach ($pool as $badge) {
            $paragraphs[$last][] = ['t' => 'text', 'v' => ' '];
            $paragraphs[$last][] = ['t' => 'badge', 'b' => $badge];
        }

        $segments = [];

        foreach ($paragraphs as $index => $paragraph) {
            if ($index > 0) {
                $segments[] = ['t' => 'br'];
            }

            array_push($segments, ...$paragraph);
        }

        if ($pinned !== []) {
            $segments[] = ['t' => 'br'];

            foreach ($pinned as $index => $badge) {
                if ($lineTwo && $index > 0) {
                    $segments[] = ['t' => 'text', 'v' => ' '];
                }

                $segments[] = ['t' => 'badge', 'b' => $badge];
            }
        }

        return $segments;
    }

    /**
     * Sabit rozetler (durum, Sorumlu) ve digerleri.
     *
     * @param  list<array<string, mixed>>  $badges
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function splitBadges(array $badges): array
    {
        $pinned = [];
        $pool = [];

        foreach ($badges as $badge) {
            $isPinned = ($badge['type'] === 'status' && ! isset($pinned['status']))
                || ($badge['type'] === 'person' && ($badge['role'] ?? null) === 'owner' && ! isset($pinned['owner']));

            if ($isPinned) {
                $pinned[$badge['type'] === 'status' ? 'status' : 'owner'] = $badge;
            } else {
                $pool[] = $badge;
            }
        }

        return [array_values(array_filter([$pinned['status'] ?? null, $pinned['owner'] ?? null])), $pool];
    }

    /**
     * Okunusun satirlari. Baslik noktayla bitiyorsa aciklamanin ilk satiri ayni
     * paragrafa eklenir. Eski kayit: baslik 200 karakterde kesilip "…" ile
     * bitmis ve aciklama tam metinle baslar; ayni metin iki kez gosterilmez.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function textLines(WorkItem $item): array
    {
        $title = trim((string) $item->title);
        $noteLines = filled($item->note) ? array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $item->note) ?: []), static fn (string $line): bool => $line !== '')) : [];
        $stem = rtrim(mb_substr($title, 0, -1));

        if (str_ends_with($title, '…') && $noteLines !== [] && $stem !== '' && str_starts_with($noteLines[0], $stem)) {
            return [array_shift($noteLines), $noteLines];
        }

        if ($noteLines !== [] && preg_match('/[.!?…]$/u', $title) === 1) {
            $title .= ' '.array_shift($noteLines);
        }

        return [$title, $noteLines];
    }

    /**
     * Metni, adi icinde gecen rozetlerle boler; yerlestirilen rozet havuzdan cikar.
     *
     * @param  list<array<string, mixed>>  $pool
     * @return list<array<string, mixed>>
     */
    private function inline(string $text, array &$pool): array
    {
        $taken = [];
        $order = array_keys($pool);
        usort($order, static fn (int $a, int $b): int => mb_strlen((string) ($pool[$b]['label'] ?? '')) <=> mb_strlen((string) ($pool[$a]['label'] ?? '')));

        foreach ($order as $index) {
            $label = trim((string) ($pool[$index]['label'] ?? ''));

            if ($label === '' || ! in_array($pool[$index]['type'], self::INLINE_TYPES, true)) {
                continue;
            }

            foreach (['mb_strpos', 'mb_stripos'] as $find) {
                $offset = 0;

                while (($position = $find($text, $label, $offset)) !== false) {
                    $end = $position + mb_strlen($label);
                    $free = true;

                    foreach ($taken as [$start, $stop]) {
                        if ($position < $stop && $end > $start) {
                            $free = false;

                            break;
                        }
                    }

                    if ($free) {
                        $taken[] = [$position, $end, $index];

                        continue 3;
                    }

                    $offset = $position + 1;
                }
            }
        }

        usort($taken, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $segments = [];
        $cursor = 0;

        foreach ($taken as [$start, $stop, $index]) {
            if ($start > $cursor) {
                $segments[] = ['t' => 'text', 'v' => mb_substr($text, $cursor, $start - $cursor)];
            }

            $segments[] = ['t' => 'badge', 'b' => $pool[$index]];
            $cursor = $stop;
            unset($pool[$index]);
        }

        if ($cursor < mb_strlen($text)) {
            $segments[] = ['t' => 'text', 'v' => mb_substr($text, $cursor)];
        }

        $pool = array_values($pool);

        return $segments;
    }

    /**
     * Isin rozetleri (sira: durum, kisiler, ekip / departman, taahhut, bagli
     * kayitlar, tarihler, efor, kritiklik, is grubu, kategori, talep eden, bekletici).
     *
     * @return list<array<string, mixed>>
     */
    public function badges(WorkItem $item): array
    {
        $badges = [];
        $extended = WorkItemQueries::extended();

        if ($item->status !== null) {
            $badges[] = ['type' => 'status', 'value' => $item->status->value, 'label' => (string) $item->status->getLabel()];
        }

        // Sorumlu (D-235): rozet satirinin basinda "Sorumlu: Ad"; diger kisiler ek sorumlu.
        if ($item->personnel instanceof Personnel) {
            $badges[] = ['type' => 'person', 'role' => 'owner', 'id' => (int) $item->personnel->getKey(), 'label' => (string) $item->personnel->full_name];
        }

        if ($extended) {
            foreach ($item->assignees as $person) {
                $badges[] = ['type' => 'person', 'id' => (int) $person->getKey(), 'label' => (string) $person->full_name];
            }

            if ((bool) $item->getAttribute('for_everyone')) {
                $badges[] = ['type' => 'team', 'value' => '1', 'label' => (string) __('work_item.ui.qa_type_team')];
            }
        }

        if ($item->orgUnit !== null) {
            $badges[] = ['type' => 'unit', 'id' => (int) $item->orgUnit->getKey(), 'label' => (string) $item->orgUnit->name];
        }

        if ($item->project !== null) {
            $badges[] = ['type' => 'project', 'id' => (int) $item->project->getKey(), 'label' => (string) $item->project->display_name];
        }

        $link = $this->presenter->link($item);

        if ($link !== null) {
            $badges[] = $this->linkBadge($link, $item->source?->value === 'automatic');
        }

        foreach ($this->presenter->extraLinks($item) as $extra) {
            $badges[] = $this->linkBadge($extra, false);
        }

        if ($item->work_on !== null) {
            $badges[] = ['type' => 'start', 'value' => $item->work_on->format('Y-m-d'), 'label' => (string) __('work_item.ui.qa_type_start')];
        }

        if ($item->due_on !== null) {
            $badges[] = ['type' => 'due', 'value' => $item->due_on->format('Y-m-d'), 'label' => (string) __('work_item.ui.qa_type_due')];
        }

        if ($item->work_hours !== null && (float) $item->work_hours > 0) {
            $badges[] = ['type' => 'hours', 'value' => (string) (float) $item->work_hours, 'label' => (string) __('work_item.ui.qa_type_hours')];
        }

        $priority = $item->priorityLevel();

        if ($priority !== WorkItemPriority::Normal) {
            $badges[] = ['type' => 'priority', 'value' => $priority->value, 'text' => (string) $priority->getLabel(), 'label' => (string) __('work_item.ui.qa_type_priority')];
        }

        // D-245: is grubu ekranlarda yok (WorkItemQueries::disciplineUi()).
        $discipline = $extended && WorkItemQueries::disciplineUi() ? $item->getAttribute('discipline') : null;

        if ($discipline instanceof WorkDiscipline) {
            $badges[] = ['type' => 'discipline', 'value' => $discipline->value, 'text' => (string) $discipline->getLabel(), 'label' => (string) __('work_item.ui.qa_type_discipline')];
        }

        if (filled($item->category_code)) {
            $badges[] = ['type' => 'category', 'value' => (string) $item->category_code, 'text' => (string) ($item->categoryLabel() ?? $item->category_code), 'label' => (string) __('work_item.ui.qa_type_category')];
        }

        if ($item->requester_kind instanceof WorkWaitingKind && SchemaReadiness::hasBatch('B37') && filled($item->requesterLabel())) {
            $badges[] = $this->sourceBadge('requester', $item->requester_kind, $item->requester_personnel_id, $item->requester_party_id, $item->requester_text, (string) $item->requesterLabel());
        }

        if ($item->waiting_kind instanceof WorkWaitingKind && filled($item->waitingLabel())) {
            $badges[] = $this->sourceBadge('waiting', $item->waiting_kind, $item->waiting_personnel_id, $item->waiting_party_id, $item->waiting_text, (string) $item->waitingLabel());
        }

        return $badges;
    }

    /**
     * Filament tablolari icin cizim (Isler yedek listesi, personel karti; kq-t-*
     * renkleri). D-240, kullanici 23 Ekim 2026: "Listelemede baslik felan yok.
     * Direk tek text alani olusturuldugu gibi olacak": React listesiyle ayni
     * okunus (segments), tek metin; son satirda durum ve Sorumlu.
     */
    public function html(WorkItem $item): Htmlable
    {
        // D-247 (kullanici 23 Ekim 2026: "Sorumlu ve is'in durumu birde tarih (efor, baslangic,
        // termin) ve kritiklik onlar alta 2. satir olayi vardi ya oraya dussun"): React okunusu
        // (work-quick.js readingSegments) gibi tarih, efor ve kritiklik son satirda.
        return new HtmlString('<span class="kw kq-line">'.$this->segmentsHtml($this->build($item, true)).'</span>');
    }

    /** @param  list<array<string, mixed>>  $segments */
    private function segmentsHtml(array $segments): string
    {
        $html = '';

        foreach ($segments as $segment) {
            $html .= match ($segment['t']) {
                'badge' => $this->badgeHtml($segment['b']),
                'br' => '<br>',
                default => e((string) ($segment['v'] ?? '')),
            };
        }

        return $html;
    }

    /** Rozetin gorunen metni (work-quick.js badgeText ile ayni). */
    public static function badgeText(array $badge): string
    {
        $type = (string) ($badge['type'] ?? '');
        $label = (string) ($badge['label'] ?? '');

        return match ($type) {
            'start', 'due' => $label.': '.self::date((string) ($badge['value'] ?? '')),
            'hours' => $label.': '.str_replace('.', ',', (string) (float) ($badge['value'] ?? 0)).' '.__('work_item.ui.qa_hours_unit'),
            'priority', 'discipline', 'category' => $label.': '.($badge['text'] ?? $badge['value'] ?? ''),
            'person' => ($badge['role'] ?? null) === 'owner' ? (string) __('work_item.ui.qa_owner', ['name' => $label]) : $label,
            'requester' => __('work_item.ui.qa_type_requester').': '.$label,
            'waiting' => __('work_item.ui.qa_type_waiting').': '.$label,
            default => $label,
        };
    }

    /**
     * @param  array{kind: string, kind_label: string, id: int, no: string|null, label: string, url: string|null}  $link
     * @return array<string, mixed>
     */
    private function linkBadge(array $link, bool $locked): array
    {
        return array_filter([
            'type' => self::LINK_TYPES[$link['kind']] ?? 'document',
            'id' => (int) $link['id'],
            'label' => (string) $link['label'],
            'code' => $link['no'],
            'locked' => $locked ? true : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function sourceBadge(string $type, WorkWaitingKind $kind, mixed $personnelId, mixed $partyId, ?string $text, string $label): array
    {
        return [
            'type' => $type,
            'kind' => $kind->value,
            'id' => match ($kind) {
                WorkWaitingKind::Personnel => $personnelId !== null ? (int) $personnelId : null,
                WorkWaitingKind::Party => $partyId !== null ? (int) $partyId : null,
                default => null,
            },
            'value' => $kind === WorkWaitingKind::Text || ($kind === WorkWaitingKind::Party && $partyId === null) ? $text : null,
            'label' => $label,
        ];
    }

    /** @param  array<string, mixed>  $badge */
    private function badgeHtml(array $badge): string
    {
        $type = (string) $badge['type'];
        $classes = 'kq-badge kq-t-'.$type
            .($type === 'priority' ? ' kq-p-'.e((string) $badge['value']) : '')
            .($type === 'status' ? ' kq-s-'.e((string) $badge['value']) : '');
        $icon = '';

        try {
            $icon = svg(self::ICONS[$type] ?? 'heroicon-o-tag', 'kq-icon')->toHtml();
        } catch (Throwable) {
            $icon = '';
        }

        $title = filled($badge['code'] ?? null) ? ' title="'.e((string) $badge['code']).'"' : '';

        return '<span class="'.$classes.'"'.$title.'>'.$icon.'<span class="kq-badge-text">'.e(self::badgeText($badge)).'</span></span>';
    }

    private static function date(string $ymd): string
    {
        $parts = explode('-', $ymd);

        return count($parts) === 3 ? $parts[2].'.'.$parts[1].'.'.$parts[0] : $ymd;
    }
}
