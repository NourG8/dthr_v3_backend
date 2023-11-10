<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function contract()
    {
        return $this->hasOne(Contract::class,'id','contract_id');
    }
}
