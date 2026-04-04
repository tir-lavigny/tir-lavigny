<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MapIframe from '@/components/MapIframe.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Undo2 } from '@lucide/vue';
import { watch } from 'vue';

const props = withDefaults(
    defineProps<{
        formDefaults: {
            name: string;
            address: string;
            latitude: number;
            longitude: number;
        };
        showMap?: boolean;
        onSubmit: (form: any) => void;
    }>(),
    {
        showMap: true,
    },
);

const emit = defineEmits(['form-reset']);

const form = useForm({
    name: props.formDefaults.name,
    address: props.formDefaults.address,
    latitude: props.formDefaults.latitude,
    longitude: props.formDefaults.longitude,
});

watch(
    () => props.formDefaults,
    (newDefaults) => {
        form.defaults('name', newDefaults.name);
        form.defaults('address', newDefaults.address);
        form.defaults('latitude', newDefaults.latitude);
        form.defaults('longitude', newDefaults.longitude);
        form.reset();
    },
);

const handleSubmit = () => {
    props.onSubmit(form);
};

defineExpose({
    form,
});
</script>

<template>
    <form class="flex flex-col gap-4 px-4" @submit.prevent="handleSubmit">
        <div class="grid gap-2">
            <Label for="name">Nom du lieu</Label>
            <Input id="name" type="text" required v-model="form.name" placeholder="Nom du lieu" />
            <InputError :message="form.errors.name" />
        </div>
        <div class="grid gap-2">
            <Label for="address">Adresse</Label>
            <Input id="address" type="text" required v-model="form.address" placeholder="Adresse" />
            <InputError :message="form.errors.address" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
                <Label for="latitude">Latitude</Label>
                <Input id="latitude" type="number" step="0.000001" required v-model="form.latitude" placeholder="Latitude" />
                <InputError :message="form.errors.latitude" />
            </div>
            <div class="grid gap-2">
                <Label for="longitude">Longitude</Label>
                <Input id="longitude" type="number" step="0.000001" required v-model="form.longitude" placeholder="Longitude" />
                <InputError :message="form.errors.longitude" />
            </div>
        </div>

        <MapIframe
            v-if="showMap"
            :title="form.name"
            :latitude="form.latitude"
            :longitude="form.longitude"
            :zoom="18"
            class="h-64 w-full rounded-xl"
        />

        <div class="flex flex-row gap-2">
            <Button class="flex-grow" :disabled="!form.isDirty" type="submit">Enregistrer</Button>
            <Button
                variant="destructive"
                size="icon"
                @click="
                    form.reset();
                    emit('form-reset');
                "
                :disabled="!form.isDirty"
            >
                <Undo2 class="h-4 w-4" />
                <span class="sr-only">Annuler</span>
            </Button>
        </div>
    </form>
</template>
