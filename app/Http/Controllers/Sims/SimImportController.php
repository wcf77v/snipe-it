<?php

namespace App\Http\Controllers\Sims;

use App\Actions\Sims\ImportSimsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSimsRequest;
use App\Models\Sim;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import of SIM cards, with a dry-run "check" mode. Export is the SIM
 * list's regular table export (CSV/XLSX/...), whose files this importer
 * accepts back.
 */
class SimImportController extends Controller
{
    public function create(): View
    {
        if (! Gate::any(['create', 'update'], Sim::class)) {
            abort(403);
        }

        return view('sims/import', ['result' => null, 'columns' => ImportSimsAction::columns()]);
    }

    public function store(ImportSimsRequest $request): View
    {
        $result = ImportSimsAction::run(
            $request->file('file'),
            $request->input('mode') !== 'import',
            $request->boolean('create_missing'),
        );

        return view('sims/import', ['result' => $result, 'columns' => ImportSimsAction::columns()]);
    }

    /**
     * An empty CSV with the canonical headers plus one example row.
     */
    public function template(): StreamedResponse
    {
        if (! Gate::any(['create', 'update'], Sim::class)) {
            abort(403);
        }

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ImportSimsAction::columns(), ';', '"', '');
            fputcsv($out, ['+375 29 123-45-67', 'BY', '8937501234567890123', 'A1', 'Business 10GB', 'ivanov', 'IT', '', 'physical', '', '', '', '', '2026-01-15', '', ''], ';', '"', '');
            fclose($out);
        }, 'sims-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
