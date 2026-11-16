/* NightLifeBD SOS: long-press (3 s) or double-tap. Works offline for SMS / call / nearest help points. */
(function () {
    'use strict';
    var LS = 'nl_emergency', LAST = 'nl_sos_last';
    var cfg = window.NLSOS || null;
    var data = null;
    try { data = JSON.parse(localStorage.getItem(LS) || 'null'); } catch (e) { data = null; }

    if (cfg) {
        data = Object.assign({}, data || {}, { user: cfg.user, contacts: cfg.contacts });
        try { localStorage.setItem(LS, JSON.stringify(data)); } catch (e) { /* storage full */ }
        if (navigator.onLine) {
            fetch('/api/emergency-data', { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (j) { if (j) { data = j; try { localStorage.setItem(LS, JSON.stringify(j)); } catch (e) {} } })
                .catch(function () {});
        }
    }

    var btn = document.getElementById('sos-btn');
    var modal = document.getElementById('sos-modal');
    if (!btn || !modal) return;
    var statusEl = document.getElementById('sos-status');
    var actionsEl = document.getElementById('sos-actions');
    var havensEl = document.getElementById('sos-havens');
    var holdTimer = null, holdStart = 0, holdTick = null;

    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function distance(a, b, c, d) {
        var R = 6371000, r = Math.PI / 180, dp = (c - a) * r, dl = (d - b) * r;
        var x = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(dl / 2) * Math.sin(dl / 2);
        return 2 * R * Math.asin(Math.sqrt(x));
    }
    function getPos() {
        return new Promise(function (resolve) {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                function (p) { resolve({ lat: p.coords.latitude, lng: p.coords.longitude }); },
                function () { resolve(null); },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 20000 }
            );
        });
    }

    function startHold(e) {
        if (e.cancelable) e.preventDefault();
        holdStart = Date.now();
        holdTick = setInterval(function () {
            var left = Math.max(0, 3 - (Date.now() - holdStart) / 1000);
            btn.textContent = Math.ceil(left) || '!';
        }, 100);
        holdTimer = setTimeout(function () { endHold(); trigger(); }, 3000);
    }
    function endHold() {
        clearTimeout(holdTimer); clearInterval(holdTick);
        btn.textContent = 'SOS';
    }
    btn.addEventListener('pointerdown', startHold);
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (ev) { btn.addEventListener(ev, endHold); });
    btn.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    btn.addEventListener('dblclick', function (e) { e.preventDefault(); trigger(); });
    document.getElementById('sos-close').addEventListener('click', function () { modal.classList.add('hidden'); });

    async function trigger() {
        var last = parseInt(localStorage.getItem(LAST) || '0', 10);
        if (Date.now() - last < 60000 && !window.confirm('An SOS was sent less than a minute ago. Send another one?')) return;
        localStorage.setItem(LAST, String(Date.now()));

        modal.classList.remove('hidden');
        actionsEl.innerHTML = ''; havensEl.innerHTML = '';
        statusEl.textContent = 'Getting your location... / অবস্থান খোঁজা হচ্ছে...';
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);

        var pos = await getPos();
        var link = pos ? 'https://maps.google.com/?q=' + pos.lat + ',' + pos.lng : null;
        var user = (data && data.user) || 'A NightLifeBD user';
        var msg = 'SOS! ' + user + ' needs help. ' + (link ? 'Location: ' + link : 'Location unavailable.') +
            ' / জরুরি! ' + user + ' সাহায্য চাইছেন।' + (link ? ' অবস্থান: ' + link : '');
        statusEl.textContent = pos ? 'Location found. Send the message below. / অবস্থান পাওয়া গেছে।' : 'Could not read your location, the message will not include it.';

        var contacts = (data && data.contacts) || [];
        var sep = /iPhone|iPad|iPod/i.test(navigator.userAgent) ? '&' : '?';
        contacts.forEach(function (c) {
            var cleanDigits = String(c.phone).replace(/\D/g, '');
            if (cleanDigits.startsWith('01')) cleanDigits = '88' + cleanDigits;
            else if (!cleanDigits.startsWith('88') && cleanDigits.length === 10) cleanDigits = '880' + cleanDigits;

            var row = el('div', 'flex gap-2 items-center');
            var wa = el('a', 'flex-1 text-center rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 font-semibold text-xs', '💬 WhatsApp ' + c.name);
            wa.href = 'https://wa.me/' + cleanDigits + '?text=' + encodeURIComponent(msg);
            wa.target = '_blank';

            var sms = el('a', 'rounded-lg bg-indigo-600 text-white px-3 py-2.5 font-semibold text-xs text-center', 'SMS');
            sms.href = 'sms:' + c.phone + sep + 'body=' + encodeURIComponent(msg);

            var call = el('a', 'rounded-lg bg-slate-800 text-white px-3 py-2.5 font-semibold text-xs text-center', '📞');
            call.href = 'tel:' + c.phone;

            row.appendChild(wa); row.appendChild(sms); row.appendChild(call); actionsEl.appendChild(row);
        });
        if (contacts.length) {
            var bcast = el('a', 'block text-center rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white py-2.5 text-xs font-bold', '📢 Broadcast SOS via WhatsApp');
            bcast.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(msg);
            bcast.target = '_blank';
            actionsEl.appendChild(bcast);
        }
        if (!contacts.length) {
            actionsEl.appendChild(el('p', 'text-sm text-amber-700', 'You have no emergency contacts yet. Add some in Safety tools > Emergency contacts.'));
        }
        var c999 = el('a', 'block text-center rounded-lg bg-red-600 text-white py-4 text-lg font-bold', 'Call 999 / ৯৯৯ এ কল করুন');
        c999.href = 'tel:999';
        actionsEl.appendChild(c999);

        if (pos && data && data.havens) {
            var list = data.havens.map(function (h) { return Object.assign({ d: distance(pos.lat, pos.lng, h.lat, h.lng) }, h); })
                .sort(function (a, b) { return a.d - b.d; }).slice(0, 3);
            if (list.length) {
                havensEl.appendChild(el('div', 'font-semibold mb-1', 'Nearest help / নিকটতম সাহায্য'));
                list.forEach(function (h) {
                    var a = el('a', 'block underline text-indigo-700', h.type + ': ' + h.name + ' (' + (h.d / 1000).toFixed(1) + ' km)');
                    a.href = 'https://www.openstreetmap.org/?mlat=' + h.lat + '&mlon=' + h.lng + '#map=17/' + h.lat + '/' + h.lng;
                    a.target = '_blank'; a.rel = 'noopener';
                    havensEl.appendChild(a);
                });
            }
        }

        if (cfg) {
            try {
                var reqId = 'sos_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);
                var payload = Object.assign({}, pos || {}, { request_id: reqId });
                var r = await fetch(cfg.endpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify(payload)
                });
                var j = await r.json();
                if (r.ok) {
                    if (j.is_simulation) {
                        statusEl.textContent += ' [Simulation/Dev Mode: Electronic alerts logged locally on server. For real emergencies, use WhatsApp, SMS, or 999.]';
                    } else if (j.summary) {
                        statusEl.textContent += ' ' + j.summary;
                    }
                }
            } catch (e) {
                statusEl.textContent += ' (No internet: use the SMS and call buttons.)';
            }
        } else {
            statusEl.textContent += ' (Offline mode: use the SMS and call buttons.)';
        }
    }
    window.NLSOS_TRIGGER = trigger;
})();
