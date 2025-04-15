<script setup lang="ts">
import LocationForm from '@/components/back-office/LocationForm.vue';
import LocationsList from '@/components/back-office/LocationsList.vue';
import MapIframe from '@/components/MapIframe.vue';
import { H3 } from '@/components/typography/headings';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import { Location, type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';
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

const createLocationForm = useTemplateRef('createLocationForm');
const isSheetOpen = ref(false);
const currentLocation = ref<Location | null>(null);

const handleEditLocation = (id: number) => {
    currentLocation.value = props.locations?.find((location) => location.id === id) || null;
    if (!currentLocation.value) {
        toast.error('Location not found');
        return;
    }
    isSheetOpen.value = true;
};

const handleSubmitUpdate = (form: any) => {
    form.put(route('locations.update', currentLocation.value?.id), {
        onSuccess: () => {
            toast.success('Lieu mis à jour avec succès');
            form.reset();
            isSheetOpen.value = false;
        },
        onError: () => {
            toast.error('Échec de la mise à jour du lieu');
        },
    });
};

const handleSubmitCreate = (form: any) => {
    form.post(route('locations.store'), {
        onSuccess: () => {
            toast.success('Lieu créé avec succès');
            form.reset();
        },
        onError: () => {
            toast.error('Échec de la création du lieu');
        },
    });
};

const handleSubmitDelete = () => {
    const form = useForm({
        id: currentLocation.value?.id,
    });
    form.delete(route('locations.destroy', currentLocation.value?.id), {
        onSuccess: () => {
            toast.success('Lieu supprimé avec succès');
            form.reset();
            isSheetOpen.value = false;
        },
        onError: (error) => {
            const errorMessage = Object.entries(error)
                .map(([, value]) => `${value}`)
                .join(', ');
            toast.error(errorMessage);
        },
    });
};
</script>

<template>
    <Head title="Locations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div
                    class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-32 overflow-hidden rounded-xl border p-4 md:col-span-2"
                >
                    <H3>Créer un lieu</H3>
                    <LocationForm
                        ref="createLocationForm"
                        class="mt-6"
                        :formDefaults="{
                            name: '',
                            address: '',
                            latitude: 0,
                            longitude: 0,
                        }"
                        :showMap="false"
                        @submit="handleSubmitCreate"
                    />
                </div>
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-32 overflow-hidden rounded-xl border p-4">
                    <MapIframe
                        title="Map"
                        :latitude="createLocationForm?.form.latitude || 0"
                        :longitude="createLocationForm?.form.longitude || 0"
                        :zoom="18"
                        class="h-64 w-full rounded-xl"
                    />
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <H3>Lieux</H3>
                <LocationsList class="mt-6" v-if="locations && locations.length" :locations="locations" @edit-location="handleEditLocation" />
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
                <LocationForm
                    v-if="currentLocation"
                    :formDefaults="{
                        name: currentLocation.name,
                        address: currentLocation.address,
                        latitude: currentLocation.latitude,
                        longitude: currentLocation.longitude,
                    }"
                    @submit="handleSubmitUpdate"
                />
                <Dialog>
                    <DialogTrigger class="mt-4 flex justify-end px-4"> <Button variant="destructive"> Supprimer le lieu </Button> </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Supprimer le lieu</DialogTitle>
                            <DialogDescription> Êtes-vous sûr de vouloir supprimer ce lieu ? Cette action est irréversible. </DialogDescription>
                        </DialogHeader>

                        <DialogFooter>
                            <form @submit.prevent="handleSubmitDelete">
                                <Button variant="destructive" type="submit"> Supprimer le lieu </Button>
                            </form>
                            <DialogClose>
                                <Button variant="secondary"> Annuler </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SheetContent>
        </Sheet>
    </AppLayout>
</template>
