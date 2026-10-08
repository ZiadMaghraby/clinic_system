@extends('clinic.layout')
@section('title', __('Activity log'))
@section('content')
<div class="page-heading"><div><p class="eyebrow">{{ __('ACCOUNTABILITY, BUILT IN') }}</p><h1>{{ __('Activity log') }}</h1><p>{{ __('A record of changes and clinical-note access. Medical content is never copied into this log.') }}</p></div></div><section class="panel"><div class="table-wrap"><table><thead><tr><th>{{ __('Time') }}</th><th>{{ __('Team member') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th></tr></thead><tbody>@forelse($logs as $log)<tr><td><bdi>{{ $log->created_at->format('Y-m-d H:i') }}</bdi></td><td>{{ $log->user?->name ?? __('System') }}</td><td>{{ __($log->action) }}</td><td>{{ __($log->subject_type) }} #{{ $log->subject_id }}</td></tr>@empty<tr><td colspan="4" class="empty">{{ __('No activity yet.') }}</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $logs->links('clinic.partials.pagination') }}</div></section>
@endsection
