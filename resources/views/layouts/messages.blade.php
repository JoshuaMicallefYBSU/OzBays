{{-- Public Trial notice - shown on every page until dismissed, then cookied for 30 days --}}
@unless(request()->cookie('ozbays_vatpac_trial_notification'))
    <div class="alert-wrapper alert-raised">
        <div class="alert oz-trial-alert">
            <i class="fa fa-info-circle"></i> OzBays is now live in <strong>Public Trial</strong> for VATSIM Australia Pacific (VATPAC).
            <a href="https://vatpac.org/publications/news/ozbays-trial" target="_blank" rel="noopener">Read the announcement</a>.
            <button type="button" class="alert-close" data-cookie="ozbays_vatpac_trial_notification" data-cookie-days="30" aria-label="Dismiss">&times;</button>
        </div>
    </div>
@endunless

@if(session()->has('success'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-success">
            {{ session('success') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

@if(session()->has('error'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-danger">
            <b>ERR.</b> {{ session('error') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

@if(session()->has('info'))
    <div class="alert-wrapper auto-close alert-raised">
        <div class="alert alert-info">
            {{ session('info') }}
            <button type="button" class="alert-close">&times;</button>
        </div>
    </div>
@endif

<style>
.alert-wrapper {
    overflow: hidden;
    transition: height 0.6s ease, opacity 0.5s ease, margin 0.6s ease;
}

.alert-raised {
    margin-top: -25px;
}

.alert-wrapper.closing {
    opacity: 0;
    margin-top: 0;
    margin-bottom: 0;
}

.alert {
    position: relative;
    padding-right: 2.5rem;
}

.alert-close {
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.alert-close:hover {
    opacity: 1;
}

/* Bootstrap's default .alert-info is light-on-light and clashes with the dark theme (and
   the site's amber link colour, meant for dark backgrounds, is unreadable against it) -
   this restyles the Public Trial notice to match the rest of the "Control Tower" theme. */
.oz-trial-alert {
    background: rgba(255, 199, 44, 0.08);
    border: 1px solid rgba(255, 199, 44, 0.3);
    border-radius: 12px;
    color: var(--oz-text);
}

.oz-trial-alert i {
    color: var(--oz-amber);
    margin-right: 0.3rem;
}

.oz-trial-alert a {
    color: var(--oz-amber);
    font-weight: 600;
    text-decoration: underline;
}

.oz-trial-alert a:hover {
    color: var(--oz-amber-strong);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function closeWrapper(wrapper) {
        if (wrapper.classList.contains('closing')) return;
            wrapper.style.height = wrapper.offsetHeight + 'px';
            wrapper.offsetHeight;
            wrapper.classList.add('closing');
            wrapper.style.height = '0px';
            setTimeout(() => wrapper.remove(), 700);
    }

    const autoCloseTimer = setTimeout(() => {
        document.querySelectorAll('.alert-wrapper.auto-close').forEach(closeWrapper);
    }, 25000);

    // Emanuel Close Activated *PARTY NOISES HEARD*
    document.querySelectorAll('.alert-close').forEach(button => {
        button.addEventListener('click', function () {
            const wrapper = this.closest('.alert-wrapper');
            closeWrapper(wrapper);

            // Alerts opted into persistence (e.g. the Public Trial notice) get cookied
            // so they stay dismissed instead of reappearing on the next page load.
            const cookieName = this.dataset.cookie;
            if (cookieName) {
                const days = parseInt(this.dataset.cookieDays || '30', 10);
                const expires = new Date(Date.now() + days * 24 * 60 * 60 * 1000).toUTCString();
                document.cookie = `${cookieName}=1; expires=${expires}; path=/; SameSite=Lax`;
            }
        });
    });

});
</script>
