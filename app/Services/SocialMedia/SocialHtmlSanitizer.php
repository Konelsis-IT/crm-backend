<?php

declare(strict_types=1);

namespace App\Services\SocialMedia;

use App\Exceptions\SocialMedia\BodyTooLargeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;
use Throwable;

/**
 * Uzun metin ve blog govdesini (body_html) kaydetmeden once temizler
 * (B31, D-106). Tarayicidaki editor yalniz kolaylik saglar; guvenlik burada
 * saglanir ve arayuz yalniz BURADAN gecmis HTML'i basar.
 *
 * Izinli etiketler: p, br, h2, h3, h4, strong, b, em, i, u, s, blockquote,
 * ul, ol, li, a[href], img[src|alt|width|height], figure, figcaption, table,
 * thead, tbody, tr, th, td[colspan|rowspan], hr, pre, code, span.
 *
 * - Baglanti yalniz http / https / mailto olabilir; her baglantiya
 *   rel="noopener noreferrer" ve target="_blank" zorla yazilir.
 * - Gorsel kaynagi yalniz modulun kendi dosya rotasidir
 *   (".../social/media/<sayi>/file", istege bagli ?variant=original|thumbnail|
 *   preview). Ayni sunucuya ait tam adres once yola cevrilir; protokolsuz
 *   ("//..."), data: ve dis adresli gorseller atilir.
 * - Gorsel genisligi yalniz width="25%|50%|100%" olarak tasinir.
 * - div paragrafa, h1 h2'ye, h5/h6 h4'e cevrilir; zararsiz sarmalayicilar
 *   (section, font...) atilir ama icerikleri korunur; script, style, iframe
 *   ve benzerleri icerikleriyle birlikte atilir.
 */
final class SocialHtmlSanitizer
{
    /** Genislik icin izinli degerler (editor bu uc secenegi sunar). */
    public const IMAGE_WIDTHS = ['25%', '50%', '100%'];

    /** Gorsel rotasinda izinli ?variant= degerleri. */
    private const IMAGE_VARIANTS = 'original|thumbnail|preview';

    /**
     * Atilan ama icerigi korunan etiketler (yapistirilan metin kaybolmasin).
     *
     * @var list<string>
     */
    private const UNWRAPPED = [
        'section', 'article', 'header', 'footer', 'main', 'aside', 'nav', 'center', 'font', 'small', 'big',
        'sub', 'sup', 'mark', 'abbr', 'cite', 'q', 'ins', 'del', 'strike', 'time', 'address', 'label', 'caption',
        'dl', 'dt', 'dd', 'tfoot', 'kbd', 'samp', 'var', 'tt', 'bdi', 'bdo', 'details', 'summary',
    ];

    private ?HtmlSanitizer $engine = null;

