<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Onay motoru SLA kontrolu (B07, D-76): suresi gecen adimlar kapanir, escalation bildirilir.
Schedule::command('konelsis:approvals:expire')->everyFifteenMinutes()->withoutOverlapping();

// Yaklasan / gecmis son tarih taramasi (B11A, D-82): is uyarisi acar, sahiplerini bildirir.
Schedule::command('konelsis:deadlines:scan')->dailyAt('07:30')->withoutOverlapping();

// Sosyal medya paylasim hatirlatmasi (B31, D-106): hazirlayana ve sorumlulara gunluk tek ozet;
// kurum saatiyle 09:00 (uygulama saati UTC). Yarim kalmis video yuklemelerini de temizler.
Schedule::command('konelsis:social:remind')->dailyAt('09:00')->timezone('Europe/Istanbul')->withoutOverlapping();

// Gorusme plani ve sonraki adim hatirlatmasi (B34, D-109): 1 gun once ve gunun sabahi;
// kurum saatiyle 08:30. Sorumlu ve katilan personele zil bildirimi.
Schedule::command('konelsis:meetings:remind')->dailyAt('08:30')->timezone('Europe/Istanbul')->withoutOverlapping();
