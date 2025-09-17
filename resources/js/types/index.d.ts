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
    roles?: Role[];
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface Post {
    public_id: string;
    title: string;
    content: string;
    publish_at: string; // ISO 8601 format
    expire_at?: string; // ISO 8601 format
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
}

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

export abstract class PaginatedData<T> {
    abstract current_page: number;
    abstract data?: T[];
    abstract first_page_url: string;
    abstract from: number;
    abstract last_page: number;
    abstract last_page_url: string;
    abstract links: PaginationLink[];
    abstract next_page_url: string | null;
    abstract path: string;
    abstract per_page: number;
    abstract prev_page_url: string | null;
    abstract to: number;
    abstract total: number;
}
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginatedEvents extends PaginatedData<Event> {
    data?: Event[];
}

export interface PaginatedPosts extends PaginatedData<Event> {
    data?: Post[];
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
    pivot: {
        role_id: number;
        permission_id: number;
    };
}
export interface Role {
    id: number;
    name: string;
    guard_name: string;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
    pivot: {
        model_type: string;
        model_id: number;
        role_id: number;
    };
    permissions?: Permission[];
}

export interface ActivityLog {
    id: number;
    model: string;
    model_id: number;
    action: string;
    changes?: Record<string, any>;
    user_id?: number;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
}
