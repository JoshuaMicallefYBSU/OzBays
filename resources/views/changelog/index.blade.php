@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-code-branch"></i> Changelog</div>

    @if($entries->count() > 0)
        <ul class="oz-changelog-list mt-3">
            @foreach($entries as $entry)
                <li class="oz-changelog-item">
                    <div class="oz-changelog-row">
                        <span class="oz-changelog-date">{{ optional($entry->merged_at)->format('d M Y') }}</span>
                        <a href="{{ $entry->url }}" target="_blank" rel="noopener" class="oz-changelog-title">
                            {{ $entry->title }}
                        </a>
                        @if($entry->description)
                            <button type="button" class="oz-changelog-toggle" data-toggle="collapse"
                                data-target="#changelog-desc-{{ $entry->id }}" aria-expanded="false"
                                aria-controls="changelog-desc-{{ $entry->id }}" aria-label="Toggle description">
                                <i class="fa fa-chevron-right"></i>
                            </button>
                        @endif
                        @if($entry->pr_number)
                            <span class="oz-changelog-pr">#{{ $entry->pr_number }}</span>
                        @endif
                        @if($entry->author)
                            <span class="oz-changelog-author">by {{ $entry->author }}</span>
                        @endif
                    </div>

                    @if($entry->description)
                        <div class="collapse oz-changelog-desc" id="changelog-desc-{{ $entry->id }}">
                            {!! $entry->description_html !!}
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="oz-card-dark mt-3">
            <p class="mb-0">No changes have been logged yet &mdash; check back soon!</p>
        </div>
    @endif
@endsection
