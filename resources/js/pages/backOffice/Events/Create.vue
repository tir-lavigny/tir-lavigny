<script setup lang="ts">
import DropdownSelect from '@/components/back-office/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { handleTabKey } from '@/lib/utils';
import { Content, Location, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from 'lucide-vue-next';
import { marked } from 'marked';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    templates: Content[];
    locations: Location[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Événements',
        href: route('bo.events.index'),
    },
    {
        title: 'Création',
        href: route('bo.events.create'),
    },
];

const createForm = useForm<{
    title: string;
    start_datetime: string;
    end_datetime: string;
    body: string;
    template_id: number | null;
    location_id: number | null;
}>({
    title: '',
    start_datetime: '',
    end_datetime: '',
    body: '',
    template_id: null,
    location_id: null,
});

const isContentTemplate = computed(() => {
    return createForm.template_id !== null && createForm.template_id !== 0;
});
const showPreview = ref(isContentTemplate.value);
const bufferContent = ref('');

const handleContentChange = (id: string | number) => {
    const template: Content | undefined = props.templates.find((template: Content) => template.id === +id);
    createForm.template_id = template ? template.id : null;
    createForm.body = template ? template.body : '';
    bufferContent.value = template ? template.body : '';
    showPreview.value = isContentTemplate.value;
};

const saveEvent = () => {
    if (createForm.template_id) {
        createForm.body = ''; // Clear the body if a template is selected
    } else {
        createForm.body = bufferContent.value; // Use the buffer content
    }
    if (createForm.location_id === 0) {
        createForm.location_id = null;
    }
    createForm.post(route('bo.events.store'), {
        preserveState: true,
        onSuccess: () => {
            createForm.reset();
            bufferContent.value = '';
            toast.success("L'événement a été créé avec succès.");
        },
        onError: () => {
            toast.error("Une erreur est survenue lors de la création de l'événement.", {
                description: 'Veuillez vérifier les informations saisies.',
            });
        },
        onFinish: () => {
            createForm.clearErrors();
        },
    });
};

const resetForm = () => {
    createForm.reset();
};
</script>

<template>
    <Head title="Création d'un événement" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1> Création d'un événement </H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!createForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!createForm.isDirty" @click="saveEvent">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Créer l'événement</p>
                    </TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!createForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!createForm.isDirty" @click="resetForm">
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
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border p-4">
                    <H3>Informations générales</H3>
                    <div class="mt-6 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="title">Titre</Label>
                            <Input id="title" type="text" required v-model="createForm.title" placeholder="Titre" />
                            <InputError :message="createForm.errors.title" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_datetime">Début</Label>
                                <Input id="start_datetime" type="datetime-local" required v-model="createForm.start_datetime" placeholder="Début" />
                                <InputError :message="createForm.errors.start_datetime" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_datetime">Fin <span class="text-muted-foreground text-xs">(Optionnel)</span></Label>
                                <Input id="end_datetime" type="datetime-local" v-model="createForm.end_datetime" placeholder="Fin" />
                                <InputError :message="createForm.errors.end_datetime" />
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative flex aspect-video flex-col justify-between gap-6 overflow-hidden rounded-xl border p-4"
                >
                    <DropdownSelect
                        :modelValue="String(createForm.location_id)"
                        :options="[
                            { label: 'Pas de lieu', value: '0' },
                            ...props.locations.map((location) => ({ label: location.name, value: String(location.id) })),
                        ]"
                        @update:modelValue="createForm.location_id = $event ? Number($event) : null"
                        placeholder="Sélectionner un lieu"
                        groupLabel="Lieux"
                        label="Lieu"
                    />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border p-4">
                    <DropdownSelect
                        :modelValue="String(createForm.template_id)"
                        :options="[
                            { label: 'Contenu personnalisé', value: '0' },
                            ...props.templates.map((template) => ({ label: template.title, value: String(template.id) })),
                        ]"
                        @update:modelValue="handleContentChange($event)"
                        placeholder="Sélectionner une template"
                        groupLabel="Templates"
                        label="Template de contenu"
                    />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <div class="flex items-center gap-4">
                        <H3>Contenu ({{ !showPreview ? 'markdown' : 'prévisualisation' }})</H3>
                        <span class="text-muted-foreground text-sm">
                            {{ isContentTemplate ? 'Template de contenu' : "Contenu de l'événement" }}
                        </span>
                        <InputError :message="createForm.errors.body" />
                    </div>
                    <div v-if="!isContentTemplate" class="flex flex-row items-center gap-2">
                        <Switch v-model="showPreview" />
                        <Small>Prévisualisation</Small>
                    </div>
                </div>
                <Separator />
                <template v-if="isContentTemplate">
                    <div v-if="bufferContent" class="prose dark:prose-invert mt-8 w-full" v-html="marked(bufferContent)" />
                </template>
                <template v-else>
                    <div class="mt-4 flex flex-col gap-4">
                        <Textarea
                            v-show="!showPreview"
                            class="w-full resize-none"
                            v-model="bufferContent"
                            @keydown="handleTabKey"
                            @input="createForm.body = bufferContent"
                        />
                        <div v-show="showPreview" v-html="marked(bufferContent)" class="prose dark:prose-invert w-full" />
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
