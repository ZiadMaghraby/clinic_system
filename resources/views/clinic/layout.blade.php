<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', __('Overview')) · {{ config('clinic.name') }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="@auth workspace @else public @endauth">
<a class="skip" href="#main">{{ __('Skip to content') }}</a>
@auth
<aside class="sidebar"><a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">+</span><span>{{ config('clinic.name') }}<small>{{ __('CARE, CONNECTED') }}</small></span></a><p class="nav-label">{{ __('WORKSPACE') }}</p><nav aria-label="{{ __('Main navigation') }}">
@foreach(['dashboard'=>['Overview','◫'], 'appointments.index'=>['Appointments','▦'], 'patients.index'=>['Patients','◎'], 'doctors.index'=>['Care team','✚'], 'invoices.index'=>['Billing','▤'], 'team.index'=>['Staff access','◇'], 'audit.index'=>['Activity log','↻']] as $route => [$label,$icon])
@continue(in_array($route,['patients.index']) && !in_array(auth()->user()->role,['admin','receptionist']))
@continue($route === 'invoices.index' && auth()->user()->role === 'doctor')
@continue(in_array($route,['team.index','audit.index']) && !auth()->user()->isAdmin())
<a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}"><span aria-hidden="true">{{ $icon }}</span>{{ __($label) }}</a>
@endforeach</nav><div class="sidebar-bottom"><span class="status-dot"></span>{{ __(ucfirst(auth()->user()->role)) }}<small>{{ __('Your workspace. Your focus.') }}</small></div></aside>
@endauth
<div class="page"><header class="topbar">@guest<a class="brand" href="{{ route('home') }}"><span class="brand-mark">+</span>{{ config('clinic.name') }}</a>@else<span class="breadcrumb">{{ __('Workspace') }} <span>/</span> @yield('title', __('Overview'))</span>@endguest<div class="top-actions"><form method="post" action="{{ route('language') }}">@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}"><button class="lang" lang="{{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</button></form>@auth<a class="user-chip" href="{{ route('profile.edit') }}"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span>{{ auth()->user()->name }}</a><form method="post" action="{{ route('logout') }}">@csrf<button class="text-button">{{ __('Sign out') }}</button></form>@else<a class="button small" href="{{ route('login') }}">{{ __('Sign in') }}</a>@endauth</div></header>
<main id="main">@if(session('success'))<div class="notice" role="status">{{ session('success') }}</div>@endif @if(session('status'))<div class="notice" role="status">{{ __(session('status')) }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>{{ __('Please check the following:') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main><footer class="footer">{{ config('clinic.name') }} <span>·</span> {{ __('Thoughtful care. A clearer day.') }} <span>·</span> {{ config('clinic.timezone') }}</footer></div>
</body></html>
