<script setup lang="ts">
withDefaults(
    defineProps<{
        latitude: number;
        longitude: number;
        title: string;
        zoom?: number;
        language?: string;
        height?: string;
        width?: string;
    }>(),
    {
        zoom: 15,
        language: 'fr',
        height: '400px',
        width: '100%',
    },
);
</script>

<template>
    <iframe
        class="rounded-lg"
        :style="{ height: height, width: width }"
        frameborder="0"
        scrolling="no"
        marginheight="0"
        marginwidth="0"
        :src="`https://maps.google.com/maps?q=${latitude},${longitude}&hl=${language}&title=${encodeURIComponent(title)}&z=${zoom}&amp;output=embed`"
    >
    </iframe>
</template>
