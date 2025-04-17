<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\Notification;
use App\Models\Post;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Helpers\ApiResponse;

class PostController extends Controller
{
    private $apiResponse;
    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $param = $request->all();
        $file = null;
        if ($request->hasFile("image")) {
            $file = $request->file('image');
            $fileName = Auth::user()->id .'.'. date('ymdhis') . "." .
            $file->getClientOriginalExtension();
            if (!is_dir(public_path('post_images'))) {
                mkdir(directory: public_path('post_images'));
            }
            move_uploaded_file(
                $file, public_path('post_images') . "\\" . $fileName
            );
        }
        $post = new Post();
        $post->user_id = Auth::user()->id;
        $post->content = $param['content'];
        $post->timeline_orders = Carbon::now();
        $post->view_count = 0;
        $post->images = $fileName;
        $post->save();
        // insert to notifications table
        $friends = Friend::where('user_id', Auth::user()->id)
            ->get();
        if (count($friends) > 0) {
            $arrPushToFriend = [];
            foreach ($friends as $friend) {
                $arrPushToFriend[] = [
                    'user_id' => $friend->friend_id,
                    'actor_id' => Auth::user()->id,
                    'content' => '「'.Auth::user()->name.'」さんがあなたの投稿にコメントしました。',
                    'is_view' => Notification::UNVIEWED,
                    'status' => Notification::STATUS_WAIT
                ];
            }
            DB::table('notifications')->insert($arrPushToFriend);
        }
        return $this->apiResponse->success($post);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
