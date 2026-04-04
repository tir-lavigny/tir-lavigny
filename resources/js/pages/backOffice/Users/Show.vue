<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { H1, H4 } from '@/components/typography/headings';
import { P, Small } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { User, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { PenBox, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

const props = defineProps<{
    user: User;
    protectedEmail?: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: route('bo.users.index'),
    },
    {
        title: `${props.user.name}`,
        href: route('bo.users.show', { user: props.user.id }),
    },
];

const isProtected = computed(() => props.user.email === props.protectedEmail);

const handleDelete = (id: number) => {
    if (!hasPermissionTo(currentUser, 'delete user')) {
        toast.error("Vous n'avez pas le droit de supprimer un utilisateur");
        return;
    }

    if (isProtected.value) {
        toast.error("Impossible de supprimer l'utilisateur protégé");
        return;
    }

    const form = useForm({
        id,
    });

    form.delete(route('bo.users.destroy', id), {
        onSuccess: () => {
            toast.success('Utilisateur supprimé avec succès');
            form.reset();
        },
        onError: () => {
            toast.error("Une erreur est survenue lors de la suppression de l'utilisateur");
            form.reset();
        },
    });
};
</script>

<template>
    <Head :title="`Utilisateur - ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-4 flex flex-row items-center justify-between">
            <div class="flex w-full flex-row justify-between">
                <div class="flex items-center gap-2">
                    <H1>{{ user.name }}</H1>
                    <Badge v-if="isProtected" variant="secondary">Protégé</Badge>
                </div>
                <div class="flex flex-row items-center gap-2">
                    <Tooltip v-if="hasPermissionTo(currentUser, 'edit user')">
                        <TooltipTrigger>
                            <Link class="flex items-center gap-2" :href="route('bo.users.edit', user.id)">
                                <Button size="icon" variant="secondary">
                                    <PenBox />
                                </Button>
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Modifier l'utilisateur</p>
                        </TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="hasPermissionTo(currentUser, 'delete user') && !isProtected">
                        <TooltipTrigger>
                            <AppAlertDialog
                                title="Êtes-vous absolument sûr ?"
                                description="Cette action est irréversible. Cela supprimera définitivement cet utilisateur."
                                cancelText="Annuler"
                            >
                                <template #trigger>
                                    <Button size="icon" variant="destructive">
                                        <Trash2 />
                                    </Button>
                                </template>
                                <template #action>
                                    <Button variant="destructive" @click="handleDelete(user.id)">Supprimer</Button>
                                </template>
                            </AppAlertDialog>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Supprimer l'utilisateur</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="flex flex-col gap-4 lg:flex-row">
                <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4">
                    <H4>Informations de l'utilisateur</H4>
                    <Separator class="my-2" />
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Nom</Small>
                            <P class="col-span-2 !mt-0">{{ user.name }}</P>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Email</Small>
                            <P class="col-span-2 !mt-0">{{ user.email }}</P>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Statut email</Small>
                            <div class="col-span-2">
                                <Badge v-if="user.email_verified_at" variant="secondary">Vérifié</Badge>
                                <Badge v-else variant="outline">Non vérifié</Badge>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>Rôle(s)</Small>
                            <div class="col-span-2 flex gap-2">
                                <Badge v-for="role in user.roles" :key="role.id" variant="default">
                                    {{ role.name.charAt(0).toUpperCase() + role.name.slice(1) }}
                                </Badge>
                                <P v-if="!user.roles || user.roles.length === 0" class="text-muted-foreground !mt-0">Aucun rôle assigné</P>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-sidebar-border/70 dark:border-sidebar-border relative w-full overflow-hidden rounded-xl border p-4 lg:w-1/2">
                    <H4>Informations système</H4>
                    <Separator class="my-2" />
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 items-center gap-2">
                            <Small>ID</Small>
                            <P class="col-span-2 !mt-0">{{ user.id }}</P>
                        </div>
                        <div v-if="user.created_at" class="grid grid-cols-3 items-start gap-2">
                            <Small>Créé le</Small>
                            <P class="col-span-2 !mt-0">{{ formatDate(user.created_at, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                        </div>
                        <div v-if="user.updated_at" class="grid grid-cols-3 items-start gap-2">
                            <Small>Modifié le</Small>
                            <P class="col-span-2 !mt-0">{{ formatDate(user.updated_at, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                        </div>
                        <div v-if="user.email_verified_at" class="grid grid-cols-3 items-start gap-2">
                            <Small>Email vérifié le</Small>
                            <P class="col-span-2 !mt-0">{{ formatDate(user.email_verified_at, 'dddd DD MMMM YYYY à HH:mm') }}</P>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
