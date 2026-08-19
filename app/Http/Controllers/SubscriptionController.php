<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionOrder;
use App\Services\PaystackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SubscriptionController extends Controller
{
    public function checkout(Request $request, SubscriptionOrder $order, PaystackService $paystack): Response|RedirectResponse
    {
        abort_unless($request->user()->id === $order->user_id || $request->user()->role === 'super_admin', 403);

        if ($order->status === 'paid') {
            return redirect()->route('dashboard')->with('success', 'This subscription order is already paid.');
        }

        $error = null;
        if (! $order->paystack_access_code) {
            try {
                $checkout = $paystack->initialize($order, $order->user()->value('email'));
                $order->update([
                    'paystack_access_code' => $checkout['access_code'],
                    'paystack_authorization_url' => $checkout['authorization_url'],
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $error = 'Paystack checkout is temporarily unavailable. Check the Paystack keys and try again.';
            }
        }

        return Inertia::render('Subscription/Checkout', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'amount' => $order->amount_kobo / 100,
                'items' => $order->items,
            ],
            'accessCode' => $order->fresh()->paystack_access_code,
            'error' => $error,
        ]);
    }

    public function callback(Request $request, PaystackService $paystack): RedirectResponse
    {
        $reference = (string) $request->query('reference');
        $order = SubscriptionOrder::where('reference', $reference)->firstOrFail();

        try {
            $success = $paystack->fulfill($order, $paystack->verify($reference));
        } catch (Throwable $exception) {
            report($exception);
            $success = false;
        }

        return $success
            ? redirect()->route('dashboard')->with('success', 'Payment confirmed. Your selected modules are now active.')
            : redirect()->route('subscriptions.checkout', $order)->with('error', 'Payment could not be verified. No modules were activated.');
    }

    public function webhook(Request $request, PaystackService $paystack)
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature');
        abort_unless($secret !== '' && hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature), 401);

        $event = $request->json()->all();
        if (($event['event'] ?? null) === 'charge.success' && isset($event['data']['reference'])) {
            $order = SubscriptionOrder::where('reference', $event['data']['reference'])->first();
            if ($order) {
                $paystack->fulfill($order, $event['data']);
            }
        }

        return response()->json(['received' => true]);
    }
}
