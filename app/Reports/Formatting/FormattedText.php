<?php

declare(strict_types=1);

namespace App\Reports\Formatting;

use Illuminate\Support\Str;

/**
 * Bicimli rapor metni (D-167, 6 Ekim 2026 kullanici istegi: "LLM'lerin bize
 * sundugu bir text arayuzu ... paragraf, baslik, gerekirse icon ... maddeler
 * halinde, satir boslugu, kalin yazi"). Tek bir blok listesi uc bicimde
 * yazilir:
 *
 * - markdown(): rapor detayinda gosterilen metin (Filament TextEntry markdown);
 * - plain(): panoya duz metin olarak kopyalanan hali (isaretsiz);
 * - html(): panoya zengin metin (e-posta / Word) ve PDF govdesi. PDF'te
 *   simgeler (emoji) yazilmaz; dompdf yazi tipi onlari basamaz.
 *
 * Kullanici metni (not, ozet) Markdown olarak yorumlanir; satir baslarindaki
 * "-" ve "1." maddeleri korunur, baslik ve cizgi isaretleri kacirilir, HTML
 * etiketi metne cevrilir (html_input: escape).
 */
final class FormattedText
{
    private const HEADING = 'heading';

    private const TEXT = 'text';

    private const FACTS = 'facts';

    private const BULLETS = 'bullets';

    private const RULE = 'rule';

    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    public function heading(int $level, string $text, ?string $icon = null): self
    {
        $this->blocks[] = ['type' => self::HEADING, 'level' => max(1, min(3, $level)), 'text' => $text, 'icon' => $icon];

        return $this;
    }

    /** Kullanicinin yazdigi serbest metin (paragraflar, maddeler). */
    public function text(?string $text): self
    {
        if (filled($text)) {
            $this->blocks[] = ['type' => self::TEXT, 'text' => trim((string) $text)];
        }

        return $this;
    }

    /**
     * Kalin etiketli bilgi satirlari ("Hazirlayan: ...").
     *
     * @param  list<array{label: string, value: string, icon?: string|null}>  $facts
     */
    public function facts(array $facts): self
    {
        $facts = array_values(array_filter($facts, static fn (array $fact): bool => filled($fact['value'] ?? null)));

        if ($facts !== []) {
            $this->blocks[] = ['type' => self::FACTS, 'facts' => $facts];
        }

        return $this;
    }

    /**
     * Madde listesi. Her madde: kalin baslik, ayni satirda ek bilgi, alt
     * satirda ayrinti, alintilanan metin ve kalin etiketli ek satir.
     *
     * @param  list<array{title?: string|null, rest?: string|null, detail?: string|null, quote?: string|null, note_label?: string|null, note?: string|null, icon?: string|null}>  $items
     */
    public function bullets(array $items): self
    {
        $items = array_values(array_filter($items, static fn (array $item): bool => filled($item['title'] ?? null) || filled($item['rest'] ?? null)));

        if ($items !== []) {
            $this->blocks[] = ['type' => self::BULLETS, 'items' => $items];
        }

        return $this;
    }

