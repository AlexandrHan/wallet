@push('styles')
  <link rel="stylesheet" href="/css/project.css?v={{ filemtime(public_path('css/project.css')) }}">
@endpush

@extends('layouts.app')

@section('content')
@include('projects.partials.assigned-projects', [
  'title' => '🏗 Мої проекти',
  'containerId' => 'installerProjectsContainer',
  'matchField' => 'installation_team',
  'assignmentLabel' => 'Монтажна бригада',
  'assignmentMap' => [
    'kryzhanovskyi' => 'Крижановський',
    'kukuiaka' => 'Кукуяка',
    'shevchenko' => 'Шевченко',
  ],
  'scheduleField' => 'panel_work_start_date',
  'scheduleDurationField' => 'panel_work_days',
  'scheduleDatesKey' => 'installer_schedule_dates',
  'emptyText' => 'У вас поки немає проєктів, де ви вказані як монтажна бригада.',
])

@include('partials.nav.bottom')

@endsection
