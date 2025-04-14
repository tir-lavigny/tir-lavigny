<script setup lang="ts">
import LocationsList from '@/components/back-office/LocationsList.vue';
import InputError from '@/components/InputError.vue';
import MapIframe from '@/components/MapIframe.vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { Location, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Undo2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Locations',
        href: route('dashboard'),
    },
];

const props = defineProps<{
    locations?: Location[];
}>();

const isSheetOpen = ref(false);
const currentLocation = ref<Location | null>(null);
const handleEditLocation = (id: number) => {
    currentLocation.value = props.locations?.find((location) => location.id === id) || null;
    if (!currentLocation.value) {
        toast.error('Location not found');
        return;
    }

    updateForm.clearErrors();

    updateForm.defaults('name', currentLocation.value.name);
    updateForm.defaults('address', currentLocation.value.address);
    updateForm.defaults('latitude', currentLocation.value.latitude);
    updateForm.defaults('longitude', currentLocation.value.longitude);

    updateForm.reset();

    isSheetOpen.value = true;
};

const updateForm = useForm({
    name: '',
    address: '',
    latitude: 0,
    longitude: 0,
});

const submitUpdateForm = () => {
    updateForm.put(route('locations.update', currentLocation.value?.id), {
        onSuccess: () => {
            toast.success('Location updated successfully');
            isSheetOpen.value = false;
        },
        onError: () => {
            toast.error('Failed to update location');
        },
    });
};
</script>

<template>
    <Head title="Locations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-32 overflow-hidden rounded-xl border md:col-span-2">
                    <PlaceholderPattern />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-32 overflow-hidden rounded-xl border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <H3>Lieux</H3>
                <LocationsList v-if="locations && locations.length" :locations="locations" @edit-location="handleEditLocation" />
            </div>
        </div>

        <Sheet :open="isSheetOpen" @update:open="isSheetOpen = $event">
            <SheetContent class="w-full sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle>Édition de "{{ currentLocation?.name }}"</SheetTitle>
                    <SheetDescription>
                        {{ currentLocation?.address }}
                    </SheetDescription>
                </SheetHeader>
                <form class="flex flex-col gap-4 px-4" @submit.prevent="submitUpdateForm">
                    <div class="grid gap-2">
                        <Label for="name">Nom du lieu</Label>
                        <Input id="name" type="text" required v-model="updateForm.name" placeholder="Nom de la location" />
                        <InputError :message="updateForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="address">Adresse</Label>
                        <Input id="address" type="text" required v-model="updateForm.address" placeholder="Adress" />
                        <InputError :message="updateForm.errors.address" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="latitude">Latitude</Label>
                            <Input id="latitude" type="number" step="0.000001" required v-model="updateForm.latitude" placeholder="Latitude" />
                            <InputError :message="updateForm.errors.latitude" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="longitude">Longitude</Label>
                            <Input id="longitude" type="number" step="0.000001" required v-model="updateForm.longitude" placeholder="Longitude" />
                            <InputError :message="updateForm.errors.longitude" />
                        </div>
                    </div>

                    <MapIframe
                        :title="updateForm.name"
                        :latitude="updateForm.latitude"
                        :longitude="updateForm.longitude"
                        :zoom="18"
                        class="h-64 w-full rounded-xl"
                    />

                    <div class="flex flex-row gap-2">
                        <Button class="flex-grow" :disabled="!updateForm.isDirty" type="submit">Enregistrer</Button>
                        <Button variant="destructive" size="icon" @click="updateForm.reset()" :disabled="!updateForm.isDirty">
                            <Undo2 class="h-4 w-4" />
                            <span class="sr-only">Annuler</span>
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
