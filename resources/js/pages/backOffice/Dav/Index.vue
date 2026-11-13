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

interface SyncField {
    member: string | null;
    dav: string | null;
    base: string | null;
    conflict: boolean;
}

interface SyncMember {
    member_id: number;
    member_name: string;
    dav_uid: string;
    missing_contact: boolean;
    fields: Record<string, SyncField>;
}

interface UnlinkedContact {
    uid: string;
    name: string;
    email: string | null;
    tel: string | null;
    category: string;
    suggested_status: string | null;
    archived: boolean;
    suggested_member_id: number | null;
    suggested_member_name: string | null;
}

interface UnlinkedMember {
    member_id: number;
    member_name: string;
    email: string | null;
    status: string;
    suggested_contact_uid: string | null;
    suggested_contact_name: string | null;
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
const syncMembers = ref<SyncMember[]>([]);
const syncChoices = ref<Record<number, Record<string, 'member' | 'dav'>>>({});
const loadingSync = ref(false);
const syncing = ref(false);

const showLinkDialog = ref(false);
const unlinkedContacts = ref<UnlinkedContact[]>([]);
const unlinkedMembers = ref<UnlinkedMember[]>([]);
const linkChoices = ref<Record<string, string | null>>({});
const memberLinkChoices = ref<Record<number, string | null>>({});
const loadingLinks = ref(false);
const linking = ref(false);
const linkForm = useForm<{ member_id: number | null; dav_uid: string | null }>({ member_id: null, dav_uid: null });

const existingDavUidSet = computed(() => new Set(props.existingDavUids));

const hasCategory = (category: string, value: string): boolean =>
    category
        .split(',')
        .map((part) => part.trim())
        .includes(value);

const contactsToImport = computed(() => props.contacts.filter((c) => hasCategory(c.category, 'Actifs') && !existingDavUidSet.value.has(c.uid)));

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
        syncMembers.value = [...data.members].sort((a: SyncMember, b: SyncMember) =>
            a.member_name.localeCompare(b.member_name, 'fr'),
        );
        syncChoices.value = {};
        for (const member of data.members) {
            const row: Record<string, 'member' | 'dav'> = {};
            for (const field of Object.keys(member.fields)) {
                row[field] = 'member';
            }
            syncChoices.value[member.member_id] = row;
        }
        showSyncDialog.value = true;
    } catch {
        toast.error("Erreur lors du chargement de l'aperçu de synchronisation.");
    } finally {
        loadingSync.value = false;
    }
}

function setAllChoices(source: 'member' | 'dav') {
    for (const member of syncMembers.value) {
        const row: Record<string, 'member' | 'dav'> = {};
        for (const field of Object.keys(member.fields)) {
            row[field] = source;
        }
        syncChoices.value[member.member_id] = row;
    }
}

const syncForm = useForm<{ choices: { member_id: number; fields: Record<string, string> }[] }>({ choices: [] });

