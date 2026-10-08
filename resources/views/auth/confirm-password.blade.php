@extends('clinic.layout')
@section('title', __('Confirm password'))
@section('content')
<div class="auth-wrap panel padded"><h1>{{ __('Confirm password') }}</h1><form method="post" action="{{ route('password.confirm') }}" class="form-stack">@csrf<x-clinic-field name="password" label="Password" type="password" required autocomplete="current-password"/><button class="button">{{ __('Confirm') }}</button></form></div>
@endsection