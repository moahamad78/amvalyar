<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | اموال‌یار</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#090b14">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('branding/amvalyar-mark-original.svg') }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:site_name" content="اموال‌یار">
    <meta property="og:title" content="{{ $title }} | اموال‌یار">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <script type="application/ld+json">{"\u0040context":"https://schema.org","\u0040type":"Organization","name":"اموال‌یار","url":"https://amvalyar.ir/","logo":"https://amvalyar.ir/branding/amvalyar-mark-original.svg"}</script>
    @php
        $softwareJson = json_encode([
            chr(64).'context' => 'https://schema.org',
            chr(64).'type' => 'SoftwareApplication',
            'name' => 'اموال‌یار',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'url' => $canonical,
            'description' => $description,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @endphp
    <script type="application/ld+json">{!! $softwareJson !!}</script>
    <style>
        @font-face{font-family:Vazirmatn;src:url("{{ asset('fonts/Vazirmatn-variable.woff2') }}") format("woff2");font-weight:100 900;font-style:normal;font-display:swap}
        :root{--night:#090b14;--panel:#11182a;--paper:#f7f8fc;--ink:#151827;--muted:#667085;--line:#e5e8f0;--violet:#6466f1;--mint:#40c99b;--radius:22px}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink);font-family:Vazirmatn,Tahoma,sans-serif;line-height:1.9}.shell{width:min(1120px,calc(100% - 40px));margin-inline:auto}a{text-decoration:none;color:inherit}.skip{position:fixed;top:-80px;right:20px;background:#fff;padding:10px;z-index:9}.skip:focus{top:15px}.site-header{background:rgba(9,11,20,.94);border-bottom:1px solid rgba(255,255,255,.08);position:sticky;top:0;z-index:5}.nav{height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px}.brand img{display:block;width:170px}.links{display:flex;align-items:center;gap:24px;color:#c8ccdb;font-weight:700;font-size:13px}.links a:hover{color:#fff}.login{padding:9px 16px;border-radius:12px;background:#fff;color:var(--night)}.hero{position:relative;overflow:hidden;padding:88px 0 78px;background:radial-gradient(circle at 82% 15%,rgba(100,102,241,.28),transparent 30%),var(--night);color:#fff}.hero:after{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:44px 44px;mask-image:linear-gradient(#000,transparent)}.hero .shell{position:relative;z-index:1}.eyebrow{color:#c7c9ff;font:700 12px monospace;letter-spacing:1px}.hero h1{font-size:clamp(35px,5vw,60px);line-height:1.3;letter-spacing:-1.6px;max-width:830px;margin:14px 0}.hero p{max-width:700px;margin:0;color:#bcc2d4;font-size:17px}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:13px;font-weight:800}.button.primary{background:linear-gradient(135deg,#6567f1,#8b7cf6);color:#fff}.button.secondary{border:1px solid rgba(255,255,255,.25);color:#fff}.section{padding:76px 0}.section h2{font-size:clamp(28px,3.7vw,43px);line-height:1.4;margin:0 0 12px}.lead{max-width:760px;color:var(--muted);margin:0 0 32px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:26px}.card b{font-size:18px}.card p{font-size:14px;color:var(--muted);margin:9px 0 0}.number{display:grid;place-items:center;width:40px;height:40px;margin-bottom:15px;border-radius:12px;background:#ededff;color:var(--violet);font-weight:900}.band{background:var(--panel);color:#fff;padding:54px;border-radius:30px}.band p{color:#b9c2d6;max-width:720px}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-top:28px}.step{border-top:1px solid rgba(255,255,255,.16);padding-top:15px}.step span{color:#989ffb;font:700 12px monospace}.step b{display:block;margin-top:6px}.faq{display:grid;gap:12px}.faq details{background:#fff;border:1px solid var(--line);border-radius:15px;padding:16px 19px}.faq summary{cursor:pointer;font-weight:800}.faq p{margin:9px 0 0;color:var(--muted);font-size:14px}.cta{background:linear-gradient(125deg,#5c5df0,#8065e7);border-radius:28px;padding:52px;color:#fff;text-align:center}.cta h2{margin:0 0 12px}.cta p{margin:0 0 22px;color:#eceaff}.cta .button{background:#fff;color:var(--ink)}footer{background:var(--night);color:#a4aaba;padding:31px 0}.footer{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:12px}.footer img{width:140px}@media(max-width:780px){.links a:not(.login){display:none}.nav{height:65px}.brand img{width:145px}.hero{padding:62px 0}.section{padding:55px 0}.grid,.steps{grid-template-columns:1fr}.band{padding:27px}.shell{width:min(100% - 26px,1120px)}}
    </style>
</head>
<body>
<a class="skip" href="#main">رفتن به محتوای اصلی</a>
<header class="site-header"><nav class="shell nav" aria-label="منوی عمومی"><a class="brand" href="{{ url('/') }}"><img src="{{ asset('branding/amvalyar-logo-original.svg') }}" alt="اموال‌یار"></a><div class="links"><a href="{{ route('public.features') }}">راهکارها</a><a href="{{ route('public.asset-management') }}">مدیریت اموال</a><a href="{{ route('public.barcode-stocktake') }}">انبارگردانی بارکدی</a><a href="{{ route('public.guides') }}">راهنما</a><a class="login" href="{{ route('login') }}">ورود به سامانه</a></div></nav></header>
<main id="main">@yield('content')</main>
<footer><div class="shell footer"><img src="{{ asset('branding/amvalyar-logo-original-light.svg') }}" alt="اموال‌یار"><span>سامانه مدیریت اموال و دارایی‌های سازمانی</span><span>© {{ date('Y') }} اموال‌یار</span></div></footer>
@include('partials.public-support-widget')
</body>
</html>
