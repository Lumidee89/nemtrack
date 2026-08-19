<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('app_notifications')->where('user_id', $request->user()->id);
        return response()->json(['success' => true, 'message' => 'Notifications retrieved.', 'data' => [
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'items' => $query->latest()->limit(50)->get(),
        ]]);
    }

    public function read(Request $request, int $notification)
    {
        DB::table('app_notifications')->where('id', $notification)->where('user_id', $request->user()->id)->update(['read_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'Notification marked as read.', 'data' => null]);
    }

    public function readAll(Request $request)
    {
        DB::table('app_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return response()->json(['success' => true, 'message' => 'All notifications marked as read.', 'data' => null]);
    }
}
