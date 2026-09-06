@extends('layouts.app')

@section('content')
<style>
    .support-admin{direction:rtl}.support-admin-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.support-admin h1{margin:0;font-size:28px}.support-admin-head p{margin:5px 0 0;color:#64748b}.ticket-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}.ticket-stat{padding:18px;border:1px solid #e2e8f0;border-radius:16px;background:#fff}.ticket-stat span{display:block;color:#64748b;font-size:12px}.ticket-stat b{font-size:25px}.ticket-filters{display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:10px;padding:15px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;margin-bottom:16px}.ticket-filters input,.ticket-filters select,.ticket-edit select,.ticket-edit textarea{width:100%;border:1px solid #dbe1ea;border-radius:10px;padding:10px;font:inherit;background:#fff}.ticket-filters button,.ticket-edit button{border:0;border-radius:10px;background:#4f46e5;color:#fff;padding:10px 18px;font:700 13px inherit;cursor:pointer}.ticket-card{margin-bottom:13px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;overflow:hidden}.ticket-summary{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:20px;align-items:center;padding:17px 20px}.ticket-code{direction:ltr;font:700 12px monospace;color:#4f46e5}.ticket-meta small{display:block;color:#718096}.ticket-status{padding:6px 10px;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:800}.ticket-details{display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:0 20px 20px}.ticket-message{padding:15px;border-radius:12px;background:#f8fafc;white-space:pre-wrap}.ticket-edit{display:grid;gap:9px}.ticket-empty{text-align:center;padding:60px;border:1px dashed #cbd5e1;border-radius:18px;color:#64748b}@media(max-width:850px){.ticket-stats{grid-template-columns:1fr 1fr}.ticket-filters,.ticket-summary,.ticket-details{grid-template-columns:1fr}.support-admin-head{align-items:flex-start;flex-direction:column}}
</style>
<div class="support-admin">
    <div class="support-admin-head"><div><h1>فروش و پشتیبانی</h1><p>درخواست‌های ثبت‌شده از وب‌سایت عمومی اموال‌یار</p></div></div>
    <div class="ticket-stats">
        @foreach(['new'=>'جدید','in_progress'=>'در حال بررسی','answered'=>'پاسخ داده‌شده','closed'=>'بسته‌شده'] as $key=>$label)
            <div class="ticket-stat"><span>{{ $label }}</span><b>{{ $counts[$key] ?? 0 }}</b></div>
        @endforeach
    </div>
    <form class="ticket-filters" method="get">
        <input name="q" value="{{ request('q') }}" placeholder="جست‌وجوی نام، موبایل یا کد پیگیری">
        <select name="type"><option value="">همه موضوع‌ها</option>@foreach(['sales'=>'خرید پنل','demo'=>'درخواست دمو','support'=>'پشتیبانی فنی','other'=>'سایر موارد'] as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select>
        <select name="status"><option value="">همه وضعیت‌ها</option>@foreach(['new'=>'جدید','in_progress'=>'در حال بررسی','answered'=>'پاسخ داده‌شده','closed'=>'بسته‌شده'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select>
        <button>اعمال فیلتر</button>
    </form>
    @forelse($tickets as $ticket)
        <article class="ticket-card">
            <div class="ticket-summary"><div class="ticket-meta"><strong>{{ $ticket->name }}</strong><small>{{ $ticket->organization ?: 'بدون نام سازمان' }}</small></div><div class="ticket-meta"><a href="tel:{{ $ticket->mobile }}" dir="ltr">{{ $ticket->mobile }}</a><small>{{ $ticket->email ?: 'بدون ایمیل' }}</small></div><div class="ticket-meta"><strong>{{ $ticket->typeLabel() }}</strong><small>{{ $ticket->created_at->diffForHumans() }}</small></div><div><span class="ticket-status">{{ $ticket->statusLabel() }}</span><div class="ticket-code">{{ $ticket->tracking_code }}</div></div></div>
            <div class="ticket-details"><div><strong>متن درخواست</strong><div class="ticket-message">{{ $ticket->message }}</div></div><form class="ticket-edit" method="post" action="{{ route('support-tickets.update', $ticket) }}">@csrf @method('PATCH')<label>وضعیت<select name="status">@foreach(['new'=>'جدید','in_progress'=>'در حال بررسی','answered'=>'پاسخ داده‌شده','closed'=>'بسته‌شده'] as $key=>$label)<option value="{{ $key }}" @selected($ticket->status===$key)>{{ $label }}</option>@endforeach</select></label><label>یادداشت داخلی<textarea name="admin_note" rows="3">{{ $ticket->admin_note }}</textarea></label><button>ذخیره تغییرات</button></form></div>
        </article>
    @empty <div class="ticket-empty">درخواستی با این مشخصات پیدا نشد.</div> @endforelse
    {{ $tickets->links() }}
</div>
@endsection
