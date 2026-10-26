@extends('layouts.app')

@section('title', __('app.aboutTitle'))

@section('content')
    <h1>{{ __('app.aboutTitle') }}</h1>

    <div class="card">
        <h2>{{ __('app.aboutWhat') }}</h2>
        <ul><li>{{ __('app.ob1') }}</li><li>{{ __('app.ob2') }}</li><li>{{ __('app.ob4') }}</li></ul>
        <p style="margin-top:10px">{{ __('app.aboutWhatBody') }}</p>
    </div>

    <div class="card">
        <h2>{{ __('app.tmTitle') }}</h2>
        <p>{{ __('app.tmBody') }}</p>
    </div>

    <div class="card">
        <h2>{{ __('app.dataTitle') }}</h2>
        <p>{{ __('app.dataBody') }}</p>
        <p>{{ __('app.dataBody2') }}</p>
        <form method="POST" action="{{ route('about.clear') }}">
            @csrf
            @method('DELETE')
            <button class="btn ghost" type="submit">{{ __('app.clear') }}</button>
        </form>
    </div>

    <div class="card">
        <h2>{{ __('app.author') }}</h2>
        <p>{{ config('examprep.author.name') }}<br><span class="muted small">{{ config('examprep.author.credential') }}</span></p>
    </div>
@endsection

@section('nav')
    @include('partials.nav', ['current' => 'about'])
@endsection
