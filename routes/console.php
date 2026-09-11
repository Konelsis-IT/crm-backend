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
