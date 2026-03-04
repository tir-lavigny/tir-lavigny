<script setup lang="ts">
import DropdownSelect from '@/components/back-office/DropdownSelect.vue';
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { handleTabKey } from '@/lib/utils';
import { Content, EventChain, Location, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Undo2 } from 'lucide-vue-next';
import { marked } from 'marked';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    chain: EventChain;
    templates: Content[];
    locations: Location[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Séries d'événements",
        href: route('bo.event-chains.index'),
    },
    {
        title: props.chain.title,
        href: route('bo.event-chains.show', props.chain.id),
    },
    {
        title: 'Modification',
        href: route('bo.event-chains.edit', props.chain.id),
    },
];

const recurrenceTypes = [
    { value: 'daily', label: 'Quotidien' },
    { value: 'weekly', label: 'Hebdomadaire' },
    { value: 'biweekly', label: 'Bihebdomadaire' },
    { value: 'monthly', label: 'Mensuel' },
    { value: 'custom', label: 'Personnalisé' },
];

const daysOfWeek = [
    { value: '0', label: 'Lundi' },
    { value: '1', label: 'Mardi' },
    { value: '2', label: 'Mercredi' },
    { value: '3', label: 'Jeudi' },
    { value: '4', label: 'Vendredi' },
    { value: '5', label: 'Samedi' },
    { value: '6', label: 'Dimanche' },
];

const updateForm = useForm<{
    title: string;
    recurrence_type: string;
    interval: number | null;
    interval_unit: string | null;
    day_of_week: number | null;
    day_of_month: number | null;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string;
    location_id: number | null;
    content_id: number | null;
    custom_body: string;
    update_scope: 'all' | 'from_date';
    from_date: string;
}>({
    title: props.chain.title,
    recurrence_type: props.chain.recurrence_type,
    interval: props.chain.interval,
    interval_unit: props.chain.interval_unit,
    day_of_week: props.chain.day_of_week,
    day_of_month: props.chain.day_of_month,
    start_date: props.chain.start_date,
    end_date: props.chain.end_date,
    start_time: props.chain.start_time.slice(0, 5),
    end_time: props.chain.end_time?.slice(0, 5) ?? '',
    location_id: props.chain.location_id,
    content_id: props.chain.content_id,
    custom_body: props.chain.custom_body ?? '',
    update_scope: 'all',
    from_date: '',
});

const showPreview = ref(false);
const bufferContent = ref(props.chain.custom_body ?? '');

const needsDayOfWeek = computed(() => ['weekly', 'biweekly'].includes(updateForm.recurrence_type));
const needsDayOfMonth = computed(() => updateForm.recurrence_type === 'monthly');
const needsInterval = computed(() => updateForm.recurrence_type === 'custom');

const handleTemplateChange = (id: string | number) => {
    const template = props.templates.find((t) => t.id === +id);
    updateForm.content_id = template ? template.id : null;
};

const saveChanges = () => {
    updateForm.custom_body = bufferContent.value;
    updateForm.put(route('bo.event-chains.update', props.chain.id), {
        preserveState: true,
        onSuccess: () => {
            toast.success('Série mise à jour avec succès.');
        },
        onError: () => {
            toast.error('Une erreur est survenue lors de la mise à jour.', {
                description: 'Veuillez vérifier les informations saisies.',
            });
        },
    });
};

const resetForm = () => {
    updateForm.reset();
    bufferContent.value = props.chain.custom_body ?? '';
};
</script>

