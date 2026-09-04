<script setup lang="ts">
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { onMounted, ref } from 'vue';
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
    vcard_raw: string;
}

defineProps<{
    contacts: Contact[];
}>();

const page = usePage();
const expandedContact = ref<string | null>(null);

const toggleExpand = (url: string) => {
    expandedContact.value = expandedContact.value === url ? null : url;
};

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
                        <Muted>{{ contacts.length }} contact{{ contacts.length !== 1 ? 's' : '' }}</Muted>
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
                                <TableCell>{{ contact.vcard_raw.match(/CATEGORIES:(.+)/)?.[1] || '—' }}</TableCell>
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
    </AppLayout>
</template>
