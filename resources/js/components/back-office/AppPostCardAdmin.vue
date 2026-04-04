<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate } from '@/lib/utils';
import { Post } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, PenBox, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';

const props = withDefaults(
    defineProps<{
        post: Post;
        actions?: string[];
    }>(),
    {
        actions: () => ['edit', 'delete'],
    },
);

const handleDelete = (public_id: string) => {
    if (!props.actions.includes('delete')) {
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
    <Card class="justify-between">
        <CardHeader class="flex flex-col items-center justify-between sm:flex-row">
            <CardTitle>
                {{ post.title }}
            </CardTitle>
            <div class="flex flex-row items-center gap-2">
                <Tooltip v-if="actions.includes('edit')">
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
                <Tooltip v-if="actions.includes('delete')">
                    <TooltipTrigger>
                        <AppAlertDialog
                            title="Êtes-vous absolument sûr ?"
                            description="Cette action est irréversible. Cela supprimera définitivement cette communication."
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
                        <p>Supprimer la communication</p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </CardHeader>
        <CardContent>
            <div class="flex flex-col gap-4">
                <Small> Début de publication : {{ formatDate(post.publish_at, 'dddd DD MMMM YYYY') }} </Small>
                <Small> Fin de publication : {{ post.expire_at ? formatDate(post.expire_at, 'dddd DD MMMM YYYY') : '-' }} </Small>
            </div>
        </CardContent>
        <CardFooter>
            <Button variant="secondary" size="sm">
                <Link class="flex items-center gap-2" :href="route('bo.posts.show', post.public_id)">
                    Voir la communication <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>

<style scoped></style>
