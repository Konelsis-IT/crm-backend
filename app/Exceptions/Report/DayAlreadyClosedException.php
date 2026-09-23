<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Gun zaten kapatildi: gunluk rapor gonderilmis (B36, D-115). */
final class DayAlreadyClosedException extends AbstractException {}
