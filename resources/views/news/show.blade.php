@extends('layouts.app')

@section('content')
    <div class="oz-news-banner {{$article->has_custom_image ? '' : 'oz-news-no-image'}}" style="background-image: linear-gradient(180deg, rgba(10,14,24,0.35), rgba(10,14,24,0.92)), url('{{$article->image_url}}');">
        <div class="oz-news-banner-inner">
            <p class="oz-news-meta mb-2">By {{$article->author}} &middot; {{$article->created_at->format('d M Y')}}</p>
            <h1>{{$article->subject}}</h1>
        </div>
    </div>

    <div class="pb-3 mt-3">
        <a href="{{route('home')}}"> <i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>

    <div class="oz-card-dark oz-news-body">
        {!! $article->body !!}
    </div>
@endsection
