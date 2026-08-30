<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { hasRole } from '@/lib/utils';
import type { SharedData } from '@/types';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Calendar, CalendarDays, FileText, LayoutGrid, MapPin, Newspaper, PanelTop, Repeat, ScanEye, ShoppingCart, Tag, Telescope, Ticket, Users } from '@lucide/vue';
import AppLogo from './AppLogo.vue';

const user = usePage<SharedData>().props.auth.user;

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: route('dashboard'),
        icon: LayoutGrid,
    },
    {
        title: 'Événements',
        href: route('bo.events.index'),
        icon: Calendar,
    },
    {
        title: "Séries d'événements",
        href: route('bo.event-chains.index'),
        icon: Repeat,
    },
    {
        title: 'Communications',
        href: route('bo.posts.index'),
        icon: Newspaper,
    },
    {
        title: 'Utilisateurs',
        href: route('bo.users.index'),
        icon: Users,
    },
    {
        title: 'Lieux',
        href: route('locations.index'),
        icon: MapPin,
    },
    {
        title: 'Contenu template',
        href: route('contents.index'),
        icon: FileText,
    },
];

const registerNavItems: NavItem[] = [
    {
        title: 'Produits',
        href: route('bo.register.products.index'),
        icon: ShoppingCart,
    },
    {
        title: 'Catégories',
        href: route('bo.register.categories.index'),
        icon: Tag,
    },
    {
        title: 'Saisons',
        href: route('bo.register.seasons.index'),
        icon: Calendar,
    },
    {
        title: 'Membres',
        href: route('bo.register.members.index'),
        icon: Users,
    },
    {
        title: 'Bons',
        href: route('bo.register.vouchers.index'),
        icon: Ticket,
    },
    {
        title: 'Journées',
        href: route('bo.register.operating-days.index'),
        icon: CalendarDays,
    },
];

const footerNavItems: NavItem[] = [
    ...(hasRole(user, 'admin')
        ? [
              {
                  title: 'Pulse',
                  href: '/back-office/pulse',
                  icon: ScanEye,
              },
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
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain :items="registerNavItems" label="Caisse" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
