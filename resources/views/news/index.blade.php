@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-newspaper"></i> News Articles</div>

    @if($articles->count() > 0)
        <div class="oz-news-grid mt-3">
            @foreach($articles as $article)
                <a href="{{route('news.show', $article)}}" class="oz-news-featured {{$article->has_custom_image ? '' : 'oz-news-no-image'}}" style="background-image: linear-gradient(180deg, rgba(10,14,24,0.15), rgba(10,14,24,0.95)), url('{{$article->image_url}}');">
                    <span class="oz-news-featured-title">{{$article->subject}}</span>
                    <p class="oz-news-excerpt">{{$article->excerpt}}</p>
                    <span class="oz-news-meta">By {{$article->author}} &middot; {{$article->created_at->diffForHumans()}}</span>
                </a>
            @endforeach
        </div>
    @else
        <div class="oz-card-dark mt-3">
            <p class="mb-0">No news articles have been published yet &mdash; check back soon!</p>
        </div>
    @endif
@endsection
