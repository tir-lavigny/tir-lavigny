<?php

namespace App\Models;

use App\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'address', 'latitude', 'longitude'];

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
