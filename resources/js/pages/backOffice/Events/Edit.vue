<script setup lang="ts">
import DropdownSelect from '@/components/back-office/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import MapIframe from '@/components/MapIframe.vue';
import { H1, H3, H4 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { handleTabKey, toLocalDatetimeString } from '@/lib/utils';
import { Content, Event, Location, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from '@lucide/vue';
import { marked } from 'marked';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    event: Event;
    content?: string;
    contentSource?: string;
    templates: Content[];
    locations: Location[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Événements',
        href: route('bo.events.index'),
    },
    {
        title: `${props.event.title}`,
        href: route('bo.events.show', { event: props.event.id }),
    },
    {
        title: 'Modification',
        href: route('bo.events.edit', { event: props.event.id }),
    },
];

const isInitialContentTemplate = computed(() => props.contentSource?.startsWith('template'));
const isContentTemplate = ref(isInitialContentTemplate.value);

const formatDateTimeLocal = (dateString?: string) => {
    if (!dateString) return undefined;
    return toLocalDatetimeString(dateString);
};

const updateForm = useForm({
    id: props.event.id,
    title: props.event.title,
    start_datetime: formatDateTimeLocal(props.event.start_datetime),
    end_datetime: formatDateTimeLocal(props.event.end_datetime),
    body: props.content,
    template_id: computed(() => (isContentTemplate.value ? parseInt(props.contentSource?.split('_')[1] || '0', 10) : undefined)).value,
    location_id: props.event.location_id,
});

const selectedLocation = computed(() => props.locations.find((location) => location.id === updateForm.location_id));

const showPreview = ref(isContentTemplate.value);
const bufferContent = ref(props.content);

const handleContentChange = (id: string | number) => {
    const template: Content | undefined = props.templates.find((template: Content) => template.id === +id);
    showPreview.value = false;
    isContentTemplate.value = Boolean(template);
    updateForm.template_id = template ? template.id : undefined;
    updateForm.body = template ? template.body : '';
    bufferContent.value = template ? template.body : '';
};

const saveChanges = () => {
    if (updateForm.template_id) {
        updateForm.body = ''; // Clear the body if a template is selected
    } else {
        updateForm.body = bufferContent.value; // Use the buffer content
    }
    if (updateForm.location_id === 0) {
        updateForm.location_id = undefined;
    }
    updateForm.put(route('bo.events.update', { event: props.event.id }), {
        preserveState: true,
        onSuccess: () => {
            toast.success('Événement mis à jour avec succès');
        },
        onError: () => {
            toast.error("Erreur lors de la mise à jour de l'événement");
        },
    });
};

const resetForm = () => {
    updateForm.reset();
    isContentTemplate.value = isInitialContentTemplate.value;
    bufferContent.value = props.content;
};
</script>

<template>
    <Head title="Événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1 class=""> Modification de : {{ event.title }} </H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!updateForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!updateForm.isDirty" @click="saveChanges">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>Enregistrer les modifications</p>
                    </TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!updateForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!updateForm.isDirty" @click="resetForm()">
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
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border p-4">
                    <H4>Événements</H4>
                    <div class="mt-6 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="title">Titre</Label>
                            <Input id="title" type="text" required v-model="updateForm.title" placeholder="Titre" />
                            <InputError :message="updateForm.errors.title" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_datetime">Début</Label>
                                <Input id="start_datetime" type="datetime-local" required v-model="updateForm.start_datetime" placeholder="Début" />
                                <InputError :message="updateForm.errors.start_datetime" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_datetime">Fin <span class="text-muted-foreground text-xs">(Optionnel)</span></Label>
                                <Input id="end_datetime" type="datetime-local" required v-model="updateForm.end_datetime" placeholder="Fin" />
                                <InputError :message="updateForm.errors.end_datetime" />
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative flex aspect-video flex-col justify-between gap-6 overflow-hidden rounded-xl border p-4"
                >
                    <DropdownSelect
                        :modelValue="String(updateForm.location_id)"
                        :options="[
                            { label: 'Pas de lieu', value: '0' },
                            ...props.locations.map((location) => ({ label: location.name, value: String(location.id) })),
                        ]"
                        @update:modelValue="updateForm.location_id = $event ? Number($event) : undefined"
                        placeholder="Sélectionner un lieu"
                        groupLabel="Lieux"
                        label="Lieu"
                    />
                    <MapIframe :latitude="selectedLocation?.latitude || 0" :longitude="selectedLocation?.longitude || 0" :zoom="15" height="100%" />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border p-4">
                    <DropdownSelect
                        :modelValue="String(updateForm.template_id)"
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
                    </div>
                    <div v-if="!isContentTemplate" class="flex flex-row items-center gap-2">
                        <Switch v-model="showPreview" />
                        <Small>Prévisualisation</Small>
                    </div>
                </div>
                <Separator />
                <template v-if="isContentTemplate">
                    <div v-if="content && bufferContent" class="prose dark:prose-invert mt-8 w-full" v-html="marked(bufferContent)" />
                </template>
                <template v-else>
                    <div class="mt-4 flex flex-col gap-4" v-if="updateForm?.id">
                        <Textarea
                            v-show="!showPreview"
                            class="w-full resize-none"
                            v-model="bufferContent"
                            @keydown="handleTabKey"
                            @input="updateForm.body = bufferContent"
                        />
                        <div v-if="bufferContent" v-show="showPreview" v-html="marked(bufferContent)" class="prose dark:prose-invert w-full" />
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
