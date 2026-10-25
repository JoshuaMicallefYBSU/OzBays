@extends('layouts.app')

@section('content')
    <div class="oz-section-title"><i class="fa fa-chart-line"></i> Page Access</div>
    <p>How many unique visitors the homepage, live map, airport ladder and dashboard have had. A visit only counts once every 15 minutes per visitor (tracked via a cookie), whether they're logged in or not. Developer access only.</p>

    <div class="row">
        @foreach($ranges as $rangeKey => $range)
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="oz-card-dark">
                    <div class="oz-stat-heading">{{ $range['label'] }}</div>
                    @foreach($pages as $pageKey => $pageLabel)
                        <div class="oz-access-row">
                            <span class="oz-access-label">{{ $pageLabel }}</span>
                            <span class="oz-access-value">{{ number_format($summary[$rangeKey][$pageKey]) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="oz-card-dark">
                <div class="oz-section-title d-flex justify-content-between align-items-center flex-wrap">
                    <span><i class="fa fa-calendar"></i> Daily Access &mdash; Last 30 Days</span>
                    <select id="pageAccessSelect" class="form-control" style="width: auto;">
                        @foreach($pages as $pageKey => $pageLabel)
                            <option value="{{ $pageKey }}">{{ $pageLabel }}</option>
                        @endforeach
                    </select>
                </div>

                @foreach($pages as $pageKey => $pageLabel)
                    <div class="oz-access-chart" data-page="{{ $pageKey }}"@if(! $loop->first) style="display: none;"@endif>
                        @include('partials.bar-chart', ['series' => $daily[$pageKey], 'label' => $pageLabel.' daily page views'])
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        document.getElementById('pageAccessSelect').addEventListener('change', function (e) {
            document.querySelectorAll('.oz-access-chart').forEach(function (el) {
                el.style.display = el.dataset.page === e.target.value ? '' : 'none';
            });
        });
    </script>
@endsection
