<?php

declare(strict_types=1);

namespace App\Enums\Document;

use App\Enums\Concerns\HasTranslatedLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DocumentTemplateOutputKind: string implements HasColor, HasLabel
{
    use HasTranslatedLabel;

    case ReportPdf = 'report_pdf';
    case DocumentPdf = 'document_pdf';
    case LetterPdf = 'letter_pdf';
    case XlsxExport = 'xlsx_export';

    public function getColor(): string
    {
        return match ($this) {
            self::ReportPdf => 'primary',
            self::DocumentPdf => 'info',
            self::LetterPdf => 'gray',
            self::XlsxExport => 'success',
        };
    }
}
