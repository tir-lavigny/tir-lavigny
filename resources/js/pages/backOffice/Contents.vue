<script setup lang="ts">
import ContentsCreate from '@/components/back-office/ContentsCreate.vue';
import ContentsTable from '@/components/back-office/ContentsTable.vue';
import InputError from '@/components/InputError.vue';
import { H3, H4 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { handleTabKey, hasPermissionTo } from '@/lib/utils';
import { Content, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Save, Undo2 } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { marked } from 'marked';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Contenu template',
        href: route('contents.index'),
    },
];

defineProps<{
    contents?: Content[];
}>();

const showPreview = ref(false);
const isMobile = useMediaQuery('(max-width: 768px)');

const updateForm = useForm({
    id: null as number | null,
    title: '',
    body: '',
});

const deleteForm = useForm({
    id: null as number | null,
});

const flushUpdateForm = () => {
    updateForm.defaults('id', null);
    updateForm.defaults('title', '');
    updateForm.defaults('body', '');
    updateForm.reset();
};

const handleSelection = (selectedRows: Content) => {
    updateForm.defaults('id', selectedRows.id);
    updateForm.defaults('title', selectedRows.title);
    updateForm.defaults('body', selectedRows.body);
    updateForm.reset();
};

const handleSelectionDelete = (selectedRows: Content) => {
    if (!hasPermissionTo(user, 'delete content')) {
        toast.error("Vous n'avez pas la permission de supprimer le contenu");
        return;
    }

    deleteForm.defaults('id', selectedRows.id);
    deleteForm.reset();
    deleteForm.delete(route('contents.destroy', { id: selectedRows.id }), {
        onSuccess: () => {
            toast.success('Contenu supprimé avec succès');
            window.location.reload();
        },
        onError: (error) => {
            const errorMessage = Object.entries(error)
                .map(([, value]) => `${value}`)
                .join(', ');
            toast.error(errorMessage);
        },
    });
};

const handleUpdate = () => {
    if (!hasPermissionTo(user, 'edit content')) {
        toast.error("Vous n'avez pas la permission de modifier le contenu");
        return;
    }

    updateForm.clearErrors();

    if (!updateForm.id) {
        toast.error('Veuillez sélectionner un contenu à mettre à jour');
        return;
    }
    if (!updateForm.isDirty) {
        toast.error('Aucune modification à enregistrer');
        return;
    }
    if (!updateForm.title) updateForm.setError('title', 'Le titre est requis');
    if (!updateForm.body) updateForm.setError('body', 'Le contenu est requis');

    if (updateForm.hasErrors) return;

    updateForm.put(route('contents.update', { id: updateForm.id }), {
        onSuccess: () => {
            flushUpdateForm();
            toast.success('Contenu mis à jour avec succès');
            window.location.reload();
        },
        onError: (error) => {
            const errorMessage = Object.entries(error)
                .map(([, value]) => `${value}`)
                .join(', ');
            toast.error(errorMessage);
        },
    });
};

const handleSubmitCreate = (form: any) => {
    if (!hasPermissionTo(user, 'create content')) {
        toast.error("Vous n'avez pas la permission de créer du contenu");
        return;
    }

    form.clearErrors();

    if (!form.title) form.setError('title', 'Le titre est requis');
    if (!form.body) form.setError('body', 'Le contenu est requis');

    if (form.hasErrors) return;

    form.post(route('contents.store'), {
        onSuccess: () => {
            flushUpdateForm();
            toast.success('Template créée avec succès');
            window.location.reload();
        },
        onError: (error: any) => {
            const errorMessage = Object.entries(error)
                .map(([, value]) => `${value}`)
                .join(', ');
            toast.error(errorMessage);
        },
    });
};
</script>

