<?php

namespace App\Http\Controllers;

use App\Services\BpsApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DinamisController extends Controller
{
    protected BpsApiService $bpsApi;

    public function __construct(BpsApiService $bpsApi)
    {
        $this->bpsApi = $bpsApi;
    }


   
    public function index(Request $request): View
    {
        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');

        $subjectCategories = $this->bpsApi->getAllSubjectCategories($domain);

        return view('dinamis.index', [
            'subjectCategories' => $subjectCategories,
            'domain' => $domain,
        ]);
    }


    public function subjects(Request $request): JsonResponse
    {
        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');
        $subcat = (string) $request->input('subcat', '0');

        $subjects = $this->bpsApi->getAllSubjects($domain, $subcat === '0' ? null : $subcat);

        return response()->json([
            'subjects' => array_map(fn ($s) => [
                'id'    => $s['sub_id'] ?? $s['val'] ?? null,
                'title' => $s['title'] ?? $s['label'] ?? '-',
            ], $subjects),
        ]);
    }


    public function variables(Request $request): JsonResponse
    {
        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');
        $subject = (string) $request->input('subject', '');

        if ($subject === '' || $subject === '0') {
            return response()->json(['variables' => []]);
        }

        $variables = $this->bpsApi->getAllVariables($domain, $subject);

        return response()->json([
            'variables' => array_map(fn ($v) => [
                'id'    => $v['var_id'] ?? $v['val'] ?? null,
                'title' => $v['title'] ?? $v['label'] ?? '-',
            ], $variables),
        ]);
    }

    public function filterOptions(Request $request): JsonResponse
    {
        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');
        $varId = (string) $request->input('var', '');

        if ($varId === '' || $varId === '0') {
            return response()->json(['message' => 'Pilih 1 Tabel/Indikator.'], 422);
        }

        $options = $this->bpsApi->getVariableFilterOptions($varId, $domain);

        if (! $options['available']) {
            $reachable = $options['reachable'] ?? true;

            return response()->json([
                'available' => false,
                'reachable' => $reachable,
                'message' => $reachable
                    ? 'Data untuk Tabel/Indikator yang dipilih tidak tersedia dari API BPS saat ini.'
                    : 'Gagal menghubungi API BPS (kemungkinan koneksi lambat/timeout). Silakan coba lagi.',
            ]);
        }

        return response()->json([
            'available'   => true,
            'tahun'       => $options['tahun'],
            'turtahun'    => $options['turtahun'],
            'vervar'      => $options['vervar'],
            'labelvervar' => $options['labelvervar'] ?? 'Karakteristik',
            'turvar'      => count($options['turvar']) > 1 ? $options['turvar'] : [],
        ]);
    }

    public function runQuery(Request $request): JsonResponse
    {
        $payload = $this->validateQuery($request);

        if (isset($payload['error'])) {
            return response()->json(['message' => $payload['error']], 422);
        }

        return response()->json($this->buildTable($payload));
    }


    public function export(Request $request): Response
    {
        $payload = $this->validateQuery($request);

        if (isset($payload['error'])) {
            abort(422, $payload['error']);
        }

        $table = $this->buildTable($payload);
        $filename = 'data-dinamis-bps-' . $payload['var'] . '-' . now()->format('Ymd-His') . '.csv';

        $callback = function () use ($table) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_merge([$table['row_label'] ?? 'Karakteristik'], $table['columns']));

            foreach ($table['rows'] as $row) {
                fputcsv($handle, array_merge([$row['label']], $row['values']));
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }


    private function validateQuery(Request $request): array
    {
        $domain = $request->input('domain') ?? env('BPS_DOMAIN_DEFAULT', '1674');
        $varId = (string) $request->input('var', '');
        $tahun = $this->sanitizeIds($request->input('tahun', []));
        $vervar = $this->sanitizeIds($request->input('vervar', []));
        $turth = $this->sanitizeIds($request->input('turth', []));
        $turvar = $this->sanitizeIds($request->input('turvar', []));

        if ($varId === '' || $varId === '0') {
            return ['error' => 'Pilih 1 Tabel/Indikator sebelum menjalankan query.'];
        }

        if (empty($tahun)) {
            return ['error' => 'Pilih minimal 1 tahun sebelum menjalankan query.'];
        }

        return [
            'domain' => $domain,
            'var' => $varId,
            'tahun' => $tahun,
            'vervar' => $vervar,
            'turth' => $turth,
            'turvar' => $turvar,
        ];
    }

    private function buildTable(array $payload): array
    {
        $response = $this->bpsApi->getDynamicData(
            $payload['var'],
            implode(';', $payload['tahun']),
            $payload['domain'],
            !empty($payload['turvar']) ? implode(';', $payload['turvar']) : null,
            !empty($payload['vervar']) ? implode(';', $payload['vervar']) : null,
            !empty($payload['turth']) ? implode(';', $payload['turth']) : null
        );

        return $this->buildPivotTable($response, $payload['var'], [
            'tahun' => $payload['tahun'],
            'vervar' => $payload['vervar'],
            'turvar' => $payload['turvar'],
            'turth' => $payload['turth'],
        ]);
    }

    private function buildPivotTable(array $response, string $var, array $selected): array
    {
        $datacontent = $response['datacontent'] ?? [];
        $labelVervar = $response['labelvervar'] ?? 'Karakteristik';

        $vervarOptions = $this->indexOptionsByVal($response['vervar'] ?? []);
        $turvarOptions = $this->indexOptionsByVal($response['turvar'] ?? []);
        $tahunOptions = $this->indexOptionsByVal($response['tahun'] ?? []);
        $turthOptions = $this->indexOptionsByVal($response['turtahun'] ?? []);

        $selectedVervar = !empty($selected['vervar']) ? $selected['vervar'] : array_keys($vervarOptions);
        $selectedTurvar = !empty($selected['turvar']) ? $selected['turvar'] : (array_keys($turvarOptions) ?: [null]);
        $selectedTahun = $selected['tahun'];
        $selectedTurth = !empty($selected['turth']) ? $selected['turth'] : (array_keys($turthOptions) ?: [null]);

        if (empty($selectedVervar)) {
            $selectedVervar = [null];
        }

        $columns = [];
        $columnKeys = [];
        foreach ($selectedTahun as $th) {
            foreach ($selectedTurth as $turth) {
                $label = $tahunOptions[$th]['label'] ?? (string) $th;
                if ($turth !== null && isset($turthOptions[$turth]) && ($turthOptions[$turth]['label'] ?? '') !== 'Tahun') {
                    $label .= ' - ' . $turthOptions[$turth]['label'];
                }
                $columns[] = $label;
                $columnKeys[] = [$th, $turth];
            }
        }

        $rows = [];
        foreach ($selectedVervar as $vervar) {
            foreach ($selectedTurvar as $turvar) {
                $labelParts = [];
                if ($vervar !== null) {
                    $labelParts[] = $vervarOptions[$vervar]['label'] ?? (string) $vervar;
                }
                if ($turvar !== null && isset($turvarOptions[$turvar])) {
                    $labelParts[] = $turvarOptions[$turvar]['label'];
                }
                $label = !empty($labelParts) ? implode(' - ', $labelParts) : 'Nilai';

                $values = [];
                foreach ($columnKeys as [$th, $turth]) {
                    $key = ($vervar ?? '') . $var . ($turvar ?? '0') . ($th ?? '') . ($turth ?? '0');
                    $values[] = $datacontent[$key] ?? null;
                }

                $rows[] = ['label' => $label, 'values' => $values];
            }
        }

        return [
            'row_label' => $labelVervar,
            'var_title' => $response['var'][0]['label'] ?? ('Variabel ' . $var),
            'unit' => $response['var'][0]['unit'] ?? null,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function sanitizeIds($raw): array
    {
        $values = array_filter((array) $raw, fn ($v) => $v !== '' && $v !== null);
        $values = array_map('strval', $values);

        return array_values(array_unique($values));
    }

    /**
     * @param  array<int,array<string,mixed>>
     * @return array<string,array<string,mixed>>
     */
    private function indexOptionsByVal(array $options): array
    {
        $indexed = [];

        foreach ($options as $opt) {
            if (isset($opt['val'])) {
                $indexed[(string) $opt['val']] = $opt;
            }
        }

        return $indexed;
    }
}
