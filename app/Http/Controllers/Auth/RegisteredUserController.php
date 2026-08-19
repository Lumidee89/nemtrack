<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Models\Module;
use App\Models\SubscriptionOrder;
use App\Services\ModulePricing;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['plans' => ModulePricing::catalog()]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'organization_name' => 'nullable|string|max:255',
            'modules' => 'required|array|min:1',
            'modules.*.code' => 'required|string|distinct',
            'modules.*.cycle' => 'required|string|in:monthly,yearly',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $items = ModulePricing::resolve($request->input('modules', []));
        [$user, $order] = DB::transaction(function () use ($request, $items) {
            $organization = Organization::create(['name' => $request->organization_name ?: $request->name.' Workspace', 'slug' => Str::slug($request->organization_name ?: $request->name).'-'.Str::lower(Str::random(5)), 'type' => 'organization']);
            foreach($items as $item){$module=Module::where('code',$item['code'])->where('available',true)->firstOrFail();$organization->modules()->attach($module->id,['enabled'=>false]);}
            $user=User::create(['organization_id' => $organization->id, 'name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'role' => 'organization_admin']);
            $order=SubscriptionOrder::create(['organization_id'=>$organization->id,'user_id'=>$user->id,'reference'=>'NMT-'.Str::upper(Str::random(20)),'items'=>$items,'amount_kobo'=>collect($items)->sum('amount_kobo'),'currency'=>'NGN','status'=>'pending']);
            return [$user,$order];
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('subscriptions.checkout', $order);
    }
}
