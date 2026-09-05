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
import { ChevronDown, ChevronUp, RefreshCw, UserPlus } from '@lucide/vue';
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

interface SyncPair {
    member_id: number;
    member_first_name: string;
    member_last_name: string;
    dav_first_name: string;
    dav_last_name: string;
    dav_uid: string;
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

const showSyncDialog = ref(false);
const syncPairs = ref<SyncPair[]>([]);
const syncChoices = ref<Record<number, 'member' | 'dav'>>({});
const loadingSync = ref(false);
const syncing = ref(false);

const existingDavUidSet = computed(() => new Set(props.existingDavUids));

const contactsToImport = computed(() =>
    props.contacts.filter((c) => c.category.includes('Actifs') && !existingDavUidSet.value.has(c.uid)),
);

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

// ── Sync ──

async function fetchSyncPreview() {
    loadingSync.value = true;
    try {
        const response = await fetch(route('bo.dav.sync.preview'), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
        });
        const data = await response.json();
        syncPairs.value = data.pairs;
        syncChoices.value = {};
        for (const pair of data.pairs) {
            syncChoices.value[pair.member_id] = 'member';
        }
        showSyncDialog.value = true;
    } catch {
        toast.error('Erreur lors du chargement de l\'aperçu de synchronisation.');
    } finally {
        loadingSync.value = false;
    }
}

function setAllChoices(source: 'member' | 'dav') {
    for (const pair of syncPairs.value) {
        syncChoices.value[pair.member_id] = source;
    }
}

const syncForm = useForm<{ choices: { member_id: number; source: string }[] }>({ choices: [] });

function confirmSync() {
    syncing.value = true;
    syncForm.choices = syncPairs.value.map((p) => ({
        member_id: p.member_id,
        source: syncChoices.value[p.member_id] ?? 'member',
    }));
    syncForm.post(route('bo.dav.sync'), {
        onSuccess: () => {
            toast.success('Synchronisation effectuée avec succès.');
            showSyncDialog.value = false;
            syncPairs.value = [];
            syncChoices.value = {};
        },
        onError: () => {
            toast.error('Erreur lors de la synchronisation.');
        },
        onFinish: () => {
            syncing.value = false;
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
                            <Button
                                v-if="hasPermissionTo(user, 'manage members')"
                                :loading="loadingSync"
                                variant="outline"
                                size="sm"
                                @click="fetchSyncPreview"
                            >
                                <RefreshCw class="mr-1 h-4 w-4" />
                                Synchroniser
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

        <!-- Import dialog -->
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

        <!-- Sync dialog -->
        <Dialog v-model:open="showSyncDialog">
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Synchroniser les noms</DialogTitle>
                    <DialogDescription>
                        <template v-if="syncPairs.length > 0">
                            {{ syncPairs.length }} modification(s) détectée(s). Choisissez la source de vérité pour chaque paire.
                        </template>
                        <template v-else> Aucune différence détectée. </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="syncPairs.length > 0">
                    <div class="mb-3 flex gap-2">
                        <Button size="sm" variant="outline" @click="setAllChoices('member')">
                            Tout Membre
                        </Button>
                        <Button size="sm" variant="outline" @click="setAllChoices('dav')">
                            Tout DAV
                        </Button>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Membre</TableHead>
                                    <TableHead>DAV</TableHead>
                                    <TableHead>Choix</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="pair in syncPairs" :key="pair.member_id">
                                    <TableCell>
                                        <span :class="syncChoices[pair.member_id] === 'member' ? 'font-semibold' : 'text-muted-foreground'">
                                            {{ pair.member_last_name }} {{ pair.member_first_name }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span :class="syncChoices[pair.member_id] === 'dav' ? 'font-semibold' : 'text-muted-foreground'">
                                            {{ pair.dav_last_name }} {{ pair.dav_first_name }}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-4">
                                            <label class="flex items-center gap-1 text-sm">
                                                <input
                                                    type="radio"
                                                    :name="'sync-' + pair.member_id"
                                                    value="member"
                                                    :checked="syncChoices[pair.member_id] === 'member'"
                                                    @change="syncChoices[pair.member_id] = 'member'"
                                                />
                                                Membre
                                            </label>
                                            <label class="flex items-center gap-1 text-sm">
                                                <input
                                                    type="radio"
                                                    :name="'sync-' + pair.member_id"
                                                    value="dav"
                                                    :checked="syncChoices[pair.member_id] === 'dav'"
                                                    @change="syncChoices[pair.member_id] = 'dav'"
                                                />
                                                DAV
                                            </label>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showSyncDialog = false">Annuler</Button>
                    <Button
                        :disabled="syncPairs.length === 0 || syncing"
                        :loading="syncing"
                        @click="confirmSync"
                    >
                        <RefreshCw class="mr-1 h-4 w-4" />
                        Synchroniser {{ syncPairs.length }} modification(s)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>