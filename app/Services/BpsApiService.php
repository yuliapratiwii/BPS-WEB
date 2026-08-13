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

    public function __construct()
    {
        $rawBaseUrl = config('services.bps.base', env('BPS_API_BASE', 'https://webapi.bps.go.id/v1/'));

        $this->baseUrl = rtrim($rawBaseUrl, '/');
        $this->apiKey = config('services.bps.key', env('BPS_API_KEY'));
        $this->defaultDomain = config('services.bps.domain', env('BPS_DOMAIN_DEFAULT', '1674'));
    }


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

        $response = Http::get($url);


        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }

    private function fetchPagesInParallel(string $domain, string $keyword, array $pages, string $year = ''): array
    {
        if (empty($pages)) {
            return [];
        }

        $buildUrl = function (int $page) use ($domain, $keyword, $year) {
            $url = "{$this->baseUrl}/api/list/model/publication/lang/ind/domain/{$domain}/key/{$this->apiKey}/page/{$page}";

            if (!empty($keyword)) {
                $url .= "/keyword/" . urlencode($keyword);
            }

            if (!empty($year)) {
                $url .= "/year/" . urlencode($year);
            }

            return $url;
        };

        $responses = Http::pool(function ($pool) use ($pages, $buildUrl) {
            foreach ($pages as $page) {
                $pool->as((string) $page)->timeout(10)->get($buildUrl($page));
            }
        });

        $results = [];
        foreach ($pages as $page) {
            $response = $responses[(string) $page] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $results[$page] = $response->json();
            }
        }

        return $results;
    }

    private function splitKeywords(string $keyword): array
    {
        $parts = preg_split('/\s+/u', trim($keyword)) ?: [];
        $parts = array_filter($parts, fn ($w) => $w !== '');

        $seen = [];
        $words = [];
        foreach ($parts as $word) {
            $lower = mb_strtolower($word);
            if (! isset($seen[$lower])) {
                $seen[$lower] = true;
                $words[] = $word;
            }
        }

        return $words;
    }

    private function filterItemsContainingAllWords(array $items, array $words): array
    {
        $words = array_map(fn ($w) => mb_strtolower($w), $words);

        return array_values(array_filter($items, function ($item) use ($words) {
            $title = mb_strtolower($item['title'] ?? '');

            foreach ($words as $word) {
                if (! str_contains($title, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function fetchAllPublicationsForKeyword(string $keyword, string $domain, string $year, int $maxPages): array
    {
        $cacheKey = 'bps_pub_search_' . $domain . '_' . md5(mb_strtolower(trim($keyword))) . '_' . ($year ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($domain, $keyword, $maxPages, $year) {
            $first = $this->getPublications($domain, 1, $keyword, $year);

            $items = [];
            if (isset($first['data'][1]) && is_array($first['data'][1])) {
                $items = $first['data'][1];
            }

            $totalPages = isset($first['data'][0]['pages']) ? (int) $first['data'][0]['pages'] : 1;
            $lastPage   = min($totalPages, $maxPages);

            if ($lastPage >= 2) {
                foreach ($this->fetchPagesInParallel($domain, $keyword, range(2, $lastPage), $year) as $json) {
                    if (isset($json['data'][1]) && is_array($json['data'][1])) {
                        $items = array_merge($items, $json['data'][1]);
                    }
                }
            }

            return $items;
        });
    }


    public function searchAllPublications(string $keyword, $domain = null, int $maxPages = 29, string $year = ''): array
    {
        $domain = $domain ?? $this->defaultDomain;
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $words = $this->splitKeywords($keyword);

        if (count($words) <= 1) {
            return $this->fetchAllPublicationsForKeyword($keyword, $domain, $year, $maxPages);
        }

        $cacheKey = 'bps_pub_msearch_' . $domain . '_' . md5(mb_strtolower(implode(' ', $words))) . '_' . ($year ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($words, $domain, $year, $maxPages) {
            $candidates = [];

            foreach ($words as $word) {
                foreach ($this->fetchAllPublicationsForKeyword($word, $domain, $year, $maxPages) as $item) {
                    $key = $item['id'] ?? $item['title'] ?? null;
                    if ($key !== null) {
                        $candidates[$key] = $item;
                    }
                }
            }

            return $this->filterItemsContainingAllWords($candidates, $words);
        });
    }

    public function getPublicationDetail($pubId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/model/publication/lang/ind/domain/{$domain}/id/{$pubId}/key/{$this->apiKey}/";

        $response = Http::get($url);

        if ($response->successful() && isset($response->json()['data'])) {
            return $response->json()['data'];
        }

        return null;
    }


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

        $response = Http::get($url);


        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }


    private function fetchPressReleasePagesInParallel(string $domain, string $keyword, array $pages, string $year = '', string $month = ''): array
    {
        if (empty($pages)) {
            return [];
        }

        $buildUrl = function (int $page) use ($domain, $keyword, $year, $month) {
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

            return $url;
        };

        $responses = Http::pool(function ($pool) use ($pages, $buildUrl) {
            foreach ($pages as $page) {
                $pool->as((string) $page)->timeout(10)->get($buildUrl($page));
            }
        });

        $results = [];
        foreach ($pages as $page) {
            $response = $responses[(string) $page] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $results[$page] = $response->json();
            }
        }

        return $results;
    }



    private function fetchAllPressReleasesForKeyword(string $keyword, string $domain, string $year, string $month, int $maxPages): array
    {
        $cacheKey = 'bps_brs_search_' . $domain . '_' . md5(mb_strtolower(trim($keyword))) . '_' . ($year ?: 'all') . '_' . ($month ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($domain, $keyword, $maxPages, $year, $month) {
            $first = $this->getPressReleases($domain, 1, $keyword, $year, $month);

            $items = [];
            if (isset($first['data'][1]) && is_array($first['data'][1])) {
                $items = $first['data'][1];
            }

            $totalPages = isset($first['data'][0]['pages']) ? (int) $first['data'][0]['pages'] : 1;
            $lastPage   = min($totalPages, $maxPages);

            if ($lastPage >= 2) {
                foreach ($this->fetchPressReleasePagesInParallel($domain, $keyword, range(2, $lastPage), $year, $month) as $json) {
                    if (isset($json['data'][1]) && is_array($json['data'][1])) {
                        $items = array_merge($items, $json['data'][1]);
                    }
                }
            }

            return $items;
        });
    }


    public function searchAllPressReleases(string $keyword, $domain = null, int $maxPages = 152, string $year = '', string $month = ''): array
    {
        $domain = $domain ?? $this->defaultDomain;
        $keyword = trim($keyword);

        if ($keyword === '') {
            return [];
        }

        $words = $this->splitKeywords($keyword);

        if (count($words) <= 1) {
            return $this->fetchAllPressReleasesForKeyword($keyword, $domain, $year, $month, $maxPages);
        }

        $cacheKey = 'bps_brs_msearch_' . $domain . '_' . md5(mb_strtolower(implode(' ', $words))) . '_' . ($year ?: 'all') . '_' . ($month ?: 'all');

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($words, $domain, $year, $month, $maxPages) {
            $candidates = [];

            foreach ($words as $word) {
                foreach ($this->fetchAllPressReleasesForKeyword($word, $domain, $year, $month, $maxPages) as $item) {
                    $key = $item['id'] ?? $item['title'] ?? null;
                    if ($key !== null) {
                        $candidates[$key] = $item;
                    }
                }
            }

            return $this->filterItemsContainingAllWords($candidates, $words);
        });
    }


    public function getPressReleaseDetail($brsId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/model/pressrelease/lang/ind/domain/{$domain}/id/{$brsId}/key/{$this->apiKey}/";

        $response = Http::get($url);

        if ($response->successful() && isset($response->json()['data'])) {
            return $response->json()['data'];
        }

        return null;
    }


    //Static
     public function getStaticTables($domain = null, $page = 1, $keyword = '')
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/list/model/statictable/domain/{$domain}/lang/ind/page/{$page}";

        if (!empty($keyword)) {
            $url .= "/keyword/" . urlencode($keyword);
        }

        $url .= "/key/{$this->apiKey}";

        $response = Http::get($url);

        if ($response->successful()) {
            return $response->json();
        }

    return [];
    }

    public function getStaticTableDetail($tableId, $domain = null)
    {
        $domain = $domain ?? $this->defaultDomain;

        $url = "{$this->baseUrl}/api/view/domain/{$domain}/model/statictable/lang/ind/id/{$tableId}/key/{$this->apiKey}/";

        $response = Http::get($url);

        if ($response->successful() && isset($response->json()['data'])) {
            $data = $response->json()['data'];

            if (isset($data['table'])) {
                $data['table'] = html_entity_decode($data['table']);
            }

            return $data;
        }
    }
}
