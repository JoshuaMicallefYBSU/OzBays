<!DOCTYPE html>
<?php 
$button_active = false;

use Carbon\Carbon;

?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{config('app.name', 'OzBays')}}</title>

        <!-- Bootstrap Content -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.slim.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        
        <script src="https://cdn.tiny.cloud/1/nw91byr1as4hmu04oukdb8aq4rdbclr596fv0xi16i28y6bm/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
        
        <!-- Navbar Links -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

        {{-- DataTable --}}
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/dt-1.10.18/datatables.min.css"/>
        <script type="text/javascript" src="https://cdn.datatables.net/v/bs4/dt-1.10.18/datatables.min.js"></script>

        <script>
          document.addEventListener("DOMContentLoaded", function () {
            document.body.classList.add("loaded");
        });
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&family=IBM+Plex+Mono:wght@500;600;700&display=swap" rel="stylesheet">

        <!-- OzBays site styling -->
        <link rel="stylesheet" href="{{ asset('css/site.css') }}">

    </head>

    <div name="navigation-bar">
      <nav class="navbar oz-navbar navbar-expand-lg navbar-dark">
        <a style="margin-right: 20px;" class="navbar-brand" href="{{route('home')}}">
            <img src="{{ asset('img/logo - Text.png') }}" alt="OzBays">
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <!-- Nav Left -->
          <ul class="navbar-nav mr-auto">

            <!-- Home Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'home') == true ? 'active' : '' }} " href="{{route('home')}}">
                    <i class="fa fa-home"></i>Home
                    <span class="sr-only"></span>
                </a>
            </li>

            <!-- News Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'news') == true ? 'active' : '' }} " href="{{route('news.index')}}">
                    <i class="fa fa-newspaper"></i>News
                    <span class="sr-only"></span>
                </a>
            </li>

            <!-- Airports Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'airports') == true ? 'active' : '' }} " href="{{route('airportIndex')}}">
                    <i class="fa fa-plane"></i>Airports
                    <span class="sr-only"></span>
                </a>
            </li>

            <!-- Map Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'map') == true ? 'active' : '' }} " href="{{route('mapIndex')}}">
                    <i class="fa fa-map"></i>Live Map
                    <span class="sr-only"></span>
                </a>
            </li>
          </ul>

          <ul class="navbar-nav ">
            @can('view data')
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle {{ str_contains(request()->url(), 'admin') == true ? 'active' : '' }} " href="" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-cog">
                </i>
                Administration
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="{{route('dashboard.admin.airport.all')}}">Airports</a>
                <a class="dropdown-item" href="{{route('dashboard.admin.aircraft.all')}}">Aircraft</a>
                @can('approve changes')
                  <div class="dropdown-divider"></div> {{-- Divider --}}
                  <a class="dropdown-item disabled" href="#">Changes Requiring Approval</a>
                @endcan

                @can('manage news')
                  <div class="dropdown-divider"></div> {{-- Divider --}}
                  <a class="dropdown-item" href="{{route('dashboard.admin.news.index')}}">Manage News</a>
                  <a class="dropdown-item" href="{{route('dashboard.admin.notifications.index')}}">Manage Notifications</a>
                @endcan

                @can('view users')
                  <div class="dropdown-divider"></div> {{-- Divider --}}
                  <a class="dropdown-item" href="{{route('dashboard.admin.users.list')}}">View All Users</a>
                @endcan
              </div>
            </li>
          @endcan

            @if(Auth::guest())
            <!-- Login/Signup if not logged in-->
            <li class="nav-item">
              <a class="nav-link" href="{{ route('auth.sso.login') }}">
                <i class="fa fa-user-circle-o">
                </i>
                Login
              </a>
            </li>

            @else
            <!-- Notifications -->
            <li class="nav-item dropdown" id="notification-bell">
              <a class="nav-link" href="" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-bell"></i>
              </a>
            </li>

            <!-- My Account -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle {{ str_contains(request()->url(), 'dashboard') == true ? 'active' : '' }} " href="" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-user">
                </i>
                {{Auth::user()->fullName('FLC')}}
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                {{-- <a class="dropdown-item">{{Auth::user()->highestRole()->name}}</a>
                <div class="dropdown-divider"></div> --}}
                <a class="dropdown-item" href="{{route('dashboard.index')}}">Dashboard</a>
                <a class="dropdown-item" href="{{route('dashboard.settings.index')}}">My Settings</a>
                {{-- <a class="dropdown-item disabled" href="#">My Data</a> --}}
                <div class="dropdown-divider"></div> {{-- Divider --}}
                <a class="dropdown-item" href="{{ route('auth.logout') }}"onclick="event.preventDefault();document.getElementById('logout-form').submit();">{{ __('Logout') }}</a><form id="logout-form" action="{{ route('auth.logout') }}" method="GET" class="d-none">@csrf</form>
              </div>
            </li>
          @endif
        </ul>
        </div>
      </nav>
    </div>

    <body>
      <div id="content-wrapper">
        <div class="container @yield('container-class')" style="padding-top: 50px;">
            @include('layouts.messages')
            @yield('content')
        </div>
        @include('layouts.footer')
      </div>

    </body>

    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable();
        } );
    </script>

    @auth
    <script>
        function loadNotifications() {
            fetch('/partial/notifications')
                .then(res => res.text())
                .then(html => {
                    const container = document.getElementById('notification-bell');

                    const temp = document.createElement('div');
                    temp.innerHTML = html;

                    container.replaceChildren(...temp.children);
                });
        }

        // Initial load
        loadNotifications();

        // Refresh every 30s
        setInterval(loadNotifications, 30000);

        // Mark-as-read button — delegated so it survives the periodic
        // replaceChildren() refresh above.
        document.addEventListener('click', function (e) {
            const button = e.target.closest('.oz-notification-mark-read');
            if (!button) return;

            e.preventDefault();
            e.stopPropagation();

            const item = button.closest('.oz-notification-item');
            const id = item?.dataset.notificationId;
            if (!id) return;

            fetch(`/notifications/${id}/mark-read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            }).then(() => loadNotifications());
        });
    </script>
    @endauth

</html>
