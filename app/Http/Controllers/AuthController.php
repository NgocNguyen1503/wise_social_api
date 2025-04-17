<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Mail\RegisterMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private $apiResponse;

    public function __construct()
    {
        $this->apiResponse = new ApiResponse();
    }

    /**
     * Controller function register
     *
     * @param \Illuminate\Http\Request $request
     * @return string JSON
     */
    public function register(Request $request)
    {
        $param = $request->all();
        // Validate repassword
        if ($param['password'] != $param['re_password']) {
            return $this->apiResponse->BadRequest(trans('message.auth.re_password_err'));
        }
        // Check email exit's
        $checkEmail = User::where('email', $param['email'])->first();
        if ($checkEmail) {
            return $this->apiResponse->BadRequest(trans('message.auth.email_already'));
        }
        // Store new user
        $user = new User();
        $user->email = $param['email'];
        $user->password = Hash::make($param['password']);
        $user->name = $param['name'];
        $user->save();
        Mail::to($param['email'])->send(new RegisterMail($param));
        return $this->apiResponse->success();
    }
}
