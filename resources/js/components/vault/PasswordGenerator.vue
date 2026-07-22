<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
import { onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    defaultGeneratorOptions,
    generatePassword,
} from '@/composables/useGenerator';

const emit = defineEmits<{
    use: [password: string];
}>();

const options = reactive({ ...defaultGeneratorOptions });
const password = ref('');

const regenerate = () => {
    password.value = generatePassword(options);
};

onMounted(regenerate);
</script>

<template>
    <div class="space-y-3 rounded-lg border border-input p-3">
        <div class="flex items-center gap-2">
            <output
                class="min-w-0 flex-1 truncate rounded-md bg-muted px-3 py-2 font-mono text-sm"
            >
                {{ password }}
            </output>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                title="Regenerate"
                @click="regenerate"
            >
                <RefreshCw class="size-4" />
            </Button>
        </div>

        <div class="flex items-center gap-3">
            <Label class="shrink-0" for="generator-length">
                Length: {{ options.length }}
            </Label>
            <input
                id="generator-length"
                v-model.number="options.length"
                type="range"
                min="8"
                max="64"
                class="w-full accent-primary"
                @input="regenerate"
            />
        </div>

        <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
            <label class="flex items-center gap-1.5">
                <Checkbox
                    :model-value="options.uppercase"
                    @update:model-value="(v: boolean | 'indeterminate') => { options.uppercase = v === true; regenerate(); }"
                />
                A-Z
            </label>
            <label class="flex items-center gap-1.5">
                <Checkbox
                    :model-value="options.lowercase"
                    @update:model-value="(v: boolean | 'indeterminate') => { options.lowercase = v === true; regenerate(); }"
                />
                a-z
            </label>
            <label class="flex items-center gap-1.5">
                <Checkbox
                    :model-value="options.digits"
                    @update:model-value="(v: boolean | 'indeterminate') => { options.digits = v === true; regenerate(); }"
                />
                0-9
            </label>
            <label class="flex items-center gap-1.5">
                <Checkbox
                    :model-value="options.symbols"
                    @update:model-value="(v: boolean | 'indeterminate') => { options.symbols = v === true; regenerate(); }"
                />
                !@#
            </label>
        </div>

        <Button
            type="button"
            variant="secondary"
            class="w-full"
            :disabled="!password"
            @click="emit('use', password)"
        >
            Use this password
        </Button>
    </div>
</template>
