@extends('layouts.flight-display')

@section('title', $display['flight']->callsign.' Gate Display')

@section('content')
<main class="oz-gate-display-shell" data-partial-url="{{ route('airport.flight-display.partial', ['icao' => $display['airport']->icao, 'callsign' => $display['flight']->callsign]) }}">
    <div class="oz-gate-display-toolbar">
        <span><i class="fas fa-circle" aria-hidden="true"></i> Live Gate Information</span>
        <span class="oz-gate-display-clock" id="oz-gate-clock">--:--:-- UTC</span>
        <button type="button" class="oz-gate-fullscreen" id="oz-gate-fullscreen">
            <i class="fas fa-expand" aria-hidden="true"></i>
            <span>Fullscreen</span>
        </button>
    </div>

    <section id="oz-gate-display-content" aria-live="polite">
        @include('partials.flight-display', ['display' => $display])
    </section>
</main>

<script>
    const gateShell = document.querySelector('.oz-gate-display-shell');
    const displayContent = document.getElementById('oz-gate-display-content');
    const alertBox = document.getElementById('oz-gate-alert');
    const alertText = document.getElementById('oz-gate-alert-text');
    const fullscreenButton = document.getElementById('oz-gate-fullscreen');
    let lastGateKey = displayContent.querySelector('[data-gate-key]')?.dataset.gateKey ?? 'unassigned';
    let gateRequest = null;
    let pollingStopped = false;

    function tickGateClock() {
        const clock = document.getElementById('oz-gate-clock');
        if (!clock) return;
        clock.textContent = `${new Date().toISOString().substr(11, 8)} UTC`;
    }

    function gateMessage(newPanel) {
        const gate = newPanel.dataset.gate || '';
        const terminal = newPanel.dataset.terminal || '';
        if (!gate) return 'Gate update: please check the display';
        return `Gate changed: proceed to Gate ${gate}${terminal ? ` · Terminal ${terminal}` : ''}`;
    }

    function showGateAlert(message) {
        alertText.textContent = message;
        alertBox.hidden = false;
    }

    function refreshGateDisplay() {
        if (pollingStopped || document.visibilityState === 'hidden') return;
        gateRequest?.abort();
        gateRequest = new AbortController();
        fetch(gateShell.dataset.partialUrl, { signal: gateRequest.signal })
            .then(response => {
                if (response.status === 404) {
                    pollingStopped = true;
                    displayContent.innerHTML = '<div class="oz-gate-unavailable">Flight is no longer tracked</div>';
                    return null;
                }
                if (!response.ok) throw new Error(`Display refresh failed (${response.status})`);
                return response.text();
            })
            .then(html => {
                if (html === null) return;
                const template = document.createElement('template');
                template.innerHTML = html;
                const nextPanel = template.content.querySelector('[data-gate-key]');
                if (nextPanel && nextPanel.dataset.gateKey !== lastGateKey) {
                    showGateAlert(gateMessage(nextPanel));
                    lastGateKey = nextPanel.dataset.gateKey;
                }
                displayContent.replaceChildren(template.content);
            })
            .catch(error => {
                if (error.name !== 'AbortError') gateShell.classList.add('oz-gate-display-shell--stale');
            });
    }

    function updateFullscreenLabel() {
        const label = fullscreenButton.querySelector('span');
        const icon = fullscreenButton.querySelector('i');
        const active = document.fullscreenElement !== null;
        label.textContent = active ? 'Exit Fullscreen' : 'Fullscreen';
        icon.className = active ? 'fas fa-compress' : 'fas fa-expand';
    }

    if (!document.documentElement.requestFullscreen) {
        fullscreenButton.hidden = true;
    }

    fullscreenButton.addEventListener('click', () => {
        if (document.fullscreenElement) {
            document.exitFullscreen();
            return;
        }
        document.documentElement.requestFullscreen();
    });
    document.addEventListener('fullscreenchange', updateFullscreenLabel);
    document.getElementById('oz-gate-alert-close').addEventListener('click', () => alertBox.hidden = true);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshGateDisplay();
    });

    tickGateClock();
    setInterval(tickGateClock, 1000);
    setInterval(refreshGateDisplay, 15000);
</script>
@endsection
