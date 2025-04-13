<script setup lang="ts">
import { P } from '@/components/typography/texts';
import { Location } from '@/types';

defineProps<{
    location: Location;
}>();
</script>

<template>
    <div class="bg-secondary text-secondary-foreground rounded-lg p-12">
        <p class="scroll-m-20 pb-2 text-3xl font-semibold tracking-tight transition-colors first:mt-0">
            {{ location.name }}
        </p>
        <div class="flex flex-col gap-16 sm:flex-row">
            <div class="sm:w-1/2">
                <P>
                    <span v-for="(part, index) in location.address.split(',')" :key="index">
                        {{ part }}<template v-if="index < location.address.split(',').length - 1">, </template
                        ><br v-if="index < location.address.split(',').length - 1" />
                    </span>
                </P>
            </div>
            <div class="sm:w-1/2">
                <iframe
                    class="rounded-lg"
                    :style="{ height: '400px', width: '100%' }"
                    frameborder="0"
                    scrolling="no"
                    marginheight="0"
                    marginwidth="0"
                    :src="`https://maps.google.com/maps?q=${location.latitude},${location.longitude}&hl=fr&title=${encodeURIComponent(location.name)}&z=14&amp;output=embed`"
                >
                </iframe>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
