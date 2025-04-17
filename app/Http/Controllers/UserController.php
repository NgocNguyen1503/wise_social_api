<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\SendMail;
use App\Models\DeviceToken;
use App\Models\Follow;
use App\Models\Friend;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    private $apiResponse;

    public function __construct() {
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @return string JSON
     */
    public function show(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return $this->apiResponse->UnAuthorization();
        }
        $user = User::with('follower', 'follows')
            ->select(
                'id', 'name', 'email',
                'avatar', 'overview'
            )->where('id', $userId)->first();
        $user->followers = count($user->follower);
        $user->following = count($user->follows);
        unset($user->follower, $user->follows);
        $folderAvatar = null;
        if (!is_null($user->avatar)) {
            $folderAvatar = explode('@', $user->email);
            $user->avatar = url(
                'avatars/' . $folderAvatar[0] . '/' . $user->avatar
            );
        }
        return $this->apiResponse->success($user);
    }

    /**
     * Controller method suggest friend
     *
     * @param \Illuminate\Http\Request $request
     * @return bool|string
     */
    public function suggestFriend(Request $request)
    {
        $userId = Auth::id();
        // List friend id
        $listFriendId = DB::table('friends')
            ->where('user_id', $userId)
            ->select('friend_id')
            ->pluck('friend_id')->toArray();
        $listFriendId[] = $userId;
        // List sugget friend
        $suggests = User::with([
            'experiences' => function ($experienceQuery) {
                return $experienceQuery->select('id', 'user_id', 'title');
            }
        ])->whereNotIn('id', $listFriendId)
            ->where('status', User::STATUS_ACTIVE)
            ->select(
                'id', 'name', 'avatar',
                'created_at'
            )->orderBy('created_at', 'ASC')
            ->limit(config('constant.limit'))
            ->get();
        if (count($suggests) > 0) {
            foreach ($suggests as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->experience = $this->truncateString($txtExperience, 20);
                unset($user->experiences);
            }
        }
        return $this->apiResponse->success($suggests);
    }

    private function truncateString($string, $length, $append = '...')
    {
        if (mb_strlen($string) > $length) {
            return mb_substr($string, 0, $length) . $append;
        }
        return $string;
    }

    public function listFriendRequest(Request $request)
    {
        $userId = Auth::id();
        // List sugget friend
        $requests = User::with([
            'experiences' => function ($experienceQuery) {
                return $experienceQuery->select('id', 'user_id', 'title');
            }
        ])->join(
            'friends', 'users.id', 'friends.user_id'
            )->where('friends.friend_id', $userId)
            ->where('users.status', User::STATUS_ACTIVE)
            ->where('friends.approved', Friend::UN_APPROVED)
            ->select(
                'friends.id', 'users.email', 'users.name', 'users.avatar',
                'users.created_at'
            )->orderBy('friends.created_at', 'ASC')
            ->limit(config('constant.limit'))
            ->get();
        if (count($requests) > 0) {
            foreach ($requests as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->experience = $this->truncateString($txtExperience, 15);
                $user->name = $this->truncateString($user->name, 10);
                unset($user->experiences);
            }
        }
        return $this->apiResponse->success($requests);
    }

    /**
     * Controller method add friend
     *
     * @param \Illuminate\Http\Request $request
     * @return bool|string
     */
    public function addFriend(Request $request)
    {
        $param = $request->all();
        try {
            DB::beginTransaction();
            $friend = new Friend();
            $friend->user_id = Auth::id();
            $friend->friend_id = $param['friend_id'];
            $friend->approved = Friend::UN_APPROVED;
            $friend->created_at = Carbon::now();
            $friend->save();
            DB::commit();
            // Send mail
            $recUser = User::find($param['friend_id']);
            $sendMail = new SendMail();
            $sendMail->sendMail003($recUser->email, Auth::user());
            // Firebase send notify
            $notification = new Notification();
            $notification->user_id = $param['friend_id'];
            $notification->actor_id = Auth::id();
            $notification->content = "「'".Auth::user()->name."'」さんがあなたに友達リクエストを送信しました。";
            $notification->is_view = Notification::UNVIEWED;
            $notification->status = Notification::STATUS_WAIT;
            $notification->save();
            return $this->apiResponse->success();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->apiResponse->InternalServerError();
        }
    }

    public function accept(Request $request)
    {
        $param = $request->all();
        if ($param['type'] == 'accept') {
            // Approve
            return DB::table('friends')
            ->where('id', $param['id'])
            ->update([
                'approved' => Friend::APPROVED
            ]);
        } else {
            // Remove
            return DB::table('friends')
                ->where('id', $param['id'])
                ->delete();
        }
    }

    public function mostFollowed(Request $request)
    {
        $user = User::select(
            'users.id', 'users.name', 'users.email', 'users.avatar',
            DB::raw('COUNT(follows.id) as total_follow'),
            'follows.follow_id'
        )->join('follows', 'users.id', 'follows.follow_id')
        ->with([
            'experiences' => function ($experienceQuery) {
                return $experienceQuery->select('id', 'user_id', 'title');
            }
        ])->groupBy(
            'follows.follow_id', 'users.id', 'users.name',
            'users.email', 'users.avatar'
        )->orderBy('total_follow', 'DESC')->first();
        $folderAvatar = null;
        if (!is_null($user->avatar)) {
            $folderAvatar = explode('@', $user->email);
            $user->avatar = url(
                'avatars/' . $folderAvatar[0] . '/' . $user->avatar
            );
        }
        $txtExperience = '';
        $i = 1;
        foreach ($user->experiences as $experience) {
            if ($i < count($user->experiences)) {
                $txtExperience .= $experience->title . ', ';
            } else {
                $txtExperience .= $experience->title;
            }
            $i++;
        }
        $user->experience = $this->truncateString($txtExperience, 15);
        $user->name = $this->truncateString($user->name, 10);
        unset($user->experiences);
        return $this->apiResponse->success($user);
    }

    public function search(Request $request)
    {
        $param = $request->all();
        $users = User::with([
            'experiences' => function ($experienceQuery) {
                return $experienceQuery->select('id', 'user_id', 'title');
            }
        ])->select(
            'id', 'name', 'email', 'avatar'
        )->whereHas('experiences', function ($query) use ($param) {
            return $query->where('title', 'Like', '%' . $param['key-word'] . '%');
        })->orWhere('name', 'Like', '%'.$param['key-word'] . '%')
        ->orWhere('email', 'Like', '%'.$param['key-word'] . '%')
        ->orderBy('id', 'DESC')->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $folderAvatar = null;
                if (!is_null($user->avatar)) {
                    $folderAvatar = explode('@', $user->email);
                    $user->avatar = url(
                        'avatars/' . $folderAvatar[0] . '/' . $user->avatar
                    );
                }
                $txtExperience = '';
                $i = 1;
                foreach ($user->experiences as $experience) {
                    if ($i < count($user->experiences)) {
                        $txtExperience .= $experience->title . ', ';
                    } else {
                        $txtExperience .= $experience->title;
                    }
                    $i++;
                }
                $user->experience = $this->truncateString($txtExperience, 100);
                $user->name = $this->truncateString($user->name, length: 100);
                unset($user->experiences);
            }
        }
        return $this->apiResponse->success($users);
    }

    public function setDeviceToken(Request $request)
    {
        $param = $request->all();
        $userId = Auth::user()->id;
        // Check token already exi't
        $checkToken = DeviceToken::where('user_id', $userId)
            ->get();
        if (count($checkToken) == 0) {
            $deviceToken = new DeviceToken();
            $deviceToken->user_id = $userId;
            $deviceToken->token = $param['fcmToken'];
            $deviceToken->save();
        } else {
            $deviceToken = DeviceToken::where('user_id', $userId)
                ->first();
            $deviceToken->token = $param['fcmToken'];
            $deviceToken->updated_at = Carbon::now();
            $deviceToken->update();
        }
        return $this->apiResponse->success($deviceToken);
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
