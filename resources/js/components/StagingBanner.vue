<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const env = page.props.env;
</script>

<template>
    <Teleport to="body">
        <div v-if="env !== 'production'" class="fixed top-0 right-0 left-0 z-[9999] bg-yellow-500 py-1 text-center text-sm font-bold text-black">
            {{ env === 'staging' ? 'Staging Environment' : 'Development Environment' }}
        </div>
    </Teleport>
</template>
