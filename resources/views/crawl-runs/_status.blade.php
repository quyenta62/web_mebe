@php
    $badge = match ($run->status) {
        \App\Enums\CrawlRunStatus::Pending => ['text-bg-info', 'Pending'],
        \App\Enums\CrawlRunStatus::Running => ['text-bg-primary', 'Running'],
        \App\Enums\CrawlRunStatus::Success => ['text-bg-success', 'Success'],
        \App\Enums\CrawlRunStatus::Failed => ['text-bg-danger', 'Failed'],
    };
@endphp
<span class="badge {{ $badge[0] }}">{{ $badge[1] }}</span>
