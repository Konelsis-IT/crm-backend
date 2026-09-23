<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\AbstractException;

/** Oneri artik kullanilamaz: karta donmus, yoksayilmis ya da baskasinin hareketi (B36, D-115). */
final class SuggestionUnavailableException extends AbstractException {}
