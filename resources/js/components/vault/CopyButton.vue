<script setup lang="ts">
import { Copy } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    /** What gets copied, in the owner's words — the field's label. */
    label: string;
    disabled?: boolean;
}>();
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon"
        class="shrink-0"
        :title="`Copy ${label}`"
        :disabled="disabled"
    >
        <Copy class="size-4" />
        <span class="sr-only">Copy {{ label }}</span>
    </Button>
</template>
