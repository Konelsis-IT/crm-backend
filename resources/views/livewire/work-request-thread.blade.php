@php
    use App\Livewire\WorkRequestThread;

    $fileUrl = fn ($file, array $query = []) => route('filament.admin.files.work-request', ['file' => $file->getKey(), ...$query]);
@endphp

<div class="wr-thread">
    {{-- Talep, yazismanin ilk mesajidir. --}}
    <article class="wr-msg wr-msg--first">
        <header class="wr-msg__head">
            <strong>{{ $request->requesterLabel() }}</strong>
            <span>{{ $request->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
            <em>{{ __('work_request.thread.first') }}</em>
        </header>
        <div class="wr-msg__body">
            <p class="wr-msg__title">{{ $request->title }}</p>
            @if (filled($request->description))
                <p>{{ WorkRequestThread::format((string) $request->description) }}</p>
            @endif
            @foreach ($initialMessages as $initial)
                @include('livewire.work-request-files', ['files' => $initial->files])
            @endforeach
        </div>
    </article>

    @php $replyNo = 1; @endphp
    @foreach ($messages as $message)
        @if ($message->message_kind === 'initial')
            @continue
        @endif

        @if ($message->isForward())
            <div class="wr-forward" wire:key="wr-msg-{{ $message->id }}">
                <strong>{{ __('work_request.thread.forwarded_by', ['name' => $message->author?->full_name ?? '-']) }}</strong>
                <span>{{ $message->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                <p>{{ WorkRequestThread::format((string) $message->body) }}</p>
            </div>
        @else
            <article class="wr-msg {{ (int) $message->author_personnel_id === (int) auth()->id() ? 'wr-msg--mine' : '' }}" wire:key="wr-msg-{{ $message->id }}">
                <header class="wr-msg__head">
                    <strong>{{ $message->author?->full_name ?? '-' }}</strong>
                    <span>{{ $message->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                    <em>{{ __('work_request.thread.reply_no', ['no' => ++$replyNo]) }}</em>
                </header>
                <div class="wr-msg__body">
                    @if (filled($message->body))
                        <p>{{ WorkRequestThread::format((string) $message->body) }}</p>
                    @endif

                    @include('livewire.work-request-files', ['files' => $message->files])
                </div>
            </article>
        @endif
    @endforeach

    @if ($canReply)
        <form wire:submit="send" class="wr-compose">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                {{ __('work_request.thread.send') }}
            </x-filament::button>
        </form>
    @else
        <p class="wr-closed">{{ __('work_request.thread.closed') }}</p>
    @endif
</div>
