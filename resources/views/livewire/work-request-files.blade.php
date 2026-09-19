@php
    $fileUrl = fn ($attachment, array $query = []) => route('filament.admin.files.work-request', ['file' => $attachment->getKey(), ...$query]);
@endphp

    @if ($files->isNotEmpty())
        <div class="wr-files">
            @foreach ($files as $attachment)
                @php $file = $attachment->fileObject; @endphp
                @continue($file === null)

                @if ($file->isImage() && $file->isInlinePreviewable())
                    <a class="wr-file wr-file--image" href="{{ $fileUrl($attachment) }}" target="_blank" rel="noopener" title="{{ $file->original_name }}">
                        <img src="{{ $fileUrl($attachment, ['variant' => 'thumbnail']) }}" alt="{{ $file->original_name }}" loading="lazy">
                    </a>
                @else
                    <span class="wr-file">
                        <span class="wr-file__name">{{ $file->original_name }} <small>({{ $file->humanSize() }})</small></span>
                        @if ($file->isInlinePreviewable())
                            <a href="{{ $fileUrl($attachment) }}" target="_blank" rel="noopener">{{ __('work_request.thread.preview') }}</a>
                        @endif
                        <a href="{{ $fileUrl($attachment, ['disposition' => 'download']) }}">{{ __('work_request.thread.download') }}</a>
                    </span>
                @endif
            @endforeach
        </div>
    @endif
