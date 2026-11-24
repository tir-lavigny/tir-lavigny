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
    env: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    breadcrumbs?: BreadcrumbItem[];
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

export interface BillingCustomer {
    id: number;
    member_id: number | null;
    type: 'individual' | 'organization';
    first_name: string | null;
    last_name: string | null;
    organization_name: string | null;
    address: string;
    postal_code: string;
    city: string;
    country: string;
    email: string | null;
    phone: string | null;
    preferred_invoice_delivery: 'email' | 'postal';
    member?: {
        id: number;
        first_name: string;
        last_name: string;
        email: string | null;
    } | null;
    created_at: string; // ISO 8601 format
    updated_at: string; // ISO 8601 format
}

export interface PaginatedBillingCustomers extends PaginatedData<BillingCustomer> {
    data?: BillingCustomer[];
}

export interface FinancialDocument {
    id: number;
    status: 'pending' | 'stored' | 'failed';
    error: string | null;
    generated_at: string;
    archived_at: string | null;
}

export interface InvoiceItem {
    id: number;
    invoice_id: number;
    product_id: number | null;
    type: 'product' | 'fee' | 'discount' | 'adjustment';
    description: string;
    quantity: string;
    unit_price: string;
    total: string;
    position: number;
    metadata?: Record<string, unknown> | null;
    type_label?: string;
}

export interface InvoiceActivityLog {
    id: number;
    invoice_id: number | null;
    user_id: number | null;
    action: string;
    description: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    action_label?: string;
    user?: { id: number; name: string } | null;
}

