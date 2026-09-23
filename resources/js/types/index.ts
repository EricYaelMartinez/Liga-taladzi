import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    status: string;
    forcePasswordChange: boolean;
    roles: string[];
    permissions: string[];
};

export interface AppPageProps extends InertiaPageProps {
    appName: string;
    auth: { user: AuthUser | null };
    flash: { success?: string; error?: string };
}

export type StatusOption = { value: string; label: string };
export type RoleOption = { id: number; name: string; slug: string };

export type ManagedUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    force_password_change: boolean;
    last_login_at: string | null;
    created_at: string;
    roles: RoleOption[];
};