<template>
    <Head :title="`Modification : ${chain.title}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Modification : {{ chain.title }}</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!updateForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!updateForm.isDirty" @click="saveChanges">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Enregistrer les modifications</p></TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!updateForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!updateForm.isDirty" @click="resetForm">
                            <Undo2 />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Annuler les modifications</p></TooltipContent>
                </Tooltip>
            </div>
        </div>

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                    <H3>Informations générales</H3>
                    <div class="mt-4 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label for="title">Titre</Label>
                            <Input id="title" type="text" v-model="updateForm.title" placeholder="Titre de la série" />
                            <InputError :message="updateForm.errors.title" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_date">Date de début</Label>
                                <Input id="start_date" type="date" v-model="updateForm.start_date" />
                                <InputError :message="updateForm.errors.start_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_date">Date de fin</Label>
                                <Input id="end_date" type="date" v-model="updateForm.end_date" />
                                <InputError :message="updateForm.errors.end_date" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_time">Heure de début</Label>
                                <Input id="start_time" type="time" v-model="updateForm.start_time" />
                                <InputError :message="updateForm.errors.start_time" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_time">
                                    Heure de fin
                                    <span class="text-muted-foreground text-xs">(Optionnel)</span>
                                </Label>
                                <Input id="end_time" type="time" v-model="updateForm.end_time" />
                                <InputError :message="updateForm.errors.end_time" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                    <H3>Récurrence</H3>
                    <div class="mt-4 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label>Type de récurrence</Label>
                            <Select v-model="updateForm.recurrence_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Choisir un type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in recurrenceTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="updateForm.errors.recurrence_type" />
                        </div>
                        <div v-if="needsDayOfWeek" class="grid gap-2">
                            <Label>Jour de la semaine</Label>
                            <Select
                                :model-value="String(updateForm.day_of_week ?? '')"
                                @update:model-value="updateForm.day_of_week = $event !== '' ? Number($event) : null"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Choisir un jour" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="day in daysOfWeek" :key="day.value" :value="day.value">
                                        {{ day.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="updateForm.errors.day_of_week" />
                        </div>
                        <div v-if="needsDayOfMonth" class="grid gap-2">
                            <Label for="day_of_month">Jour du mois</Label>
                            <Input
                                id="day_of_month"
                                type="number"
                                min="1"
                                max="31"
                                :model-value="updateForm.day_of_month ?? undefined"
                                @update:model-value="updateForm.day_of_month = $event !== '' ? Number($event) : null"
                            />
                            <InputError :message="updateForm.errors.day_of_month" />
                        </div>
                        <template v-if="needsInterval">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="grid gap-2">
                                    <Label for="interval">Intervalle</Label>
                                    <Input
                                        id="interval"
                                        type="number"
                                        min="1"
                                        :model-value="updateForm.interval ?? undefined"
                                        @update:model-value="updateForm.interval = $event !== '' ? Number($event) : null"
                                    />
                                    <InputError :message="updateForm.errors.interval" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Unité</Label>
                                    <Select
                                        :model-value="updateForm.interval_unit ?? ''"
                                        @update:model-value="updateForm.interval_unit = $event ? String($event) : null"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Unité" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="days">Jours</SelectItem>
                                            <SelectItem value="weeks">Semaines</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="updateForm.errors.interval_unit" />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                    <H3>Lieu & contenu</H3>
                    <div class="mt-4 flex flex-col gap-4">
                        <DropdownSelect
                            :modelValue="String(updateForm.location_id ?? 0)"
                            :options="[{ label: 'Pas de lieu', value: '0' }, ...props.locations.map((l) => ({ label: l.name, value: String(l.id) }))]"
                            @update:modelValue="updateForm.location_id = $event && +$event ? Number($event) : null"
                            placeholder="Sélectionner un lieu"
                            groupLabel="Lieux"
                            label="Lieu"
                        />
                        <DropdownSelect
                            :modelValue="String(updateForm.content_id ?? 0)"
                            :options="[
                                { label: 'Contenu personnalisé', value: '0' },
                                ...props.templates.map((t) => ({ label: t.title, value: String(t.id) })),
                            ]"
                            @update:modelValue="handleTemplateChange($event)"
                            placeholder="Sélectionner un template"
                            groupLabel="Templates"
                            label="Template de contenu"
                        />
                    </div>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                <H3>Portée de la mise à jour</H3>
                <p class="text-muted-foreground mb-4 text-sm">
                    Définissez quels événements liés seront affectés par les modifications des champs propageable (titre, lieu, contenu, horaires).
                </p>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="grid gap-2">
                        <Label>Appliquer à</Label>
                        <Select v-model="updateForm.update_scope">
                            <SelectTrigger class="w-48">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Tous les événements</SelectItem>
                                <SelectItem value="from_date">À partir d'une date</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="updateForm.errors.update_scope" />
                    </div>
                    <div v-if="updateForm.update_scope === 'from_date'" class="grid gap-2">
                        <Label for="from_date">À partir du</Label>
                        <Input id="from_date" type="date" v-model="updateForm.from_date" />
                        <InputError :message="updateForm.errors.from_date" />
                    </div>
                </div>
            </div>

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <div class="flex items-center gap-4">
                        <H3>Contenu personnalisé ({{ !showPreview ? 'markdown' : 'prévisualisation' }})</H3>
                        <span class="text-muted-foreground text-sm">Optionnel</span>
                    </div>
                    <div class="flex flex-row items-center gap-2">
                        <Switch v-model="showPreview" />
                        <Small>Prévisualisation</Small>
                    </div>
                </div>
                <Separator />
                <div class="mt-4 flex flex-col gap-4">
                    <Textarea
                        v-show="!showPreview"
                        class="w-full resize-none"
                        v-model="bufferContent"
                        @keydown="handleTabKey"
                        @input="updateForm.custom_body = bufferContent"
                        placeholder="Contenu en markdown..."
                    />
                    <div v-show="showPreview && bufferContent" v-html="marked(bufferContent)" class="prose dark:prose-invert w-full" />
                    <p v-show="showPreview && !bufferContent" class="text-muted-foreground text-sm">Aucun contenu saisi.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
