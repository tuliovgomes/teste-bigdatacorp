<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    /** @use HasFactory<\Database\Factories\ClubFactory> */
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'championship',
        'founding_date',
        'city',
        'state',
        'country',
        'stadium',
        'president',
        'nickname',
        'colors',
        'titles',
    ];

    protected function casts(): array
    {
        return [
            'founding_date' => 'date',
            'colors' => 'array',
            'titles' => 'integer',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}
