import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User | null;
    lodges: LodgeSummary[];
    can_review_registrations: boolean;
}

export interface LodgeSummary {
    id: number;
    name: string;
    slug: string;
    can_manage_lodge: boolean;
    can_manage_website: boolean;
    can_view_people: boolean;
    can_manage_officers: boolean;
    can_manage_roles: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface AuthenticatedSharedData extends SharedData {
    auth: Omit<Auth, 'user'> & { user: User };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    current_lodge_id: number | null;
    is_platform_admin: boolean;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
