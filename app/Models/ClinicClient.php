<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organization',
        'phone',
        'email',
        'notes',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(ClinicAppointment::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('organization', 'like', "%{$term}%"));
    }

    /**
     * Distinct organisations already on file, for the free-text autocomplete.
     */
    public static function organizations(): array
    {
        return static::query()
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->distinct()
            ->orderBy('organization')
            ->pluck('organization')
            ->all();
    }
}
