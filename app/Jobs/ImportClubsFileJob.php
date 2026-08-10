<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportClubsFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_CLUBS_PER_CHUNK = 50;
    private const MAX_PLAYERS_PER_CHUNK = 2000;

    public int $tries = 1;

    public function __construct(private readonly string $path)
    {
    }

    public function handle(): void
    {
        $stream = Storage::readStream($this->path);

        if ($stream === null) {
            Log::error('Import file not found.', ['path' => $this->path]);

            return;
        }

        $clubBuffer = [];
        $playersInBuffer = 0;
        $dispatchedChunks = 0;
        $skippedLines = 0;

        while (($line = fgets($stream)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $club = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $skippedLines++;
                Log::warning('Skipping invalid JSON line while importing clubs.', [
                    'path' => $this->path,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (! is_array($club)) {
                $skippedLines++;
                Log::warning('Skipping JSONL line that is not an object.', ['path' => $this->path]);

                continue;
            }

            $clubBuffer[] = $club;
            $playersInBuffer += is_array($club['players'] ?? null) ? count($club['players']) : 0;

            if (count($clubBuffer) >= self::MAX_CLUBS_PER_CHUNK || $playersInBuffer >= self::MAX_PLAYERS_PER_CHUNK) {
                ImportClubsChunkJob::dispatch($clubBuffer);
                $dispatchedChunks++;
                $clubBuffer = [];
                $playersInBuffer = 0;
            }
        }

        fclose($stream);

        if (! empty($clubBuffer)) {
            ImportClubsChunkJob::dispatch($clubBuffer);
            $dispatchedChunks++;
        }

        Storage::delete($this->path);

        Log::info('Finished reading clubs import file.', [
            'path' => $this->path,
            'chunks_dispatched' => $dispatchedChunks,
            'lines_skipped' => $skippedLines,
        ]);
    }
}
