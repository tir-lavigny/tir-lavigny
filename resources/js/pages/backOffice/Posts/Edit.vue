<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, handleTabKey } from '@/lib/utils';
import { type BreadcrumbItem, type Post } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { marked } from 'marked';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    post: Post;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Communications',
        href: route('bo.posts.index'),
    },
    {
        title: props.post.title,
        href: route('bo.posts.show', { post: props.post.public_id }),
    },
    {
        title: 'Modification',
        href: route('bo.posts.edit', { post: props.post.public_id }),
    },
];

const form = useForm({
    public_id: props.post.public_id,
    title: props.post.title,
    content: props.post.content,
    publish_at: formatDate(props.post.publish_at, 'YYYY-MM-DD HH:mm'),
    expire_at: props.post.expire_at ? formatDate(props.post.expire_at, 'YYYY-MM-DD HH:mm') : '',
});

const showPreview = ref(false);
const bufferContent = ref(props.post.content);

const saveChanges = () => {
    if (form.expire_at && form.expire_at <= form.publish_at) {
        form.setError('expire_at', "La date d'expiration doit être postérieure à la date de publication.");
        return;
    }
    form.content = bufferContent.value;
    form.put(route('bo.posts.update', { post: props.post.public_id }), {
        preserveState: true,
        onSuccess: () => {
            toast.success('Communication mise à jour avec succès.');
        },
        onError: () => {
            toast.error('Erreur lors de la mise à jour de la communication.');
        },
    });
};

const resetForm = () => {
    form.reset();
    bufferContent.value = props.post.content;
};
</script>

<template>
    <Head :title="`Modifier : ${props.post.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modification de : {{ props.post.title }}</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="saveChanges">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Enregistrer les modifications</p>
                    </TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!form.isDirty" @click="resetForm">
                            <Undo2 />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Annuler les modifications</p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                    <H3>Informations générales</H3>
                    <div class="mt-6 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="title" class="block text-sm font-medium">Titre</Label>
                            <Input id="title" required v-model="form.title" type="text" placeholder="Titre" maxlength="255" />
                            <InputError :message="form.errors.title" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="publish_at" class="block text-sm font-medium">Date de publication</Label>
                                <Input id="publish_at" required v-model="form.publish_at" type="datetime-local" />
                                <InputError :message="form.errors.publish_at" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="expire_at" class="block text-sm font-medium">Date d'expiration</Label>
                                <Input id="expire_at" v-model="form.expire_at" type="datetime-local" />
                                <InputError :message="form.errors.expire_at" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] w-full flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <div class="flex items-center gap-4">
                        <H3>Contenu ({{ !showPreview ? 'markdown' : 'prévisualisation' }})</H3>
                        <InputError :message="form.errors.content" />
                    </div>
                    <div class="flex flex-row items-center gap-2">
                        <Switch v-model="showPreview" />
                        <Small>Prévisualisation</Small>
                    </div>
                </div>
                <Separator />
                <Textarea
                    v-show="!showPreview"
                    class="mt-4 h-[90vh] w-full flex-grow resize-none overflow-y-scroll md:h-[49vh]"
                    v-model="bufferContent"
                    @keydown="handleTabKey"
                    @change="form.content = bufferContent"
                    placeholder="# Contenu de la communication en markdown"
                />
                <div v-show="showPreview" v-html="marked(bufferContent)" class="prose dark:prose-invert overflow-y-scroll" />
            </div>
        </div>
    </AppLayout>
</template>
