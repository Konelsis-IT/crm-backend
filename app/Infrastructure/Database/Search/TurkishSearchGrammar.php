<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

/**
 * Turkce arama (D-214, 15 Ekim 2026 kullanici: "Projedeki tum arama sisteminde
 * de turkce aramaya izin verelim ... Buyuk i harfi kucuk i harfi gibi bazi
 * ingilizce kaynakli zorlamalar olmamali. Idris ile idris aynidir, Ayse ile
 * ayse aynidir, Irmak ile irmak aynidir ... irmak Irmak yazilsa da arama dogru
 * sonucu getirmelidir.").
 *
 * Veritabaninin harmanlamasi (utf8mb4_0900_ai_ci) noktasiz i'yi ayri harf
 * sayar: "irmak" aramasi "Irmak" / "irmak" (noktasiz) bulmuyordu. Her LIKE /
 * NOT LIKE karsilastirmasina utf8mb4_general_ci eklenir; bu harmanlamada
 * I, i, I (noktali) ve i (noktasiz) ayni harftir, buyuk / kucuk harf ve
 * s / s (cedilla) gibi isaretler de esit sayilir. Filament tablo aramasi,
 * genel arama, secim kutusu aramalari ve uygulamanin kendi arama sorgulari
 * ayni yerden gecer. Buyuk / kucuk harfe acikca duyarli isteyen "like binary"
 * dokunulmaz.
 *
 * Sutun once utf8mb4'e cevrilir (16 Ekim 2026): kod sutunlari (KonelsisMigration
 * code(), seed_archive...) ascii karakter setindedir ve "COLLATE
 * utf8mb4_general_ci" ascii sutunda SQL hatasi verir (1253). CONVERT utf8mb4
 * sutunda etkisizdir; sayi, tarih ve JSON sutunlarini da metne cevirir.
 */
final class TurkishSearchGrammar extends MySqlGrammar
{
    public const COLLATION = 'utf8mb4_general_ci';

    /**
     * @param  array<string, mixed>  $where
     */
    protected function whereBasic(Builder $query, $where): string
    {
        $operator = strtolower(trim((string) $where['operator']));

        if ($operator !== 'like' && $operator !== 'not like') {
            return parent::whereBasic($query, $where);
        }

        return 'convert('.$this->wrap($where['column']).' using utf8mb4) collate '.self::COLLATION.' '.$operator.' '.$this->parameter($where['value']);
    }
}
