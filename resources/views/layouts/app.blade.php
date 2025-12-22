<!DOCTYPE html>
<?php 
$button_active = false;

use Carbon\Carbon;

?>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{config('app.name', 'QFA100 Team')}}</title>

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
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js"></script>
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
        <a style="margin-right: 20px;" class="navbar-brand" href="">
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

          <ul class="navbar-nav ">

        </ul>
        </div>
      </nav>
    </div>

    <body>
      <div id="content-wrapper">
        <div class="container" style="padding-top: 50px;">

          <div style="margin-bottom: 20px;" class="shadow-lg mx-4">
            {{-- Success --}}
            @if(session('success'))
            <div class="alert alert-success">
            <b>SUCCESS!</b> {{session('success')}}
            </div>
            @endif
        
            {{-- Error --}}
            @if(session('error'))
            <div class="alert alert-danger">
            <b>ERROR:</b> {{session('error')}}
            </div>
            @endif
        </div>

            @yield('content')

            {{-- @include('layouts.footer') --}}
        </div>
      </div>
        
    </body>
</html>