<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Search;

use Illuminate\Database\MySqlConnection;

/**
 * MySQL baglantisi; sorgu dilbilgisi Turkce aramalidir (TurkishSearchGrammar,
 * D-214). AppServiceProvider::register() mysql surucusunu buna baglar.
 */
final class TurkishSearchMySqlConnection extends MySqlConnection
{
    protected function getDefaultQueryGrammar()
    {
        return new TurkishSearchGrammar($this);
    }
}
