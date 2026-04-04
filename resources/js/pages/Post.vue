<script setup lang="ts">
import AppHeroSection from '@/components/AppHeroSection.vue';
import { P } from '@/components/typography/texts';
import WebLayout from '@/layouts/WebLayout.vue';
import { formatDate } from '@/lib/utils';
import { Post } from '@/types';
import { Head } from '@inertiajs/vue3';
import DOMPurify from 'dompurify';
import { Calendar } from '@lucide/vue';
import { marked } from 'marked';
import { ref, watchEffect } from 'vue';

const props = defineProps<{
    post: Post;
}>();

const cleanContent = ref('');

watchEffect(async () => {
    const raw = await marked(props.post.content);
    cleanContent.value = DOMPurify.sanitize(raw);
});
</script>

<template>
    <Head title="Communication" />

    <WebLayout>
        <AppHeroSection :title="post.title" image="/images/hero.jpg">
            <template #description>
                <div class="flex flex-col items-center justify-center gap-2 sm:flex-row sm:gap-4">
                    <div class="flex flex-row items-center gap-2">
                        <Calendar class="h-4 w-4 min-w-4" />
                        <P class="!mt-0">
                            {{ formatDate(post.publish_at, 'ddd DD MMMM YYYY') }}
                        </P>
                    </div>
                </div>
            </template>
        </AppHeroSection>
        <section class="mt-96 flex flex-col gap-8 py-12">
            <div v-html="cleanContent" class="prose dark:prose-invert xl:prose-xl max-w-full"></div>
        </section>
    </WebLayout>
</template>

<style scoped></style>
