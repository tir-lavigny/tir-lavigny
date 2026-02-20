<script setup lang="ts">
import AppSelect from '@/components/AppSelect.vue';
import InputError from '@/components/InputError.vue';
import { H1, H3 } from '@/components/typography/headings';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Label from '@/components/ui/label/Label.vue';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { hasPermissionTo } from '@/lib/utils';
import { Role, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Save, Undo2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage<SharedData>();
const user = page.props.auth.user;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: route('bo.users.index'),
    },
    {
        title: 'Créer',
        href: route('bo.users.create'),
    },
];

const props = defineProps<{
    roles: Role[];
}>();

const form = useForm({
    name: '',
    email: '',
    role: '',
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
    if (!hasPermissionTo(user, 'create user')) {
        toast.error("Vous n'avez pas le droit de créer un utilisateur");
        return;
    }

    form.post(route('bo.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            toast.success("L'utilisateur a été créé avec succès.", {
                description: 'Un email avec les identifiants a été envoyé.',
            });
        },
        onError: () => {
            toast.error("Une erreur est survenue lors de la création de l'utilisateur.", {
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
    <Head title="Créer un utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border p-4 md:min-h-min">
                <div class="mb-4 flex w-full flex-row">
                    <div class="flex flex-1 items-center justify-between">
                        <H1>Créer un utilisateur</H1>
                    </div>
                </div>

                <Separator class="mb-4" />

                <form @submit.prevent="handleSubmit" class="space-y-6">
                    <div class="space-y-4">
                        <H3>Informations de l'utilisateur</H3>

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
                                <Small class="text-muted-foreground">Un mot de passe sera généré et envoyé à cette adresse</Small>
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
                                <p>Réinitialiser le formulaire</p>
                            </TooltipContent>
                        </Tooltip>

                        <Tooltip>
                            <TooltipTrigger>
                                <Button type="submit" :disabled="form.processing">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ form.processing ? 'Création...' : "Créer l'utilisateur" }}
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Créer l'utilisateur</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
