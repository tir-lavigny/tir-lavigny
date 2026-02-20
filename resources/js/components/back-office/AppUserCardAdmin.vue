<script setup lang="ts">
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Small } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate } from '@/lib/utils';
import { User } from '@/types';
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, PenBox, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const props = withDefaults(
    defineProps<{
        user: User;
        actions?: string[];
        protectedEmail?: string;
    }>(),
    {
        actions: () => ['edit', 'delete'],
        protectedEmail: '',
    },
);

const isProtected = props.user.email === props.protectedEmail;

const handleDelete = (id: number) => {
    if (!props.actions.includes('delete')) {
        toast.error("Vous n'avez pas le droit de supprimer un utilisateur");
        return;
    }

    if (isProtected) {
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
    <Card class="justify-between">
        <CardHeader class="flex flex-col items-center justify-between sm:flex-row">
            <CardTitle class="flex items-center gap-2">
                {{ user.name }}
                <Badge v-if="isProtected" variant="secondary">Protégé</Badge>
            </CardTitle>
            <div class="flex flex-row items-center gap-2">
                <Tooltip v-if="actions.includes('edit')">
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
                <Tooltip v-if="actions.includes('delete') && !isProtected">
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
        </CardHeader>
        <CardContent>
            <div class="flex flex-col gap-4">
                <Small>Email : {{ user.email }}</Small>
                <Small>
                    Rôle :
                    <Badge v-for="role in user.roles" :key="role.id" variant="outline" class="ml-2">
                        {{ role.name }}
                    </Badge>
                </Small>
                <Small v-if="user.created_at">Créé le : {{ formatDate(user.created_at, 'dddd DD MMMM YYYY') }}</Small>
            </div>
        </CardContent>
        <CardFooter>
            <Button variant="secondary" size="sm">
                <Link class="flex items-center gap-2" :href="route('bo.users.show', user.id)">
                    Voir l'utilisateur <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>

<style scoped></style>
