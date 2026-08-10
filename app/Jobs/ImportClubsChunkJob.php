<?php

namespace App\Jobs;

use App\Jobs\Concerns\SanitizesImportData;
use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportClubsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SanitizesImportData;

    private const PLAYERS_PER_CHUNK = 500;

    private const CLUB_UPDATE_COLUMNS = [
        'name', 'championship', 'founding_date', 'city', 'state', 'country',
        'stadium', 'president', 'nickname', 'colors', 'titles', 'updated_at',
    ];

    public int $tries = 3;

    /**
     * @param array<int, array<string, mixed>> $clubs raw club records decoded from JSONL, each optionally carrying a "players" array
     */
    public function __construct(private readonly array $clubs)
    {
    }

    public function handle(): void
    {
        $now = now();

        $clubRows = [];
        $playersByClubId = [];

        foreach ($this->clubs as $club) {
            if (! is_array($club)) {
                Log::warning('Skipping non-object club record.');

                continue;
            }

            $clubId = $this->nullableStringValue($club['club_id'] ?? null);

            if ($clubId === null || $clubId === '') {
                Log::warning('Skipping club without a valid club_id.');

                continue;
            }

            $foundingDate = $this->dateValue($club['founding_date'] ?? null);

            if ($foundingDate === null) {
                Log::warning('Skipping club with missing/invalid founding_date.', ['club_id' => $clubId]);

                continue;
            }

            $colors = $club['colors'] ?? [];

            $clubRows[$clubId] = [
                'club_id' => $clubId,
                'name' => $this->stringValue($club['name'] ?? null),
                'championship' => $this->stringValue($club['championship'] ?? null),
                'founding_date' => $foundingDate,
                'city' => $this->stringValue($club['city'] ?? null),
                'state' => $this->stringValue($club['state'] ?? null),
                'country' => $this->stringValue($club['country'] ?? null),
                'stadium' => $this->stringValue($club['stadium'] ?? null),
                'president' => $this->stringValue($club['president'] ?? null),
                'nickname' => $this->nullableStringValue($club['nickname'] ?? null),
                'colors' => json_encode(is_array($colors) ? $colors : []),
                'titles' => $this->intValue($club['titles'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (is_array($club['players'] ?? null) && ! empty($club['players'])) {
                $playersByClubId[$clubId] = $club['players'];
            }
        }

        if (empty($clubRows)) {
            return;
        }

        $this->upsertClubs(array_values($clubRows));

        if (! empty($playersByClubId)) {
            $this->dispatchPlayerChunks($playersByClubId);
        }
    }

    /**
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertClubs(array $rows): void
    {
        try {
            Club::upsert($rows, ['club_id'], self::CLUB_UPDATE_COLUMNS);

            return;
        } catch (Throwable $e) {
            Log::warning('Bulk club upsert failed, retrying rows individually.', ['error' => $e->getMessage()]);
        }

        foreach ($rows as $row) {
            try {
                Club::upsert([$row], ['club_id'], self::CLUB_UPDATE_COLUMNS);
            } catch (Throwable $e) {
                Log::warning('Skipping club that failed to upsert.', [
                    'club_id' => $row['club_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     *
     * @param array<string, array<int, array<string, mixed>>> $playersByClubId keyed by the club's business club_id
     */
    private function dispatchPlayerChunks(array $playersByClubId): void
    {
        $clubIdMap = Club::whereIn('club_id', array_keys($playersByClubId))
            ->pluck('id', 'club_id');

        $playerRows = [];

        foreach ($playersByClubId as $clubBusinessId => $players) {
            $clubId = $clubIdMap[$clubBusinessId] ?? null;

            if ($clubId === null) {
                Log::warning('Skipping players for club that failed to upsert.', ['club_id' => $clubBusinessId]);

                continue;
            }

            foreach ($players as $player) {
                if (! is_array($player)) {
                    Log::warning('Skipping non-object player record.', ['club_id' => $clubBusinessId]);

                    continue;
                }

                $player['club_id'] = $clubId;
                $playerRows[] = $player;
            }
        }

        foreach (array_chunk($playerRows, self::PLAYERS_PER_CHUNK) as $chunk) {
            ImportPlayersChunkJob::dispatch($chunk);
        }
    }
}
