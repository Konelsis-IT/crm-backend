<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Projedeki butun is hatalarinin atasi.
 *
 * Mesaj sinifin icinde yazilmaz; dil dosyasindan gelir. Alt sinifin tek
 * yapmasi gereken kendi adiyla var olmaktir:
 *
 *     final class EmailAlreadyInUseException extends AbstractException {}
 *
 * Gerekirse yalnizca bir kod tanimlanir:
 *
 *     protected $code = 4001;
 *
 * Ceviri anahtari sinif adindan uretilir:
 *
 *     App\Exceptions\StaleRecordException
 *       -> exceptions.stale_record
 *     App\Exceptions\Personnel\EmailAlreadyInUseException
 *       -> exceptions.personnel.email_already_in_use
 *
 * Metindeki yer tutucular kurucuya verilir:
 *
 *     throw EmailAlreadyInUseException::make(['email' => $email]);
 */
abstract class AbstractException extends RuntimeException
{
    /**
     * Sinif adindan uretilen anahtar yerine baska bir anahtar kullanilacaksa.
     */
    protected string $translationKey = '';

    /**
     * @param  array<string, string|int|float>  $replacements  Dil metnindeki :yer_tutucu degerleri.
     */
    public function __construct(
        protected readonly array $replacements = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->resolveMessage(), $this->getCode(), $previous);
    }

    /**
     * @param  array<string, string|int|float>  $replacements
     */
    public static function make(array $replacements = [], ?Throwable $previous = null): static
    {
        return new static($replacements, $previous);
    }

    /** Dil dosyasindaki anahtar. */
    public function translationKey(): string
    {
        if ($this->translationKey !== '') {
            return $this->translationKey;
        }

        return static::defaultTranslationKey();
    }

    /**
     * Kullaniciya gosterilecek metin. Mesajin kendisi zaten cevrilmistir;
     * arayuz katmani bu metodu cagirir.
     */
    public function userMessage(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, string|int|float>
     */
    public function replacements(): array
    {
        return $this->replacements;
    }

    /**
     * Sinif adindan ceviri anahtari uretir.
     */
    protected static function defaultTranslationKey(): string
    {
        $relative = Str::after(static::class, __NAMESPACE__.'\\');
        $segments = explode('\\', $relative);

        $class = array_pop($segments);
        $class = Str::snake(Str::beforeLast($class, 'Exception'));

        $prefix = array_map(static fn (string $segment): string => Str::snake($segment), $segments);

        return implode('.', ['exceptions', ...$prefix, $class]);
    }

    /**
     * Ceviri yoksa genel metne duser; boylece kullanici hicbir zaman
     * ham anahtar gormez.
     */
    private function resolveMessage(): string
    {
        $key = $this->translationKey();
        $message = __($key, $this->replacements);

        if (is_string($message) && $message !== $key) {
            return $message;
        }

        $fallback = __('exceptions.generic');

        return is_string($fallback) && $fallback !== 'exceptions.generic'
            ? $fallback
            : 'Islem tamamlanamadi.';
    }
}
