@extends('layouts.crm')

@section('title', 'WhatsApp Inbox')

@section('content')
<div class="crm-page-header">
    <h1>WhatsApp Inbox</h1>
    <a href="{{ route('whatsapp.settings') }}" class="crm-btn crm-btn-secondary btn-sm">Settings</a>
</div>

@livewire('whatsapp.chat-widget')
@endsection
