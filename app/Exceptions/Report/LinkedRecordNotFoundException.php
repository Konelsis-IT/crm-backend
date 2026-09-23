<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Kartin bagli kaydi bulunamadi (B36, D-115). */
final class LinkedRecordNotFoundException extends AbstractException {}
