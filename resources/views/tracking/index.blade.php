@extends('layouts.crm')

@section('title', 'Tracking Links — CRM')

@section('content')
    <h1 class="crm-page-title">Tracking Links &amp; Attribution</h1>
    <p class="crm-muted crm-mb-2">Create campaign tracking links, manage Lead custom fields, and configure each link's <code>/go/{slug}</code> WhatsApp capture form in one place. Clicks log raw traffic only; revenue is attributed strictly on Won deals.</p>

    @livewire('tracking.tracking-link-manager')
@endsection
