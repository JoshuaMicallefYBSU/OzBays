@extends('layouts.app')

@section('container-class', 'oz-map-page')

@section('content')

<style>
    /* Map fills all the space between the navbar and footer - no gap below it, no page scroll */
    #content-wrapper > .container.oz-map-page {
        max-width: none;
        width: 100%;
        padding: 0 !important;
        display: flex;
        flex-direction: column;
    }

    .oz-map-frame {
        display: block;
        flex: 1 1 auto;
        width: 100%;
        min-height: 420px;
        border: 0;
    }
</style>

<iframe
    class="oz-map-frame"
    src="{{ route('mapEmbed') }}"
    title="OzBays Live Network Map"
    allowfullscreen
></iframe>

@endsection
