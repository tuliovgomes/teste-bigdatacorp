<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportClubsRequest;
use App\Jobs\ImportClubsFileJob;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index(ImportClubsRequest $request)
    {
        $path = $request->file('file')->store('imports');

        ImportClubsFileJob::dispatch($path);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Importação iniciada. Os clubes e jogadores serão processados em segundo plano.',
        ]);

        return back();
    }
}
