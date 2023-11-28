<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $with = ['document'];

    protected $guarded = [];

    public function document()
    {
        return $this->hasOne(Document::class,'id','document_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,'id','user_id');
    }
}
