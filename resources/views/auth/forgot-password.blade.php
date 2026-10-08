@extends('clinic.layout')
@section('title', __('Reset password'))
@section('content')
<div class="auth-wrap panel padded"><h1>{{ __('Reset password') }}</h1><p>{{ __('Enter your email and we will send you a secure reset link.') }}</p><form method="post" action="{{ route('password.email') }}" class="form-stack">@csrf<x-clinic-field name="email" label="Email" type="email" required autocomplete="email"/><button class="button">{{ __('Send reset link') }}</button></form><p class="auth-bottom"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p></div>
@endsection