export interface Invoice {
    id: number;
    billing_customer_id: number;
    membership_fee_campaign_id: number | null;
    type: 'membership_fee' | 'one_off';
    status: 'draft' | 'issued' | 'partially_paid' | 'paid' | 'cancelled';
    invoice_number: string | null;
    recipient_name: string;
    recipient_address: string;
    recipient_postal_code: string;
    recipient_city: string;
    recipient_country: string;
    recipient_email: string | null;
    delivery_method: 'email' | 'postal';
    issue_date: string | null;
    due_date: string | null;
    currency: string;
    subtotal: string;
    total: string;
    remaining_cents?: number;
    credited_cents?: number;
    paid_cents?: number;
    issued_at: string | null;
    sent_at: string | null;
    cancelled_at: string | null;
    cancelled_by: number | null;
    cancellation_reason: string | null;
    created_by: number | null;
    items?: InvoiceItem[];
    activity_logs?: InvoiceActivityLog[];
    billing_customer?: BillingCustomer | null;
    credit_note_applications?: CreditNoteApplication[];
    document?: FinancialDocument | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedInvoices extends PaginatedData<Invoice> {
    data?: Invoice[];
}

export interface CreditNoteItem {
    id: number;
    credit_note_id: number;
    invoice_item_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    total: string;
    position: number;
}

export interface CreditNoteApplication {
    id: number;
    credit_note_id: number;
    invoice_id: number;
    amount: string;
    applied_at: string;
    invoice?: Invoice;
    credit_note?: CreditNote;
    reversals?: CreditNoteApplicationReversal[];
}

export interface CreditNoteApplicationReversal {
    id: number;
    credit_note_application_id: number;
    amount: string;
    reason: string;
    reversed_at: string;
}

export interface CreditNoteRefund {
    id: number;
    credit_note_id: number;
    amount: string;
    refund_date: string;
    payment_method: string;
    payment_method_label?: string;
    reference: string | null;
    notes: string | null;
}

export interface CreditNoteReversal {
    id: number;
    credit_note_id: number;
    credit_note_reversal_number: string | null;
    status: 'draft' | 'issued';
    reason: string;
    issue_date: string | null;
    total: string;
    items?: Array<{
        id: number;
        description: string;
        amount: string;
    }>;
    document?: FinancialDocument | null;
}

export interface CreditNote {
    id: number;
    invoice_id: number;
    billing_customer_id: number;
    credit_note_number: string | null;
    status: 'draft' | 'issued' | 'partially_used' | 'fully_used' | 'partially_reversed' | 'reversed';
    reason: string;
    issue_date: string | null;
    currency: string;
    subtotal: string;
    total: string;
    recipient_name: string;
    recipient_address: string;
    recipient_postal_code: string;
    recipient_city: string;
    recipient_country: string;
    recipient_email: string | null;
    created_by: number | null;
    issued_at: string | null;
    invoice?: Invoice;
    items?: CreditNoteItem[];
    applications?: CreditNoteApplication[];
    refunds?: CreditNoteRefund[];
    reversals?: CreditNoteReversal[];
    document?: FinancialDocument | null;
    created_at: string;
    updated_at: string;
}

export interface PaginatedCreditNotes extends PaginatedData<CreditNote> {
    data?: CreditNote[];
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
    is_stockable: boolean;
    category?: Category;
    child_products?: (Product & { pivot?: { quantity: number } })[];
    parent_products?: Product[];
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface PriceList {
    id: number;
    name: string;
    notes?: string | null;
    is_active: boolean;
    last_applied_at?: string | null;
    items_count?: number;
    drift?: PriceListDrift;
    items?: PriceListItem[];
    created_at: string;
    updated_at: string;
}

export interface PriceListItem {
    id: number;
    price_list_id: number;
    product_id: number;
    unit_price: number;
    product?: Product;
    created_at: string;
    updated_at: string;
}

export interface PriceListDrift {
    changed: number;
    missing: number;
    not_in_list: number;
}

export interface PriceListPreviewRow {
    product_id: number;
    product_name: string;
    category_name: string | null;
    current_price: number | null;
    new_price: number | null;
    status: 'changed' | 'unchanged' | 'missing' | 'not_in_list';
}

export interface PriceListPreview {
    rows: PriceListPreviewRow[];
    summary: {
        changed: number;
        unchanged: number;
        missing: number;
        not_in_list: number;
    };
}

export interface CashMovement {
    id: number;
    season_id: number;
    amount: number | string;
    occurred_at: string;
    remark?: string | null;
    created_at: string;
    updated_at: string;
}

export interface Season {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    initial_balance: number;
    end_balance?: number;
    operating_days?: OperatingDay[];
    cash_movements?: CashMovement[];
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface Member {
    id: number;
    first_name: string;
    last_name: string;
    email?: string | null;
    tel?: string | null;
    avs?: string | null;
    avs_masked?: string | null;
    is_swiss_citizen?: boolean | null;
    street?: string | null;
    npa?: string | null;
    city?: string | null;
    canton?: string | null;
    country?: string | null;
    status: 'active' | 'passive';
    dav_uid?: string | null;
    dav_synced_at?: string | null;
    dav_sync_error?: string | null;
    anonymized_at?: string | null;
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
    ticket_items?: TicketItem[];
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
    stock_location?: StockLocation;
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

export interface MemberRegistration {
    id: number;
    form_uuid: string;
    status: string;
    member_id: number | null;
    form_data: Record<string, any> | null;
    form_name?: string | null;
    form_submitted_at: string | null;
    email_verified_at: string | null;
    error_message: string | null;
    failed_at: string | null;
    member?: Member;
    form_url?: string;
    created_at: string;
    updated_at: string;
}

export interface PaginatedMemberRegistrations extends PaginatedData<MemberRegistration> {
    data?: MemberRegistration[];
}

// ── Stock ──

export interface StockLocation {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    created_at: string;
    updated_at: string;
}

export interface StockCount {
    id: number;
    season_id: number;
    stock_location_id: number;
    type: 'initial' | 'adjustment' | 'final';
    status: string;
    completed_at: string | null;
    stock_location?: StockLocation;
    stock_count_lines?: StockCountLine[];
    created_at: string;
    updated_at: string;
}

export interface StockCountLine {
    id: number;
    stock_count_id: number;
    product_id: number;
    expected_quantity: number;
    counted_quantity: number | null;
    product?: Product;
}

export interface StockTransfer {
    id: number;
    from_location_id: number;
    to_location_id: number;
    season_id: number;
    reason: string | null;
    from_location?: StockLocation;
    to_location?: StockLocation;
    stock_transfer_lines?: StockTransferLine[];
    created_at: string;
    updated_at: string;
}

export interface StockTransferLine {
    id: number;
    stock_transfer_id: number;
    product_id: number;
    quantity: number;
    product?: Product;
}

export interface StockLoss {
    id: number;
    stock_location_id: number;
    product_id: number;
    season_id: number;
    quantity: number;
    type: 'loss';
    reason: string | null;
    stock_location?: StockLocation;
    product?: Product;
    created_at: string;
    updated_at: string;
}

export interface PaginatedStockCounts extends PaginatedData<StockCount> {
    data?: StockCount[];
}

export interface PaginatedStockTransfers extends PaginatedData<StockTransfer> {
    data?: StockTransfer[];
}

export interface PaginatedStockLosses extends PaginatedData<StockLoss> {
    data?: StockLoss[];
}

export interface StockMovement {
    id: number;
    stock_location_id: number;
    product_id: number;
    season_id: number | null;
    quantity: number;
    type: 'initial' | 'purchase' | 'sale' | 'return' | 'transfer' | 'loss' | 'adjustment' | 'reversal';
    reference_type: string | null;
    reference_id: number | null;
    reason: string | null;
    product?: Product;
    stock_location?: StockLocation;
    created_at: string;
    updated_at: string;
}

export interface StockLocationStock {
    product_id: number;
    product: Product;
    quantity: number;
}

export interface PaginatedStockLocations extends PaginatedData<StockLocation> {
    data?: StockLocation[];
}

export interface PaginatedStockMovements extends PaginatedData<StockMovement> {
    data?: StockMovement[];
}

export interface StockSnapshot {
    id: number;
    stock_location_id: number;
    snapshot_at: string;
    stock_snapshot_lines?: StockSnapshotLine[];
    created_at: string;
    updated_at: string;
}

export interface StockSnapshotLine {
    id: number;
    stock_snapshot_id: number;
    product_id: number;
    quantity: number;
    product?: Product;
}
