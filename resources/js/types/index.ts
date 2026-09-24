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
    activeContext: ActiveContext | null;
}

export type ActiveContext = {
    league: {
        id: number;
        name: string;
        logoUrl: string | null;
        primaryColor: string;
        secondaryColor: string;
    };
    role: RoleOption;
};

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

export type LeagueSummary = {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
    primary_color: string;
    secondary_color: string;
    status: string;
    active_admins_count?: number;
    created_at: string;
};