function confirmSync() {
    syncing.value = true;
    syncForm.choices = syncMembers.value.map((member) => ({
        member_id: member.member_id,
        fields: syncChoices.value[member.member_id] ?? {},
    }));
    syncForm.post(route('bo.dav.sync'), {
        onSuccess: () => {
            toast.success('Synchronisation effectuée avec succès.');
            showSyncDialog.value = false;
            syncMembers.value = [];
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

async function fetchLinkPreview() {
    loadingLinks.value = true;
    try {
        const response = await fetch(route('bo.dav.sync.preview'), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
        });
        const data = await response.json();
        unlinkedContacts.value = data.unlinked_contacts ?? [];
        unlinkedMembers.value = data.unlinked_members ?? [];
        linkChoices.value = {};
        for (const contact of unlinkedContacts.value) {
            linkChoices.value[contact.uid] = contact.suggested_member_id !== null ? String(contact.suggested_member_id) : null;
        }
        memberLinkChoices.value = {};
        for (const member of unlinkedMembers.value) {
            memberLinkChoices.value[member.member_id] = member.suggested_contact_uid ?? null;
        }
        showLinkDialog.value = true;
    } catch {
        toast.error("Erreur lors du chargement des contacts non liés.");
    } finally {
        loadingLinks.value = false;
    }
}

function sendLink(memberId: number, davUid: string) {
    linking.value = true;
    linkForm.member_id = memberId;
    linkForm.dav_uid = davUid;
    linkForm.post(route('bo.dav.link'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Contact lié avec succès.');
            fetchLinkPreview();
        },
        onError: () => toast.error('Erreur lors de la liaison.'),
        onFinish: () => {
            linking.value = false;
        },
    });
}

function linkContact(contact: UnlinkedContact) {
    const memberId = Number(linkChoices.value[contact.uid] ?? '');

    if (!memberId) {
        toast.error('Choisissez un membre.');

        return;
    }

    sendLink(memberId, contact.uid);
}

function linkMember(member: UnlinkedMember) {
    const davUid = memberLinkChoices.value[member.member_id];

    if (!davUid) {
        toast.error('Choisissez un contact.');

        return;
    }

    sendLink(member.member_id, davUid);
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
                            <Button
                                v-if="hasPermissionTo(user, 'manage members')"
                                :loading="loadingLinks"
                                variant="outline"
                                size="sm"
                                @click="fetchLinkPreview"
                            >
                                <UserPlus class="mr-1 h-4 w-4" />
                                Lier un contact
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

        <!-- Link dialog -->
        <Dialog v-model:open="showLinkDialog">
            <DialogContent class="max-w-5xl">
                <DialogHeader>
                    <DialogTitle>Lier les contacts DAV</DialogTitle>
                    <DialogDescription>
                        Associez un contact DAV à un membre sans lien. Les suggestions reposent sur un email unique.
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[60vh] space-y-6 overflow-y-auto">
                    <div>
                        <H3>Contacts sans membre</H3>
                        <Muted v-if="unlinkedContacts.length === 0" class="mt-2">Aucun contact non lié.</Muted>
                        <Table v-else class="mt-2">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Contact</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Catégorie</TableHead>
                                    <TableHead>Membre</TableHead>
                                    <TableHead class="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="contact in unlinkedContacts" :key="contact.uid">
                                    <TableCell class="font-medium">{{ contact.name || '—' }}</TableCell>
                                    <TableCell>{{ contact.email || '—' }}</TableCell>
                                    <TableCell>{{ contact.category || '—' }}</TableCell>
                                    <TableCell>
                                        <select v-model="linkChoices[contact.uid]" class="border-input bg-background h-9 rounded-md border px-2 text-sm">
                                            <option value="">Choisir…</option>
                                            <option v-for="member in unlinkedMembers" :key="member.member_id" :value="String(member.member_id)">
                                                {{ member.member_name }}
                                                <template v-if="contact.suggested_member_id === member.member_id"> (suggéré)</template>
                                            </option>
                                        </select>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button size="sm" variant="outline" :disabled="linking || !linkChoices[contact.uid]" @click="linkContact(contact)">
                                            Lier
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div>
                        <H3>Membres sans contact</H3>
                        <Muted v-if="unlinkedMembers.length === 0" class="mt-2">Aucun membre non lié.</Muted>
                        <Table v-else class="mt-2">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Membre</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead>Contact DAV</TableHead>
                                    <TableHead class="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="member in unlinkedMembers" :key="member.member_id">
                                    <TableCell class="font-medium">{{ member.member_name }}</TableCell>
                                    <TableCell>{{ member.email || '—' }}</TableCell>
                                    <TableCell>{{ member.status }}</TableCell>
                                    <TableCell>
                                        <select
                                            v-model="memberLinkChoices[member.member_id]"
                                            class="border-input bg-background h-9 rounded-md border px-2 text-sm"
                                        >
                                            <option value="">Choisir…</option>
                                            <option v-for="contact in unlinkedContacts" :key="contact.uid" :value="contact.uid">
                                                {{ contact.name || contact.uid }}
                                                <template v-if="member.suggested_contact_uid === contact.uid"> (suggéré)</template>
                                            </option>
                                        </select>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="linking || !memberLinkChoices[member.member_id]"
                                            @click="linkMember(member)"
                                        >
                                            Lier
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showLinkDialog = false">Fermer</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Sync dialog -->
        <Dialog v-model:open="showSyncDialog">
            <DialogContent class="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Synchroniser les contacts</DialogTitle>
                    <DialogDescription>
                        <template v-if="syncMembers.length > 0">
                            {{ syncMembers.length }} membre(s) avec des différences. Choisissez la source de vérité pour chaque champ.
                        </template>
                        <template v-else> Aucune différence détectée. </template>
                    </DialogDescription>
                </DialogHeader>

                <div v-if="syncMembers.length > 0">
                    <div class="mb-3 flex gap-2">
                        <Button size="sm" variant="outline" @click="setAllChoices('member')"> Tout Membre </Button>
                        <Button size="sm" variant="outline" @click="setAllChoices('dav')"> Tout DAV </Button>
                    </div>

                    <div class="max-h-96 space-y-4 overflow-y-auto">
                        <div v-for="member in syncMembers" :key="member.member_id" class="rounded-lg border p-3">
                            <p class="font-medium">{{ member.member_name }}</p>
                            <p v-if="member.missing_contact" class="text-muted-foreground text-sm">
                                Contact DAV introuvable. Il sera recréé lors de la synchronisation.
                            </p>
                            <Table v-else>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Champ</TableHead>
                                        <TableHead>Membre</TableHead>
                                        <TableHead>DAV</TableHead>
                                        <TableHead>Choix</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="(field, name) in member.fields" :key="name">
                                        <TableCell class="font-medium">{{ name }}</TableCell>
                                        <TableCell>
                                            <span :class="syncChoices[member.member_id]?.[name] === 'member' ? 'font-semibold' : 'text-muted-foreground'">
                                                {{ field.member ?? '—' }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <span :class="syncChoices[member.member_id]?.[name] === 'dav' ? 'font-semibold' : 'text-muted-foreground'">
                                                {{ field.dav ?? '—' }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-col gap-1">
                                                <label class="flex items-center gap-1 text-sm">
                                                    <input
                                                        type="radio"
                                                        :name="'sync-' + member.member_id + '-' + name"
                                                        value="member"
                                                        :checked="syncChoices[member.member_id]?.[name] === 'member'"
                                                        @change="syncChoices[member.member_id][name] = 'member'"
                                                    />
                                                    Membre
                                                </label>
                                                <label class="flex items-center gap-1 text-sm">
                                                    <input
                                                        type="radio"
                                                        :name="'sync-' + member.member_id + '-' + name"
                                                        value="dav"
                                                        :checked="syncChoices[member.member_id]?.[name] === 'dav'"
                                                        @change="syncChoices[member.member_id][name] = 'dav'"
                                                    />
                                                    DAV
                                                </label>
                                            </div>
                                            <span v-if="field.conflict" class="text-xs text-red-600">Conflit</span>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showSyncDialog = false">Annuler</Button>
                    <Button :disabled="syncMembers.length === 0 || syncing" :loading="syncing" @click="confirmSync">
                        <RefreshCw class="mr-1 h-4 w-4" />
                        Synchroniser {{ syncMembers.length }} membre(s)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
