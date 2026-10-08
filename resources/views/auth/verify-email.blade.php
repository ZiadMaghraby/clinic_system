@extends('clinic.layout')
@section('title', __('Verify your email'))
@section('content')
<div class="auth-wrap panel padded"><p class="eyebrow">{{ __('ONE LAST STEP') }}</p><h1>{{ __('Verify your email') }}</h1><p>{{ __('Open the verification link in your email to access the clinic. If it has not arrived, you can request another one.') }}</p><form method="post" action="{{ route('verification.send') }}" class="form-stack">@csrf<button class="button">{{ __('Resend verification email') }}</button></form></div>
@endsection