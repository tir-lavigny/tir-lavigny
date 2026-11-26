<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Download } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        routeName: string;
        routeParams?: Record<string, string | number>;
        filters?: Record<string, unknown>;
        label?: string;
    }>(),
    {
        routeParams: () => ({}),
        filters: () => ({}),
        label: 'Exporter',
    },
);

const exportUrl = (format: 'csv' | 'xlsx'): string => {
    const url = new URL(route(props.routeName, props.routeParams), window.location.origin);

    Object.entries(props.filters).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '' && value !== false) {
            url.searchParams.set(key, value === true ? '1' : String(value));
        }
    });

    url.searchParams.set('format', format);

    return url.toString();
};

const download = (format: 'csv' | 'xlsx'): void => {
    window.location.assign(exportUrl(format));
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline">
                <Download class="mr-2 h-4 w-4" />
                {{ label }}
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuItem @click="download('xlsx')">Excel (.xlsx)</DropdownMenuItem>
            <DropdownMenuItem @click="download('csv')">CSV (.csv)</DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
