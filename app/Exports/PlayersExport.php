<?php

namespace App\Exports;

use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PlayersExport implements FromQuery, WithChunkReading, WithCustomCsvSettings, WithHeadings, WithMapping
{
    use Exportable;

    public function __construct(private readonly string $championship) {}

    public function query(): Builder
    {
        return Player::query()
            ->select('players.*', 'clubs.club_id as club_business_id')
            ->join('clubs', 'clubs.id', '=', 'players.club_id')
            ->when(
                $this->championship === ClubsExport::ALL_CHAMPIONSHIPS,
                fn ($query) => $query->whereIn('clubs.championship', ClubsExport::ALLOWED_CHAMPIONSHIPS),
                fn ($query) => $query->where('clubs.championship', $this->championship),
            )
            ->orderBy('players.id');
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            'Id do Clube', 'Id do Jogador', 'Nome', 'Idade', 'Gols',
            'Data de Estreia', 'Posição', 'Número da Camisa',
        ];
    }

    public function map($player): array
    {
        return [
            $player->getAttribute('club_business_id'),
            $player->player_id,
            $player->name,
            $player->age,
            $player->goals,
            $player->debut_date?->format('Y-m-d') ?? '',
            $player->position,
            $player->shirt_number,
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'excel_compatibility' => true,
        ];
    }
}
