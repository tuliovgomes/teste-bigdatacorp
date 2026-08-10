<?php

namespace App\Jobs;

use App\Jobs\Concerns\SanitizesImportData;
use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportPlayersChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SanitizesImportData;

    private const PLAYER_UPDATE_COLUMNS = [
        'name', 'age', 'goals', 'debut_date', 'position', 'shirt_number',
        'nationality', 'market_value', 'club_id', 'updated_at',
    ];

    public int $tries = 3;

    /**
     * @param array<int, array<string, mixed>> $players raw player records decoded from JSONL, each already carrying a resolved integer club_id
     */
    public function __construct(private readonly array $players)
    {
    }

    public function handle(): void
    {
        if (empty($this->players)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($this->players as $player) {
            if (! is_array($player)) {
                Log::warning('Skipping non-object player record.');

                continue;
            }

            $playerId = $this->nullableStringValue($player['player_id'] ?? null);

            if ($playerId === null || $playerId === '') {
                Log::warning('Skipping player without a valid player_id.');

                continue;
            }

            $debutDate = $this->dateValue($player['debut_date'] ?? null);

            if ($debutDate === null) {
                Log::warning('Skipping player with missing/invalid debut_date.', ['player_id' => $playerId]);

                continue;
            }

            $rows[$playerId] = [
                'player_id' => $playerId,
                'name' => $this->stringValue($player['name'] ?? null),
                'age' => $this->intValue($player['age'] ?? null),
                'goals' => $this->intValue($player['goals'] ?? null),
                'debut_date' => $debutDate,
                'position' => $this->stringValue($player['position'] ?? null),
                'shirt_number' => $this->intValue($player['shirt_number'] ?? null),
                'nationality' => $this->stringValue($player['nationality'] ?? null),
                'market_value' => $this->intValue($player['market_value'] ?? null),
                'club_id' => $this->intValue($player['club_id'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($rows)) {
            return;
        }

        $this->upsertPlayers(array_values($rows));
    }

    /**
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertPlayers(array $rows): void
    {
        try {
            Player::upsert($rows, ['player_id'], self::PLAYER_UPDATE_COLUMNS);

            return;
        } catch (Throwable $e) {
            Log::warning('Bulk player upsert failed, retrying rows individually.', ['error' => $e->getMessage()]);
        }

        foreach ($rows as $row) {
            try {
                Player::upsert([$row], ['player_id'], self::PLAYER_UPDATE_COLUMNS);
            } catch (Throwable $e) {
                Log::warning('Skipping player that failed to upsert.', [
                    'player_id' => $row['player_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
