@extends('layouts.app')

@section('content')

<style>
    .oz-credits-table td, .oz-credits-table th { vertical-align: middle; font-size: 14px; }
    .oz-credits-table .oz-licence { white-space: nowrap; }
    .oz-credits-table .oz-usage { color: var(--oz-text-muted); }
    .oz-credits-direct {
        font-size: 11px;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(255, 199, 44, 0.15);
        color: var(--oz-amber-soft);
        margin-left: 6px;
    }
    details.oz-credits-more > summary { cursor: pointer; color: var(--oz-amber-soft); margin-bottom: 12px; }
</style>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="card-title">Copyright &amp; Usage</h3>

        <p class="card-text">
            OzBays &copy; Joshua Micallef 2025&ndash;{{ now()->year }}. OzBays is a community project for flight simulation on the VATSIM network.
            It is not affiliated with or endorsed by any airline or airport, and must not be used for real-world aviation.
        </p>
        <p class="card-text">
            Airline names, callsigns, aircraft types and airport names belong to their respective owners and are used for identification only.
            Bay and airport data is maintained by the OzBays team and contributors.
        </p>
        <p class="card-text mb-0 text-muted">
            OzBays is built with the open-source software, assets and services below. Each is used under its own licence or terms - follow the links for details.
        </p>
    </div>
</div>

@forelse($sections as $section)
    @php($items = $section['items'] ?? [])
    @php($collapse = count($items) > 15)

    <div class="card mb-4">
        <div class="card-body">
            <h4 class="card-title mb-1">{{ $section['title'] }}</h4>
            @if(!empty($section['description']))
                <p class="text-muted">{{ $section['description'] }}</p>
            @endif

            @if($collapse)
                <details class="oz-credits-more">
                    <summary>Show all {{ count($items) }} packages</summary>
            @endif

            <div class="table-responsive">
                <table class="table table-hover oz-credits-table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Licence</th>
                            <th scope="col">Used For</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>
                                    @if(!empty($item['url']))
                                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['name'] }}</a>
                                    @else
                                        {{ $item['name'] }}
                                    @endif
                                    @if(!empty($item['version']))
                                        <span class="text-muted">{{ $item['version'] }}</span>
                                    @endif
                                    @if(!empty($item['direct']) && $collapse)
                                        <span class="oz-credits-direct">direct</span>
                                    @endif
                                </td>
                                <td class="oz-licence">
                                    @if(!empty($item['license_url']))
                                        <a href="{{ $item['license_url'] }}" target="_blank" rel="noopener">{{ $item['license'] }}</a>
                                    @else
                                        {{ $item['license'] }}
                                    @endif
                                </td>
                                <td class="oz-usage">{{ $item['usage'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($collapse)
                </details>
            @endif
        </div>
    </div>
@empty
    <div class="card mb-4">
        <div class="card-body text-muted">Credits are not available right now.</div>
    </div>
@endforelse

@endsection
