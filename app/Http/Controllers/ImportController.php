<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\CropCycle;
use App\Services\Import\ImportService;
use App\Services\Rules\RecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * The deposit screen, and the report of the run that just finished.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('imports/index', [
            'colonnes' => ImportService::COLUMNS,
            'separateur' => ImportService::SEPARATOR,
            'rapport' => $request->session()->get('rapport'),
        ]);
    }

    /**
     * Walk the deposited file and report line by line. The run never
     * stops at the first faulty line (SPEC section 11.3).
     */
    public function store(
        StoreImportRequest $request,
        ImportService $importer,
        RecommendationService $engine,
    ): RedirectResponse {
        $report = $importer->import($request->file('fichier')->getRealPath());

        // Les indicateurs sont recalculés une seule fois par cycle touché,
        // et non à chaque ligne du fichier.
        foreach (CropCycle::whereKey($report['touched_cycles'])->get() as $cycle) {
            $engine->runFor($cycle);
        }

        return to_route('imports.index')->with('rapport', $report);
    }
}
