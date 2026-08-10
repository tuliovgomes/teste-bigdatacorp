<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Player;

class DashBoardController extends Controller
{
    public function index()
    {
        $clubsByChampionship = Club::query()
            ->selectRaw('championship, count(*) as total')
            ->groupBy('championship')
            ->orderByDesc('total')
            ->get();

        $clubs = Club::query()
            ->withCount('players')
            ->with(['players' => function ($query) {
                $query->select('id', 'club_id', 'name', 'position', 'shirt_number', 'goals')
                    ->orderByDesc('goals');
            }])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return inertia('Dashboard', [
            'stats' => [
                'totalClubs' => Club::count(),
                'totalPlayers' => Player::count(),
            ],
            'clubsByChampionship' => $clubsByChampionship,
            'clubs' => $clubs,
        ]);
    }
}
