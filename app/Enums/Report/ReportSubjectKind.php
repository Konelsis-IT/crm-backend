<?php

declare(strict_types=1);

namespace App\Enums\Report;

use App\Enums\Concerns\HasTranslatedLabel;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Personnel\Personnel;
use App\Models\Project\ComponentDefinition;
use App\Models\Project\Project;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Raporun bagli oldugu kayit turu (D-86). Simdilik personel, proje,
 * urun/bilesen, teklif ve is dosyasi; her tur ayri bir FK kolonudur
 * (polimorfik bag yok).
 */
enum ReportSubjectKind: string implements HasIcon, HasLabel
{
    use HasTranslatedLabel;

    case None = 'none';
    case Personnel = 'personnel';
    case Project = 'project';
    case Component = 'component';
    case Proposal = 'proposal';
    case BusinessCase = 'business_case';

    /** `reports` tablosundaki FK kolonu; konu yoksa null. */
    public function column(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Personnel => 'subject_personnel_id',
            self::Project => 'subject_project_id',
            self::Component => 'subject_component_definition_id',
            self::Proposal => 'subject_proposal_id',
            self::BusinessCase => 'subject_business_case_id',
        };
    }

    /** Report modelindeki iliski adi; konu yoksa null. */
    public function relation(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Personnel => 'subjectPersonnel',
            self::Project => 'subjectProject',
            self::Component => 'subjectComponent',
            self::Proposal => 'subjectProposal',
            self::BusinessCase => 'subjectBusinessCase',
        };
    }

    /** @return class-string<Model>|null */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Personnel => Personnel::class,
            self::Project => Project::class,
            self::Component => ComponentDefinition::class,
            self::Proposal => Proposal::class,
            self::BusinessCase => BusinessCase::class,
        };
    }

    /** Verilen kaydin konu turu; taninmayan model icin None. */
    public static function forModel(Model $model): self
    {
        foreach (self::cases() as $kind) {
            $class = $kind->modelClass();

            if ($class !== null && $model instanceof $class) {
                return $kind;
            }
        }

        return self::None;
    }

    /** Konu kaydinin ekranda gosterilecek adi. */
    public function labelFor(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        return match ($this) {
            self::None => null,
            self::Personnel => (string) $model->getAttribute('full_name'),
            self::Project => (string) $model->getAttribute('name'),
            self::Component => (string) ($model->getAttribute('name_tr') ?? $model->getAttribute('code')),
            self::Proposal => trim((string) $model->getAttribute('proposal_no').' · '.(string) $model->getAttribute('title'), ' ·'),
            self::BusinessCase => (string) $model->getAttribute('title'),
        };
    }

    /**
     * @return list<string>
     */
    public static function columns(): array
    {
        $columns = [];

        foreach (self::cases() as $kind) {
            if (($column = $kind->column()) !== null) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::None => Heroicon::OutlinedMinus,
            self::Personnel => Heroicon::OutlinedUser,
            self::Project => Heroicon::OutlinedBriefcase,
            self::Component => Heroicon::OutlinedCube,
            self::Proposal => Heroicon::OutlinedDocumentCurrencyDollar,
            self::BusinessCase => Heroicon::OutlinedFolderOpen,
        };
    }
}