<template>
    <Head title="Contenu" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border p-4 md:min-h-min" style="flex: 1 1 10%">
                <div class="flex flex-row items-center justify-between">
                    <H3>Templates</H3>
                    <ContentsCreate
                        v-if="hasPermissionTo(user, 'create content')"
                        title="Créer une nouvelle template"
                        description="Créer une nouvelle template de contenu, cette template peut ensuite être utilisée dans plusieurs événements pour en unifier le contenu."
                        @submit="handleSubmitCreate"
                    >
                        <template #trigger>
                            <Button size="icon"> <Plus /> </Button>
                        </template>
                    </ContentsCreate>
                </div>
                <ContentsTable
                    :contents="contents"
                    :pageSize="5"
                    :actions="[
                        ...(hasPermissionTo(user, 'edit content') ? ['edit'] : []),
                        ...(hasPermissionTo(user, 'delete content') ? ['delete'] : []),
                    ]"
                    @update:row-selection="handleSelection"
                    @delete:rowSelection="handleSelectionDelete"
                />
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border p-4 md:min-h-min" style="flex: 2 1 90%">
                <div class="flex flex-row justify-between">
                    <div class="flex flex-row gap-2">
                        <H3 class="text-nowrap">Contenu <template v-if="updateForm.id">de</template></H3>
                        <div class="flex flex-row items-center gap-1">
                            <Input
                                v-if="updateForm.id"
                                :disabled="!hasPermissionTo(user, 'edit content')"
                                class="w-96"
                                required
                                type="text"
                                placeholder="Titre"
                                v-model="updateForm.title"
                            />
                            <InputError :message="updateForm.errors.title" />
                        </div>
                    </div>
                    <div class="flex flex-row items-center gap-2" v-if="hasPermissionTo(user, 'edit content')">
                        <Tooltip :disabled="!updateForm.isDirty">
                            <TooltipTrigger>
                                <Button size="icon" :disabled="!updateForm.isDirty" @click="handleUpdate">
                                    <Save />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Sauvegarder</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip :disabled="!updateForm.isDirty">
                            <TooltipTrigger>
                                <Button variant="destructive" size="icon" :disabled="!updateForm.isDirty" @click="updateForm.reset()">
                                    <Undo2 />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Annuler</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <template v-if="!isMobile">
                    <div class="mt-8 grid grid-cols-2 gap-4" v-if="updateForm?.id">
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-row items-center gap-1">
                                <H4>Markdown</H4>
                                <InputError :message="updateForm.errors.body" />
                            </div>
                            <Separator />
                            <Textarea
                                :disabled="!hasPermissionTo(user, 'edit content')"
                                class="w-full resize-none"
                                v-model="updateForm.body"
                                @keydown="handleTabKey"
                            ></Textarea>
                        </div>
                        <div class="flex flex-col gap-4">
                            <H4>Prévisualisation</H4>
                            <Separator />
                            <div v-html="marked(updateForm.body)" class="prose dark:prose-invert w-full"></div>
                        </div>
                    </div>
                    <div v-else class="mt-4">
                        <div class="text-muted-foreground flex-1 text-sm">Sélectionnez un contenu pour voir les détails.</div>
                    </div>
                </template>
                <template v-else>
                    <div class="mt-4 flex flex-col gap-4" v-if="updateForm?.id">
                        <div class="flex flex-row items-center justify-between">
                            <div class="flex flex-row items-center gap-1">
                                <H4>{{ !showPreview ? 'Markdown' : 'Prévisualisation' }}</H4>
                                <InputError :message="updateForm.errors.body" />
                            </div>
                            <div class="flex flex-row items-center gap-2">
                                <Switch v-model="showPreview" />
                                <Small>Prévisualisation</Small>
                            </div>
                        </div>
                        <Separator />
                        <Textarea
                            v-if="!showPreview"
                            :disabled="!hasPermissionTo(user, 'edit content')"
                            class="w-full resize-none"
                            v-model="updateForm.body"
                            @keydown="handleTabKey"
                        ></Textarea>
                        <div v-else v-html="marked(updateForm.body)" class="prose dark:prose-invert w-full"></div>
                    </div>
                    <div v-else class="mt-4">
                        <div class="text-muted-foreground flex-1 text-sm">Sélectionnez un contenu pour voir les détails.</div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
