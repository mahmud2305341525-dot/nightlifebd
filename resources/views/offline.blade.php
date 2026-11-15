<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NightLifeBD - Offline</title>
    <style>
        body{font-family:system-ui,"Nirmala UI",sans-serif;background:#0f172a;color:#fff;margin:0;padding:24px;text-align:center}
        h1{margin:8px 0}.btn{display:block;margin:12px auto;max-width:360px;padding:16px;border-radius:12px;background:#334155;color:#fff;text-decoration:none;font-weight:700}
        #sos-btn{width:150px;height:150px;border-radius:50%;border:8px solid #fecaca;background:#dc2626;color:#fff;font-size:34px;font-weight:800;margin:16px auto;display:block}
        .hidden{display:none!important}
        #sos-modal{position:fixed;inset:0;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;padding:16px;color:#0f172a;text-align:left}
        #sos-modal>div{background:#fff;border-radius:16px;width:100%;max-width:420px;padding:16px;max-height:90vh;overflow:auto}
        #sos-modal a{display:block;text-align:center;margin:8px 0;padding:14px;border-radius:10px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:700}
        #sos-modal a[href^="tel:999"]{background:#dc2626}
        #sos-modal .flex{display:flex;gap:8px}#sos-modal .flex a{flex:1}
        #sos-havens a{background:none;color:#4338ca;text-align:left;padding:4px 0;font-weight:400;text-decoration:underline}
    </style>
</head>
<body>
    <h1>🌙 NightLifeBD</h1>
    <p>You are offline. Emergency tools still work.<br>আপনি অফলাইনে আছেন। জরুরি টুল কাজ করবে।</p>
    <button id="sos-btn" type="button">SOS</button>
    <p style="font-size:13px;color:#94a3b8">Hold 3 seconds / double-tap</p>
    <a class="btn" href="tel:999" style="background:#dc2626">📞 Call 999</a>
    <a class="btn" href="/" onclick="location.reload();return false;">Try again / আবার চেষ্টা করুন</a>
    @include('partials.sos-modal')
    <script src="/js/sos.js"></script>
</body>
</html>
