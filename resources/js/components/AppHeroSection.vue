<script setup lang="ts">
import { H1 } from './typography/headings';

defineProps<{
    title: string;
    image: string;
}>();
</script>

<template>
    <div class="absolute left-0 w-full overflow-hidden">
        <section
            class="relative flex h-96 w-full max-w-screen items-center justify-center bg-cover bg-center bg-no-repeat"
            :style="{ backgroundImage: `url(${image})` }"
        >
            <div class="absolute inset-0 bg-black opacity-50"></div>
            <div class="relative z-10 flex flex-col gap-8 text-center text-white">
                <H1>{{ title }}</H1>
                <slot name="description"></slot>
            </div>
        </section>
    </div>
</template>

<style scoped></style>
