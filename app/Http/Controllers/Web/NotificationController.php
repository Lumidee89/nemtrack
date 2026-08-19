<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function read(Request $request, int $notification)
    {
        DB::table('app_notifications')->where('id', $notification)->where('user_id', $request->user()->id)->update(['read_at' => now(), 'updated_at' => now()]);
        return back();
    }

    public function readAll(Request $request)
    {
        DB::table('app_notifications')->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return back();
    }
}
