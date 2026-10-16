<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Support\RecordHeading;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Detay ve duzenleme sayfasinin ortak basligi (D-205, RecordHeading):
 * baslik kaydin turu ("Teklif detayi", "Is duzenle"), alt baslik kaydin adi,
 * ust gezinme satirinda kaydin kodu (yoksa kisaltilmis adi), tarayici
 * sekmesinde "kod · Teklif detayi". Ayni ad sayfada uc kez yazilmaz.
 *
 * Her ViewRecord / EditRecord sayfasi bu trait'i kullanir (safe-verify). Kaydin
 * adini farkli yazan sayfa (taraf · tarih, politika · surum...) yalniz
 * recordSubheading()'i ezer; getTitle / getHeading yazilmaz.
 */
trait HasRecordHeading
{
    public function getHeading(): string | Htmlable | null
    {
        return __($this instanceof EditRecord ? 'record_heading.edit' : 'record_heading.view', [
            'type' => RecordHeading::typeLabel(static::getResource()),
        ]);
    }

    public function getSubheading(): string | Htmlable | null
    {
        return $this->recordSubheading();
    }

    public function getTitle(): string | Htmlable
    {
        $crumb = RecordHeading::crumb($this->getRecord(), $this->recordSubheading());

        return trim($crumb.' · '.$this->getHeading(), ' ·');
    }

    public function getBreadcrumb(): string
    {
        return $this instanceof EditRecord
            ? parent::getBreadcrumb()
            : (string) __('record_heading.crumb_view');
    }

    /**
     * Kaydin adimi: ad yerine kaydin kodu. Kodu olmayan kayitta ad yazilmaz
     * (alt baslikta zaten var): detay sayfasinda adim kalkar, duzenleme
     * sayfasinda detaya giden "Detay" olur.
     *
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $title = $this->getRecordTitle();
        $title = $title instanceof Htmlable ? $title->toHtml() : (string) $title;
        $code = RecordHeading::code($this->getRecord());
        $toDetail = $this instanceof EditRecord && static::getResource()::hasPage('view');

        foreach ($breadcrumbs as $url => $label) {
            if (! is_string($url) || (string) $label !== $title) {
                continue;
            }

            if ($code !== null) {
                $breadcrumbs[$url] = $code;
            } elseif ($toDetail) {
                $breadcrumbs[$url] = (string) __('record_heading.crumb_view');
            } else {
                unset($breadcrumbs[$url]);
            }
        }

        return $breadcrumbs;
    }

    /** Kaydin adi (alt baslik); sayfa kaydi farkli adlandiriyorsa bunu ezer. */
    protected function recordSubheading(): ?string
    {
        return RecordHeading::name($this->getRecord());
    }
}
