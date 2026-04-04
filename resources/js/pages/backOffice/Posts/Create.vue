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
import { formatDate, handleTabKey, hasPermissionTo } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { marked } from 'marked';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Communications',
        href: route('bo.posts.index'),
    },
    {
        title: 'Créer',
        href: route('bo.posts.create'),
    },
];

const form = useForm({
    title: '',
    content: '',
    publish_at: formatDate(new Date().toString(), 'YYYY-MM-DD HH:mm'),
    expire_at: '',
});

const showPreview = ref(false);
const bufferContent = ref('');

const resetForm = () => {
    form.reset();
};

const handleSubmit = () => {
    if (!hasPermissionTo(user, 'create post')) {
        toast.error("Vous n'avez pas le droit de créer une communication");
        return;
    }

    if (form.expire_at && form.expire_at <= form.publish_at) {
        form.setError('expire_at', "La date d'expiration doit être postérieure à la date de publication.");
        return;
    }

    form.post(route('bo.posts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            bufferContent.value = '';
            toast.success('La communication a été créé avec succès.');
        },
        onError: () => {
            toast.error('Une erreur est survenue lors de la création de la communication.', {
                description: 'Veuillez vérifier les informations saisies.',
            });
        },
        onFinish: () => {
            form.clearErrors();
        },
    });
};
</script>

<template>
    <Head title="Créer une communication" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Créer une communication</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!form.isDirty" @click="handleSubmit">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Créer la communication</p>
                    </TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!form.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!form.isDirty" @click="resetForm">
                            <Undo2 />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Annuler</p>
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
                    @input="form.content = bufferContent"
                    placeholder="# Contenu de la communication en markdown"
                />
                <div v-show="showPreview" v-html="marked(bufferContent)" class="prose dark:prose-invert overflow-y-scroll" />
            </div>
        </div>
    </AppLayout>
</template>
