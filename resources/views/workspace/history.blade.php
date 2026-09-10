@extends('layouts.app')
@section('title','سابقه ورود و فعالیت')
@section('content')
@php
    $localTime = static fn ($value) => $value ? \App\Support\JalaliDate::dateTime(\Carbon\Carbon::parse($value)->setTimezone('Asia/Tehran')) : '—';
@endphp
<div class="container"><h1 class="h3">سابقه ورود و فعالیت</h1>
<p class="text-muted">نشست‌های ورود و رویدادهای ثبت‌شده سامانه؛ اطلاعات مرورگر و نام سیستم گزارش‌شده توسط کاربر، هویت قطعی دستگاه نیستند. فعالیت‌های قدیمی ثبت‌نشده قابل بازیابی نیستند.</p>
<form class="row g-2 mb-3" method="get">
<div class="col-md-2"><label>نوع سابقه</label><select name="kind" class="form-select"><option value="logins" @selected($kind==='logins')>ورودها</option><option value="activity" @selected($kind==='activity')>فعالیت‌ها</option></select></div>
<div class="col-md-3"><label>کاربر</label><select name="user_id" class="form-select"><option value="">همه کاربران مجاز</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id')==$user->id)>{{ $user->name }} ({{ $user->username }})</option>@endforeach</select></div>
<div class="col-md-2"><label>IP</label><input class="form-control" name="ip" value="{{ request('ip') }}" dir="ltr"></div>
<div class="col-md-2"><label>از تاریخ</label><x-workspace-date name="from" :value="request('from')" /></div>
<div class="col-md-2"><label>تا تاریخ</label><x-workspace-date name="to" :value="request('to')" /></div>
<div class="col-auto align-self-end"><button class="btn btn-primary">نمایش</button> <a class="btn btn-outline-secondary" href="{{ route('workspace.history') }}">پاک کردن فیلترها</a></div></form>
<div class="card card-body table-responsive"><table class="table align-middle"><thead><tr><th>کاربر</th><th>زمان (شمسی، تهران)</th><th>IP</th><th>سیستم / مرورگر</th><th>رویداد / وضعیت</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $kind==='logins' ? $row->name.' / '.$row->username : ($row->user?->name ?? 'کاربر حذف‌شده') }}</td><td>{{ $localTime($kind==='logins' ? $row->occurred_at : $row->created_at) }}</td><td dir="ltr">{{ $row->ip_address }}</td><td style="max-width:280px;overflow-wrap:anywhere">@if($kind==='logins'){{ $row->computer_name ?: 'نام سیستم نامشخص' }}<br>@endif{{ $row->user_agent }}</td><td>
@if($kind==='logins'){{ $row->revoked_at ? 'بسته‌شده در '.$localTime($row->revoked_at) : 'لغو نشده' }}<br>آخرین فعالیت: {{ $localTime($row->last_activity_at) }}
@else{{ $row->action }}<br>{{ $row->description }}<br>{{ $row->subject_label }}@endif
</td></tr>@empty<tr><td colspan="5">سابقه‌ای مطابق فیلتر پیدا نشد.</td></tr>@endforelse
</tbody></table>{{ $rows->links() }}</div></div>
@endsection
