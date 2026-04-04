<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H3, H4 } from '@/components/typography/headings';
import { P, Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { Post, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { PenBox, Trash2 } from '@lucide/vue';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const props = defineProps<{
    post: Post;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Communications',
        href: route('bo.posts.index'),
    },
    {
        title: `${props.post.title}`,
        href: route('bo.posts.show', { post: props.post.public_id }),
    },
];

const cleanContent = ref('');

watchEffect(async () => {
    const raw = await marked(props.post.content);
    cleanContent.value = DOMPurify.sanitize(raw);
});

const handleDelete = (public_id: string) => {
    if (!hasPermissionTo(user, 'delete post')) {
        toast.error("Vous n'avez pas le droit de supprimer une communication");
        return;
    }
    const form = useForm({
        public_id,
    });

    form.delete(route('bo.posts.destroy', public_id), {
        onSuccess: () => {
            toast.success('Communication supprimée avec succès');
            form.reset();
        },
        onError: () => {
            toast.error('Une erreur est survenue lors de la suppression de la communication');
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex w-full flex-row justify-between">
                <H1> {{ post.title }} </H1>
                <div class="flex flex-row items-center gap-2">
                    <Tooltip v-if="hasPermissionTo(user, 'edit post')">
                        <TooltipTrigger>
                            <Link class="flex items-center gap-2" :href="route('bo.posts.edit', post.public_id)">
                                <Button size="icon" variant="secondary">
                                    <PenBox />
                                </Button>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Modifier la communication</p>
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="hasPermissionTo(user, 'delete post')">
                        <TooltipTrigger>
                            <AppAlertDialog
                                title="Êtes-vous absolument sûr ?"
                                description="Cette action est irréversible. Cela supprimera définitivement cet événement."
                                cancelText="Annuler"
                            >
                                <template #trigger>
                                    <Button size="icon" variant="destructive">
                                        <Trash2 />
                                    </Button>
                                </template>
                                <template #action>
                                    <Button variant="destructive" @click="handleDelete(post.public_id)">Supprimer</Button>
                                </template>
                            </AppAlertDialog>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Supprimer l'événement</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex flex-row gap-4">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                    <H4>Communication</H4>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Titre</Small>
                        <P class="col-span-2 !mt-0">{{ post.title }}</P>
                    </div>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Début de publication</Small>
                        <P class="col-span-2 !mt-0">{{ formatDate(post.publish_at, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                    </div>
                    <div class="grid grid-cols-3 items-center justify-center gap-2">
                        <Small>Fin de publication</Small>
                        <P class="col-span-2 !mt-0" v-if="post.expire_at">{{ formatDate(post.expire_at, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                        <P class="col-span-2 !mt-0" v-else>Non défini</P>
                    </div>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <H3>Contenu</H3>
                </div>
                <Separator />
                <div v-html="marked(cleanContent)" class="prose dark:prose-invert mt-4 w-full" />
            </div>
        </div>
    </AppLayout>
</template>
