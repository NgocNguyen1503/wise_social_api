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

    public function like(Request $request)
    {
        $param = $request->all();
        try {
            $now = Carbon::now();
            if ($param['action'] == 'like') {
                DB::table('likes')->insert([
                    'user_id' => Auth::user()->id,
                    'post_id' => $param['post_id'],
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
            } else {
                DB::table('likes')
                    ->where('user_id', Auth::user()->id)
                    ->where('post_id', $param['post_id'])
                    ->delete();
            }
            return $this->apiResponse->success($param['post_id']);
        } catch (\Exception $e) {
            return $this->apiResponse->InternalServerError();
        }
    }

    /**
     * List comments for a specific post.
     *
     * Retrieves paginated comments ordered by ID descending for a given post.
     *
     * @param \Illuminate\Http\Request $request The incoming HTTP request containing post_id, offset, and limit.
     * @return string|mixed JSON response with comments or an empty array.
     */
    public function listComment(Request $request)
    {
        // Retrieve request parameters (post_id, offset, limit)
        $param = $request->all();

        // Fetch comments for the given post_id, joining with users table to get author details.
        // Order by comment ID descending and apply pagination.
        $comments = Comment::join(
            'users', // Join the 'comments' table with the 'users' table
            'comments.user_id', // on the user_id column from comments
            'users.id' // and the id column from users
        )
            // Load relationship 'child'
            ->with('child', 'child.author')
            ->select(
                // Select necessary columns, aliasing comment ID for clarity
                'users.id as user_id',
                'users.name',
                'users.avatar',
                'comments.id',
                'comments.comment',
                'comments.post_id',
                'comments.parent_id',
                'comments.created_at',
            )
            // Filter to get only top-level comments (comments with no parent)
            ->where('comments.parent_id', 0)
            // Filter comments belonging to the specific post ID from the request
            ->where('comments.post_id', $param['post_id'])
            // Order the results by comment ID in descending order
            ->orderBy('comments.id', 'DESC')
            // Skip a number of results for pagination (offset)
            ->skip($param['offset'])
            // Limit the number of results fetched for pagination (limit)
            ->take($param['limit'])
            // Execute the query and get the results
            ->get();

        // Check if there are comments found
        if (count($comments) > 0) {
            // Loop through each top-level comment
            foreach ($comments as $comment) {

                // Generate avatar URL for the top-level comment author if available
                // Generate avatar URL for the top-level comment author if available
                if (!is_null($comment->avatar)) {
                    $avatarTmp = $comment->avatar;
                    $comment->_avatar = env('APP_URL')
                        . '/avatars/'
                        . explode('@', $comment->email)[0]
                        . '/'
                        . $avatarTmp;
                } else {
                    $comments->_avatar = null;
                }

                // Check if the comment has any replies (child comments)
                if (count($comment->child) > 0) {
                    // Loop through each child comment
                    foreach ($comment->child as $cmtChild) {
                        // Generate avatar URL for the child comment author if available
                        if (!is_null($cmtChild->author->avatar)) {
                            $avatarTmp = $cmtChild->author->avatar;
                            $cmtChild->author->_avatar = env('APP_URL')
                                . 'avatars/'
                                . explode('@', $cmtChild->author->email)[0]
                                . '/'
                                . $avatarTmp;
                        } else {
                            $cmtChild->author->_avatar = null;
                        }
                    }
                }
                $created_at_tmp = Carbon::create($comment->created_at);
                $comment->_created_at = $created_at_tmp->format('Y-m-d h:i');
            }
        }
        // Return a success response with the fetched comments
        return $this->apiResponse->success($comments);
    }

    public function postComment(Request $request)
    {
        $param = $request->all();
        $comment = new Comment();
        $comment->user_id = Auth::user()->id;
        $comment->post_id = $param['post_id'];
        $comment->comment = $param['comment'];
        $comment->parent_id = $param['parent'];
        $comment->save();

        $avatar = null;
        if (!is_null(Auth::user()->avatar)) {
            $avatarTmp = Auth::user()->avatar;
            $avatar = env('APP_URL') . 'avatars/'
                . explode('@', Auth::user()->email)[0] . '/'
                . $avatarTmp;
        }
        $timeNow = Carbon::now();

        $responseData = [
            'id' => $comment->id,
            'comment' => $param['comment'],
            'avatar' => $avatar,
            'name' => Auth::user()->name,
            'created_at' => $timeNow,
            'updated_at' => $timeNow,
            'parent_id' => $param['parent'],
            'child' => null,
            'post_id' => $param['post_id'],
            'type' => 'comment',
            'action' => 'send_coment'
        ];
        return $this->apiResponse->success();
    }
}
