@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
@include('projects.partials.assigned-projects', [
  'title' => '⚡ Мої проекти',
  'containerId' => 'electricianProjectsContainer',
  'matchField' => 'electrician',
  'assignmentLabel' => 'Електрик',
  'assignmentMap' => [
    'serviceman_1' => 'Савенков',
    'serviceman_2' => 'Малінін',
  ],
  'scheduleField' => 'electric_work_start_date',
  'emptyText' => 'У вас поки немає проєктів, де ви вказані як електрик.',
])

@include('partials.nav.bottom')

@endsection
