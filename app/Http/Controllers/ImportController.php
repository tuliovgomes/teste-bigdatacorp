<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportClubsRequest;
use App\Jobs\ImportClubsFileJob;

class ImportController extends Controller
{
    public function index(ImportClubsRequest $request)
    {
        $path = $request->file('file')->store('imports');

        ImportClubsFileJob::dispatch($path);

        return back()->with('success', 'Importação iniciada. Os clubes e jogadores serão processados em segundo plano.');
    }
}
