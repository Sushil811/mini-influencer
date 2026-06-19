<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileSnapshot extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\ProfileSnapshotFactory> */
    use HasFactory;

    // Time-series snapshots are insert-only, so we disable the updated_at column
    const UPDATED_AT = null;

    protected $fillable = [
        'profile_id',
        'followers_count',
        'following_count',
        'posts_count',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the profile that owns the snapshot.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
