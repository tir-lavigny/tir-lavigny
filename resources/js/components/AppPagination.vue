<script setup lang="ts">
import { Button } from '@/components/ui/button';

import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';

withDefaults(
    defineProps<{
        itemsPerPage: number;
        totalItems: number;
        siblingCount?: number;
        showEdges?: boolean;
        page?: number;
        showPrevNext?: boolean;
        showFirstLast?: boolean;
    }>(),
    {
        itemsPerPage: 10,
        totalItems: 0,
        siblingCount: 1,
        showEdges: true,
        page: 1,
        showPrevNext: true,
        showFirstLast: true,
    },
);

defineEmits<{
    (e: 'page-changed', page: number): void;
}>();
</script>

<template>
    <Pagination
        v-if="totalItems > itemsPerPage"
        v-slot="{ page: currentPage }"
        :items-per-page="itemsPerPage"
        :total="totalItems"
        :sibling-count="siblingCount"
        :show-edges="showEdges"
        :page="page"
        @update:page="(value) => $emit('page-changed', value)"
    >
        <PaginationContent v-slot="{ items }" class="flex items-center gap-1">
            <PaginationFirst v-if="showFirstLast" />
            <PaginationPrevious v-if="showPrevNext" />

            <template v-for="(item, index) in items">
                <PaginationItem v-if="item.type === 'page'" :key="index" :value="item.value" as-child>
                    <Button class="h-10 w-10 p-0" :variant="item.value === currentPage ? 'default' : 'outline'">
                        {{ item.value }}
                    </Button>
                </PaginationItem>
                <PaginationEllipsis v-else :key="item.type" :index="index" />
            </template>

            <PaginationNext v-if="showPrevNext" />
            <PaginationLast v-if="showFirstLast" />
        </PaginationContent>
    </Pagination>
</template>
