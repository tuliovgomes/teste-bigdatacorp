<?php

namespace App\Http\Controllers;

use App\Exports\ClubsExport;
use App\Exports\PlayersExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ExportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'in:clubs,players'],
            'championship' => ['required', 'in:'.implode(',', [...ClubsExport::ALLOWED_CHAMPIONSHIPS, ClubsExport::ALL_CHAMPIONSHIPS])],
        ]);

        return $data['file'] === 'clubs'
            ? (new ClubsExport($data['championship']))->download('clubs.csv', ExcelFormat::CSV)
            : (new PlayersExport($data['championship']))->download('players.csv', ExcelFormat::CSV);
    }
}
