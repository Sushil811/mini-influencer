<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'platform',
        'followers_count',
        'following_count',
        'posts_count',
        'bio',
        'profile_picture_url',
        'status',
        'error_message',
        'last_refreshed_at',
    ];

    protected $casts = [
        'last_refreshed_at' => 'datetime',
    ];

    /**
     * Get the historical snapshots for the profile.
     *
     * @return HasMany<ProfileSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class);
    }
}
