import { formatDate as FD, UseDateFormatOptions } from '@vueuse/core';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

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
