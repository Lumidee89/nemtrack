import GuestLayout from '@/Layouts/GuestLayout';
import { Head, router } from '@inertiajs/react';
import PaystackPop from '@paystack/inline-js';
import { useCallback, useEffect, useRef, useState } from 'react';

type Item = { code: string; cycle: string; amount_kobo: number };
type Order = { id: number; reference: string; amount: number; items: Item[] };

export default function Checkout({ order, accessCode, error }: { order: Order; accessCode: string | null; error?: string | null }) {
    const opened = useRef(false);
    const [message, setMessage] = useState(error ?? 'Your dashboard unlocks immediately after Paystack confirms payment.');

    const openCheckout = useCallback(() => {
        if (!accessCode) {
            router.reload();
            return;
        }

        setMessage('Complete your payment in the secure Paystack window.');
        new PaystackPop().resumeTransaction(accessCode, {
            onSuccess: transaction => {
                setMessage('Confirming payment…');
                window.location.assign(route('subscriptions.callback', { reference: transaction.reference }));
            },
            onCancel: () => setMessage('Payment was not completed. Your dashboard remains locked.'),
            onError: response => setMessage(response.message || 'Unable to open Paystack. Please try again.'),
        });
    }, [accessCode]);

    useEffect(() => {
        if (accessCode && !opened.current) {
            opened.current = true;
            openCheckout();
        }
    }, [accessCode, openCheckout]);

    return (
        <GuestLayout>
            <Head title="Complete payment" />
            <div className="auth-heading">
                <span>FINAL STEP</span>
                <h2>Activate your workspace.</h2>
                <p>Payment is required before you can access the NEMTRACK dashboard.</p>
            </div>
            <section className="payment-summary">
                <div className="payment-lock">⌾</div>
                <div className="payment-items">
                    {order.items.map(item => (
                        <div key={item.code}>
                            <span><b>{item.code}</b><small>{item.cycle} subscription</small></span>
                            <strong>₦{(item.amount_kobo / 100).toLocaleString()}</strong>
                        </div>
                    ))}
                </div>
                <div className="payment-due"><span>Total due now</span><b>₦{order.amount.toLocaleString()}</b></div>
                <p className={error ? 'payment-message error' : 'payment-message'}>{message}</p>
                <button type="button" className="payment-button" onClick={openCheckout}>
                    {accessCode ? 'Pay securely with Paystack →' : 'Retry Paystack connection'}
                </button>
            </section>
            <div className="payment-footer">
                <span>Reference: {order.reference}</span>
                <button type="button" onClick={() => router.post(route('logout'))}>Sign out</button>
            </div>
        </GuestLayout>
    );
}
