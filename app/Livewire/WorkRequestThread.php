<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Exceptions\AbstractException;
use App\Models\WorkRequest\WorkRequest;
use App\Models\WorkRequest\WorkRequestMessage;
use App\Services\WorkRequest\WorkRequestService;
use App\Filament\Support\DomainNotifications;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Talep yazismasi (B32): talep ilk mesaj, cevaplar altinda balon olarak.
 * Sohbetten (B12A) ayridir; cevrimici durumu ve yaziyor bilgisi yoktur.
 * Yazma kutusu yalniz talep aciksa ve kullanici tarafsa gorunur.
 */
class WorkRequestThread extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public int $requestId;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(int $requestId): void
    {
        $this->requestId = $requestId;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('body')
                    ->hiddenLabel()
                    ->placeholder(__('work_request.thread.placeholder'))
                    ->rows(3)
                    ->maxLength(10000),
                FileUpload::make('files')
                    ->hiddenLabel()
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(20480)
                    ->disk('local')
                    ->directory('work-request-tmp')
                    ->visibility('private')
                    ->storeFileNamesIn('file_names')
                    ->helperText(__('work_request.thread.files_help')),
            ]);
    }

    public function send(): void
    {
        $request = $this->workRequest();

        if (! Gate::allows('reply', $request)) {
            return;
        }

        $state = $this->form->getState();
        $names = (array) ($state['file_names'] ?? []);
        $files = [];

        foreach ((array) ($state['files'] ?? []) as $key => $path) {
            $files[(string) $path] = $names[$key] ?? basename((string) $path);
        }

        try {
            app(WorkRequestService::class)->reply($request, $state['body'] ?? null, $files);
        } catch (AbstractException $exception) {
            DomainNotifications::failure($exception);

            return;
        }

        $this->form->fill();
        DomainNotifications::success(__('work_request.messages.message_posted'));
    }

    public function render(): View
    {
        $request = $this->workRequest();
        $messages = $request->messages()->with(['author', 'files.fileObject'])->get();

        return view('livewire.work-request-thread', [
            'request' => $request,
            'messages' => $messages,
            'initialMessages' => $messages->where('message_kind', WorkRequestMessage::KIND_INITIAL),
            'canReply' => Gate::allows('reply', $request),
        ]);
    }

    /** Metni guvenle gosterir: HTML kacirilir, http(s) baglantilari tiklanabilir olur. */
    public static function format(string $text): HtmlString
    {
        $safe = e($text);
        $linked = preg_replace(
            '~(https?://[^\s<]+[^\s<.,;:!?)\]])~u',
            '<a href="$1" target="_blank" rel="noopener noreferrer nofollow">$1</a>',
            $safe,
        );

        return new HtmlString(nl2br($linked ?? $safe));
    }

    private function workRequest(): WorkRequest
    {
        $request = WorkRequest::query()->with(['requester', 'requesterOrgUnit'])->findOrFail($this->requestId);

        abort_unless(Gate::allows('view', $request), 403);

        return $request;
    }
}
