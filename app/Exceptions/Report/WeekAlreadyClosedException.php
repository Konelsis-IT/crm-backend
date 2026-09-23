<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Hafta zaten kapatildi: haftalik rapor gonderilmis (B36, D-115). */
final class WeekAlreadyClosedException extends AbstractException {}
