<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>گزارش اموال</title>
    <style>
        @font-face { font-family: Vazirmatn; src: url('{{ asset('fonts/Vazirmatn-variable.woff2') }}') format('woff2'); }
        * { box-sizing: border-box; } body { margin: 24px; color:#111827; font-family:Vazirmatn,Tahoma,sans-serif; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; } h1 { margin:0 0 5px; font-size:22px; } .muted { color:#64748b; font-size:12px; }
        button, a { border:1px solid #cbd5e1; background:#fff; border-radius:7px; padding:8px 14px; text-decoration:none; color:#0f172a; cursor:pointer; }
        table { width:100%; border-collapse:collapse; margin-top:16px; font-size:12px; } th,td { border:1px solid #cbd5e1; padding:8px; text-align:right; } th { background:#f1f5f9; }
        @media print { .toolbar button, .toolbar a { display:none; } body { margin:0; } }
    </style>
</head>
<body>
    <div class="toolbar"><div><h1>گزارش اموال</h1><div class="muted">{{ $assets->count() }} رکورد — تولید شده در {{ $generatedAt->format('Y/m/d H:i') }}</div></div><div><button onclick="window.print()">چاپ / ذخیره PDF</button><a href="{{ route('reports.index', request()->query()) }}">بازگشت</a></div></div>
    <table><thead><tr><th>کد اموال</th><th>عنوان</th><th>دسته‌بندی</th><th>وضعیت</th><th>دارنده / واحد</th><th>سایت</th><th>ارزش خرید</th></tr></thead><tbody>
    @forelse($assets as $asset)
        <tr><td>{{ $asset->asset_code ?: $asset->inventory_code ?: '-' }}</td><td>{{ $asset->title }}</td><td>{{ $asset->category?->name ?: '-' }}</td><td>{{ ['warehouse'=>'انبار','assigned'=>'تحویل‌شده','destroyed'=>'اسقاط‌شده'][$asset->status] ?? $asset->status }}</td><td>{{ $asset->custodyEmployee?->display_name ?: ($asset->custodyDepartment?->name ?: '-') }}</td><td>{{ $asset->currentSite?->name ?: '-' }}</td><td>{{ number_format((float) $asset->purchase_price) }}</td></tr>
    @empty <tr><td colspan="7">رکوردی مطابق فیلتر پیدا نشد.</td></tr> @endforelse
    </tbody></table>
</body>
</html>