    public function rule(): self
    {
        $this->blocks[] = ['type' => self::RULE];

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    public function markdown(bool $icons = true): string
    {
        $out = [];

        foreach ($this->blocks as $block) {
            $out[] = match ($block['type']) {
                self::HEADING => str_repeat('#', $block['level']).' '.$this->withIcon($this->escape($block['text'], lineStart: true), $block['icon'], $icons),
                self::TEXT => $this->userMarkdown($block['text']),
                self::FACTS => implode("  \n", array_map(
                    fn (array $fact): string => $this->withIcon('**'.$this->escape($fact['label']).':** '.$this->escape((string) $fact['value']), $fact['icon'] ?? null, $icons),
                    $block['facts'],
                )),
                self::BULLETS => implode("\n", array_map(fn (array $item): string => $this->bulletMarkdown($item, $icons), $block['items'])),
                default => '---',
            };
        }

        return implode("\n\n", $out)."\n";
    }

    public function plain(bool $icons = true): string
    {
        $out = [];

        foreach ($this->blocks as $block) {
            $out[] = match ($block['type']) {
                // Buyuk harfe cevirme yok (Turkce i / I); baslik alt cizgiyle ayrilir.
                self::HEADING => $this->plainHeading($block, $icons),
                self::TEXT => $block['text'],
                self::FACTS => implode("\n", array_map(
                    fn (array $fact): string => $this->withIcon($fact['label'].': '.$fact['value'], $fact['icon'] ?? null, $icons),
                    $block['facts'],
                )),
                self::BULLETS => implode("\n", array_map(fn (array $item): string => $this->bulletPlain($item, $icons), $block['items'])),
                default => str_repeat('─', 32),
            };
        }

        return implode("\n\n", $out)."\n";
    }

    /**
     * HTML; $inlineStyles e-posta / Word yapistirmasi icin bicimi etiketlere yazar.
     */
    public function html(bool $icons = true, bool $inlineStyles = false): string
    {
        $html = Str::markdown($this->markdown($icons), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        if (! $inlineStyles) {
            return $html;
        }

        $styles = [
            'h1' => 'font-size:20px;line-height:1.3;margin:0 0 10px;color:#111827;',
            'h2' => 'font-size:16px;line-height:1.35;margin:20px 0 8px;color:#991b1b;',
            'h3' => 'font-size:14px;line-height:1.4;margin:14px 0 6px;color:#374151;',
            'p' => 'margin:0 0 10px;line-height:1.55;',
            'ul' => 'margin:0 0 10px;padding-left:22px;',
            'ol' => 'margin:0 0 10px;padding-left:22px;',
            'li' => 'margin:0 0 6px;line-height:1.5;',
            'blockquote' => 'margin:4px 0 6px;padding:4px 12px;border-left:3px solid #e5e7eb;color:#4b5563;',
            'em' => 'color:#6b7280;',
            'hr' => 'border:0;border-top:1px solid #e5e7eb;margin:16px 0;',
        ];

        foreach ($styles as $tag => $style) {
            $html = (string) preg_replace('/<'.$tag.'(\s[^>]*)?>/', '<'.$tag.' style="'.$style.'">', $html);
        }

        return '<div style="font-family:Segoe UI,Calibri,Arial,sans-serif;font-size:14px;color:#1f2937;">'.$html.'</div>';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function bulletMarkdown(array $item, bool $icons): string
    {
        $head = '';

        if (filled($item['title'] ?? null)) {
            $head = '**'.$this->escape((string) $item['title'], lineStart: true).'**';
        }

        if (filled($item['rest'] ?? null)) {
            $head .= ($head !== '' ? ' — ' : '').$this->escape((string) $item['rest'], lineStart: $head === '');
        }

        // Baslik ve ayrinti ayni paragrafta (sert satir sonu); alinti ve ek
        // satir madde icinde ayri paragraf (iki bosluk girintili).
        $markdown = '- '.$this->withIcon($head, $item['icon'] ?? null, $icons);

        if (filled($item['detail'] ?? null)) {
            $markdown .= "  \n  _".$this->escape((string) $item['detail']).'_';
        }

        if (filled($item['quote'] ?? null)) {
            $quote = array_map(
                fn (string $line): string => '  > '.$this->escape($line),
                array_values(array_filter(preg_split('/\r?\n/', trim((string) $item['quote'])) ?: [], static fn (string $line): bool => trim($line) !== '')),
            );
            $markdown .= "\n\n".implode("  \n", $quote);
        }

        if (filled($item['note'] ?? null)) {
            $label = filled($item['note_label'] ?? null) ? '**'.$this->escape((string) $item['note_label']).':** ' : '';
            $markdown .= "\n\n  ".$label.$this->escape((string) $item['note']);
        }

        return $markdown;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function bulletPlain(array $item, bool $icons): string
    {
        $head = trim((string) ($item['title'] ?? ''));

        if (filled($item['rest'] ?? null)) {
            $head .= ($head !== '' ? ' — ' : '').$item['rest'];
        }

        $lines = ['• '.$this->withIcon($head, $item['icon'] ?? null, $icons)];

        if (filled($item['detail'] ?? null)) {
            $lines[] = '  '.$item['detail'];
        }

        if (filled($item['quote'] ?? null)) {
            foreach (preg_split('/\r?\n/', trim((string) $item['quote'])) ?: [] as $line) {
                $lines[] = '  "'.trim($line).'"';
            }
        }

        if (filled($item['note'] ?? null)) {
            $lines[] = '  '.(filled($item['note_label'] ?? null) ? $item['note_label'].': ' : '').$item['note'];
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function plainHeading(array $block, bool $icons): string
    {
        $text = $this->withIcon((string) $block['text'], $block['icon'], $icons);

        if ($block['level'] >= 3) {
            return $text;
        }

        return $text."\n".str_repeat($block['level'] === 1 ? '═' : '─', max(8, min(60, mb_strlen((string) $block['text']) + 2)));
    }

    private function withIcon(string $text, mixed $icon, bool $icons): string
    {
        return $icons && is_string($icon) && $icon !== '' ? $icon.' '.$text : $text;
    }

    /** Satir ici kacirma: bicim isaretleri metin olarak kalir. */
    private function escape(string $text, bool $lineStart = false): string
    {
        $text = (string) preg_replace('/([\\\\`*_\[\]#<>|])/', '\\\\$1', Str::squish($text));

        // Satir basinda "1." / "-" / "+" madde isareti sanilmasin.
        return $lineStart
            ? (string) preg_replace(['/^(\d+)([.)])/', '/^([-+])/'], ['$1\\\\$2', '\\\\$1'], $text)
            : $text;
    }

    /**
     * Kullanici metni: tek satir sonu sert satir sonu olur, bos satir paragraf;
     * baslik (#), alinti (>), cizgi (---) ve tablo isaretleri kacirilir; madde
     * isaretleri ("-", "*", "1.") korunur.
     */
    private function userMarkdown(string $text): string
    {
        $lines = preg_split('/\r?\n/', str_replace("\t", '    ', $text)) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (preg_match('/^\s*([-=_*]\s*){3,}$/', $line) === 1) {
                $line = '\\'.ltrim($line);
            } elseif (preg_match('/^(\s*)([#>|])/', $line) === 1) {
                $line = (string) preg_replace('/^(\s*)([#>|])/', '$1\\\\$2', $line);
            }

            $line = str_replace(['<', '`'], ['&lt;', '\\`'], $line);
            $out[] = $line;
        }

        $markdown = '';
        $count = count($out);
        $isItem = static fn (string $line): bool => preg_match('/^\s*([-*+]|\d+[.)])\s/', $line) === 1;

        foreach ($out as $index => $line) {
            $markdown .= $line;

            if ($index < $count - 1) {
                $next = $out[$index + 1];

                // Maddeden sonra gelen duz satir listeyi bitirir (bos satir); bos
                // satir ya da sonraki madde duz satir sonu; aksi halde sert satir sonu.
                $markdown .= match (true) {
                    $isItem($line) && $next !== '' && ! $isItem($next) && ! str_starts_with($next, ' ') => "\n\n",
                    $line === '' || $next === '' || $isItem($next) => "\n",
                    default => "  \n",
                };
            }
        }

        return $markdown;
    }
}
