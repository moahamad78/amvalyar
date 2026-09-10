@extends('layouts.app')

@section('title', 'اسکن پلاک اموال')

@section('content')
<style>
    .scanner-shell { max-width: 900px; margin: 0 auto; }
    .scanner-card { border: 0; border-radius: 22px; box-shadow: 0 16px 45px rgba(15,23,42,.10); overflow: hidden; }
    .scanner-hero { background: linear-gradient(135deg, var(--tenant-primary), var(--tenant-secondary)); color: #fff; padding: 28px; }
    .scanner-video-wrap { position: relative; aspect-ratio: 16 / 10; background: #0f172a; border-radius: 16px; overflow: hidden; }
    #scannerVideo { width: 100%; height: 100%; object-fit: cover; display: block; }
    .scanner-frame { position: absolute; inset: 17% 17%; border: 3px solid #fff; border-radius: 18px; box-shadow: 0 0 0 999px rgba(2,6,23,.28); pointer-events: none; }
    .scanner-status { min-height: 28px; }
    .scanner-result { border: 1px solid #dbeafe; background: #eff6ff; border-radius: 14px; padding: 14px 16px; }
    @media (max-width: 600px) { .scanner-hero { padding: 22px 18px; } .scanner-card .card-body { padding: 16px !important; } .scanner-video-wrap { aspect-ratio: 1 / 1; } }
</style>

<div class="scanner-shell">
    <div class="scanner-card card">
        <div class="scanner-hero">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h4 mb-2">اسکن پلاک اموال</h1>
                    <p class="mb-0 opacity-75">با دوربین موبایل QR یا بارکد پلاک را بخوانید و مستقیماً به شناسنامهٔ مال بروید.</p>
                </div>
                <span class="fs-2" aria-hidden="true">▣</span>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="scanner-video-wrap mb-3">
                <video id="scannerVideo" playsinline muted></video>
                <div class="scanner-frame"></div>
            </div>
            <div id="scannerStatus" class="scanner-status text-muted small mb-3" role="status">برای شروع اسکن، دسترسی دوربین را فعال کنید.</div>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <button id="startScanner" type="button" class="btn btn-primary">شروع اسکن دوربین</button>
                <button id="stopScanner" type="button" class="btn btn-outline-secondary" disabled>توقف دوربین</button>
            </div>

            <div class="border-top pt-4">
                <h2 class="h6 mb-3">ورود دستی کد</h2>
                <form method="post" action="{{ route('asset-scanner.lookup') }}" class="row g-2">
                    @csrf
                    <div class="col-sm"><label class="visually-hidden" for="assetCode">کد اموال یا سریال</label><input id="assetCode" name="code" value="{{ old('code', $code) }}" class="form-control form-control-lg" placeholder="کد پلاک، شماره اموال یا سریال" autocomplete="off" required></div>
                    <div class="col-sm-auto"><button class="btn btn-dark btn-lg w-100">مشاهده مال</button></div>
                </form>
                <div id="scannerResult" class="scanner-result mt-3 d-none"></div>
            </div>

            @if($asset)
                <div class="scanner-result mt-3">
                    <div class="fw-bold">{{ $asset->title }}</div>
                    <div class="small text-muted">کد اموال: {{ $asset->asset_code ?: '-' }}</div>
                    <a class="btn btn-sm btn-primary mt-2" href="{{ route('assets.show', $asset) }}">باز کردن شناسنامه</a>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
(() => {
    const video = document.getElementById('scannerVideo');
    const start = document.getElementById('startScanner');
    const stop = document.getElementById('stopScanner');
    const status = document.getElementById('scannerStatus');
    const codeInput = document.getElementById('assetCode');
    const result = document.getElementById('scannerResult');
    let stream = null;
    let detector = null;
    let timer = null;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const supported = ['qr_code','code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','itf','codabar','data_matrix','aztec','pdf417'];

    const setStatus = (message, danger = false) => { status.textContent = message; status.className = `scanner-status small mb-3 ${danger ? 'text-danger' : 'text-muted'}`; };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
    const stopCamera = () => { if (timer) { clearTimeout(timer); timer = null; } if (stream) { stream.getTracks().forEach(track => track.stop()); stream = null; } video.srcObject = null; start.disabled = false; stop.disabled = true; };
    const lookup = async (value) => {
        const code = String(value || '').trim();
        if (!code) return;
        codeInput.value = code;
        setStatus('کد خوانده شد؛ در حال پیدا کردن مال…');
        try {
            const response = await fetch('{{ route('asset-scanner.lookup') }}', { method: 'POST', headers: {'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({code}) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'مال پیدا نشد.');
            stopCamera();
            result.classList.remove('d-none');
            result.innerHTML = `<strong>${escapeHtml(data.asset.title)}</strong><div class="small text-muted">کد اموال: ${escapeHtml(data.asset.asset_code || '-')}</div><a class="btn btn-sm btn-primary mt-2" href="${escapeHtml(data.redirect_url)}">باز کردن شناسنامه</a>`;
            setStatus('مال پیدا شد.');
        } catch (error) { setStatus(error.message || 'خطا در جست‌وجوی کد.', true); }
    };
    const scan = async () => {
        if (!detector || !video.srcObject) return;
        try {
            const codes = await detector.detect(video);
            if (codes.length && codes[0].rawValue) { await lookup(codes[0].rawValue); return; }
        } catch (_) {}
        timer = setTimeout(scan, 180);
    };
    start.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window)) { setStatus('این مرورگر اسکن دوربین را پشتیبانی نمی‌کند؛ کد را دستی وارد کنید.', true); return; }
        try {
            const available = await BarcodeDetector.getSupportedFormats();
            const formats = supported.filter(format => available.includes(format));
            detector = new BarcodeDetector({formats: formats.length ? formats : available});
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            video.srcObject = stream; await video.play(); start.disabled = true; stop.disabled = false; setStatus('دوربین فعال است؛ پلاک را داخل کادر قرار دهید.'); scan();
        } catch (error) { stopCamera(); setStatus(error.name === 'NotAllowedError' ? 'اجازهٔ دوربین داده نشد.' : 'دوربین در دسترس نیست؛ ورود دستی را امتحان کنید.', true); }
    });
    stop.addEventListener('click', () => { stopCamera(); setStatus('دوربین متوقف شد.'); });
    window.addEventListener('pagehide', stopCamera);
})();
</script>
@endsection
