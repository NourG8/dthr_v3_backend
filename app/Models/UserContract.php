<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserContract extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function contract()
    {
        return $this->hasOne(Contract::class,'id','contract_id')->where('is_deleted', false);
    }
}
