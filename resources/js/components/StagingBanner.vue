<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();
const env = page.props.env;
</script>

<template>
    <Teleport to="body">
        <!-- Mobile: small top bar -->
        <div
            v-if="env !== 'production'"
            class="pointer-events-none fixed top-0 right-0 left-0 z-[9999] bg-yellow-500 py-0.5 text-center text-[10px] font-bold text-black md:hidden"
        >
            {{ env === 'staging' ? 'STAGING' : 'DEV' }}
        </div>
        <!-- Desktop: corner ribbon -->
        <div
            v-if="env !== 'production'"
            class="pointer-events-none fixed top-0 left-0 z-[9999] hidden overflow-hidden md:block"
            style="width: 150px; height: 150px"
        >
            <div
                class="absolute w-[220px] bg-yellow-500 py-1 text-center text-xs font-bold text-black"
                style="transform: rotate(-45deg); transform-origin: top left; top: 10vh; left: -4vw"
            >
                {{ env === 'staging' ? 'STAGING' : 'DEV' }}
            </div>
        </div>
    </Teleport>
</template>
