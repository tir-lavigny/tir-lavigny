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
        title: "Séries d'événements",
        href: route('bo.event-chains.index'),
    },
    {
        title: 'Création',
        href: route('bo.event-chains.create'),
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

const createForm = useForm<{
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
}>({
    title: '',
    recurrence_type: 'weekly',
    interval: 1,
    interval_unit: 'weeks',
    day_of_week: null,
    day_of_month: null,
    start_date: '',
    end_date: '',
    start_time: '',
    end_time: '',
    location_id: null,
    content_id: null,
    custom_body: '',
});

const showPreview = ref(false);
const bufferContent = ref('');

const needsDayOfWeek = computed(() => ['weekly', 'biweekly'].includes(createForm.recurrence_type));
const needsDayOfMonth = computed(() => createForm.recurrence_type === 'monthly');
const needsInterval = computed(() => createForm.recurrence_type === 'custom');

const handleTemplateChange = (id: string | number) => {
    const template = props.templates.find((t) => t.id === +id);
    createForm.content_id = template ? template.id : null;
    bufferContent.value = template ? template.body : '';
};

const saveChain = () => {
    createForm.custom_body = bufferContent.value;
    createForm.post(route('bo.event-chains.store'), {
        preserveState: true,
        onSuccess: () => {
            createForm.reset();
            bufferContent.value = '';
            toast.success('La série a été créée et les événements générés avec succès.');
        },
        onError: () => {
            toast.error('Une erreur est survenue lors de la création de la série.', {
                description: 'Veuillez vérifier les informations saisies.',
            });
        },
    });
};
</script>

<template>
    <Head title="Création d'une série d'événements" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <H1>Création d'une série d'événements</H1>
            <div class="flex flex-row gap-2">
                <Tooltip :disabled="!createForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" :disabled="!createForm.isDirty" @click="saveChain">
                            <Save />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Créer la série</p></TooltipContent>
                </Tooltip>
                <Tooltip :disabled="!createForm.isDirty">
                    <TooltipTrigger>
                        <Button size="icon" variant="destructive" :disabled="!createForm.isDirty" @click="createForm.reset()">
                            <Undo2 />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent><p>Réinitialiser</p></TooltipContent>
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
                            <Input id="title" type="text" v-model="createForm.title" placeholder="Titre de la série" />
                            <InputError :message="createForm.errors.title" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_date">Date de début</Label>
                                <Input id="start_date" type="date" v-model="createForm.start_date" />
                                <InputError :message="createForm.errors.start_date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_date">Date de fin</Label>
                                <Input id="end_date" type="date" v-model="createForm.end_date" />
                                <InputError :message="createForm.errors.end_date" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label for="start_time">Heure de début</Label>
                                <Input id="start_time" type="time" v-model="createForm.start_time" />
                                <InputError :message="createForm.errors.start_time" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="end_time">
                                    Heure de fin
                                    <span class="text-muted-foreground text-xs">(Optionnel)</span>
                                </Label>
                                <Input id="end_time" type="time" v-model="createForm.end_time" />
                                <InputError :message="createForm.errors.end_time" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                    <H3>Récurrence</H3>
                    <div class="mt-4 flex flex-col gap-4">
                        <div class="grid gap-2">
                            <Label>Type de récurrence</Label>
                            <Select v-model="createForm.recurrence_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Choisir un type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in recurrenceTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="createForm.errors.recurrence_type" />
                        </div>

                        <div v-if="needsDayOfWeek" class="grid gap-2">
                            <Label>Jour de la semaine</Label>
                            <Select
                                :model-value="String(createForm.day_of_week ?? '')"
                                @update:model-value="createForm.day_of_week = $event !== '' ? Number($event) : null"
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
                            <InputError :message="createForm.errors.day_of_week" />
                        </div>

                        <div v-if="needsDayOfMonth" class="grid gap-2">
                            <Label for="day_of_month">Jour du mois</Label>
                            <Input
                                id="day_of_month"
                                type="number"
                                min="1"
                                max="31"
                                :model-value="createForm.day_of_month ?? undefined"
                                @update:model-value="createForm.day_of_month = $event !== '' ? Number($event) : null"
                                placeholder="ex: 15"
                            />
                            <InputError :message="createForm.errors.day_of_month" />
                        </div>

                        <template v-if="needsInterval">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="grid gap-2">
                                    <Label for="interval">Intervalle</Label>
                                    <Input
                                        id="interval"
                                        type="number"
                                        min="1"
                                        :model-value="createForm.interval ?? undefined"
                                        @update:model-value="createForm.interval = $event !== '' ? Number($event) : null"
                                        placeholder="ex: 2"
                                    />
                                    <InputError :message="createForm.errors.interval" />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Unité</Label>
                                    <Select
                                        :model-value="createForm.interval_unit ?? ''"
                                        @update:model-value="createForm.interval_unit = $event ? String($event) : null"
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Unité" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="days">Jours</SelectItem>
                                            <SelectItem value="weeks">Semaines</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="createForm.errors.interval_unit" />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="border-sidebar-border/70 dark:border-sidebar-border relative overflow-hidden rounded-xl border p-4">
                    <H3>Lieu & contenu</H3>
                    <div class="mt-4 flex flex-col gap-4">
                        <DropdownSelect
                            :modelValue="String(createForm.location_id ?? 0)"
                            :options="[{ label: 'Pas de lieu', value: '0' }, ...props.locations.map((l) => ({ label: l.name, value: String(l.id) }))]"
                            @update:modelValue="createForm.location_id = $event && +$event ? Number($event) : null"
                            placeholder="Sélectionner un lieu"
                            groupLabel="Lieux"
                            label="Lieu"
                        />
                        <DropdownSelect
                            :modelValue="String(createForm.content_id ?? 0)"
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

            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="flex flex-row items-center justify-between">
                    <div class="flex items-center gap-4">
                        <H3>Contenu personnalisé ({{ !showPreview ? 'markdown' : 'prévisualisation' }})</H3>
                        <span class="text-muted-foreground text-sm">Optionnel — remplace le template si renseigné</span>
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
                        @input="createForm.custom_body = bufferContent"
                        placeholder="Contenu en markdown..."
                    />
                    <div v-show="showPreview && bufferContent" v-html="marked(bufferContent)" class="prose dark:prose-invert w-full" />
                    <p v-show="showPreview && !bufferContent" class="text-muted-foreground text-sm">Aucun contenu saisi.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
