<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\Report\ReportSubjectKind;
use App\Exceptions\AbstractException;
use App\Filament\Concerns\HasColoredFormActions;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Support\DomainNotifications;
use App\Models\Personnel\Personnel;
use App\Query\Report\ReportQueries;
use App\Reports\ReportTemplateRegistry;
use App\Services\Report\ReportService;
use App\Services\Report\ReportSuggestions;
use App\Support\DisplayTime;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Rapor olusturma. Sorgu parametreleriyle on doldurulur: ?taslak=kod,
 * ?konu=tur&kayit=ID (ilgili kaydin sayfasindan "Rapor yaz"), ?tarih=Y-m-d.
 * Pano tipli taslakta onceki raporun bitmemis kalemleri tasinir; gunluk /
 * haftalik calisma raporunda (D-167) donemin is panosu kartlari, gorusme
 * notlari ve yazilan raporlar oneri olarak gelir.
 */
class CreateReport extends CreateRecord
{
    use HasColoredFormActions;

    protected static string $resource = ReportResource::class;

    public const QUERY_TEMPLATE = 'taslak';

    public const QUERY_SUBJECT_KIND = 'konu';

    public const QUERY_SUBJECT_ID = 'kayit';

    /** Donem tarihi (Y-m-d), gun / hafta / ay taslaklarinda (D-167). */
    public const QUERY_DATE = 'tarih';

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
            $date = (string) request()->query(self::QUERY_DATE);
            $data['period_start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1
                ? $date
                : Carbon::today(DisplayTime::zone())->format('Y-m-d');
        }

        // D-167: gunluk / haftalik raporda donemin isleri (is panosu), gorusme
        // notlari ve yazilan raporlar isaretli oneri olarak gelir; diger pano
        // taslaklarinda onceki raporun bitmemis kalemleri tasinir.
        $prefill = app(ReportSuggestions::class)->prefill($template, (int) $user->getKey(), $data['period_start'] ?? null);
        $data['payload'] = $prefill['payload'];

        if ($prefill['items'] !== null) {
            $data['items'] = $prefill['items'];
        }

        return $data;
    }
}
