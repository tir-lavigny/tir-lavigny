import type { Updater } from '@tanstack/vue-table';
import { formatDate as FD, UseDateFormatOptions } from '@vueuse/core';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatDate(date: string, format: string, options?: UseDateFormatOptions) {
    date = date.replace('Z', '');
    const dateObj = new Date(date);

    return FD(dateObj, format, {
        locales: 'fr-CH',
        ...options,
    });
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
