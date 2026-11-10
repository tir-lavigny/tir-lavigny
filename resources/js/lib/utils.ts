import { User } from '@/types';
import type { Updater } from '@tanstack/vue-table';
import { formatDate as FD, UseDateFormatOptions } from '@vueuse/core';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatDate(date: string, format: string, options?: UseDateFormatOptions) {
    if (!date) return '—';
    const dateObj = new Date(date);

    return FD(dateObj, format, {
        locales: 'fr-CH',
        ...options,
    });
}

export function formatDateOnly(date: string, format = 'DD.MM.YYYY') {
    if (!date) return '—';
    const [year, month, day] = date.slice(0, 10).split('-').map(Number);

    return FD(new Date(year, month - 1, day), format, { locales: 'fr-CH' });
}

export function todayIsoDate(): string {
    return new Date().toLocaleDateString('sv-SE');
}

export function toLocalDatetimeString(utcIso: string): string {
    if (!utcIso) return '';
    const d = new Date(utcIso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function localToUtcIso(localDatetime: string): string {
    if (!localDatetime) return '';
    const d = new Date(localDatetime);
    return d.toISOString();
}

export function parseDateOnly(dateStr: string): Date {
    return new Date(dateStr + 'T12:00:00Z');
}
export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
    ref.value = typeof updaterOrValue === 'function' ? updaterOrValue(ref.value) : updaterOrValue;
}

export const handleTabKey = (event: KeyboardEvent) => {
    if (event.key === 'Tab') {
        event.preventDefault();
        const target = event.target as HTMLTextAreaElement;
        const start = target.selectionStart;
        const end = target.selectionEnd;
        target.value = target.value.substring(0, start) + '\t' + target.value.substring(end);
        target.selectionStart = target.selectionEnd = start + 1;
    }
};

export const debounce = (func: any, wait: number) => {
    let timeout: any;
    return function (this: any, ...args: any[]) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
};

export const hasRole = (user: User, role: string) => {
    if (user.roles) {
        return user.roles.some((r) => r.name === role);
    }
    return false;
};

export const hasPermissionTo = (user: User, permission: string) => {
    if (user.roles) {
        return user.roles.some((r) => r.permissions?.some((p) => p.name === permission) ?? false);
    }
    return false;
};

export function formatMoney(value: unknown): string {
    return Number(value).toFixed(2);
}
