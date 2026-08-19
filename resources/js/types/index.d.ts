export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    phone?: string;
    role?: string;
    status?: string;
    organization?: { id: number; name: string; type: string };
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    navigation: { activeInvitations: number };
    enabledModules: string[];
    pendingSubscription?: { id: number; amount_kobo: number };
    notifications: {
        unreadCount: number;
        items: Array<{ id: number; type: string; title: string; message: string; action_url?: string; read_at?: string; created_at: string }>;
    };
};
