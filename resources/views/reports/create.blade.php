<x-app-layout>
    @include('partials.leaflet')
    <x-slot name="header"><h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100">{{ __('Report an incident or write a review') }}</h2></x-slot>
    <div class="max-w-2xl mx-auto p-4">
        <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm p-5 space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                @foreach (['incident' => __('Incident / safety issue'), 'review' => __('Venue review')] as $k => $l)
                    <label class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 text-sm cursor-pointer has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-950/50 text-slate-800 dark:text-slate-200">
                        <input type="radio" name="kind" value="{{ $k }}" class="me-2 text-indigo-600 focus:ring-indigo-500" @checked(old('kind', 'incident') === $k) id="kind_{{ $k }}">{{ $l }}
                    </label>
                @endforeach
            </div>

            {{-- Star Rating (shown only for reviews) --}}
            <div id="rating-section" class="hidden">
                <label class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Your rating') }}</label>
                @php
                    $starDescriptions = [
                        1 => __('1 star - Poor'),
                        2 => __('2 stars - Fair'),
                        3 => __('3 stars - Good'),
                        4 => __('4 stars - Very Good'),
                        5 => __('5 stars - Excellent'),
                    ];
                @endphp
                <div class="flex items-center gap-1.5 mt-1" id="star-rating" role="group" aria-label="{{ __('Rating selection (1 to 5 stars)') }}">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" data-star="{{ $i }}" class="star-btn text-3xl transition-transform hover:scale-110 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 rounded-md p-0.5 text-slate-300 dark:text-slate-600" aria-label="{{ $starDescriptions[$i] }}" aria-pressed="false">★</button>
                    @endfor
                    <input type="hidden" name="rating" id="rating-input" value="{{ old('rating') }}">
                    <span id="rating-label" class="ml-2 text-sm font-medium text-slate-700 dark:text-slate-300" aria-live="polite"></span>
                </div>
                @error('rating')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex flex-wrap justify-between items-center gap-2">
                    <label class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('What happened? (Bangla, English or Banglish)') }}</label>
                    {{-- Voice Dictation Button --}}
                    <div class="flex items-center gap-1.5">
                        <select id="voice-lang" class="text-xs py-1 px-2 border-slate-300 dark:border-slate-700 rounded-md bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <option value="bn-BD">🇧🇩 বাংলা</option>
                            <option value="en-US">🇬🇧 English</option>
                        </select>
                        <button type="button" id="voice-btn" onclick="toggleVoiceDictation()"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 hover:bg-red-100 transition shadow-sm cursor-pointer select-none">
                            <span id="voice-icon" class="text-sm">🎙️</span>
                            <span id="voice-label">{{ __('Speak (Voice)') }}</span>
                        </button>
                    </div>
                </div>

                {{-- Listening wave indicator --}}
                <div id="voice-status" class="hidden text-xs text-red-600 dark:text-red-400 font-semibold items-center gap-1.5 mt-1.5 p-2 bg-red-50/70 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-md animate-pulse">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-600"></span>
                    <span id="voice-status-text">🔴 Listening... বলুন...</span>
                </div>

                <textarea name="body" rows="5" maxlength="500" id="body" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm" placeholder="রাত ১১টায় ধানমন্ডি ২৭ এর কাছে রাস্তায় আলো নেই, একজন ছিনতাই করার চেষ্টা করেছে...">{{ old('body') }}</textarea>
                <div class="text-xs text-slate-500 dark:text-slate-400 text-right"><span id="count">0</span>/500</div>
                @error('body')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Venue (optional if you mark the place on the map)') }}</label>
                <select name="venue_id" id="venue_id" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                    <option value="">{{ __('- none -') }}</option>
                    @foreach ($venues as $v)
                        <option value="{{ $v->id }}" data-lat="{{ $v->lat }}" data-lng="{{ $v->lng }}" @selected(old('venue_id', $selected) == $v->id)>{{ $v->name }} ({{ $v->area }})</option>
                    @endforeach
                </select>
                @error('venue_id')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('Mark the place on the map (click)') }}</label>
                <div id="map" style="height:260px" class="mt-1 rounded-lg border border-slate-300 dark:border-slate-700 z-0"></div>
                <input type="hidden" name="lat" id="lat" value="{{ old('lat') }}">
                <input type="hidden" name="lng" id="lng" value="{{ old('lng') }}">
            </div>

            {{-- Photo Evidence Upload --}}
            <div>
                <label for="photo-input" class="text-sm font-medium text-slate-800 dark:text-slate-200 cursor-pointer">📸 {{ __('Photo evidence (optional, up to 3)') }}</label>
                <div id="photo-drop" class="mt-1 border-2 border-dashed border-slate-300 dark:border-slate-700 dark:bg-slate-800/40 rounded-lg p-4 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition-colors focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:bg-indigo-50/20">
                    <input type="file" name="photos[]" id="photo-input" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" max="3" aria-label="{{ __('Upload photo evidence, up to 3 photos in JPG, PNG or WebP format') }}">
                    <div class="text-slate-500 dark:text-slate-400 text-sm">
                        <span class="text-2xl block mb-1">📷</span>
                        {{ __('Click or drag photos here') }}
                        <span class="block text-xs text-slate-400 dark:text-slate-500 mt-1">{{ __('JPG, PNG or WebP · max 5 MB each · up to 3 photos') }}</span>
                    </div>
                </div>
                <div id="photo-preview" class="flex gap-2 mt-2 flex-wrap"></div>
                @error('photos')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                @error('photos.*')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('When did it happen?') }}</label>
                    <input type="datetime-local" name="incident_time" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('incident_time', now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-md text-sm">
                    @error('incident_time')<p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm mt-6 text-slate-800 dark:text-slate-200"><input type="checkbox" name="is_anonymous" value="1" class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500" @checked(old('is_anonymous'))> {{ __('Post anonymously (moderators can still see who sent it)') }}</label>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('AI will read your report, classify it and check for duplicates. A moderator verifies it before it changes any safety score.') }}</p>
            @if (config('services.turnstile.site_key'))
                @push('head')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                <x-input-error :messages="$errors->get('captcha')" />
            @endif
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white rounded-md py-2.5 font-semibold transition">{{ __('Submit report') }}</button>
        </form>
    </div>
    @push('scripts')
        <script>
            // --- Map ---
            const map = NL.map('map', NL.DHAKA, 12);
            let marker = null;
            const lat = document.getElementById('lat'), lng = document.getElementById('lng');
            function setPoint(la, ln) {
                if (marker) map.removeLayer(marker);
                marker = L.marker([la, ln]).addTo(map);
                lat.value = la; lng.value = ln;
            }
            if (lat.value && lng.value) setPoint(parseFloat(lat.value), parseFloat(lng.value));
            map.on('click', e => setPoint(e.latlng.lat, e.latlng.lng));
            document.getElementById('venue_id').addEventListener('change', e => {
                const o = e.target.selectedOptions[0];
                if (o && o.dataset.lat) { setPoint(parseFloat(o.dataset.lat), parseFloat(o.dataset.lng)); map.setView([o.dataset.lat, o.dataset.lng], 16); }
            });
            const body = document.getElementById('body'), count = document.getElementById('count');
            const upd = () => count.textContent = body.value.length; body.addEventListener('input', upd); upd();
            const sel = document.getElementById('venue_id');
            if (sel.value && !lat.value) sel.dispatchEvent(new Event('change'));

            // --- Kind toggle: show rating only for reviews ---
            const ratingSection = document.getElementById('rating-section');
            document.querySelectorAll('input[name="kind"]').forEach(r => {
                r.addEventListener('change', () => {
                    ratingSection.classList.toggle('hidden', r.value !== 'review');
                });
                if (r.checked && r.value === 'review') ratingSection.classList.remove('hidden');
            });

            // --- Star Rating ---
            const ratingLabels = ['', '{{ __("Poor") }}', '{{ __("Fair") }}', '{{ __("Good") }}', '{{ __("Very Good") }}', '{{ __("Excellent") }}'];
            const starBtns = document.querySelectorAll('.star-btn');
            const ratingInput = document.getElementById('rating-input');
            const ratingLabel = document.getElementById('rating-label');
            let currentRating = parseInt(ratingInput.value) || 0;

            function setRating(n) {
                currentRating = n;
                ratingInput.value = n;
                ratingLabel.textContent = ratingLabels[n] || '';
                starBtns.forEach(btn => {
                    const s = parseInt(btn.dataset.star);
                    btn.classList.toggle('text-amber-400', s <= n);
                    btn.classList.toggle('text-slate-300', s > n);
                    btn.classList.toggle('dark:text-slate-600', s > n);
                    btn.setAttribute('aria-pressed', s <= n ? 'true' : 'false');
                });
            }

            starBtns.forEach(btn => {
                btn.addEventListener('click', () => setRating(parseInt(btn.dataset.star)));
                btn.addEventListener('mouseenter', () => {
                    const s = parseInt(btn.dataset.star);
                    starBtns.forEach(b => {
                        const bs = parseInt(b.dataset.star);
                        b.classList.toggle('text-amber-300', bs <= s);
                    });
                });
                btn.addEventListener('mouseleave', () => setRating(currentRating));
                btn.addEventListener('focus', () => {
                    const s = parseInt(btn.dataset.star);
                    starBtns.forEach(b => {
                        const bs = parseInt(b.dataset.star);
                        b.classList.toggle('text-amber-300', bs <= s);
                    });
                });
                btn.addEventListener('blur', () => setRating(currentRating));
            });
            if (currentRating > 0) setRating(currentRating);

            // --- Photo Upload with Preview ---
            const dropZone = document.getElementById('photo-drop');
            const photoInput = document.getElementById('photo-input');
            const preview = document.getElementById('photo-preview');
            let selectedFiles = new DataTransfer();

            dropZone.addEventListener('click', e => {
                if (e.target !== photoInput) photoInput.click();
            });
            dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('border-indigo-400', 'bg-indigo-50'); });
            dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('border-indigo-400', 'bg-indigo-50'); });
            dropZone.addEventListener('drop', e => {
                e.preventDefault();
                dropZone.classList.remove('border-indigo-400', 'bg-indigo-50');
                addFiles(e.dataTransfer.files);
            });
            photoInput.addEventListener('change', () => addFiles(photoInput.files));

            function addFiles(files) {
                for (const f of files) {
                    if (selectedFiles.files.length >= 3) break;
                    if (!f.type.match(/^image\/(jpeg|png|webp)$/)) continue;
                    if (f.size > 5 * 1024 * 1024) continue;
                    selectedFiles.items.add(f);
                }
                photoInput.files = selectedFiles.files;
                renderPreviews();
            }

            function renderPreviews() {
                preview.innerHTML = '';
                for (let i = 0; i < selectedFiles.files.length; i++) {
                    const f = selectedFiles.files[i];
                    const div = document.createElement('div');
                    div.className = 'relative group';
                    const img = document.createElement('img');
                    img.className = 'w-20 h-20 object-cover rounded-lg border border-slate-200 dark:border-slate-700';
                    img.src = URL.createObjectURL(f);
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 focus:opacity-100 focus:outline-none focus:ring-2 focus:ring-red-400 transition';
                    btn.textContent = '✕';
                    btn.setAttribute('aria-label', '{{ __("Remove photo") }}');
                    btn.onclick = () => removeFile(i);
                    div.appendChild(img);
                    div.appendChild(btn);
                    preview.appendChild(div);
                }
            }

            function removeFile(index) {
                const dt = new DataTransfer();
                for (let i = 0; i < selectedFiles.files.length; i++) {
                    if (i !== index) dt.items.add(selectedFiles.files[i]);
                }
                selectedFiles = dt;
                photoInput.files = selectedFiles.files;
                renderPreviews();
            }

            // --- Voice Dictation (Web Speech API) ---
            let voiceRecognition = null;
            let isVoiceListening = false;

            function toggleVoiceDictation() {
                const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRec) {
                    alert('{{ __("Voice dictation is not supported on this browser. Please use Google Chrome or Microsoft Edge.") }}');
                    return;
                }

                const btn = document.getElementById('voice-btn');
                const label = document.getElementById('voice-label');
                const status = document.getElementById('voice-status');
                const statusText = document.getElementById('voice-status-text');
                const lang = document.getElementById('voice-lang').value;
                const bodyText = document.getElementById('body');

                if (isVoiceListening) {
                    if (voiceRecognition) voiceRecognition.stop();
                    return;
                }

                try {
                    voiceRecognition = new SpeechRec();
                    voiceRecognition.lang = lang;
                    voiceRecognition.continuous = true;
                    voiceRecognition.interimResults = false;

                    voiceRecognition.onstart = function () {
                        isVoiceListening = true;
                        btn.classList.remove('bg-red-50', 'text-red-700');
                        btn.classList.add('bg-red-600', 'text-white', 'ring-2', 'ring-red-300');
                        label.textContent = '{{ __("Stop") }}';
                        status.classList.remove('hidden');
                        status.classList.add('flex');
                        statusText.textContent = lang === 'bn-BD' ? '🔴 শুনছি... আপনার অভিজ্ঞতা বাংলায় বলুন...' : '🔴 Listening... Speak your report now...';
                    };

                    voiceRecognition.onresult = function (event) {
                        for (let i = event.resultIndex; i < event.results.length; ++i) {
                            if (event.results[i].isFinal) {
                                const transcript = event.results[i][0].transcript.trim();
                                if (transcript) {
                                    if (bodyText.value.length > 0 && !bodyText.value.endsWith(' ') && !bodyText.value.endsWith('\n')) {
                                        bodyText.value += ' ';
                                    }
                                    bodyText.value += transcript;
                                    if (bodyText.value.length > 500) {
                                        bodyText.value = bodyText.value.substring(0, 500);
                                    }
                                    upd();
                                }
                            }
                        }
                    };

                    voiceRecognition.onerror = function (event) {
                        console.warn('Speech recognition warning:', event.error);
                        if (event.error === 'not-allowed') {
                            alert('{{ __("Microphone permission was denied. Please allow microphone access in your browser.") }}');
                        }
                        stopVoice();
                    };

                    voiceRecognition.onend = function () {
                        stopVoice();
                    };

                    voiceRecognition.start();
                } catch (err) {
                    console.error('Speech recognition start failed:', err);
                    stopVoice();
                }

                function stopVoice() {
                    isVoiceListening = false;
                    btn.classList.remove('bg-red-600', 'text-white', 'ring-2', 'ring-red-300');
                    btn.classList.add('bg-red-50', 'text-red-700');
                    label.textContent = '{{ __("Speak (Voice)") }}';
                    status.classList.add('hidden');
                    status.classList.remove('flex');
                }
            }
        </script>
    @endpush
</x-app-layout>
