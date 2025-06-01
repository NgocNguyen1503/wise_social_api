<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    use HasFactory;

    private $table = "chat_room";
    private $fillable = ['id', 'user_id'];
    public function massages()
    {
        return $this->hasMany('App\'Models\Message', 'user_id', 'room_id');
    }
}
