<script setup lang="ts">
import AppSelect from '@/components/AppSelect.vue';
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, hasPermissionTo } from '@/lib/utils';
import { Role, User, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const currentUser = page.props.auth.user;

const props = defineProps<{
    user: User;
    roles: Role[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: route('bo.users.index'),
    },
    {
        title: 'Modifier',
        href: route('bo.users.edit', props.user.id),
    },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    role: props.user.roles && props.user.roles.length > 0 ? props.user.roles[0].name : '',
});

const roleOptions = computed(() =>
    props.roles.map((role) => ({
        label: role.name.charAt(0).toUpperCase() + role.name.slice(1),
        value: role.name,
    })),
);

const resetForm = () => {
    form.reset();
};

const handleSubmit = () => {
    if (!hasPermissionTo(currentUser, 'edit user')) {
        toast.error("Vous n'avez pas le droit de modifier un utilisateur");
        return;
    }

    form.put(route('bo.users.update', props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success("L'utilisateur a été modifié avec succès.");
        },
        onError: () => {
            toast.error("Une erreur est survenue lors de la modification de l'utilisateur.", {
                description: 'Veuillez vérifier les informations saisies.',
            });
        },
        onFinish: () => {
            form.clearErrors();
        },
    });
};
</script>

<template>
    <Head :title="`Modifier ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H1>Modifier l'utilisateur</H1>
                    </div>
                </div>

                <Separator class="mb-4" />

                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <H3>Informations de l'utilisateur</H3>
                            <div class="flex items-center gap-2">
                                <Small class="text-muted-foreground">ID: {{ user.id }}</Small>
                                <Badge v-if="user.email_verified_at" variant="secondary">Vérifié</Badge>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Nom complet</Label>
                                <Input id="name" type="text" v-model="form.name" placeholder="Jean Dupont" required />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" v-model="form.email" placeholder="jean.dupont@example.com" required />
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="role">Rôle</Label>
                            <AppSelect
                                v-model="form.role"
                                :options="roleOptions"
                                placeholder="Sélectionner un rôle"
                                group-label="Rôles disponibles"
                            />
                            <InputError :message="form.errors.role" />
                        </div>

                        <div v-if="user.created_at" class="space-y-2">
                            <Small class="text-muted-foreground">Créé le : {{ formatDate(user.created_at, 'dddd DD MMMM YYYY à HH:mm') }}</Small>
                        </div>
                    </div>

                    <Separator />

                    <div class="flex justify-end gap-4">
                        <Tooltip>
                            <TooltipTrigger>
                                <Button type="button" variant="outline" @click="resetForm">
                                    <Undo2 class="mr-2 h-4 w-4" />
                                    Réinitialiser
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Réinitialiser les modifications</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger>
                                <Button type="submit" :disabled="form.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Enregistrement...' : 'Enregistrer les modifications' }}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Enregistrer les modifications</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
