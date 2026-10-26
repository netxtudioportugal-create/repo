@extends('layouts.app')

@section('title', __('app.obTitle'))

@section('content')
    <h1>{{ __('app.obTitle') }}</h1>
    <ul class="notice">
        <li>{{ __('app.ob1') }}</li>
        <li>{{ __('app.ob2') }}</li>
        <li>{{ __('app.ob3') }}</li>
        <li>{{ __('app.ob4') }}</li>
    </ul>
    <form method="POST" action="{{ route('onboarding.store') }}">
        @csrf
        <label class="check"><input type="checkbox" id="ack" name="ack" value="1" required> <span>{{ __('app.obCheck') }}</span></label>
        <button class="btn block" id="ackBtn" type="submit">{{ __('app.obStart') }}</button>
    </form>
@endsection
