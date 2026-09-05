<script setup lang="ts">
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, UserPlus } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Contacts (Infomaniak)',
        href: route('bo.dav.index'),
    },
];

interface Contact {
    url: string;
    etag: string;
    uid: string;
    fn: string;
    n: string;
    email: string;
    tel: string;
    org: string;
    category: string;
    vcard_raw: string;
}

const props = defineProps<{
    contacts: Contact[];
    existingDavUids: string[];
}>();

const page = usePage<SharedData>();
const user = page.props.auth.user;
const expandedContact = ref<string | null>(null);
const showPreviewDialog = ref(false);
const generating = ref(false);

const existingDavUidSet = computed(() => new Set(props.existingDavUids));

const contactsToImport = computed(() => props.contacts.filter((c) => c.category.includes('Actifs') && !existingDavUidSet.value.has(c.uid)));

const toggleExpand = (url: string) => {
    expandedContact.value = expandedContact.value === url ? null : url;
};

const form = useForm({});

function confirmImport() {
    generating.value = true;
    form.post(route('bo.dav.import'), {
        onSuccess: () => {
            toast.success('Membres créés avec succès.');
            showPreviewDialog.value = false;
        },
        onError: () => {
            toast.error('Erreur lors de la création des membres.');
        },
        onFinish: () => {
            generating.value = false;
        },
    });
}

onMounted(() => {
    const flash = page.props.flash as Record<string, string | undefined>;
    if (flash?.success) {
        toast.success(flash.success);
    }
    if (flash?.error) {
        toast.error(flash.error);
    }
});
</script>

<template>
    <Head title="Contacts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Contacts</H3>
                        <div class="flex items-center gap-3">
                            <Muted>{{ contacts.length }} contact{{ contacts.length !== 1 ? 's' : '' }}</Muted>
                            <Button
                                v-if="hasPermissionTo(user, 'manage members') && contactsToImport.length > 0"
                                :loading="generating"
                                variant="outline"
                                size="sm"
                                @click="showPreviewDialog = true"
                            >
                                <UserPlus class="mr-1 h-4 w-4" />
                                Générer les membres ({{ contactsToImport.length }})
                            </Button>
                        </div>
                    </div>
                </div>
                <Table v-if="contacts.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nom</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Téléphone</TableHead>
                            <TableHead>Catégorie</TableHead>
                            <TableHead class="w-10"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="contact in contacts" :key="contact.url">
                            <TableRow>
                                <TableCell class="font-medium">{{ contact.fn || '—' }}</TableCell>
                                <TableCell>{{ contact.email || '—' }}</TableCell>
                                <TableCell>{{ contact.tel || '—' }}</TableCell>
                                <TableCell>{{ contact.category || '—' }}</TableCell>
                                <TableCell>
                                    <Button variant="ghost" size="icon" @click="toggleExpand(contact.url)">
                                        <ChevronUp v-if="expandedContact === contact.url" class="size-4" />
                                        <ChevronDown v-else class="size-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="expandedContact === contact.url">
                                <TableCell colspan="5">
                                    <pre class="bg-muted max-h-60 overflow-auto rounded p-4 text-xs">{{ contact.vcard_raw }}</pre>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>

                <Muted v-else class="flex items-center justify-center py-8"> Aucun contact trouvé dans le carnet d'adresses. </Muted>
            </div>
        </div>

        <Dialog v-model:open="showPreviewDialog">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Aperçu des membres à créer</DialogTitle>
                    <DialogDescription>
                        <template v-if="contactsToImport.length > 0">
                            {{ contactsToImport.length }} membre(s) seront créé(s) à partir des contacts de catégorie "Actifs".
                        </template>
                        <template v-else> Aucun contact à importer. </template>
                    </DialogDescription>
                </DialogHeader>
                <div v-if="contactsToImport.length > 0" class="max-h-96 overflow-y-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nom</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Téléphone</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="contact in contactsToImport" :key="contact.uid">
                                <TableCell class="font-medium">{{ contact.fn || '—' }}</TableCell>
                                <TableCell>{{ contact.email || '—' }}</TableCell>
                                <TableCell>{{ contact.tel || '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showPreviewDialog = false">Annuler</Button>
                    <Button :disabled="contactsToImport.length === 0 || generating" :loading="generating" @click="confirmImport">
                        <UserPlus class="mr-1 h-4 w-4" /> Confirmer la création
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
