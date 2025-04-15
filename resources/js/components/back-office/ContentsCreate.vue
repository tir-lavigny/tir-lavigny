<script lang="ts" setup>
import InputError from '@/components/InputError.vue';
import { Small } from '@/components/typography/texts';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { handleTabKey } from '@/lib/utils';
import { useForm } from '@inertiajs/vue3';
import { createReusableTemplate, useMediaQuery } from '@vueuse/core';
import { marked } from 'marked';
import { ref } from 'vue';

const props = defineProps<{
    title: string;
    description: string;
    onSubmit: (form: any) => void;
}>();

defineSlots<{
    trigger: () => void;
}>();

const [UseTemplate, GridForm] = createReusableTemplate();
const isDesktop = useMediaQuery('(min-width: 768px)');
const showPreview = ref(false);

const isOpen = ref(false);

const form = useForm({
    title: '',
    body: '',
});

const handleSubmit = () => {
    props.onSubmit(form);
};
</script>

<template>
    <UseTemplate>
        <form class="grid items-start gap-4 px-4" @submit.prevent="handleSubmit">
            <div class="grid gap-2">
                <Label html-for="title">Titre</Label>
                <Input id="title" type="text" v-model="form.title" placeholder="Titre" tabindex="0" />
                <InputError :message="form.errors.title" />
            </div>
            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label html-for="body">Contenu (MD)</Label>
                    <div class="flex items-center gap-2">
                        <Switch id="preview" v-model="showPreview" tabindex="-1" />
                        <Small>Aperçu</Small>
                    </div>
                </div>
                <InputError :message="form.errors.body" />
                <Textarea
                    v-if="!showPreview"
                    id="body"
                    type="body"
                    placeholder="# Template"
                    class="resize-none"
                    tabindex="0"
                    v-model="form.body"
                    @keydown="handleTabKey"
                ></Textarea>
                <div v-else>
                    <div v-html="marked(form.body)" class="prose dark:prose-invert w-full rounded-lg border p-4"></div>
                </div>
            </div>
            <Button :disabled="!form.isDirty" type="submit"> Créer une nouvelle template </Button>
        </form>
    </UseTemplate>

    <Dialog v-if="isDesktop" v-model:open="isOpen">
        <DialogTrigger as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription> {{ description }} </DialogDescription>
            </DialogHeader>
            <GridForm />
        </DialogContent>
    </Dialog>

    <Drawer v-else v-model:open="isOpen">
        <DrawerTrigger as-child>
            <slot name="trigger" />
        </DrawerTrigger>
        <DrawerContent>
            <DrawerHeader class="text-left">
                <DrawerTitle>{{ title }}</DrawerTitle>
                <DrawerDescription> {{ description }} </DrawerDescription>
            </DrawerHeader>
            <GridForm />
            <DrawerFooter class="pt-2">
                <DrawerClose as-child>
                    <Button variant="outline"> Annuler </Button>
                </DrawerClose>
            </DrawerFooter>
        </DrawerContent>
    </Drawer>
</template>
