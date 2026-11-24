<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NightLifeBD live trip</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>body{margin:0;font-family:system-ui,"Nirmala UI",sans-serif}#bar{padding:10px 14px;background:#0f172a;color:#fff;font-size:14px}#map{height:calc(100vh - 78px)}</style>
</head>
<body>
    <div id="bar">
        <b>🌙 {{ __('Traveler') }}</b> {{ $trip->kind === 'ride' ? '(ride)' : '(trip)' }}
        @if ($trip->destination) → {{ $trip->destination }} @endif
        @if ($trip->vehicle_plate) · {{ $trip->vehicle_plate }} @endif
        <div id="s" style="font-size:12px;color:#94a3b8">Waiting for location... · link expires {{ $trip->expires_at->format('h:i A') }}</div>
    </div>
    <div id="map"></div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([23.7806, 90.4075], 13);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        let marker = null, line = null, first = true;
        async function poll() {
            try {
                const r = await fetch('{{ route('track.data', $trip->token) }}', { headers: { Accept: 'application/json' } });
                if (r.status === 410) { document.getElementById('s').textContent = '🔒 This trip has ended or the sharing link has expired.'; clearInterval(iv); return; }
                const d = await r.json();
                if (d.lat != null) {
                    if (!marker) marker = L.marker([d.lat, d.lng]).addTo(map); else marker.setLatLng([d.lat, d.lng]);
                    if (line) map.removeLayer(line);
                    line = L.polyline(d.path, { color: '#4f46e5' }).addTo(map);
                    if (first) { map.setView([d.lat, d.lng], 16); first = false; }
                    if (d.is_stale) {
                        document.getElementById('s').innerHTML = '<span style="color:#fbbf24;font-weight:bold">⚠️ Location stale (' + d.last_ping_at + ') — traveler may have lost connectivity</span> · link expires ' + d.expires_at;
                    } else {
                        document.getElementById('s').textContent = 'Last update ' + d.last_ping_at + ' · link expires ' + d.expires_at;
                    }
                }
            } catch (e) { document.getElementById('s').textContent = 'Connection problem, retrying...'; }
        }
        poll(); const iv = setInterval(poll, 5000);
    </script>
</body>
</html>
