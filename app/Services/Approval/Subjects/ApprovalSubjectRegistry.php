<?php

declare(strict_types=1);

namespace App\Services\Approval\Subjects;

use App\Exceptions\RecordNotFoundException;
use Illuminate\Contracts\Container\Container;

/**
 * Konu turu kodu -> uygulayici sinif. Yeni bir tuketici (teklif surumu,
 * sozlesme surumu, onay kapisi) baglanirken buraya bir satir eklenir.
 */
final class ApprovalSubjectRegistry
{
    /** @var array<string, class-string<ApprovalSubject>> */
    private const SUBJECTS = [
        DocumentRevisionSubject::TYPE => DocumentRevisionSubject::class,
        WorkRequestSubject::TYPE => WorkRequestSubject::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $subjectType): ApprovalSubject
    {
        $class = self::SUBJECTS[$subjectType] ?? null;

        if ($class === null) {
            throw RecordNotFoundException::make();
        }

        return $this->container->make($class);
    }

    public function has(string $subjectType): bool
    {
        return isset(self::SUBJECTS[$subjectType]);
    }

    /**
     * Politika ekranindaki konu turu secenekleri (kod => Turkce/Ingilizce ad).
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach (array_keys(self::SUBJECTS) as $type) {
            $options[$type] = __('approval_policy.subject_types.'.$type);
        }

        return $options;
    }
}
