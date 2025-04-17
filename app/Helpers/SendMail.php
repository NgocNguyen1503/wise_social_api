<?php

namespace App\Helpers;

use App\Mail\RequestFriendEmail;
use Illuminate\Support\Facades\Mail;

class SendMail
{
    /**
     * Helpers method send mail 003
     *
     * This mail send to users receiv add friend request
     * @param string email for receiver user friend request
     * @param object user for send request
     * @return boolean
     */
    public function sendMail003($email, $user)
    {
        try {
            Mail::to($email)->send(
                new RequestFriendEmail($user)
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
