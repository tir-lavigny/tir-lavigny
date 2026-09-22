<script setup lang="ts">
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarGroup, SidebarGroupLabel, SidebarGroupContent } from '@/components/ui/sidebar';
import { ChevronRight } from '@lucide/vue';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    items: NavItem[];
    label?: string;
    defaultOpen?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', open: boolean): void;
}>();

const page = usePage<SharedData>();
const isCurrentRoute = computed(() => (url: string) => {
    const urlPath = new URL(url, window.location.origin).pathname;
    return page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname === urlPath : false;
});
</script>

<template>
    <SidebarGroup as-child class="px-2 py-0">
        <Collapsible :default-open="defaultOpen" class="group/collapsible" @update:open="emit('update:open', $event)">
            <SidebarGroupLabel as-child>
                <CollapsibleTrigger
                    class="group/label text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground w-full text-left text-sm [&[data-state=open]>svg]:rotate-90"
                >
                    {{ label ?? 'Back Office' }}
                    <ChevronRight class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in items" :key="item.title">
                            <SidebarMenuButton as-child :is-active="isCurrentRoute(item.href)" :tooltip="item.title">
                                <Link :href="item.href">
                                    <component :is="item.icon" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </CollapsibleContent>
        </Collapsible>
    </SidebarGroup>
</template>
