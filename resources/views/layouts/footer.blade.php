<footer class="footer oz-footer">
    <div class="container oz-footer-inner text-center">
        <div class="copyright">
            OzBays v0.9.2 &copy; Joshua Micallef | 2025&ndash;{{ now()->year }}
        </div>
        <div class="oz-footer-links-row">
            <a href="{{ route('privacy.policy') }}" class="oz-footer-link">Privacy Policy</a>
            <a href="{{ route('news.index') }}" class="oz-footer-link">Recent News</a>
            <a href="{{ route('changelog.index') }}" class="oz-footer-link">Changelog</a>
        </div>
    </div>
</footer>