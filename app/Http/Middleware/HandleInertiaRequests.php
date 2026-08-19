<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = $user ? DB::table('app_notifications')->where('user_id', $user->id)->latest()->limit(8)->get(['id', 'type', 'title', 'message', 'action_url', 'read_at', 'created_at']) : collect();
        $invitationCount = 0;
        $enabledModules = [];
        $pendingSubscription = null;
        if ($user) {
            $invitations = DB::table('visitor_invitations')->where('status', 'active')->where('valid_until', '>', now());
            if ($user->role !== 'super_admin') $invitations->where('organization_id', $user->organization_id);
            $invitationCount = $invitations->count();
            if ($user->role === 'super_admin') $enabledModules = ['VAS','PAS','VTS','PBS'];
            elseif ($user->organization_id) $enabledModules = DB::table('organization_modules')->join('modules','modules.id','=','organization_modules.module_id')->where('organization_id',$user->organization_id)->where('enabled',true)->where(fn($query)=>$query->whereNull('expires_at')->orWhere('expires_at','>',now()))->pluck('modules.code')->all();
            if ($user->organization_id && $user->role === 'organization_admin') $pendingSubscription = DB::table('subscription_orders')->where('organization_id',$user->organization_id)->where('status','pending')->latest()->first(['id','amount_kobo']);
        }
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->loadMissing('organization'),
            ],
            'navigation' => ['activeInvitations' => $invitationCount],
            'enabledModules' => $enabledModules,
            'pendingSubscription' => $pendingSubscription,
            'notifications' => [
                'unreadCount' => $notifications->whereNull('read_at')->count(),
                'items' => $notifications,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
