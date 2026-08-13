<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $detail['title'] ?? 'Detail Press Release' }} - Portal BPS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex flex-col justify-between">

    <!-- Header / Navbar -->
    @include('partials.navbar', ['backRoute' => route('home')])

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-10 flex-1 w-full">
        @if(!empty($detail))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden p-6 md:p-10">

                <span class="inline-block px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-semibold mb-3">
                    Berita Resmi Statistik (Press Release)
                </span>

                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-snug">
                    {{ $detail['title'] ?? 'Judul Tidak Tersedia' }}
                </h1>

                <!-- QR Code (kiri) + Abstrak (kanan) -->
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-start">

                    <!-- QR Code + Tombol Unduh -->
                    <div class="md:col-span-1 flex flex-col items-center gap-3 bg-gray-50 border border-gray-100 rounded-xl p-6 h-fit">
                        @if(!empty($detail['pdf']))
                            <img
                                src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&data={{ urlencode($detail['pdf']) }}"
                                alt="QR Code Download PDF"
                                width="240" height="240"
                                class="w-[240px] h-[240px] bg-white border border-gray-200 rounded-lg p-2">
                            <p class="text-sm text-gray-500 text-center">
                                Scan pakai kamera HP untuk<br>langsung download file Press Release dalam bentuk PDF
                            </p>
                            <a href="{{ $detail['pdf'] }}" target="_blank" rel="noopener noreferrer"
                               class="w-full mt-2 inline-flex items-center justify-center gap-2 text-center bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-xl shadow transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Unduh File PDF
                            </a>
                        @else
                            <div class="w-[240px] h-[240px] bg-white border border-gray-200 rounded-lg flex items-center justify-center text-center text-xs text-gray-400 p-4">
                                QR code tidak tersedia
                            </div>
                            <div class="w-full mt-2 inline-block text-center bg-gray-100 text-gray-400 font-medium py-3 px-6 rounded-xl text-xs border border-gray-200">
                                PDF Tidak Tersedia
                            </div>
                        @endif
                    </div>

                    <!-- Abstrak -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Abstrak / Ringkasan</h3>
                        <div class="text-gray-700 leading-relaxed text-sm bg-gray-50 p-5 rounded-xl border border-gray-100">
                            @if(!empty($detail['abstract']))
                                {!! nl2br(html_entity_decode($detail['abstract'])) !!}
                            @else
                                <span class="italic text-gray-400">Tidak ada abstrak untuk press release ini.</span>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        @else
            <!-- State jika data gagal dipanggil -->
            <div class="text-center py-16 bg-white rounded-2xl border border-gray-200 p-8 shadow-sm max-w-xl mx-auto">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h2 class="text-xl font-bold text-gray-800">Detail Press Release Tidak Ditemukan</h2>
                <p class="text-gray-500 mt-2 text-sm">Data gagal diambil dari BPS API atau ID press release tidak valid.</p>
                <a href="{{ route('home') }}" class="inline-block mt-6 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium text-sm transition">
                    Kembali ke Beranda
                </a>
            </div>
        @endif
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-400 py-6 text-center text-xs">
        <p>&copy; {{ date('Y') }} Portal Publikasi BPS. Powered by BPS Web API.</p>
    </footer>

</body>
</html>
