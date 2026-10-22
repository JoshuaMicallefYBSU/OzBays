<footer class="footer oz-footer">
    <div class="container oz-footer-inner text-center">
        <div class="copyright">
            OzBays v0.10.2 &copy; Joshua Micallef | 2025&ndash;{{ now()->year }}
        </div>
        <div class="oz-footer-links-row">
            <a href="{{ route('privacy.policy') }}" class="oz-footer-link">Privacy Policy</a>
            <span class="oz-footer-sep" aria-hidden="true">&bull;</span>
            <a href="{{ route('policy.credits') }}" class="oz-footer-link">Copyright &amp; Usage</a>
            <span class="oz-footer-sep" aria-hidden="true">&bull;</span>
            <a href="{{ route('news.index') }}" class="oz-footer-link">Recent News</a>
            <span class="oz-footer-sep" aria-hidden="true">&bull;</span>
            <a href="{{ route('changelog.index', ['open' => 'latest']) }}" class="oz-footer-link">Changelog</a>
        </div>
    </div>
</footer>