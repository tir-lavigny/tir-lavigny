<script setup lang="ts">
import AppPagination from '@/components/AppPagination.vue';
import AppUserCardAdmin from '@/components/back-office/AppUserCardAdmin.vue';
import { H3 } from '@/components/typography/headings';
import { Muted } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useListFilters } from '@/composables/useListFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import { PaginatedUsers, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: route('bo.users.index'),
    },
];

const props = defineProps<{
    paginatedUsers: PaginatedUsers;
    filters: {
        search?: string;
    };
    protectedEmail?: string;
}>();

const { filters, visit } = useListFilters('bo.users.index', {
    search: props.filters.search || '',
});
</script>

<template>
    <Head title="Utilisateurs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex w-full flex-col items-center justify-between gap-6 lg:flex-row">
                <div class="relative w-full items-center lg:max-w-sm">
                    <Input id="search" type="text" v-model="filters.search" placeholder="Rechercher..." class="pl-10" />
                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-2">
                        <Search class="text-muted-foreground size-6" />
                    </span>
                </div>
            </div>
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H3>Utilisateurs</H3>
                        <Tooltip v-if="hasPermissionTo(user, 'create user')">
                            <TooltipTrigger>
                                <Link :href="route('bo.users.create')">
                                    <Button size="icon">
                                        <Plus />
                                    </Button>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Créer un nouvel utilisateur</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </div>
                <AppUserCardAdmin
                    v-for="usr in paginatedUsers.data"
                    :key="usr.id"
                    :user="usr"
                    :protected-email="protectedEmail"
                    class="mb-4"
                    :actions="[...(hasPermissionTo(user, 'edit user') ? ['edit'] : []), ...(hasPermissionTo(user, 'delete user') ? ['delete'] : [])]"
                />
                <Muted v-if="paginatedUsers.data && paginatedUsers.data.length > 0" class="my-4 flex items-center justify-center">
                    {{ paginatedUsers.total }} utilisateur{{ paginatedUsers.total > 1 ? 's' : '' }} trouvé{{ paginatedUsers.total > 1 ? 's' : '' }}
                </Muted>
                <Muted v-else class="my-4 flex items-center justify-center"> Aucun utilisateur trouvé </Muted>
                <AppPagination
                    :items-per-page="paginatedUsers.per_page"
                    :total-items="paginatedUsers.total"
                    :sibling-count="1"
                    :show-edges="true"
                    :page="paginatedUsers.current_page"
                    :show-prev-next="true"
                    :show-first-last="false"
                    @page-changed="visit"
                />
            </div>
        </div>
    </AppLayout>
</template>
