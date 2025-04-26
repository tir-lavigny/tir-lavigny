<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { hasRole } from '@/lib/utils';
import type { SharedData } from '@/types';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Calendar, FileText, LayoutGrid, MapPin, PanelTop, ScanEye, Telescope } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const user = usePage<SharedData>().props.auth.user;

const mainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: route('dashboard'),
        icon: LayoutGrid,
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
    {
        title: 'Événements',
        href: route('bo.events.index'),
        icon: Calendar,
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
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
