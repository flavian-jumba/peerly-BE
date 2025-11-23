<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'bio',
        'icon',
        'owner_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the users that belong to the group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * Get the owner of the group.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the messages for the group.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    /**
     * Get the reports for the group.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
