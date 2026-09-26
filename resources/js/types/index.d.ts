export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    kebun_id?: string;
    afdeling_id?: string;
}

export interface NotificationItem {
    id: string;
    type: string;
    notifiable_type: string;
    notifiable_id: number;
    data: {
        title?: string;
        message?: string;
        type?: string;
        blok_id?: string;
        kode_blok?: string;
        tanggal?: string;
        [key: string]: any;
    };
    read_at: string | null;
    created_at: string;
    updated_at: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        unread_notifications_count: number;
        notifications: NotificationItem[];
    };
};
