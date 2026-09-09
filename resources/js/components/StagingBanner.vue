<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const env = page.props.env;
</script>

<template>
    <Teleport to="body">
        <div
            v-if="env !== 'production'"
            class="bg-yellow-500 text-black text-center text-sm font-bold py-1 fixed top-0 left-0 right-0 z-[9999]"
        >
            {{ env === 'staging' ? 'Staging Environment' : 'Development Environment' }}
        </div>
    </Teleport>
</template>
