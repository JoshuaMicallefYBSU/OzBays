@extends('layouts.app')

@section('container-class', 'oz-airport-fids-page')

@section('content')

<div class="oz-board-header">
    <div>
        <span class="oz-eyebrow"><span class="oz-dot"></span><span id="oz-board-mode-label">Live Departures Board</span></span>
        <h1 class="oz-board-title"><span class="oz-fids-icao">{{$airport->icao}}</span> {{$airport->name}}</h1>
        <a href="{{route('airportIndex')}}" class="oz-board-back"><i class="fas fa-arrow-left"></i> See All Airports</a>
    </div>

    <div class="oz-board-clock">
        <label class="oz-board-mode-control" for="oz-board-mode">
            <span>Display</span>
            <select id="oz-board-mode" aria-label="Flight information display mode">
                <option value="auto" selected>Auto</option>
                <option value="departures">Departures</option>
                <option value="arrivals">Arrivals</option>
            </select>
        </label>
        <span class="oz-board-clock-label">UTC / Zulu</span>
        <span class="oz-board-clock-time" id="oz-zulu-clock">--:--:--</span>
    </div>
</div>

<div id="controller-info" aria-live="polite"></div>

<script>
    const airportIcao = @json($airport->icao);
    let activeBoard = 'departures';
    let ladderRequest = null;
    let boardMode = 'auto';
    const boardCycleMs = 15000;
    let boardCycleDeadline = null;
    let boardCycleTimer = null;

    function updateBoardLabel() {
        document.getElementById('oz-board-mode-label').textContent = `Live ${activeBoard === 'departures' ? 'Departures' : 'Arrivals'} Board`;
    }

    function loadLadder() {
        ladderRequest?.abort();
        ladderRequest = new AbortController();
        fetch(`/partial/airport/ladder/${airportIcao}?board=${activeBoard}`, { signal: ladderRequest.signal })
            .then(res => {
                if (!res.ok) throw new Error(`Board request failed (${res.status})`);
                return res.text();
            })
            .then(html => {
                const template = document.createElement('template');
                template.innerHTML = html;
                document.getElementById('controller-info').replaceChildren(template.content);
                updateBoardLabel();
                updateCycleProgress();
            })
            .catch(error => {
                if (error.name !== 'AbortError') console.error(error);
            });
    }

    function cycleBoard() {
        if (boardMode !== 'auto') return;
        activeBoard = activeBoard === 'departures' ? 'arrivals' : 'departures';
        loadLadder();
        scheduleNextCycle();
    }

    function setBoardMode(mode) {
        boardMode = mode;
        if (mode === 'auto') {
            scheduleNextCycle();
        } else {
            clearTimeout(boardCycleTimer);
            boardCycleDeadline = null;
            activeBoard = mode;
        }
        loadLadder();
        updateCycleProgress();
    }

    function scheduleNextCycle() {
        clearTimeout(boardCycleTimer);
        boardCycleDeadline = Date.now() + boardCycleMs;
        boardCycleTimer = setTimeout(cycleBoard, boardCycleMs);
        updateCycleProgress();
    }

    function updateCycleProgress() {
        const label = document.getElementById('oz-board-cycle-label');
        const time = document.getElementById('oz-board-cycle-time');
        const progress = document.getElementById('oz-board-cycle-progress');
        if (!label || !time || !progress) return;

        if (boardMode !== 'auto' || boardCycleDeadline === null) {
            label.textContent = 'Display cycle';
            time.textContent = 'Paused';
            progress.style.width = '0%';
            return;
        }

        const remaining = Math.max(0, boardCycleDeadline - Date.now());
        label.textContent = 'Next display';
        time.textContent = `${Math.ceil(remaining / 1000)}s`;
        progress.style.width = `${Math.min(100, ((boardCycleMs - remaining) / boardCycleMs) * 100)}%`;
    }

    function tickClock() {
        const el = document.getElementById('oz-zulu-clock');
        if (!el) return;
        el.textContent = new Date().toISOString().substr(11, 8);
    }

    // Initial load
    loadLadder();
    tickClock();
    scheduleNextCycle();
    document.getElementById('oz-board-mode').addEventListener('change', event => setBoardMode(event.target.value));

    // The board itself cycles every 15 seconds; data refreshes independently.
    setInterval(loadLadder, 30000);
    setInterval(tickClock, 1000);
    setInterval(updateCycleProgress, 250);
</script>


@endsection
