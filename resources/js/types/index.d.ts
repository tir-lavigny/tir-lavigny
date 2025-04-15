import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
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
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Event {
    id: number;
    title: string;
    start_datetime: string; // ISO 8601 format
    end_datetime?: string; // ISO 8601 format
    content_id?: number;
    location_id?: number;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
    location?: Location;
}

export interface Location {
    id: number;
    name: string;
    address: string;
    latitude: number;
    longitude: number;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
}

export interface Content {
    id: number;
    title: string;
    body: string;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
    events_count?: number;
}
