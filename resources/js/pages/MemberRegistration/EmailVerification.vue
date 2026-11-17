<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { H1 } from '@/components/typography/headings';
import { P } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    formUuid: string;
    maskedEmail: string | null;
    resendAvailableIn: number;
}>();

const form = useForm({
    form_uuid: props.formUuid,
    code: '',
});

const cooldown = ref(props.resendAvailableIn);
const resending = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const startCooldown = () => {
    if (timer) {
        clearInterval(timer);
    }

    if (cooldown.value <= 0) {
        return;
    }

    timer = setInterval(() => {
        cooldown.value = Math.max(0, cooldown.value - 1);

        if (cooldown.value === 0 && timer) {
            clearInterval(timer);
        }
    }, 1000);
};

onMounted(startCooldown);

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const submit = () => {
    form.post(route('member-registration.verify'), {
        preserveScroll: true,
        onError: () => form.reset('code'),
    });
};

const resend = () => {
    if (cooldown.value > 0 || resending.value) {
        return;
    }

    resending.value = true;

    router.post(
        route('member-registration.resend-verification'),
        { form_uuid: props.formUuid },
        {
            preserveScroll: true,
            onSuccess: () => {
                cooldown.value = 60;
                startCooldown();
                toast.success('Un nouveau code vous a été envoyé.');
            },
            onError: (errors) => {
                if (errors.code) {
                    toast.error(errors.code);
                }
            },
            onFinish: () => {
                resending.value = false;
            },
        },
    );
};
</script>

<template>
    <div class="space-y-6">
        <div class="text-center">
            <H1 class="text-2xl font-bold sm:text-3xl">Vérifiez votre email</H1>
            <P class="text-muted-foreground mt-2">
                Un code à 6 chiffres a été envoyé à
                <span class="font-medium">{{ maskedEmail ?? 'votre adresse email' }}</span
                >. Saisissez-le ci-dessous pour confirmer votre inscription.
            </P>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-2">
                <Label for="code">Code de vérification *</Label>
                <Input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    class="text-center text-2xl tracking-[0.5em]"
                    placeholder="000000"
                    required
                />
                <InputError :message="form.errors.code" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Vérifier
            </Button>
        </form>

        <div class="text-center text-sm">
            <P v-if="cooldown > 0" class="text-muted-foreground"> Nouveau code disponible dans {{ cooldown }} s. </P>
            <Button v-else variant="link" type="button" :disabled="resending" @click="resend">
                Renvoyer un code
            </Button>
        </div>
    </div>
</template>
