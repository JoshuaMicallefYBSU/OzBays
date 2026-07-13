@extends('layouts.app')

@section('content')

<div class="oz-hero">
    @if(Auth::guest())
        <h1>Welcome to <span class="oz-amber-text">OzBays</span> &mdash; coming in 2026</h1>
    @else
        <h1>Welcome back, {{Auth::user()->fullName('F')}} <br> <span class="oz-amber-text">OzBays</span> is coming in 2026</h1>
    @endif

    <span class="oz-eyebrow"><span class="oz-dot"></span>Alpha Release</span>

    <p class="lead">Automatic bay assignment for VATSIM Australia Pacific (VATPAC) controlled airports. This system is still in active development and is currently <strong>not deployed</strong> on the VATSIM network.</p>

    <div class="oz-actions">
        <a href="{{route('airportIndex')}}" class="oz-btn oz-btn-primary"><i class="fa fa-plane"></i> Explore Airports</a>
        <a href="{{route('mapIndex')}}" target="_blank" class="oz-btn oz-btn-ghost"><i class="fa fa-map"></i> View Live Map</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="row">
            <div class="col-12 mb-4">
                <div class="oz-card-dark">
                    <div class="oz-section-title"><i class="fa fa-info-circle"></i> About the System</div>
                    <p>Utilise the nav bar to access information for each airport currently supported by the system, as well as a map showing all aircraft currently being monitored by OzBays. This information is dynamic and will change frequently.</p>
                    <p class="mb-0">The system is still in alpha development, meaning it is not yet used by OzStrips or sending messages via the Hoppies network. Over time, airports will be activated to assign bays to aircraft and advise the pilot/ATC &mdash; this will show in the status section of the <a href="{{route('airportIndex')}}">Airports</a> view.</p>
                </div>
            </div>
        </div>

        <div class="oz-card-dark">
            <div class="oz-section-title"><i class="fa fa-plane-arrival"></i> Ground Traffic</div>
            <x id="ground-traffic"></x>
        </div>
    </div>

    <div class="col-md-4">
        <div class="oz-section-title d-flex justify-content-between align-items-center">
            <span><i class="fa fa-newspaper"></i> Latest News</span>
            <a href="{{route('news.index')}}" style="font-size: 0.85rem;">View All</a>
        </div>

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
</script>
@endsection