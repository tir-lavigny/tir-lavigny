<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import AppPagination from '@/components/AppPagination.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import type { BreadcrumbItem, PaginatedMemberRegistrations, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inscriptions', href: route('bo.member-registrations.index') }];

const props = withDefaults(
    defineProps<{
        registrations: PaginatedMemberRegistrations;
        filters?: { status?: string };
    }>(),
    { filters: () => ({}) },
);

const status = [
    { label: 'Toutes', value: '', displayTab: true },
    { label: 'En attente', value: 'pending', displayTab: true },
    { label: 'En attente de validation', value: 'form_filled', displayTab: true },
    { label: 'Membre créé', value: 'member_created', displayTab: false },
    { label: 'Terminé', value: 'completed', displayTab: true },
];

const handleTabClick = (status: string) => {
    router.get(route('bo.member-registrations.index'), { status: status || undefined }, { preserveState: true, preserveScroll: true });
};

const handlePageChange = (p: number = 1) => {
    router.get(route('bo.member-registrations.index'), { page: p, status: props.filters.status }, { preserveState: true, preserveScroll: true });
};

const handleGenerate = () => {
    useForm({}).post(route('bo.member-registrations.store'), {
        onSuccess: () => {
            toast.success('Formulaire créé avec succès.');
        },
    });
};

const statusBadgeVariant = (registration: any) => {
    if (registration.failed_at) return 'destructive';
    switch (registration.status) {
        case 'completed':
            return 'secondary';
        default:
            return 'default';
    }
};

const statusLabel = (registration: any) => {
    const tab = status.find((t) => t.value === registration.status);
    const label = tab?.label ?? registration.status;
    return registration.failed_at ? `${label} — Échoué` : label;
};
</script>

<template>
    <Head title="Inscriptions" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Inscriptions</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'create member registration')">
                            <TooltipTrigger>
                                <AppAlertDialog
                                    title="Créer un formulaire d'inscription ?"
                                    description="Un nouveau formulaire sera généré et accessible via un lien unique."
                                    cancelText="Annuler"
                                >
                                    <template #trigger>
                                        <Button size="icon"><Plus /></Button>
                                    </template>
                                    <template #action>
                                        <Button @click="handleGenerate">Créer</Button>
                                    </template>
                                </AppAlertDialog>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Générer un nouveau formulaire</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap gap-2">
                    <Button
                        v-for="tab in status.filter((t) => t.displayTab)"
                        :key="tab.value"
                        :variant="filters.status === tab.value || (!filters.status && tab.value === '') ? 'default' : 'outline'"
                        size="sm"
                        @click="handleTabClick(tab.value)"
                    >
                        {{ tab.label }}
                    </Button>
                </div>

                <div v-for="registration in registrations.data" :key="registration.id" class="mb-4">
                    <Link
                        :href="route('bo.member-registrations.show', registration.id)"
                        class="hover:bg-accent flex items-center justify-between rounded-lg border p-4"
                    >
                        <div>
                            <p class="font-medium">
                                {{
                                    registration.member
                                        ? registration.member.first_name + ' ' + registration.member.last_name
                                        : registration.form_data?.first_name && registration.form_data?.last_name
                                          ? registration.form_data.first_name + ' ' + registration.form_data.last_name
                                          : '-'
                                }}
                            </p>
                            <p class="text-muted-foreground text-sm">
                                {{ formatDate(registration.created_at, 'DD.MM.YYYY HH:mm') }}
                                <span v-if="registration.error_message"> · {{ registration.error_message }}</span>
                            </p>
                        </div>
                        <Badge :variant="statusBadgeVariant(registration)">
                            {{ statusLabel(registration) }}
                        </Badge>
                    </Link>
                </div>

                <Muted v-if="registrations.data?.length" class="my-4 flex items-center justify-center">
                    {{ registrations.total }} inscription(s) trouvée(s)
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center">Aucune inscription trouvée</Muted>
                <AppPagination
                    :items-per-page="registrations.per_page"
                    :total-items="registrations.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="registrations.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="handlePageChange"
                />
            </div>
        </div>
    </AppLayout>
</template>
