<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavMainCollapsible from '@/components/NavMainCollapsible.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { hasAnyPermission, hasPermissionTo, hasRole } from '@/lib/utils';
import type { SharedData } from '@/types';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Calendar,
    CalendarDays,
    ClipboardList,
    FileText,
    LayoutGrid,
    MapPin,
    Newspaper,
    PanelTop,
    Repeat,
    ShoppingCart,
    Tag,
    Tags,
    Telescope,
    Ticket,
    Trash2,
    Users,
    BookUser,
    WineOff,
    Forklift,
    Warehouse,
} from '@lucide/vue';
import AppLogo from './AppLogo.vue';
import { reactive, watch } from 'vue';

const MENUS_STATE_KEY = 'app_sidebar_menus_state';

const user = usePage<SharedData>().props.auth.user;

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: route('dashboard'),
        icon: LayoutGrid,
    },
    ...(hasAnyPermission(user, ['create event', 'edit event', 'delete event'])
        ? [
              {
                  title: 'Événements',
                  href: route('bo.events.index'),
                  icon: Calendar,
              },
          ]
        : []),
    ...(hasAnyPermission(user, ['create event chain', 'edit event chain', 'delete event chain'])
        ? [
              {
                  title: "Séries d'événements",
                  href: route('bo.event-chains.index'),
                  icon: Repeat,
              },
          ]
        : []),
    ...(hasAnyPermission(user, ['create post', 'edit post', 'delete post'])
        ? [
              {
                  title: 'Communications',
                  href: route('bo.posts.index'),
                  icon: Newspaper,
              },
          ]
        : []),
    ...(hasAnyPermission(user, ['create user', 'edit user', 'delete user'])
        ? [
              {
                  title: 'Utilisateurs',
                  href: route('bo.users.index'),
                  icon: Users,
              },
          ]
        : []),
    ...(hasAnyPermission(user, ['create location', 'edit location', 'delete location'])
        ? [
              {
                  title: 'Lieux',
                  href: route('locations.index'),
                  icon: MapPin,
              },
          ]
        : []),
    ...(hasAnyPermission(user, ['create content', 'edit content', 'delete content'])
        ? [
              {
                  title: 'Contenu template',
                  href: route('contents.index'),
                  icon: FileText,
              },
          ]
        : []),
];

const registerNavItems: NavItem[] = [
    ...(hasPermissionTo(user, 'manage seasons')
        ? [
              {
                  title: 'Saisons',
                  href: route('bo.register.seasons.index'),
                  icon: Calendar,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'manage operating days')
        ? [
              {
                  title: 'Journées',
                  href: route('bo.register.operating-days.index'),
                  icon: CalendarDays,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'manage products')
        ? [
              {
                  title: 'Produits',
                  href: route('bo.register.products.index'),
                  icon: ShoppingCart,
              },
              {
                  title: 'Listes de prix',
                  href: route('bo.register.price-lists.index'),
                  icon: Tags,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'manage categories')
        ? [
              {
                  title: 'Catégories',
                  href: route('bo.register.categories.index'),
                  icon: Tag,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'manage vouchers')
        ? [
              {
                  title: 'Bons',
                  href: route('bo.register.vouchers.index'),
                  icon: Ticket,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'view reports')
        ? [
              {
                  title: 'Rapports',
                  href: route('bo.register.reports.index'),
                  icon: BarChart3,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'restore register')
        ? [
              {
                  title: 'Corbeille',
                  href: route('bo.register.trash.index'),
                  icon: Trash2,
              },
          ]
        : []),
];

const stockNavItems: NavItem[] = [
    ...(hasPermissionTo(user, 'manage stock')
        ? [
              {
                  title: 'Emplacements',
                  href: route('bo.stock.locations.index'),
                  icon: Warehouse,
              },
              {
                  title: 'Comptage du stock',
                  href: route('bo.stock.counts.index'),
                  icon: ClipboardList,
              },
              {
                  title: 'Transferts de stock',
                  href: route('bo.stock.transfers.index'),
                  icon: Forklift,
              },
              {
                  title: 'Pertes de stock',
                  href: route('bo.stock.losses.index'),
                  icon: WineOff,
              },
          ]
        : []),
];

const managementNavItems: NavItem[] = [
    ...(hasPermissionTo(user, 'manage members')
        ? [
              {
                  title: 'Membres',
                  href: route('bo.members.index'),
                  icon: Users,
              },
              {
                  title: 'Contacts',
                  href: route('bo.dav.index'),
                  icon: BookUser,
              },
          ]
        : []),
    ...(hasPermissionTo(user, 'manage member registration')
        ? [
              {
                  title: 'Inscriptions',
                  href: route('bo.member-registrations.index'),
                  icon: ClipboardList,
              },
          ]
        : []),
];

const footerNavItems: NavItem[] = [
    ...(hasRole(user, 'admin')
        ? [
              {
                  title: 'Telescope',
                  href: '/back-office/telescope',
                  icon: Telescope,
              },
          ]
        : []),
    {
        title: 'Site web',
        href: route('home'),
        icon: PanelTop,
    },
];

const persistentMenusState = reactive<Record<string, boolean>>(JSON.parse(localStorage.getItem(MENUS_STATE_KEY) || '{}'));

watch(
    persistentMenusState,
    (newState) => {
        localStorage.setItem(MENUS_STATE_KEY, JSON.stringify(newState));
    },
    { deep: true },
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo :display-build-tag="true" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMainCollapsible
                v-if="registerNavItems.length"
                @update:open="persistentMenusState.register = $event"
                :items="registerNavItems"
                label="Caisse"
                :default-open="persistentMenusState.register"
            />
            <NavMainCollapsible
                v-if="stockNavItems.length"
                @update:open="persistentMenusState.stock = $event"
                :items="stockNavItems"
                label="Stock"
                :default-open="persistentMenusState.stock"
            />
            <NavMainCollapsible
                v-if="managementNavItems.length"
                @update:open="persistentMenusState.management = $event"
                :items="managementNavItems"
                label="Gestion"
                :default-open="persistentMenusState.management"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
