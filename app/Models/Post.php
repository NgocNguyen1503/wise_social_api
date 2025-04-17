<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    const LIKE = 1;
    const UN_LIKE = 0;

    protected $table = "posts";

    protected $fillable = [
        "user_id",
        "content",
        "timeline_orders",
        "view_count",
        "images"
    ];

    public function comments()
    {
        return $this->hasMany(
            "\App\Models\Comment",
            "post_id",
            "id"
        );
    }

    public function likes()
    {
        return $this->hasMany(
            "\App\Models\Like",
            "post_id",
            "id"
        );
    }

    public function author()
    {
        return $this->hasOne(
            "\App\Models\User",
            "id",
            "user_id"
        );
    }

    public function favorites()
    {
        return $this->hasMany(
            '\App\Models\Favorite',
            'post_id',
            'id'
        );
    }
}
