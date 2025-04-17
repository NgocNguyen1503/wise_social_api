<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Friend extends Model
{
    const APPROVED = 2;
    const UN_APPROVED = 1;

    use HasFactory;

    protected $table = "friends";

    protected $fillable = [
        "user_id", "friend_id", "approved"
    ];
}
