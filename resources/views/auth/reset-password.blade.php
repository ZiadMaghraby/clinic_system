@extends('clinic.layout')
@section('title', __('Reset password'))
@section('content')
<div class="auth-wrap panel padded"><h1>{{ __('Choose a new password') }}</h1><form method="post" action="{{ route('password.store') }}" class="form-stack">@csrf<input type="hidden" name="token" value="{{ $request->route('token') }}"><x-clinic-field name="email" label="Email" type="email" :value="$request->email" required/><x-clinic-field name="password" label="Password" type="password" required minlength="12" autocomplete="new-password"/><x-clinic-field name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password"/><button class="button">{{ __('Reset password') }}</button></form></div>
@endsection