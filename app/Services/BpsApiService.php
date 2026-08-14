<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;

class BpsApiService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $defaultDomain;

    // Lama cache (detik). Dipusatkan di sini supaya gampang di-tuning.
    private const TTL_LISTING  = 600;
    private const TTL_DETAIL   = 600;
    private const TTL_TAXONOMY = 600;
    private const TTL_DATA     = 600;

    public function __construct()
    {
        $rawBaseUrl = config('services.bps.base', env('BPS_API_BASE', 'https://webapi.bps.go.id/v1/'));

        $this->baseUrl = rtrim($rawBaseUrl, '/');
        $this->apiKey = config('services.bps.key', env('BPS_API_KEY'));
        $this->defaultDomain = config('services.bps.domain', env('BPS_DOMAIN_DEFAULT', '1674'));
    }

    /**
     * Helper pusat: GET + cache. SEMUA panggilan ke API BPS di service ini
     * lewat sini supaya tidak ada endpoint yang lupa di-cache, dan supaya
     * request yang macet/lambat tidak menahan proses terlalu lama (timeout 10 detik).
     */
    private function cachedGet(string $cacheKey, int $ttlSeconds, string $url): array
    {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $response = null;

        // API BPS cukup sering lambat/timeout sesaat, jadi coba 2x sebelum menyerah.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::timeout(15)->get($url);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $response = null;
            }

            if ($response && $response->successful()) {
                break;
            }

            if ($attempt < 2) {
                usleep(300_000);
            }
        }

        if (! $response || ! $response->successful()) {
            // PENTING: request yang gagal (timeout/limit/error API) sengaja TIDAK
            // di-cache. Sebelumnya kegagalan sesaat ikut ke-cache selama $ttlSeconds
            // (bisa 10 menit) sehingga tampak seolah datanya memang tidak tersedia,
            // padahal itu cuma gangguan sesaat ke API BPS.
            return [];
        }

        $json = $response->json() ?? [];
        Cache::put($cacheKey, $json, $ttlSeconds);

        return $json;
    }

    // ==========================================================
    // Publikasi
    // ==========================================================

    public function getPublications($domain = null, $page = 1, $keyword = '', $year = '')
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/list/model/publication/lang/ind/domain/{$domain}/key/{$this->apiKey}/page/{$page}";

        if (!empty($keyword)) {
            $url .= "/keyword/" . urlencode($keyword);
        }

        if (!empty($year)) {
            $url .= "/year/" . urlencode($year);
        }

        return $this->cachedGet('bps_pub_' . md5($url), self::TTL_LISTING, $url);
    }

    public function getPublicationDetail($pubId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/model/publication/lang/ind/domain/{$domain}/id/{$pubId}/key/{$this->apiKey}/";

        $json = $this->cachedGet('bps_pub_detail_' . md5($url), self::TTL_DETAIL, $url);

        return $json['data'] ?? null;
    }

    // ==========================================================
    // Press Release
    // ==========================================================

    public function getPressReleases($domain = null, $page = 1, $keyword = '', $year = '', $month = '')
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/list/model/pressrelease/lang/ind/domain/{$domain}/key/{$this->apiKey}/page/{$page}";

        if (!empty($keyword)) {
            $url .= "/keyword/" . urlencode($keyword);
        }

        if (!empty($month)) {
            $url .= "/month/" . urlencode($month);
        }

        if (!empty($year)) {
            $url .= "/year/" . urlencode($year);
        }

        return $this->cachedGet('bps_brs_' . md5($url), self::TTL_LISTING, $url);
    }

    public function getPressReleaseDetail($brsId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/model/pressrelease/lang/ind/domain/{$domain}/id/{$brsId}/key/{$this->apiKey}/";

        $json = $this->cachedGet('bps_brs_detail_' . md5($url), self::TTL_DETAIL, $url);

        return $json['data'] ?? null;
    }

    // ==========================================================
    // Tabel Statis
    // ==========================================================

    public function getStaticTables($domain = null, $page = 1, $keyword = '')
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/list/model/statictable/domain/{$domain}/lang/ind/page/{$page}";

        if (!empty($keyword)) {
            $url .= "/keyword/" . urlencode($keyword);
        }

        $url .= "/key/{$this->apiKey}";

        return $this->cachedGet('bps_table_' . md5($url), self::TTL_LISTING, $url);
    }

    public function getStaticTableDetail($tableId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/domain/{$domain}/model/statictable/lang/ind/id/{$tableId}/key/{$this->apiKey}/";

        $json = $this->cachedGet('bps_table_detail_' . md5($url), self::TTL_DETAIL, $url);
        $data = $json['data'] ?? null;

        if ($data && isset($data['table'])) {
            $data['table'] = html_entity_decode($data['table']);
        }

        return $data;
    }


    private function extractListItems(array $json): array
    {
        $data = $json['data'] ?? null;

        if (!is_array($data)) {
            return [];
        }

        if (isset($data[1]) && is_array($data[1])) {
            return array_values($data[1]);
        }

        return array_values(array_filter($data, fn ($row) => is_array($row)));
    }

    private function fetchAllListPages(callable $buildUrl, string $cachePrefix, int $maxPages = 30): array
    {
        $first = $this->cachedGet("{$cachePrefix}_p1", self::TTL_TAXONOMY, $buildUrl(1));

        $items = $this->extractListItems($first);
        $totalPages = (int) ($first['data'][0]['pages'] ?? 1);
        $totalPages = max(1, min($totalPages, $maxPages));

        if ($totalPages > 1) {
            $pages = range(2, $totalPages);

            $responses = Http::pool(function ($pool) use ($pages, $buildUrl) {
                foreach ($pages as $page) {
                    $pool->as((string) $page)->timeout(10)->get($buildUrl($page));
                }
            });

            foreach ($pages as $page) {
                $response = $responses[(string) $page] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    $items = array_merge($items, $this->extractListItems($response->json()));
                }
            }
        }

        return $items;
    }

    public function getAllSubjectCategories($domain = null): array
    {
        $domain = $domain ?? $this->defaultDomain;

        $buildUrl = fn ($page) => "{$this->baseUrl}/api/list/model/subcat/lang/ind/domain/{$domain}/page/{$page}/key/{$this->apiKey}";

        return Cache::remember("bps_subcat_all_{$domain}", self::TTL_TAXONOMY, function () use ($buildUrl, $domain) {
            return $this->fetchAllListPages($buildUrl, "bps_subcat_{$domain}");
        });
    }

    public function getAllSubjects($domain = null, $subcat = null): array
    {
        $domain = $domain ?? $this->defaultDomain;
        $subcatKey = $subcat ?: 'all';

        $buildUrl = function ($page) use ($domain, $subcat) {
            $url = "{$this->baseUrl}/api/list/model/subject/lang/ind/domain/{$domain}/page/{$page}";

            if (!empty($subcat)) {
                $url .= "/subcat/" . urlencode($subcat);
            }

            return $url . "/key/{$this->apiKey}";
        };

        return Cache::remember("bps_subject_all_{$domain}_{$subcatKey}", self::TTL_TAXONOMY, function () use ($buildUrl, $domain, $subcatKey) {
            return $this->fetchAllListPages($buildUrl, "bps_subject_{$domain}_{$subcatKey}");
        });
    }

    public function getAllVariables($domain = null, $subject = null): array
    {
        if (empty($subject)) {
            return [];
        }

        $domain = $domain ?? $this->defaultDomain;

        $buildUrl = function ($page) use ($domain, $subject) {
            $url = "{$this->baseUrl}/api/list/model/var/lang/ind/domain/{$domain}/page/{$page}/subject/" . urlencode($subject);

            return $url . "/key/{$this->apiKey}";
        };

        return Cache::remember("bps_var_all_{$domain}_{$subject}", self::TTL_TAXONOMY, function () use ($buildUrl, $domain, $subject) {
            return $this->fetchAllListPages($buildUrl, "bps_var_{$domain}_{$subject}");
        });
    }

    public function getDynamicData($var, $th = null, $domain = null, $turvar = null, $vervar = null, $turth = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/list/model/data/lang/ind/domain/{$domain}/var/" . urlencode((string) $var);

        if (!empty($turvar)) {
            $url .= "/turvar/" . urlencode($turvar);
        }

        if (!empty($vervar)) {
            $url .= "/vervar/" . urlencode($vervar);
        }

        if ($th !== null && $th !== '') {
            $url .= "/th/" . urlencode($th);
        }

        if (!empty($turth)) {
            $url .= "/turth/" . urlencode($turth);
        }

        $url .= "/key/{$this->apiKey}";

        return $this->cachedGet('bps_data_' . md5($url), self::TTL_DATA, $url);
    }

    /**
     * Probe metadata untuk 1 variabel: daftar pilihan turunan variabel, karakteristik
     * (vervar), tahun, dan turunan tahun -- dipakai untuk membangun checkbox di query
     * builder. Di-cache 6 jam (sama seperti taksonomi lain).
     */
    public function getVariableFilterOptions($var, $domain = null): array
    {
        $domain = $domain ?? $this->defaultDomain;
        $cacheKey = "bps_data_meta_{$domain}_{$var}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $meta = $this->getDynamicData($var, null, $domain);

        // $meta kosong berarti REQUEST ke API BPS yang gagal (timeout/limit/error),
        // beda dengan BPS yang berhasil dihubungi tapi bilang datanya tidak ada.
        // Kedua kondisi ini perlu dibedakan supaya pesan ke pengguna tidak
        // menyesatkan, dan hasil gagal-hubungi ini sengaja TIDAK di-cache supaya
        // percobaan berikutnya (klik ulang) langsung retry, bukan "terkunci"
        // selama TTL taksonomi.
        if (empty($meta)) {
            return [
                'available'   => false,
                'reachable'   => false,
                'var'         => null,
                'turvar'      => [],
                'labelvervar' => 'Karakteristik',
                'vervar'      => [],
                'tahun'       => [],
                'turtahun'    => [],
            ];
        }

        $result = [
            'available'   => ($meta['data-availability'] ?? null) === 'available',
            'reachable'   => true,
            'var'         => $meta['var'][0] ?? null,
            'turvar'      => $meta['turvar'] ?? [],
            'labelvervar' => $meta['labelvervar'] ?? 'Karakteristik',
            'vervar'      => $meta['vervar'] ?? [],
            'tahun'       => $meta['tahun'] ?? [],
            'turtahun'    => $meta['turtahun'] ?? [],
        ];

        Cache::put($cacheKey, $result, self::TTL_TAXONOMY);

        return $result;
    }
}
