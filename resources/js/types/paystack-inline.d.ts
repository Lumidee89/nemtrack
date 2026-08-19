declare module '@paystack/inline-js' {
    type Transaction = { id: number; reference: string; message: string };
    type ErrorResponse = { message: string };
    type Callbacks = {
        onSuccess?: (transaction: Transaction) => void;
        onCancel?: () => void;
        onError?: (error: ErrorResponse) => void;
    };

    export default class PaystackPop {
        resumeTransaction(accessCode: string, callbacks?: Callbacks): unknown;
    }
}
