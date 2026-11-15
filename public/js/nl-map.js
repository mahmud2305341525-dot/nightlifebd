/* NightLifeBD map helpers (Leaflet + OpenStreetMap, no API key needed) */
window.NL = window.NL || {};
NL.DHAKA = [23.7806, 90.4075];
NL.colors = { safe: '#16a34a', moderate: '#d97706', caution: '#dc2626', unrated: '#64748b' };

NL.map = function (id, center, zoom) {
    const m = L.map(id).setView(center || NL.DHAKA, zoom || 12);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(m);
    return m;
};

NL.dot = function (map, lat, lng, color, html) {
    const c = L.circleMarker([lat, lng], { radius: 9, color: color, fillColor: color, fillOpacity: 0.85, weight: 2 }).addTo(map);
    if (html) c.bindPopup(html);
    return c;
};

NL.csrf = function () {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
};

NL.post = async function (url, body) {
    const r = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': NL.csrf() },
        body: JSON.stringify(body || {})
    });
    let data = null;
    try { data = await r.json(); } catch (e) { /* not json */ }
    return { ok: r.ok, status: r.status, data: data };
};

NL.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
};

NL.locate = function () {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(new Error('Geolocation not supported'));
        navigator.geolocation.getCurrentPosition(
            p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            e => reject(e),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
        );
    });
};
