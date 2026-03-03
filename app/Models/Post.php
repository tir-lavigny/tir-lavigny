<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'title',
        'content',
        'publish_at',
        'expire_at',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
        'expire_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($post) {
            $post->public_id = Str::uuid7();
        });
    }

    protected function getActivityKey(): mixed
    {
        return $this->public_id;
    }
}
