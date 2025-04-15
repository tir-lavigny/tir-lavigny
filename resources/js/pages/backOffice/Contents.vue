<script setup lang="ts">
import ContentsTable from '@/components/back-office/ContentsTable.vue';
import InputError from '@/components/InputError.vue';
import { H3, H4 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Content, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { marked } from 'marked';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Contenu template',
        href: route('contents.index'),
    },
];

defineProps<{
    contents?: Content[];
}>();

const updateForm = useForm({
    id: null,
    title: '',
    body: '',
});

const deleteForm = useForm({
    id: null,
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
const handleTabKey = (event: KeyboardEvent) => {
    if (event.key === 'Tab') {
        event.preventDefault();
        const target = event.target as HTMLTextAreaElement;
        const start = target.selectionStart;
        const end = target.selectionEnd;
        target.value = target.value.substring(0, start) + '\t' + target.value.substring(end);
        target.selectionStart = target.selectionEnd = start + 1;
    }
};
</script>

<template>
    <Head title="Locations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border p-4 md:min-h-min" style="flex: 1 1 10%">
                <H3>Templates</H3>
                <ContentsTable
                    :contents="contents"
                    :pageSize="5"
                    @update:row-selection="handleSelection"
                    @delete:rowSelection="handleSelectionDelete"
                />
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative flex-1 rounded-xl border p-4 md:min-h-min" style="flex: 2 1 90%">
                <div class="flex flex-row justify-between">
                    <div class="flex flex-row gap-2">
                        <H3 class="text-nowrap">Contenu <template v-if="updateForm.id">de</template></H3>
                        <div class="flex flex-row items-center gap-1">
                            <Input v-if="updateForm.id" class="w-96" required type="text" placeholder="Titre" v-model="updateForm.title" />
                            <InputError :message="updateForm.errors.title" />
                        </div>
                    </div>
                    <Button :disabled="!updateForm.isDirty" @click="handleUpdate">Enregistrer</Button>
                </div>
                <div class="mt-8 grid grid-cols-2 gap-4" v-if="updateForm?.id">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-row items-center gap-1">
                            <H4>Markdown</H4>
                            <InputError :message="updateForm.errors.body" />
                        </div>
                        <hr />
                        <Textarea class="w-full resize-none" v-model="updateForm.body" @keydown="handleTabKey"></Textarea>
                    </div>
                    <div class="flex flex-col gap-4">
                        <H4>Prévisualisation</H4>
                        <hr />
                        <div v-html="marked(updateForm.body)" class="prose dark:prose-invert w-full"></div>
                    </div>
                </div>
                <div v-else class="mt-4">
                    <div class="text-muted-foreground flex-1 text-sm">Sélectionnez un contenu pour voir les détails.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
