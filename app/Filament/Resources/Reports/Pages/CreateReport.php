<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\Report\ReportSubjectKind;
use App\Exceptions\AbstractException;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Personnel\Personnel;
use App\Query\Report\ReportQueries;
use App\Reports\ReportTemplateRegistry;
use App\Services\Report\ReportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Rapor olusturma. Sorgu parametreleriyle on doldurulur: ?taslak=kod,
 * ?konu=tur&kayit=ID (ilgili kaydin sayfasindan "Rapor yaz"). Pano tipli
 * taslakta onceki raporun bitmemis kalemleri tasinir.
 */
class CreateReport extends CreateRecord
{
    protected static string $resource = ReportResource::class;

    public const QUERY_TEMPLATE = 'taslak';

    public const QUERY_SUBJECT_KIND = 'konu';

    public const QUERY_SUBJECT_ID = 'kayit';

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill($this->prefill());

        $this->callHook('afterFill');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ReportService::class)->create($data);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            throw ValidationException::withMessages(['title' => $exception->userMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return ReportResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('report.messages.created');
    }

    /**
     * @return array<string, mixed>
     */
    private function prefill(): array
    {
        $registry = app(ReportTemplateRegistry::class);
        $queries = app(ReportQueries::class);
        $user = auth()->user();
        $data = ['payload' => [], 'items' => []];

        $template = $registry->find((string) request()->query(self::QUERY_TEMPLATE));
        $subjectKind = ReportSubjectKind::tryFrom((string) request()->query(self::QUERY_SUBJECT_KIND));
        $subjectId = request()->query(self::QUERY_SUBJECT_ID);

        // Taslak verilmediyse konu turune uygun tek taslak varsa onu sec.
        if ($template === null && $subjectKind !== null && $subjectKind !== ReportSubjectKind::None) {
            $candidates = $user instanceof Personnel
                ? array_filter($registry->forSubject($subjectKind), fn ($candidate): bool => $queries->canAuthorTemplate($candidate, $user))
                : [];

            if (count($candidates) === 1) {
                $template = array_values($candidates)[0];
            }
        }

        if ($template === null || ! $user instanceof Personnel || ! $queries->canAuthorTemplate($template, $user)) {
            return $data;
        }

        $data['template_code'] = $template->code();

        $column = $template->subjectKind()->column();

        if ($column !== null && is_numeric($subjectId) && ($subjectKind === null || $subjectKind === $template->subjectKind())) {
            $data[$column] = (int) $subjectId;
        }

        if ($template->periodMode()->isCalendarUnit()) {
            $data['period_start'] = Carbon::today()->format('Y-m-d');
        }

        if ($template->hasItems()) {
            $data['items'] = $queries->carryOverItems($template->code(), (int) $user->getKey());
        }

        return $data;
    }
}
