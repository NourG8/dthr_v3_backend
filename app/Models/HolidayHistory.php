<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HolidayHistory extends Model
{
    use HasFactory , SoftDeletes;

    protected $guarded = [];

    public function holiday()
    {
        return $this->belongsTo(Holiday::class);
    }
}
