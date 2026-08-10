<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExportController extends Controller
{
    private const CHUNK_SIZE = 1000;

    private const ALLOWED_CHAMPIONSHIPS = ['Série A', 'Série B'];

    public function index(Request $request): StreamedResponse
    {
        $file = $request->validate([
            'file' => ['required', 'in:clubs,players'],
        ])['file'];

        return $file === 'clubs' ? $this->exportClubs() : $this->exportPlayers();
    }

    private function exportClubs(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = $this->openOutputStream();

            fputcsv($handle, [
                'Id do Clube', 'Nome', 'Campeonato', 'Data de Fundação', 'Cidade',
                'Estado', 'País', 'Estádio', 'Presidente', 'Apelido', 'Cores',
            ], ',', '"', '');

            Club::query()
                ->whereIn('championship', self::ALLOWED_CHAMPIONSHIPS)
                ->lazyById(self::CHUNK_SIZE)
                ->each(function (Club $club) use ($handle) {
                    try {
                        fputcsv($handle, [
                            $club->club_id,
                            $club->name,
                            $club->championship,
                            $club->founding_date?->format('Y-m-d') ?? '',
                            $club->city,
                            $club->state,
                            $club->country,
                            $club->stadium,
                            $club->president,
                            $club->nickname ?? '',
                            $this->formatColors($club->colors),
                        ], ',', '"', '');
                    } catch (Throwable $e) {
                        Log::warning('Skipping club row during export.', [
                            'club_id' => $club->club_id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });

            fclose($handle);
        }, 'clubs.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPlayers(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = $this->openOutputStream();

            fputcsv($handle, [
                'Id do Clube', 'Id do Jogador', 'Nome', 'Idade', 'Gols',
                'Data de Estreia', 'Posição', 'Número da Camisa',
            ], ',', '"', '');

            Player::query()
                ->select('players.*', 'clubs.club_id as club_business_id')
                ->join('clubs', 'clubs.id', '=', 'players.club_id')
                ->whereIn('clubs.championship', self::ALLOWED_CHAMPIONSHIPS)
                ->lazyById(self::CHUNK_SIZE, 'players.id', 'id')
                ->each(function (Player $player) use ($handle) {
                    try {
                        fputcsv($handle, [
                            $player->getAttribute('club_business_id'),
                            $player->player_id,
                            $player->name,
                            $player->age,
                            $player->goals,
                            $player->debut_date?->format('Y-m-d') ?? '',
                            $player->position,
                            $player->shirt_number,
                        ], ',', '"', '');
                    } catch (Throwable $e) {
                        Log::warning('Skipping player row during export.', [
                            'player_id' => $player->player_id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                });

            fclose($handle);
        }, 'players.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function formatColors(mixed $colors): string
    {
        return is_array($colors) ? implode('|', array_map('strval', $colors)) : '';
    }

    /**
     * @return resource
     */
    private function openOutputStream()
    {
        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            throw new RuntimeException('Unable to open output stream.');
        }

        return $handle;
    }
}
