<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/*
 * Kok adres yonetim paneline gider (D-91): sistemin tek arayuzu Filament
 * panelidir, Laravel karsilama sayfasi gosterilmez. Panel yolu
 * AdminPanelProvider'dan okunur, sabit yazilmaz.
 */
Route::get('/', fn () => redirect()->to(Filament::getDefaultPanel()->getUrl()));
