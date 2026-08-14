<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Dinamis - Portal BPS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col justify-between">

    <div>
        @include('partials.navbar', ['backRoute' => route('home')])

        <!-- Hero -->
        <section class="bg-gradient-to-r from-blue-900 to-blue-700 text-white py-14 px-4">
            <div class="max-w-4xl mx-auto text-center space-y-3">
                <h1 class="text-3xl md:text-5xl font-extrabold">Data Dinamis BPS</h1>
                <p class="text-blue-100 text-base md:text-lg">
                    Susun query sendiri: pilih kategori subjek, subjek, lalu 1 tabel/indikator beserta
                    tahun dan karakteristik yang diinginkan.
                </p>
            </div>
        </section>

        <main class="max-w-7xl mx-auto px-4 py-10 space-y-6" id="app" data-domain="{{ $domain }}">

            <!-- ============ PANEL FILTER ============ -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Kategori Subjek -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kategori Subjek</label>
                        <select id="subcat" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">

                            @foreach($subjectCategories as $cat)
                                <option value="{{ $cat['subcat_id'] ?? '' }}">{{ $cat['title'] ?? '-' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Subjek -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Subjek</label>
                        <select id="subject" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="0">Semua</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                    <!-- Tabel / Indikator (listbox, pilih 1 saja -> output selalu 1 tabel) -->
                    <div class="lg:col-span-2">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <label class="text-sm font-semibold text-gray-700">Tabel / Indikator</label>
                            <input type="text" id="tableSearch" placeholder="Cari judul tabel" disabled
                                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-52 disabled:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <p class="text-xs text-gray-400 mb-1.5">Pilih salah satu tabel/indikator di bawah ini — hasil selalu ditampilkan sebagai 1 tabel.</p>
                        <div id="tableList" class="border border-gray-200 rounded-lg p-3 max-h-80 overflow-y-auto flex flex-wrap gap-2">
                            <p class="text-sm text-gray-400">-- Pilih subjek terlebih dahulu --</p>
                        </div>
                    </div>

                    <!-- Tahun & Turunan Tahun -->
                    <div class="space-y-5">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-sm font-semibold text-gray-700">Tahun</label>
                                <button type="button" data-toggle-all="tahun" class="text-xs text-blue-600 hover:underline">semua</button>
                            </div>
                            <div id="tahunBox" class="border border-gray-200 rounded-lg p-3 h-32 overflow-y-auto space-y-1 text-sm">
                                <p class="text-xs text-gray-400">-- Pilih tabel/indikator dahulu --</p>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-sm font-semibold text-gray-700">Turunan Tahun</label>
                                <button type="button" data-toggle-all="turth" class="text-xs text-blue-600 hover:underline">semua</button>
                            </div>
                            <div id="turthBox" class="border border-gray-200 rounded-lg p-3 h-32 overflow-y-auto space-y-1 text-sm"></div>
                        </div>
                    </div>
                </div>

                <!-- Karakteristik & Judul Baris -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label id="vervarLabel" class="text-sm font-semibold text-gray-700">Karakteristik</label>
                            <button type="button" data-toggle-all="vervar" class="text-xs text-blue-600 hover:underline">semua</button>
                        </div>
                        <div id="vervarBox" class="border border-gray-200 rounded-lg p-3 h-40 overflow-y-auto space-y-1 text-sm"></div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-sm font-semibold text-gray-700">Judul Baris</label>
                            <button type="button" data-toggle-all="turvar" class="text-xs text-blue-600 hover:underline">semua</button>
                        </div>
                        <div id="turvarBox" class="border border-gray-200 rounded-lg p-3 h-40 overflow-y-auto space-y-1 text-sm">
                            <p class="text-xs text-gray-400">Tidak ada rincian tambahan untuk pilihan saat ini.</p>
                        </div>
                    </div>
                </div>

                <p id="queryError" class="text-sm text-red-500 hidden"></p>

                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" id="addQueryBtn" disabled
                        class="px-6 py-2.5 rounded-lg bg-gray-300 text-gray-500 text-sm font-semibold transition cursor-not-allowed">
                        Tampilkan Data
                    </button>
                    <button type="button" id="resetBtn"
                        class="px-6 py-2.5 rounded-lg border border-blue-700 text-blue-700 text-sm font-semibold hover:bg-blue-50 transition">
                        Atur Ulang
                    </button>
                </div>
            </div>

            <!-- ============ PREVIEW FILTER YANG DITERAPKAN ============ -->
            <div id="filterPreview" class="hidden flex flex-wrap items-center gap-2 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm"></div>

            <!-- ============ HASIL (SELALU 1 TABEL) ============ -->
            <div id="resultsCard" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8 space-y-6">

                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6 border-b border-gray-100 pb-6">
                    <div class="min-w-0">
                        <span class="inline-block w-fit px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold mb-2">
                            Hasil Query — Data Dinamis
                        </span>
                        <h2 id="resultTitle" class="text-xl md:text-2xl font-bold text-gray-900 leading-snug"></h2>
                        <p id="resultUnit" class="text-sm text-gray-500 mt-1"></p>
                    </div>

                    <!-- Unduh & QR, konsisten dengan halaman Publikasi / Tabel Statis -->
                    <div class="flex items-center gap-4 shrink-0">
                        <a id="downloadBtn" href="#"
                           class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow transition whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Unduh CSV
                        </a>
                        <div class="flex flex-col items-center gap-1.5 bg-gray-50 border border-gray-100 rounded-xl px-4 py-3">
                            <img id="qrCode" src="" alt="QR Code Unduh CSV" width="104" height="104"
                                class="w-[104px] h-[104px] bg-white border border-gray-200 rounded-lg p-1.5">
                            <span class="text-[11px] text-gray-500 text-center leading-tight">Scan utk<br>unduh CSV</span>
                        </div>
                    </div>
                </div>

                <div class="dataset-table overflow-x-auto rounded-xl border border-gray-100">
                    <table id="resultsTable" class="w-full text-sm border-collapse"></table>
                </div>
            </div>

        </main>
    </div>

    <footer class="bg-gray-800 text-gray-400 py-6 text-center text-xs mt-12">
        <p>&copy; {{ date('Y') }} Portal Publikasi BPS. Powered by BPS Web API.</p>
    </footer>

    <script>
    (function () {
        const app = document.getElementById('app');
        const domain = app.dataset.domain;

        const urls = {
            subjects: "{{ route('dinamis.subjects') }}",
            variables: "{{ route('dinamis.variables') }}",
            filterOptions: "{{ route('dinamis.filter-options') }}",
            query: "{{ route('dinamis.query') }}",
            export: "{{ route('dinamis.export') }}",
        };

        const el = {
            subcat: document.getElementById('subcat'),
            subject: document.getElementById('subject'),
            tableSearch: document.getElementById('tableSearch'),
            tableList: document.getElementById('tableList'),
            tahunBox: document.getElementById('tahunBox'),
            turthBox: document.getElementById('turthBox'),
            vervarBox: document.getElementById('vervarBox'),
            vervarLabel: document.getElementById('vervarLabel'),
            turvarBox: document.getElementById('turvarBox'),
            queryError: document.getElementById('queryError'),
            resetBtn: document.getElementById('resetBtn'),
            addQueryBtn: document.getElementById('addQueryBtn'),
            filterPreview: document.getElementById('filterPreview'),
            resultsCard: document.getElementById('resultsCard'),
            resultTitle: document.getElementById('resultTitle'),
            resultUnit: document.getElementById('resultUnit'),
            resultsTable: document.getElementById('resultsTable'),
            downloadBtn: document.getElementById('downloadBtn'),
            qrCode: document.getElementById('qrCode'),
        };

        // Cache sisi klien: sekali subjek/variabel per subjek dimuat dalam sesi
        // ini, tidak perlu request AJAX lagi kalau user bolak-balik memilih.
        const subjectsCache = new Map();
        const variablesCache = new Map();

        const state = {
            allVariables: [],       // daftar lengkap variabel utk subjek yg sedang aktif
            selectedVar: null,      // {id, title} atau null -- HANYA 1, output selalu 1 tabel
            filterData: null,       // response terakhir dari filterOptions
            requestToken: 0,        // guard supaya response AJAX lama tidak menimpa yg baru
        };

        function showHint(node, show) {
            node.classList.toggle('hidden', !show);
        }

        // ---------- Step 1: Subjek berdasarkan Kategori Subjek ----------
        async function loadSubjects(subcat) {
            el.subject.innerHTML = '<option value="0">Memuat…</option>';
            el.subject.disabled = true;

            if (subjectsCache.has(subcat)) {
                renderSubjects(subjectsCache.get(subcat));
                return;
            }

            const res = await fetch(`${urls.subjects}?domain=${domain}&subcat=${subcat}`);
            const json = await res.json();
            subjectsCache.set(subcat, json.subjects);
            renderSubjects(json.subjects);
        }

        function renderSubjects(subjects) {
            const options = ['<option value="0">Semua</option>'].concat(
                subjects.map(s => `<option value="${s.id}">${s.title}</option>`)
            );
            el.subject.innerHTML = options.join('');
            el.subject.disabled = false;
        }

        // ---------- Step 2: Tabel/Indikator (variabel) berdasarkan Subjek ----------
        async function loadVariables(subject) {
            resetSelection();

            if (subject === '0') {
                state.allVariables = [];
                el.tableSearch.disabled = true;
                el.tableSearch.value = '';
                el.tableList.innerHTML = '<p class="text-sm text-gray-400">-- Pilih subjek terlebih dahulu --</p>';
                return;
            }

            el.tableList.innerHTML = '<p class="text-sm text-gray-400">Memuat…</p>';
            el.tableSearch.disabled = true;

            if (variablesCache.has(subject)) {
                state.allVariables = variablesCache.get(subject);
            } else {
                const res = await fetch(`${urls.variables}?domain=${domain}&subject=${subject}`);
                const json = await res.json();
                state.allVariables = json.variables;
                variablesCache.set(subject, json.variables);
            }

            el.tableSearch.disabled = false;
            el.tableSearch.value = '';
            renderTableList('');
        }

        function renderTableList(filterText) {
            const q = filterText.trim().toLowerCase();
            const list = q
                ? state.allVariables.filter(v => v.title.toLowerCase().includes(q))
                : state.allVariables;

            if (list.length === 0) {
                el.tableList.innerHTML = '<p class="text-sm text-gray-400">Tidak ada tabel/indikator yang cocok.</p>';
                return;
            }

            el.tableList.innerHTML = list.map(v => {
                const selected = state.selectedVar && state.selectedVar.id === v.id;
                return `
                    <button type="button" data-var-id="${v.id}" data-var-title="${v.title.replace(/"/g, '&quot;')}"
                        class="table-btn text-left px-3.5 py-2 rounded-full border text-sm transition ${selected
                            ? 'bg-blue-600 border-blue-600 text-white font-semibold shadow-sm'
                            : 'bg-white border-gray-300 text-gray-700 hover:border-blue-400 hover:bg-blue-50'}">
                        ${v.title}
                    </button>`;
            }).join('');
        }

        function resetSelection() {
            state.selectedVar = null;
            state.filterData = null;
            clearFilterPanel();
            updateAddButtonState();
        }

        // ---------- Klik baris Tabel/Indikator: pilih 1 (klik lagi = batal) ----------
        el.tableList.addEventListener('click', (e) => {
            const row = e.target.closest('.table-btn');
            if (!row) return;

            const id = row.dataset.varId;
            const title = row.dataset.varTitle;

            state.selectedVar = (state.selectedVar && state.selectedVar.id === id)
                ? null
                : { id, title };

            renderTableList(el.tableSearch.value);
            loadFilterOptions();
        });

        // ---------- Cari judul tabel (filter di sisi klien, tanpa request baru) ----------
        el.tableSearch.addEventListener('input', () => renderTableList(el.tableSearch.value));

        // ---------- Step 3: Opsi filter (tahun/turunan tahun/karakteristik/judul baris) ----------
        function clearFilterPanel() {
            el.tahunBox.innerHTML = '<p class="text-xs text-gray-400">-- Pilih tabel/indikator dahulu --</p>';
            el.turthBox.innerHTML = '';
            el.vervarBox.innerHTML = '';
            el.vervarLabel.textContent = 'Karakteristik';
            el.turvarBox.innerHTML = '<p class="text-xs text-gray-400">Tidak ada rincian tambahan untuk pilihan saat ini.</p>';
            showHint(el.queryError, false);
        }

        async function loadFilterOptions() {
            updateAddButtonState();

            if (!state.selectedVar) {
                clearFilterPanel();
                return;
            }

            const token = ++state.requestToken;
            el.tahunBox.innerHTML = '<p class="text-xs text-gray-400">Memuat…</p>';

            const res = await fetch(`${urls.filterOptions}?domain=${domain}&var=${state.selectedVar.id}`);
            const json = await res.json();

            if (token !== state.requestToken) return; // sudah usang, ada seleksi baru menyusul

            if (!json.available) {
                const canRetry = json.reachable === false;
                el.tahunBox.innerHTML = `
                    <p class="text-xs text-red-500">${json.message || 'Data tidak tersedia.'}</p>
                    ${canRetry ? '<button type="button" id="retryFilterBtn" class="mt-1.5 text-xs text-blue-600 hover:underline">Coba lagi</button>' : ''}
                `;
                state.filterData = null;
                updateAddButtonState();

                if (canRetry) {
                    document.getElementById('retryFilterBtn')?.addEventListener('click', loadFilterOptions);
                }
                return;
            }

            state.filterData = json;
            renderCheckboxGroup(el.tahunBox, 'tahun', json.tahun, { checkFirst: true });
            renderCheckboxGroup(el.turthBox, 'turth', json.turtahun, { checkFirst: true });

            el.vervarLabel.textContent = json.labelvervar || 'Karakteristik';
            renderCheckboxGroup(el.vervarBox, 'vervar', json.vervar);

            if (json.turvar && json.turvar.length) {
                renderCheckboxGroup(el.turvarBox, 'turvar', json.turvar);
            } else {
                el.turvarBox.innerHTML = '<p class="text-xs text-gray-400">Tidak ada rincian tambahan untuk pilihan saat ini.</p>';
            }

            updateAddButtonState();
        }

        function checkboxRow(group, val, label, checked) {
            return `
                <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 rounded px-1 py-0.5">
                    <input type="checkbox" data-group="${group}" value="${val}" ${checked ? 'checked' : ''}
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>${label}</span>
                </label>`;
        }

        function renderCheckboxGroup(box, group, items, { checkFirst = false } = {}) {
            box.innerHTML = items.map((item, idx) =>
                checkboxRow(group, item.val, item.label, checkFirst && idx === 0)
            ).join('') || '<p class="text-xs text-gray-400">Tidak ada pilihan.</p>';
        }

        // ---------- "semua" toggle per grup ----------
        document.querySelectorAll('[data-toggle-all]').forEach(btn => {
            btn.addEventListener('click', () => {
                const group = btn.dataset.toggleAll;
                const boxes = document.querySelectorAll(`input[data-group="${group}"]`);
                const allChecked = Array.from(boxes).every(b => b.checked);
                boxes.forEach(b => b.checked = !allChecked);
            });
        });

        function selectedValues(group) {
            return Array.from(document.querySelectorAll(`input[data-group="${group}"]:checked`)).map(i => i.value);
        }

        function labelsFor(group) {
            return Array.from(document.querySelectorAll(`input[data-group="${group}"]:checked`))
                .map(i => i.closest('label').textContent.trim());
        }

        function updateAddButtonState() {
            const ready = state.selectedVar !== null && state.filterData !== null;
            el.addQueryBtn.disabled = !ready;
            el.addQueryBtn.classList.toggle('bg-gray-300', !ready);
            el.addQueryBtn.classList.toggle('text-gray-500', !ready);
            el.addQueryBtn.classList.toggle('cursor-not-allowed', !ready);
            el.addQueryBtn.classList.toggle('bg-emerald-500', ready);
            el.addQueryBtn.classList.toggle('hover:bg-emerald-600', ready);
            el.addQueryBtn.classList.toggle('text-white', ready);
        }

        // ---------- Build query params dari seleksi saat ini ----------
        function buildParams() {
            const params = new URLSearchParams();
            params.set('domain', domain);
            params.set('var', state.selectedVar.id);

            selectedValues('tahun').forEach(v => params.append('tahun[]', v));
            selectedValues('vervar').forEach(v => params.append('vervar[]', v));
            selectedValues('turth').forEach(v => params.append('turth[]', v));
            selectedValues('turvar').forEach(v => params.append('turvar[]', v));

            return params;
        }

        function renderPreview() {
            const chips = [
                ['Kategori', el.subcat.options[el.subcat.selectedIndex].text],
                ['Subjek', el.subject.options[el.subject.selectedIndex].text],
                ['Tabel/Indikator', state.selectedVar.title],
                ['Tahun', labelsFor('tahun').join(', ')],
            ];

            const vervarLabels = labelsFor('vervar');
            if (vervarLabels.length) {
                chips.push([el.vervarLabel.textContent, vervarLabels.join(', ')]);
            }

            const turthLabels = labelsFor('turth');
            if (turthLabels.length) {
                chips.push(['Turunan Tahun', turthLabels.join(', ')]);
            }

            const turvarLabels = labelsFor('turvar');
            if (turvarLabels.length) {
                chips.push(['Judul Baris', turvarLabels.join(', ')]);
            }

            el.filterPreview.innerHTML = chips.map(([k, v]) => `
                <span class="inline-flex items-center gap-1 bg-white border border-blue-200 text-blue-900 rounded-full px-3 py-1">
                    <strong class="font-semibold">${k}:</strong> ${v || '-'}
                </span>
            `).join('');
            el.filterPreview.classList.remove('hidden');
        }

        function renderTable(data) {
            el.resultTitle.textContent = data.var_title || state.selectedVar.title;
            el.resultUnit.textContent = data.unit ? `Satuan: ${data.unit}` : '';

            if (!data.rows || data.rows.length === 0) {
                el.resultsTable.innerHTML = '<tr><td class="text-center text-gray-400 py-6">Tidak ada data untuk kombinasi filter ini.</td></tr>';
                el.resultsCard.classList.remove('hidden');
                return;
            }

            const thead = `<thead><tr class="bg-blue-900 text-white">
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide whitespace-nowrap">${data.row_label || 'Karakteristik'}</th>
                ${data.columns.map(c => `<th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide whitespace-nowrap">${c}</th>`).join('')}
            </tr></thead>`;

            const tbody = `<tbody>${data.rows.map((row, i) => `
                <tr class="border-b border-gray-100 ${i % 2 === 1 ? 'bg-gray-50/60' : ''} hover:bg-blue-50/60 transition">
                    <td class="px-4 py-2.5 font-semibold text-gray-800 whitespace-nowrap">${row.label}</td>
                    ${row.values.map(v => `<td class="px-4 py-2.5 text-right text-gray-700 tabular-nums whitespace-nowrap">${v === null || v === undefined ? '-' : Number(v).toLocaleString('id-ID')}</td>`).join('')}
                </tr>
            `).join('')}</tbody>`;

            el.resultsTable.innerHTML = thead + tbody;
            el.resultsCard.classList.remove('hidden');
        }

        // ---------- "Tampilkan Data" ----------
        async function runQuery() {
            showHint(el.queryError, false);

            if (!state.selectedVar) {
                el.queryError.textContent = 'Pilih 1 Tabel/Indikator.';
                showHint(el.queryError, true);
                return;
            }

            if (selectedValues('tahun').length === 0) {
                el.queryError.textContent = 'Pilih minimal 1 tahun.';
                showHint(el.queryError, true);
                return;
            }

            const originalLabel = el.addQueryBtn.textContent;
            el.addQueryBtn.disabled = true;
            el.addQueryBtn.textContent = 'Memuat…';

            try {
                const params = buildParams();
                const res = await fetch(`${urls.query}?${params.toString()}`);
                const json = await res.json();

                if (!res.ok) {
                    el.queryError.textContent = json.message || 'Terjadi kesalahan saat menjalankan query.';
                    showHint(el.queryError, true);
                    return;
                }

                renderPreview();
                renderTable(json);

                const exportUrl = `${urls.export}?${params.toString()}`;
                el.downloadBtn.href = exportUrl;
                el.qrCode.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data=${encodeURIComponent(exportUrl)}`;
            } catch (e) {
                el.queryError.textContent = 'Gagal menghubungi server.';
                showHint(el.queryError, true);
            } finally {
                el.addQueryBtn.disabled = false;
                el.addQueryBtn.textContent = originalLabel;
                updateAddButtonState();
            }
        }

        // ---------- Atur Ulang ----------
        function resetAll() {
            el.subcat.value = '0';
            resetSelection();
            state.allVariables = [];
            el.tableSearch.value = '';
            el.tableSearch.disabled = true;
            el.tableList.innerHTML = '<p class="text-sm text-gray-400 p-4">-- Pilih subjek terlebih dahulu --</p>';
            loadSubjects('0');
            el.filterPreview.classList.add('hidden');
            el.filterPreview.innerHTML = '';
            el.resultsCard.classList.add('hidden');
            showHint(el.queryError, false);
        }

        // ---------- Event bindings ----------
        el.subcat.addEventListener('change', () => loadSubjects(el.subcat.value || '0'));
        el.subject.addEventListener('change', () => loadVariables(el.subject.value || '0'));
        el.resetBtn.addEventListener('click', resetAll);
        el.addQueryBtn.addEventListener('click', runQuery);

        // ---------- Init ----------
        loadSubjects('0');
    })();
    </script>

</body>
</html>