    /**
     * Temizlenmis HTML'i doner; govde bos ya da yalniz bosluksa null.
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $limit = $this->maxBytes();

        if (strlen($html) > $limit) {
            throw BodyTooLargeException::make(['max' => self::readableSize($limit)]);
        }

        $clean = $this->engine()->sanitize($this->normalizeBlocks($html));
        $clean = trim($this->dropEmptyShells($clean));

        return $this->isBlank($clean) ? null : $clean;
    }

    /** HTML govdenin duz metni (ozet ve arama icin); etiketler bosluga doner. */
    public function plainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $spaced = (string) preg_replace('#<(?:br|hr)\b[^>]*>|</(?:p|h[1-6]|li|tr|td|th|blockquote|figure|figcaption|pre|div|table|ul|ol)>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Govde gorunur bir sey tasimiyor mu (metin de gorsel de yok)? */
    public function isBlank(?string $html): bool
    {
        if ($html === null || trim($html) === '') {
            return true;
        }

        return $this->plainText($html) === '' && stripos($html, '<img') === false;
    }

    /** Govde icin izinli en buyuk boyut (bayt). */
    public function maxBytes(): int
    {
        return max(1024, (int) config('konelsis.social_media.body_html_max_bytes', 1048576));
    }

    /** Bayt degerini okunur boyuta cevirir (hata metinleri icin). */
    public static function readableSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            $value = $bytes / 1048576;

            return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 1, ',', '.').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return $bytes.' B';
    }

    private function engine(): HtmlSanitizer
    {
        return $this->engine ??= new HtmlSanitizer($this->config());
    }

    private function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength($this->maxBytes())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks(false)
            // Gorselde tam adres hicbir zaman kalmaz: ozel denetleyici gecerli
            // kaynagi kok-goreli yola cevirir, gerisini atar.
            ->allowMediaSchemes([])
            ->allowMediaHosts([])
            ->allowRelativeMedias(true);

        foreach (['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote', 'ul', 'ol', 'li', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'hr', 'pre', 'code', 'span'] as $element) {
            $config = $config->allowElement($element);
        }

        $config = $config
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'width', 'height'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank');

        foreach (self::UNWRAPPED as $element) {
            $config = $config->blockElement($element);
        }

        return $config->withAttributeSanitizer($this->attributeGuard());
    }

    /**
     * Gorsel kaynagi, gorsel olcusu ve tablo hucre birlestirme degerlerini
     * denetler. null donmek ozniteligi dusurur.
     */
    private function attributeGuard(): AttributeSanitizerInterface
    {
        return new class($this->imagePathPattern(), $this->ownOrigins()) implements AttributeSanitizerInterface
        {
            /**
             * @param  list<string>  $origins
             */
            public function __construct(
                private readonly string $imagePattern,
                private readonly array $origins,
            ) {}

            public function getSupportedElements(): ?array
            {
                return ['img', 'td', 'th'];
            }

            public function getSupportedAttributes(): ?array
            {
                return ['src', 'width', 'height', 'colspan', 'rowspan'];
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                $value = trim($value);

                if ($element === 'img') {
                    return match ($attribute) {
                        'src' => $this->imageSource($value),
                        'width' => in_array($value, SocialHtmlSanitizer::IMAGE_WIDTHS, true) ? $value : null,
                        'height' => preg_match('/^[1-9][0-9]{0,3}$/', $value) === 1 ? $value : null,
                        default => null,
                    };
                }

                if (in_array($attribute, ['colspan', 'rowspan'], true)) {
                    return preg_match('/^[1-9][0-9]?$/', $value) === 1 ? $value : null;
                }

                return null;
            }

            private function imageSource(string $value): ?string
            {
                foreach ($this->origins as $origin) {
                    $prefix = $origin.'/';

                    if (strncasecmp($value, $prefix, strlen($prefix)) === 0) {
                        $value = substr($value, strlen($origin));

                        break;
                    }
                }

                return preg_match($this->imagePattern, $value) === 1 ? $value : null;
            }
        };
    }

    /**
     * Modulun gorsel dosya rotasina uyan kok-goreli yol deseni. Rota
     * kayitliysa onek birebir alinir; degilse ".../social/media/<sayi>/file"
     * bicimi aranir.
     */
    private function imagePathPattern(): string
    {
        $query = '(?:\?variant=(?:'.self::IMAGE_VARIANTS.'))?';

        try {
            $path = parse_url((string) route('filament.admin.social.media.file', ['media' => 987654321], false), PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/') && str_contains($path, '987654321')) {
                return '#^'.str_replace('987654321', '[0-9]{1,18}', preg_quote($path, '#')).$query.'$#';
            }
        } catch (Throwable) {
            // Rota henuz kayitli degilse genel bicim kullanilir.
        }

        return '#^/(?:[A-Za-z0-9_\-]+/)*social/media/[0-9]{1,18}/file'.$query.'$#';
    }

    /**
     * Bu sunucunun adresleri (sema + alan adi); gorsel kaynagi bunlardan
     * biriyle basliyorsa yola cevrilir.
     *
     * @return list<string>
     */
    private function ownOrigins(): array
    {
        $origins = [];

        try {
            $origins[] = rtrim((string) request()->getSchemeAndHttpHost(), '/');
        } catch (Throwable) {
            // Istek yoksa (komut satiri) yalniz uygulama adresi kullanilir.
        }

        $appUrl = config('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            $host = parse_url($appUrl, PHP_URL_HOST);
            $port = parse_url($appUrl, PHP_URL_PORT);

            if (is_string($scheme) && is_string($host)) {
                $origins[] = $scheme.'://'.$host.($port !== null && $port !== false ? ':'.$port : '');
            }
        }

        return array_values(array_unique(array_filter($origins, static fn (string $origin): bool => preg_match('#^https?://[^/]+$#i', $origin) === 1)));
    }

    /**
     * Temizlemeden ONCE izinli olmayan blok etiketlerini izinli karsiliklarina
     * cevirir ki yapistirilan metnin satir yapisi kaybolmasin: div -> p,
     * h1 -> h2, h5/h6 -> h4. Cikti yine temizleyiciden gectigi icin bu adim
     * guvenlik karari vermez.
     */
    private function normalizeBlocks(string $html): string
    {
        $html = (string) preg_replace('#<(/?)div(?=[\s>/])#i', '<$1p', $html);
        $html = (string) preg_replace('#<(/?)h1(?=[\s>/])#i', '<$1h2', $html);

        return (string) preg_replace('#<(/?)h[56](?=[\s>/])#i', '<$1h4', $html);
    }

    /**
     * Temizlemeden SONRA bos kabuklari atar: kaynagi dusurulmus gorseller, bos
     * kalan figure kutulari ve ic ice bloklarin ayristirilmasindan kalan bos
     * paragraflar. Temizleyici cikti ozniteliklerinde "<", ">" ve tirnak
     * kodlanmis oldugu icin etiket siniri guvenle bulunur.
     */
    private function dropEmptyShells(string $html): string
    {
        $html = (string) preg_replace('#<img\b(?![^>]*\ssrc=")[^>]*>#i', '', $html);
        $html = (string) preg_replace('#<figure>\s*(?:<figcaption>\s*</figcaption>\s*)?</figure>#i', '', $html);

        return (string) preg_replace('#<p>\s*</p>#i', '', $html);
    }
}
