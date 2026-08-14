<?php

namespace App\Http\Controllers;

use App\Services\BpsApiService;
use Illuminate\Http\Request;

class DashBoardController extends Controller
{
    protected BpsApiService $bpsApi;

    public function __construct(BpsApiService $bpsApi)
    {
        $this->bpsApi = $bpsApi;
    }

    public function welcome(Request $request)
    {

        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');

        $keyword = (string) ($request->input('search') ?? '');

        $page = (int) ($request->input('page') ?? 1);
        $year = (string) ($request->input('year') ?? '');

        [$apiPublications, $currentPage, $totalPages] = $this->buildListing(
            $keyword,
            $page,
            fn ($pg) => $this->bpsApi->getPublications($domain, $pg, $keyword, $year)
        );


        $pageBrs = (int) ($request->input('page_brs') ?? 1);
        $yearBrs = (string) ($request->input('year_brs') ?? '');

        [$apiPressReleases, $currentPageBrs, $totalPagesBrs] = $this->buildListing(
            $keyword,
            $pageBrs,
            fn ($pg) => $this->bpsApi->getPressReleases($domain, $pg, $keyword, $yearBrs)
        );





        $availableYears = range(now()->year, now()->year - 9);

        return view('welcome', compact(
            'apiPublications',
            'keyword',
            'year',
            'availableYears',
            'currentPage',
            'totalPages',
            'apiPressReleases',
            'yearBrs',
            'currentPageBrs',
            'totalPagesBrs'
        ));
    }

    public function show($id)
    {
        $detail = $this->bpsApi->getPublicationDetail($id);
        return view('publications.show', compact('detail'));
    }

    public function showPressRelease($id)
    {
        $detail = $this->bpsApi->getPressReleaseDetail($id);
        return view('pressreleases.show', compact('detail'));
    }


    /**
     * Ambil 1 halaman listing langsung dari API BPS (keyword & tahun sudah
     * dibawa oleh closure $getList sendiri, jadi pencarian = 1x request saja,
     * sama seperti browsing biasa -- bukan scan puluhan halaman di server).
     * Relevansi judul hanya diurutkan untuk 10 item yang tampil di halaman
     * ini (murah), bukan untuk seluruh hasil pencarian.
     */
    private function buildListing(string $keyword, int $page, callable $getList): array
    {
        $apiData = $getList($page);

        $totalPages = 1;
        $items = [];

        if (isset($apiData['data'][0]['pages'])) {
            $totalPages = max(1, (int) $apiData['data'][0]['pages']);
        }

        if (isset($apiData['data'][1]) && is_array($apiData['data'][1])) {
            $items = $apiData['data'][1];
        }

        $currentPage = min(max(1, $page), $totalPages);

        if ($keyword !== '') {
            $items = $this->sortByTitleRelevance($items, $keyword);
        }

        return [$items, $currentPage, $totalPages];
    }


    private function sortByTitleRelevance(array $items, string $keyword): array
    {
        usort($items, function ($a, $b) use ($keyword) {
            return $this->titleRelevanceScore($a['title'] ?? '', $keyword)
                <=> $this->titleRelevanceScore($b['title'] ?? '', $keyword);
        });

        return $items;
    }

    private function titleRelevanceScore(string $title, string $keyword): int
    {
        $title   = mb_strtolower(trim($title));
        $keyword = mb_strtolower(trim($keyword));

        if ($keyword === '') {
            return 4;
        }

        if ($title === $keyword) {
            return 0;
        }

        if (str_starts_with($title, $keyword)) {
            return 1;
        }

        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/u', $title) === 1) {
            return 2;
        }

        if (str_contains($title, $keyword)) {
            return 3;
        }

        return 4;
    }
}
