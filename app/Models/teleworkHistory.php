<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class teleworkHistory extends Model
{
    use HasFactory , SoftDeletes;

    protected $guarded = [];

    
    public function telework()
    {
        return $this->belongsTo(Telework::class, 'telework_id', 'id');
    }
}
