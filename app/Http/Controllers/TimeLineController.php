<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Post;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Experience;
use App\Models\Skill;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TimeLineController extends Controller
{
    private $apiResponse;

    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
    }

    /**
     * Controller method list post in timeline
     * Order by timeline_orders and id for DESC
     * 
     * @param \Illuminate\Http\Request $request
     * @return string|mixed String JSON for REST api
     */
    public function timeline(Request $request)
    {
        $param = $request->all();

        // Fetch posts with related author information.
        $posts = Post::with(
            [
                // Load author information (id, name, avatar).
                'author' => function ($authorQuery) {
                    return $authorQuery->select('id', 'name', 'avatar', 'email', 'location');
                },
                // Load the author's most recent experience.
                'author.experiences' => function ($authorExpQuery) {
                    return $authorExpQuery->orderBy('id', 'DESC')->get();
                },
                'author.skills' => function ($skillQuery) {
                    return $skillQuery->select('id', 'skill', 'user_id');
                },
                'likes',
                'comments',
                'favorites' => function ($favoriteQuery) {
                    return $favoriteQuery->where('user_id', Auth::user()->id)->get();
                }
            ]
        )
            // Select the necessary columns from the posts table.
            ->select(
                'posts.id',
                'posts.content',
                'posts.timeline_orders',
                'posts.view_count',
                'posts.images',
                'posts.user_id',
                'posts.created_at'
                // 'users.id as author_id',
                // 'users.name as author_name',
                // 'users.avatar as author_avatar',
                // 'users.location'

            )
            // Order the posts by timeline_orders (descending) and then by id (descending).
            ->orderBy('posts.timeline_orders', 'DESC')
            ->orderBy('posts.id', 'DESC')
            // Apply pagination using offset and limit.
            ->skip($param['offset'])
            ->take($param['limit'])
            // Retrieve the posts.
            ->get();

        if (empty($posts)) {
            return $this->apiResponse->dataNotfound();
        }
        foreach ($posts as $post) {
            $post->experiences = $post->author->experiences[0]->title ?? "";
            $post->skills = $post->author->skills;
            $post->total_like = count($post->likes);
            $post->total_comment = count($post->comments);
            $isLike = Post::UN_LIKE;
            if (count($post->likes) > 0) {
                foreach ($post->likes as $like) {
                    if (Auth::user()->id == $like->user_id) {
                        $isLike = Post::LIKE;
                        break;
                    }
                }
            }
            $post->is_like = $isLike;
            $shortContent = null;
            if (strlen($post->content) > 100) {
                $shortContent = mb_substr(
                    $post->content,
                    0,
                    100,
                    "UTF-8"
                );
            }
            $post->short_content = $shortContent;
            // Check if the author has an avatar.
            if (!is_null($post->author->avatar)) {
                $avatarTmp = $post->author->avatar;
                // Construct the full path to the avatar image.
                // Assumes avatars are stored in a directory named after the username (part before the @ in the email) within the 'public/avatars' directory.
                // The filename is the value stored in the database.
                $post->author->_avatar =
                    env('APP_URL') . 'avatars/'
                    . explode('@', $post->author->email)[0] . '/'
                    . $avatarTmp;
            } else {
                $post->author->_avatar = null;
            }
            Carbon::setLocale('app.locale');
            $created = Carbon::create($post->created_at);
            $post->since_created = $created->diffForHumans(Carbon::now());
            unset(
                $post->author->experiences,
                $post->author->skills,
                $post->likes,
                $post->comments,
                $post->created_at
            );
        }

        // Return the posts in a successful API response.
        return $this->apiResponse->success($posts);
    }

    /**
     * Add a favorite for a post
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * @return string|mixed JSON response indicating success or error.
     */
    /**
     * Add a favorite for a post
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * @return string|mixed JSON response indicating success or error.
     */
    public function addFavorite(Request $request)
    {
        // Retrieve all request parameters
        $param = $request->all();
        try {
            // Get the current timestamp
            $now = Carbon::now();
            // Insert a new favorite record into the 'favorites' table
            DB::table('favorites')->insert([
                'user_id' => Auth::user()->id,  // Get the ID of the currently authenticated user
                'post_id' => $param['post_id'], // Get the post_id from the request parameters
                'created_at' => $now,           // Use the current timestamp for the created_at field
                'updated_at' => $now,           // Use the current timestamp for the updated_at field
            ]);
            // Return a success response with the post_id
            return $this->apiResponse->success($param['post_id']);
        } catch (\Exception $e) {
            // Return an internal server error response
            return $this->apiResponse->InternalServerError();
        }
    }

    /**
     * Remove a favorite for a post
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request.
     * @return string|mixed JSON response indicating success or error.
     */
    public function removeFavorite(Request $request)
    {
        // Retrieve all request parameters
        $param = $request->all();

        try {
            // Delete the favorite record from the 'favorites' table
            DB::table('favorites')
                ->where('user_id', Auth::user()->id) // Filter by the currently authenticated user's ID
                ->where('post_id', $param['post_id']) // Filter by the provided post_id
                ->delete(); // Perform the delete operation
            // Return a success response with the post_id
            return $this->apiResponse->success($param['post_id']);
        } catch (\Exception $e) {
            // Return an internal server error response if an exception occurs
            return $this->apiResponse->InternalServerError();
        }
    }
}
