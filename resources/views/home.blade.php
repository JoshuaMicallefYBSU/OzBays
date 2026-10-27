@extends('layouts.app')

@section('content')

<div class="oz-hero">
    @if(Auth::guest())
        <h1>Welcome to <span class="oz-amber-text">OzBays</span> &mdash; live in Public Trial</h1>
    @else
        <h1>Welcome back, {{Auth::user()->fullName('F')}} <br> <span class="oz-amber-text">OzBays</span> is live in Public Trial</h1>
    @endif

    <a class="oz-eyebrow oz-eyebrow-purple" href="https://vatpac.org/publications/news/ozbays-trial" target="_blank" rel="noopener"><span class="oz-dot"></span>IN Public Trial</a>

    <p class="lead">Automatic bay assignment for VATSIM Australia Pacific (VATPAC) controlled airports. OzBays is now in public trial, actively assigning bays and advising pilots and ATC in real time at active airports via integration with OzStrips and the Hoppie CPDLC network.</p>

    <div class="oz-actions">
        <a href="{{route('airportIndex')}}" class="oz-btn oz-btn-primary"><i class="fa fa-plane"></i> Explore Airports</a>
        <a href="{{route('mapIndex')}}" target="_blank" class="oz-btn oz-btn-ghost"><i class="fa fa-map"></i> View Live Map</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="oz-card-dark" id="oz-about-card">
            <div class="oz-section-title"><i class="fa fa-info-circle"></i> About the System</div>
            <p>OzBays is an automatic bay/gate assignment system for VATSIM Australia Pacific (VATPAC), built to take the gate-matching workload off controllers and give pilots a reliable gate the moment they're inbound. Here's what it's doing behind the scenes:</p>

            <ul class="oz-about-list">
                <li><i class="fa fa-plane-arrival"></i> Tracks every inbound aircraft live from the VATSIM data feed and matches it to a compatible bay based on aircraft size, operator, and domestic/international status.</li>
                <li><i class="fa fa-check"></i> Cross-references real-world gate data for airports with Live Bay data enabled, so assignments line up with reality wherever possible.</li>
                <li><i class="fa fa-comment"></i> Sends the assigned gate straight to the pilot via Hoppie CPDLC, and relays it to controllers through OzStrips.</li>
            </ul>

            <p class="mb-0">OzBays is in public trial, with more airports continuing to be rolled in over time &mdash; check the status section of the <a href="{{route('airportIndex')}}">Airports</a> view to see which are currently active, and use the nav bar to dig into live data for any supported airport.</p>
        </div>
    </div>

    <div class="col-md-4">
        <div class="oz-section-title d-flex justify-content-between align-items-center">
            <span><i class="fa fa-newspaper"></i> Latest News</span>
            <a href="{{route('news.index')}}" style="font-size: 0.85rem;">View All</a>
        </div>

        <div id="oz-news-list">
            @if($news->count() > 0)
                <a href="{{route('news.show', $news[0])}}" class="oz-news-featured {{$news[0]->has_custom_image ? '' : 'oz-news-no-image'}}" style="background-image: linear-gradient(180deg, rgba(10,14,24,0.15), rgba(10,14,24,0.95)), url('{{$news[0]->image_url}}');">
                    <span class="oz-news-featured-title">{{$news[0]->subject}}</span>
                    <p class="oz-news-excerpt">{{$news[0]->excerpt}}</p>
                    <span class="oz-news-meta">By {{$news[0]->author}} &middot; {{$news[0]->created_at->diffForHumans()}}</span>
                </a>

                @foreach($news->slice(1, 2) as $article)
                    <div class="oz-card-dark oz-news-link-card mt-3">
                        <a href="{{route('news.show', $article)}}" class="oz-news-link-title">{{$article->subject}}</a>
                        <p class="oz-news-excerpt">{{$article->excerpt}}</p>
                        <p class="oz-news-meta mb-0">By {{$article->author}}</p>
                    </div>
                @endforeach
            @else
                <div class="oz-card-dark">
                    <p class="mb-0">No news articles have been published yet &mdash; check back soon!</p>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="oz-card-dark">
            <div class="oz-section-title"><i class="fa fa-plane-arrival"></i> Live Statistics</div>
            <x id="ground-traffic"></x>
        </div>
    </div>
</div>

{{-- Arrivals logged across the network (PagesController::Home, cached 10 mins) --}}
<div class="row mt-4">
    <div class="col-md-12">
        <div class="oz-card-dark">
            <div class="oz-section-title">
                <i class="fa fa-chart-pie"></i> OzBays Historical Stats
                <span class="oz-section-sub">Arrivals in the last 30 days</span>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="oz-card-heading">Aircraft Types</h5>
                    @include('partials.donut-chart', ['chart' => $stats['aircraft'], 'label' => 'Aircraft types flown', 'emptyText' => 'No arrivals logged yet.'])
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="oz-card-heading">Airlines Flown</h5>
                    @include('partials.donut-chart', ['chart' => $stats['airlines'], 'label' => 'Airlines flown', 'emptyText' => 'No airline arrivals logged yet.'])
                </div>
                <div class="col-md-4">
                    <h5 class="oz-card-heading">Busiest Airports</h5>
                    @include('partials.donut-chart', ['chart' => $stats['airports'], 'label' => 'Arrivals by airport', 'emptyText' => 'No arrivals logged yet.'])
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function loadLadder() {
        fetch('/partial/home/airport-stats')
            .then(res => res.text())
            .then(html => {
                const container = document.getElementById('ground-traffic');

                // Create temp wrapper
                const temp = document.createElement('div');
                temp.innerHTML = html;

                // Replace children in one operation
                container.replaceChildren(...temp.children);
            });
        }

        // Initial load
        loadLadder();

        // Refresh every 15s
        setInterval(loadLadder, 15000);

    // News articles should never push this column taller than the "About the
    // System" card next to it - hide trailing article cards that don't fit
    // instead of letting the page grow past it.
    function fitNewsToAboutHeight() {
        const aboutCard = document.getElementById('oz-about-card');
        const newsList = document.getElementById('oz-news-list');
        if (!aboutCard || !newsList) return;

        const items = Array.from(newsList.children);
        items.forEach(item => item.style.display = '');

        // Below the md breakpoint the columns stack full-width, so there's no
        // shared row height to respect - show every article.
        if (window.innerWidth < 768) return;

        const maxBottom = aboutCard.getBoundingClientRect().bottom;

        items.forEach((item, index) => {
            // Always keep at least the featured article visible.
            if (index > 0 && item.getBoundingClientRect().bottom > maxBottom) {
                item.style.display = 'none';
            }
        });
    }

    window.addEventListener('load', fitNewsToAboutHeight);
    window.addEventListener('resize', fitNewsToAboutHeight);
</script>
@endsection