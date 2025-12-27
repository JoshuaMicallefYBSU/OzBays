<style>
    footer {
        padding: 5px;
        margin-bottom: 30px;
        background-color: rgba(255, 255, 255, 0.348); /* Same transparent effect */
        width: 100%;
        border: 1px solid black;
        position: relative;
        border-radius: 15px;
        overflow: hidden;
    }

    /* Optional: If you want to have a semi-transparent overlay effect */
    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.5); /* Semi-transparent overlay */
        z-index: -1; /* Ensure it sits behind content */
    }
</style>

<footer class="footer mt-4">
    <div class="container-fluid">
        <div class="row align-items-center justify-content-lg-between">
            <div class="col-lg-6 mb-lg-0 mb-4">
                <div class="copyright text-left text-sm text-lg-start" style="color: black;">
                    OzBays V0.8 <br>© Joshua Micallef | 2025 -
                    <script>
                        document.write(new Date().getFullYear())
                    </script>.
                </div>
            </div>
            <div class="col-lg-6">
                <ul class="nav nav-footer justify-content-center justify-content-lg-end"><br>
                    <li class="nav-item">
                        <a href="{{route('privacy.policy')}}" class="nav-link pe-0" style="color: black;" target="_blank">Privacy Policy</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>
