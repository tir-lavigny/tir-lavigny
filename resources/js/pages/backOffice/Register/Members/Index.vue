<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedMembers, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, TicketX, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Membres', href: route('bo.register.members.index') }];

const props = withDefaults(
    defineProps<{
        members: PaginatedMembers;
        filters?: { search?: string };
    }>(),
    { filters: () => ({}) },
);

const filters = ref({ search: props.filters.search || '' });

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.register.members.index'), { page: p, search: filters.value.search }, { preserveState: true, preserveScroll: true });
};

watch(
    filters,
    (f) => {
        router.get(route('bo.register.members.index'), { page: 1, search: f.search }, { preserveState: true, preserveScroll: true });
    },
    { deep: true },
);

const handleDelete = (id: number) => {
    useForm({}).delete(route('bo.register.members.destroy', id), {
        onSuccess: () => toast.success('Membre supprimé.'),
        onError: () => toast.error('Erreur lors de la suppression.'),
    });
};
</script>

<template>
    <Head title="Membres" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input v-model="filters.search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Membres</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'manage members')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.members.create')">
                                    <Button size="icon"><Plus /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Créer un membre</p></TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <div v-for="member in members.data" :key="member.id" class="mb-4 flex items-center justify-between rounded-lg border p-4">
                    <div>
                        <p class="font-medium">{{ member.first_name }} {{ member.last_name }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Tooltip v-if="hasPermissionTo(user, 'restore register')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.trash.index') + '?member_id=' + member.id">
                                    <Button size="icon" variant="outline"><TicketX /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Bons supprimés</p></TooltipContent>
                        </Tooltip>
                        <Tooltip v-if="hasPermissionTo(user, 'manage members')">
                            <TooltipTrigger>
                                <Link :href="route('bo.register.members.edit', member.id)">
                                    <Button size="icon" variant="outline"><Pencil /></Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent><p>Modifier</p></TooltipContent>
                        </Tooltip>
                        <AppAlertDialog
                            v-if="hasPermissionTo(user, 'manage members')"
                            title="Supprimer ce membre ?"
                            description="Cette action est irréversible."
                            cancelText="Annuler"
                        >
                            <template #trigger>
                                <Button size="icon" variant="destructive"><Trash2 /></Button>
                            </template>
                            <template #action>
                                <Button variant="destructive" @click="handleDelete(member.id)">Supprimer</Button>
                            </template>
                        </AppAlertDialog>
                    </div>
                </div>
                <Muted v-if="members.data?.length" class="my-4 flex items-center justify-center"> {{ members.total }} membre(s) trouvé(s) </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucun membre trouvé</Muted>
                <AppPagination
                    :items-per-page="members.per_page"
                    :total-items="members.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :default-page="members.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
