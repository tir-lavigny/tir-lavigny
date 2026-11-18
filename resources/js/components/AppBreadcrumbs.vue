<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<SharedData>();
const breadcrumbs = computed(() => (props.breadcrumbs.length ? props.breadcrumbs : (page.props.breadcrumbs ?? [])));
</script>

<template>
    <div class="py-2">
        <Breadcrumbs v-if="breadcrumbs.length > 1" :breadcrumbs="breadcrumbs" />
    </div>
</template>
