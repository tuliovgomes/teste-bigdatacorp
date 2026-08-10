<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $fillable = [
        'player_id',
        'name',
        'age',
        'goals',
        'debut_date',
        'position',
        'shirt_number',
        'nationality',
        'market_value',
        'club_id',
    ];

    protected function casts(): array
    {
        return [
            'debut_date' => 'date',
            'age' => 'integer',
            'goals' => 'integer',
            'shirt_number' => 'integer',
            'market_value' => 'integer',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
