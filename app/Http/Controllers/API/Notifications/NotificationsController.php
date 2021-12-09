<?php

namespace App\Http\Controllers\API\Notifications;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{

    public function getNotifications(Request $request) {
        $user = User::find($request->user('api')->id);
        return response()->json($user->notifications->take(10)->toArray(), config('errorCodes.HTTP_SUCCESS'));
    }

    public function getUnreadNotifcationsCount(Request $request) {
        $user = User::find($request->user('api')->id);
        return response()->json([
            'count' => $user->unreadNotifications->count()
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function markAsRead(Request $request) {
        $inputArr = $request->input();
        $notification = User::find($request->user('api')->id)->notifications()->where('id', $inputArr['id'])->first();
        $notification->read_at = Carbon::now();
        $notification->save();
        return response()->json([
            'message' => 'Updated notification'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

}
