<script setup lang="ts">
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/utils';
import { Post } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

defineProps<{
    post: Post;
}>();
</script>

<template>
    <Card class="justify-between">
        <CardHeader>
            <CardTitle>
                {{ post.title }}
            </CardTitle>
        </CardHeader>
        <CardContent></CardContent>
        <CardFooter class="flex justify-between">
            <Muted> Publié le {{ formatDate(post.publish_at, 'DD MMMM YYYY') }} </Muted>
            <Button variant="secondary" size="sm">
                <Link class="flex items-center gap-2" :href="route('posts.show', post.public_id)">
                    Lire la suite <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>

<style scoped></style>
