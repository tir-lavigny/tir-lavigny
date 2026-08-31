import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from '@lucide/vue';
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
    event_chain_id?: number | null;
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

export interface PaginatedUsers extends PaginatedData<User> {
    data?: User[];
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

export interface EventChain {
    id: number;
    title: string;
    recurrence_type: 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom';
    interval: number | null;
    interval_unit: 'days' | 'weeks' | null;
    day_of_week: number | null;
    day_of_month: number | null;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string | null;
    location_id: number | null;
    content_id: number | null;
    custom_body: string | null;
    created_at: string;
    updated_at: string;
    location?: Location;
    content?: Content;
    events_count?: number;
}

export interface ChainEvent extends Event {
    event_chain_id: number | null;
    overridden_fields: string[] | null;
    has_chain_conflict: boolean;
    pending_conflict_fields: string[] | null;
    custom_content?: { body: string; event_id: number } | null;
}

export interface PaginatedEventChains extends PaginatedData<EventChain> {
    data?: EventChain[];
}

export interface PaginatedChainEvents extends PaginatedData<ChainEvent> {
    data?: ChainEvent[];
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

// ── Register ──

export interface Category {
    id: number;
    name: string;
    is_restricted: boolean;
    products_count?: number;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Product {
    id: number;
    name: string;
    picture?: string;
    unit_price: number;
    allows_custom_price: boolean;
    category_id: number;
    category?: Category;
    child_products?: (Product & { pivot?: { quantity: number } })[];
    parent_products?: Product[];
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Season {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    initial_balance: number;
    end_balance?: number;
    operating_days?: OperatingDay[];
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Member {
    id: number;
    first_name: string;
    last_name: string;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Voucher {
    id: number;
    discount_percent?: number;
    discount_fixed?: number;
    product_id: number;
    season_id: number;
    member_id: number;
    product?: Product;
    season?: Season;
    member?: Member;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface OperatingDay {
    id: number;
    date: string;
    started_at: string;
    closed_at?: string;
    season_id: number;
    cash_remark?: string;
    closing_balance?: number;
    total_cash: number;
    total_twint: number;
    season?: Season;
    tickets?: Ticket[];
    created_at: string;
    updated_at: string;
}

export interface Ticket {
    id: number;
    total: number;
    payment_method: 'cash' | 'twint';
    operating_day_id: number;
    member_id?: number;
    sold_at: string;
    ticket_items?: TicketItem[];
    member?: Member;
    operating_day?: OperatingDay;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface TicketItem {
    id: number;
    product_id: number;
    ticket_id: number;
    voucher_id?: number;
    quantity: number;
    unit_price: number;
    subtotal: number;
    product?: Product;
    voucher?: Voucher;
    created_at: string;
    updated_at: string;
}

export interface PaginatedCategories extends PaginatedData<Category> {
    data?: Category[];
}

export interface PaginatedProducts extends PaginatedData<Product> {
    data?: Product[];
}

export interface PaginatedSeasons extends PaginatedData<Season> {
    data?: Season[];
}

export interface PaginatedMembers extends PaginatedData<Member> {
    data?: Member[];
}

export interface PaginatedTickets extends PaginatedData<Ticket> {
    data?: Ticket[];
}

export interface PaginatedVouchers extends PaginatedData<Voucher> {
    data?: Voucher[];
}

export interface PaginatedOperatingDays extends PaginatedData<OperatingDay> {
    data?: OperatingDay[];
}
