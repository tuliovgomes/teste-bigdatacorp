<?php

namespace App\Exports;

use App\Models\Club;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClubsExport implements FromQuery, WithChunkReading, WithCustomCsvSettings, WithHeadings, WithMapping
{
    use Exportable;

    public const ALLOWED_CHAMPIONSHIPS = ['SERIE A', 'SERIE B'];

    public const ALL_CHAMPIONSHIPS = 'all';

    public function __construct(private readonly string $championship) {}

    public function query(): Builder
    {
        return Club::query()
            ->when(
                $this->championship === self::ALL_CHAMPIONSHIPS,
                fn ($query) => $query->whereIn('championship', self::ALLOWED_CHAMPIONSHIPS),
                fn ($query) => $query->where('championship', $this->championship),
            )
            ->orderBy('id');
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function headings(): array
    {
        return [
            'Id do Clube', 'Nome', 'Campeonato', 'Data de Fundação', 'Cidade',
            'Estado', 'País', 'Estádio', 'Presidente', 'Apelido', 'Cores',
        ];
    }

    public function map($club): array
    {
        return [
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
            is_array($club->colors) ? implode('|', array_map('strval', $club->colors)) : '',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'excel_compatibility' => true,
        ];
    }
}
