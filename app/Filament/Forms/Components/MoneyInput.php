<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Money;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Tutar giris alani (D-180, 8 Ekim 2026 kullanici talimati: "tutar icerikli
 * inputlarda 1000 => 1.000, 1000,50 => 1.000,50 yazmali; yaninda ilgili para
 * biriminin simgesi gorulmeli"). Uygulamadaki her tutar girisi bu alandir
 * (tools/safe-verify.php "Money inputs" denetler).
 *
 * - Yazarken alan Turkce yazar: binlik nokta, ondalik virgul, kurus her
 *   zaman sabit basamakla ("1.500,00"; D-185 imlec davranisi asagida).
 * - Alanin arkasinda para biriminin simgesi durur (Money::symbol). Simge
 *   ayni formdaki para birimi seciminden okunur (varsayilan `currency_code`,
 *   `currencyField()` ile degisir) ya da `currency()` ile sabit / kayittan
 *   verilir; bos ise kurumun para birimi. Para birimi secimi `live()` olmali
 *   ki simge hemen degissin.
 * - Kaydedilen deger duz sayidir: MoneyStateCast metni sayiya cevirir,
 *   kayittaki sayiyi metne cevirir; `$get()` sayi dondurur.
 * - Dogrulama cevrilmis sayi uzerinde calisir (`numeric`, `minValue`).
 *   Varsayilan alt sinir 0; eksi tutar alabilen alan `->minValue(null)`.
 *
 * Kullanim:
 *   MoneyInput::make('total_price')->label(...)
 *   MoneyInput::make('payment_amount')->currency(fn (RelationManager $livewire) => $livewire->getOwnerRecord()->currency_code)
 */
class MoneyInput extends TextInput
{
    protected string | Closure | null $currency = null;

    protected ?string $currencyField = 'currency_code';

    protected int | Closure $decimals = 2;

    /**
     * D-185 (9 Ekim 2026 kullanici talebi: "Fiyat inputu otomatik olarak kurus
     * kismini getirmelidir ... ben yazmaya calistigimda direkt 500,00 yapacak ve
     * virgulun soluna konumlanip ... Kisi isterse kurus kismina elle tiklayarak
     * gececek"). Filament'in maskesi imleci konumlandiramadigi icin bu kucuk
     * Alpine davranisi alana x-init ile baglanir (ayri .js / Blade dosyasi yok;
     * kullanicinin istedigi davranis icin onayli tek istisna).
     *
     * - Bos alan "0,00" yer tutucusunu gosterir; durum null kalir.
     * - Rakamlar virgulun solundaki tam kisma yazilir, binlik nokta canli
     *   eklenir ("1.500,00"); kurus basamaklari hep gorunur.
     * - Imlec virgulun sagindaysa rakamlar kurusun ustune yazilir; "," ya da "."
     *   tusu imleci kurusa gecirir; Backspace kurusu sifirlar, tam kisimda rakam
     *   siler. Silme sonunda tutar sifirsa alan bosalir (null).
     * - Ayracli yapistirma ("1.234,5", "1234.50") Money::parse ile ayni kuralla
     *   cevrilir. Her degisiklik `input` olayi ile Livewire'a (wire:model)
     *   iletilir; MoneyStateCast metni sayiya cevirir.
     * - Metnin sonuna tiklamak imleci virgulun soluna alir.
     */
    private const CARET_SCRIPT = <<<'JS'
((el, D, neg) => {
  if (el._kcMoney) return;
  el._kcMoney = true;
  const zeros = '0'.repeat(D);
  const digits = s => (s.match(/\d/g) || []).length;
  const trim0 = s => s.replace(/^0+(?=\d)/, '');
  const grp = s => s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const parse = txt => {
    let t = String(txt).replace(/[^\d,.\-]/g, '');
    const n = neg && t.startsWith('-');
    t = t.replace(/-/g, '');
    if (t.includes(',')) { const i = t.indexOf(','); t = t.slice(0, i).replace(/\./g, '') + '.' + t.slice(i + 1).replace(/\D/g, ''); }
    else if (/^\d{1,3}(\.\d{3})+$/.test(t)) t = t.replace(/\./g, '');
    const [a, b = ''] = t.split('.');
    if (!/\d/.test(a + b)) return null;
    return { n, i: trim0(a.replace(/\D/g, '')) || '0', d: (b.replace(/\D/g, '') + zeros).slice(0, D) };
  };
  const fmt = s => s ? (s.n ? '-' : '') + grp(s.i) + (D ? ',' + s.d : '') : '';
  const comma = v => D && v.includes(',') ? v.indexOf(',') : v.length;
  const at = (v, p) => { const c = comma(v); return p <= c ? { r: digits(v.slice(p, c)) } : { k: Math.min(p - c - 1, D) }; };
  const put = (s, cur) => {
    const v = fmt(s);
    let p = v.length;
    if (s) {
      const ip = v.slice(0, comma(v));
      if (cur.k === undefined) { p = ip.length; let r = Math.min(cur.r, s.i.length); while (r > 0 && p > 0) { p--; if (/\d/.test(ip[p])) r--; } }
      else p = ip.length + 1 + cur.k;
    }
    if (v !== el.value) { el.value = v; el._kcBusy = true; el.dispatchEvent(new Event('input', { bubbles: true })); el._kcBusy = false; }
    if (document.activeElement === el) el.setSelectionRange(p, p);
  };
  const done = (s, cur, del) => {
    if (s) { s.i = trim0(s.i) || '0'; if (cur.r !== undefined) cur.r = Math.min(cur.r, s.i.length); }
    if (del && s && s.i === '0' && /^0*$/.test(s.d)) s = null;
    put(s, s ? cur : { r: 0 });
  };
  el.addEventListener('beforeinput', e => {
    const t = e.inputType || '';
    if (el.readOnly || t === 'insertCompositionText' || /^insert(Line|Paragraph)/.test(t) || !/^(insert|delete)/.test(t)) return;
    e.preventDefault();
    let v = el.value;
    let s = v.trim() === '' ? null : parse(v);
    if (fmt(s) !== v) { put(s, { r: 0 }); v = el.value; }
    const a = el.selectionStart ?? v.length, b = el.selectionEnd ?? a;
    let cur = at(v, a);
    const del = t.startsWith('delete');
    if (s && a !== b) {
      if (a === 0 && b === v.length) { s = null; cur = { r: 0 }; }
      else {
        const end = at(v, b);
        if (cur.k === undefined) {
          const from = s.i.length - cur.r, to = end.k === undefined ? s.i.length - end.r : s.i.length;
          s.i = s.i.slice(0, from) + s.i.slice(to);
          cur = { r: end.k === undefined ? end.r : 0 };
        }
        const k0 = cur.k === undefined ? 0 : cur.k, k1 = end.k === undefined ? 0 : end.k;
        s.d = s.d.slice(0, k0) + '0'.repeat(Math.max(0, k1 - k0)) + s.d.slice(Math.max(k0, k1));
      }
      if (del) return done(s, cur, true);
    } else if (del) {
      if (!s) return;
      if (t === 'deleteContentBackward') {
        if (cur.k === 0) cur = { r: 0 };
        if (cur.k !== undefined) { s.d = s.d.slice(0, cur.k - 1) + '0' + s.d.slice(cur.k); cur = { k: cur.k - 1 }; }
        else { const x = s.i.length - cur.r - 1; if (x >= 0) s.i = s.i.slice(0, x) + s.i.slice(x + 1); else s.n = false; }
      } else if (t === 'deleteContentForward') {
        if (cur.r === 0 && D) cur = { k: 0 };
        if (cur.k !== undefined) { if (cur.k < D) { s.d = s.d.slice(0, cur.k) + '0' + s.d.slice(cur.k + 1); cur = { k: cur.k + 1 }; } }
        else if (cur.r > 0) { const x = s.i.length - cur.r; s.i = s.i.slice(0, x) + s.i.slice(x + 1); cur = { r: cur.r - 1 }; }
      } else { s = null; cur = { r: 0 }; }
      return done(s, cur, true);
    }
    const txt = e.data ?? (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '') ?? '';
    if (txt.length > 1 && /[.,]/.test(txt)) { const p = parse(txt); if (p) { s = p; cur = { r: 0 }; } return done(s, cur, false); }
    for (const ch of txt) {
      if (/\d/.test(ch)) {
        if (!s) { s = { n: false, i: '0', d: zeros }; cur = { r: 0 }; }
        if (cur.k !== undefined) { if (cur.k < D) { s.d = s.d.slice(0, cur.k) + ch + s.d.slice(cur.k + 1); cur = { k: cur.k + 1 }; } }
        else { if (s.i === '0') { s.i = ''; cur = { r: 0 }; } if (s.i.length < 13) { const x = s.i.length - cur.r; s.i = s.i.slice(0, x) + ch + s.i.slice(x); } }
      } else if ((ch === ',' || ch === '.') && D) {
        if (!s) s = { n: false, i: '0', d: zeros };
        cur = { k: 0 };
      } else if (ch === '-' && neg && s) s.n = !s.n;
    }
    done(s, cur, false);
  });
  el.addEventListener('input', () => {
    if (el._kcBusy) return;
    const v = el.value;
    if (v.trim() === '') return;
    const s = parse(v);
    if (fmt(s) !== v) put(s, s ? at(v, el.selectionStart ?? v.length) : { r: 0 });
  });
  el.addEventListener('click', () => {
    const v = el.value;
    if (v && D && el.selectionStart === v.length && el.selectionEnd === v.length) { const c = comma(v); el.setSelectionRange(c, c); }
  });
})($el, __DECIMALS__, __NEGATIVE__)
JS;

    protected function setUp(): void
    {
        parent::setUp();

        // D-185: Filament maskesi ($money) yerine imleci yoneten davranis.
        $this->extraAlpineAttributes(static fn (MoneyInput $component): array => [
            'x-init' => $component->getCaretScript(),
        ], merge: true);
        $this->placeholder(static fn (MoneyInput $component): string => Money::fixed(0, $component->getDecimals()) ?? '0');
        $this->inputMode('decimal');
        $this->rule('numeric');
        $this->minValue(0);
        $this->suffix(static fn (MoneyInput $component): string => $component->getCurrencySymbol());
    }

    /** Sabit ISO kodu ya da kayittan / formdan kodu donduren closure. */
    public function currency(string | Closure | null $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /** Simgenin okunacagi para birimi alaninin (goreli) yolu; null: okunmaz. */
    public function currencyField(?string $path): static
    {
        $this->currencyField = $path;

        return $this;
    }

    /** Kurus basamagi (tutar kolonu 4 ondalikli birim fiyatlarda 4). */
    public function decimals(int | Closure $decimals): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function getDecimals(): int
    {
        return max(0, min(6, (int) $this->evaluate($this->decimals)));
    }

    /** D-185 imlec davranisi; kurus basamagi ve eksi tutar izni alana gore. */
    public function getCaretScript(): string
    {
        $minValue = $this->getMinValue();
        $negative = $minValue === null || (is_numeric($minValue) && (float) $minValue < 0);

        return strtr(self::CARET_SCRIPT, [
            '__DECIMALS__' => (string) $this->getDecimals(),
            '__NEGATIVE__' => $negative ? 'true' : 'false',
        ]);
    }

    public function getCurrencyCode(): string
    {
        $code = $this->currency !== null
            ? $this->evaluate($this->currency)
            : $this->readCurrencyField();

        return Money::code($code) ?? Money::defaultCurrency();
    }

    public function getCurrencySymbol(): string
    {
        return Money::symbol($this->getCurrencyCode());
    }

    /**
     * @return array<StateCast>
     */
    public function getDefaultStateCasts(): array
    {
        return [
            ...parent::getDefaultStateCasts(),
            new MoneyStateCast($this->getDecimals()),
        ];
    }

    public function mutatesStateForValidation(): bool
    {
        return true;
    }

    public function mutateStateForValidation(mixed $state): mixed
    {
        $state = parent::mutateStateForValidation($state);

        if ($state === null || $state === '') {
            return null;
        }

        // Cevrilemeyen metin oldugu gibi kalir; `numeric` kurali hatayi verir.
        return Money::parse($state) ?? $state;
    }

    private function readCurrencyField(): mixed
    {
        $path = $this->currencyField;

        if ($path === null || $path === '') {
            return null;
        }

        return $this->evaluate(static fn (Get $get): mixed => $get($path));
    }
}
