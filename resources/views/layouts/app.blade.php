<!DOCTYPE html>
<?php 
$button_active = false;

use Carbon\Carbon;

?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
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

        <script>
          document.addEventListener("DOMContentLoaded", function () {
            document.body.classList.add("loaded");
        });
        </script>

        <!-- Style for NAVBAR -->
        <style>@import url("//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css");
          .navbar-icon-top .navbar-nav .nav-link > .fa {
            position: relative;
            width: 36px;
            font-size: 24px;
            
          }
          
          .navbar-icon-top .navbar-nav .nav-link > .fa > .badge {
            font-size: 0.75rem;
            position: absolute;
            right: 0;
            font-family: sans-serif;
          }
          
          .navbar-icon-top .navbar-nav .nav-link > .fa {
            top: 3px;
            line-height: 12px;
          }
          
          .navbar-icon-top .navbar-nav .nav-link > .fa > .badge {
            top: -10px;
          }
          
          @media (min-width: 576px) {
            .navbar-icon-top.navbar-expand-sm .navbar-nav .nav-link {
              text-align: center;
              display: table-cell;
              height: 70px;
              vertical-align: middle;
              padding-top: 0;
              padding-bottom: 0;
            }
          
            .navbar-icon-top.navbar-expand-sm .navbar-nav .nav-link > .fa {
              display: block;
              width: 48px;
              margin: 2px auto 4px auto;
              top: 0;
              line-height: 24px;
            }
          
            .navbar-icon-top.navbar-expand-sm .navbar-nav .nav-link > .fa > .badge {
              top: -7px;
            }
          }
          
          @media (min-width: 768px) {
            .navbar-icon-top.navbar-expand-md .navbar-nav .nav-link {
              text-align: center;
              display: table-cell;
              height: 70px;
              vertical-align: middle;
              padding-top: 0;
              padding-bottom: 0;
            }
          
            .navbar-icon-top.navbar-expand-md .navbar-nav .nav-link > .fa {
              display: block;
              width: 48px;
              margin: 2px auto 4px auto;
              top: 0;
              line-height: 24px;
            }
          
            .navbar-icon-top.navbar-expand-md .navbar-nav .nav-link > .fa > .badge {
              top: -7px;
            }
          }
          
          @media (min-width: 992px) {
            .navbar-icon-top.navbar-expand-lg .navbar-nav .nav-link {
              text-align: center;
              display: table-cell;
              height: 70px;
              vertical-align: middle;
              padding-top: 0;
              padding-bottom: 0;
            }
          
            .navbar-icon-top.navbar-expand-lg .navbar-nav .nav-link > .fa {
              display: block;
              width: 48px;
              margin: 2px auto 4px auto;
              top: 0;
              line-height: 24px;
            }
          
            .navbar-icon-top.navbar-expand-lg .navbar-nav .nav-link > .fa > .badge {
              top: -7px;
            }
          }
          
          @media (min-width: 1200px) {
            .navbar-icon-top.navbar-expand-xl .navbar-nav .nav-link {
              text-align: center;
              display: table-cell;
              height: 70px;
              vertical-align: middle;
              padding-top: 0;
              padding-bottom: 0;
            }
          
            .navbar-icon-top.navbar-expand-xl .navbar-nav .nav-link > .fa {
              display: block;
              width: 48px;
              margin: 2px auto 4px auto;
              top: 0;
              line-height: 24px;
            }
          
            .navbar-icon-top.navbar-expand-xl .navbar-nav .nav-link > .fa > .badge {
              top: -7px;
            }
          }
          </style>
        
        <!-- Fonts -->
        <link href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

        <style>
          .card{
              border-radius: 12px;
          }
        </style>
    </head>

    <div name="navigation-bar">
      <nav class="navbar navbar-icon-top navbar-expand-lg navbar-dark bg-dark">
        <a style="margin-right: 20px;" class="navbar-brand" href="{{route('home')}}">
            <img style="height: 60px;" src="{{ asset('img/logo - Text.png') }}"></img>
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

            <!-- Airports Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'airport') == true ? 'active' : '' }} " href="{{route('airportIndex')}}">
                    <i class="fa fa-plane"></i>Airports
                    <span class="sr-only"></span>
                </a>
            </li>

            <!-- Airports Button -->
            <li class="nav-item">
                <a class="nav-link {{ str_contains(request()->url(), 'map') == true ? 'active' : '' }} " target="_blank" href="{{route('mapIndex')}}">
                    <i class="fa fa-map"></i>Map
                    <span class="sr-only"></span>
                </a>
            </li>
          </ul>

          <ul class="navbar-nav ">
            @can('view data')
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-cog">
                </i>
                Manage Data
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item disabled" href="#">Airports</a>
                <a class="dropdown-item disabled" href="#">Aircraft</a>
                @can('approve changes')
                  <div class="dropdown-divider"></div> {{-- Divider --}}
                  <a class="dropdown-item disabled" href="#">Changes Requiring Approval</a>
                @endcan

                @can('view users')
                  <div class="dropdown-divider"></div> {{-- Divider --}}
                  <a class="dropdown-item disabled" href="#">View All Users</a>
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
            <!-- My Account & Notifications -->
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fa fa-user">
                </i>
                {{Auth::user()->fullName('FLC')}}
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                {{-- <a class="dropdown-item">{{Auth::user()->highestRole()->name}}</a>
                <div class="dropdown-divider"></div> --}}
                <a class="dropdown-item" href="{{route('dashboard.index')}}">Dashboard</a>
                <a class="dropdown-item disabled" href="#">My Data</a>
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
        <div class="container" style="padding-top: 50px;">
            @include('layouts.messages')  
            @yield('content')
            @include('layouts.footer')
        </div>
      </div>
        
    </body>
</html>