<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Haftayi kapatip gondermek icin haftanin ozeti zorunludur (B36, D-115). */
final class WeekSummaryRequiredException extends AbstractException {}
