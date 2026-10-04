<script setup lang="ts">
import { TOTP } from 'otpauth';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CopyButton from '@/components/vault/CopyButton.vue';

const props = defineProps<{
    secret: string;
    /** The field's name, for the copy button. */
    label: string;
}>();

const emit = defineEmits<{
    copy: [code: string];
}>();

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

const totp = computed(() => {
    try {
        return new TOTP({ secret: props.secret, digits: 6, period: 30 });
    } catch {
        return null;
    }
});

const code = computed(() => {
    if (!totp.value) return null;
    try {
        return totp.value.generate({ timestamp: now.value });
    } catch {
        return null;
    }
});

const displayCode = computed(() =>
    code.value ? `${code.value.slice(0, 3)} ${code.value.slice(3)}` : '·· ···',
);

const secondsLeft = computed(() => 30 - (Math.floor(now.value / 1000) % 30));

// SVG countdown ring geometry.
const RADIUS = 9;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const dashOffset = computed(() => CIRCUMFERENCE * (1 - secondsLeft.value / 30));

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 500);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <div class="flex items-center gap-2">
        <CopyButton
            :label="label"
            :disabled="!code"
            data-test="view-field-copy"
            @click="code && emit('copy', code)"
        />
        <span
            class="rounded-md bg-muted px-3 py-2 font-mono text-lg tracking-widest select-text"
            data-test="view-field-value"
            >{{ displayCode }}</span
        >
        <svg viewBox="0 0 22 22" class="size-5 shrink-0 -rotate-90">
            <circle
                cx="11"
                cy="11"
                :r="RADIUS"
                fill="none"
                stroke-width="3"
                class="stroke-muted"
            />
            <circle
                cx="11"
                cy="11"
                :r="RADIUS"
                fill="none"
                stroke-width="3"
                stroke-linecap="round"
                class="stroke-primary transition-[stroke-dashoffset] duration-500 ease-linear"
                :stroke-dasharray="CIRCUMFERENCE"
                :stroke-dashoffset="dashOffset"
            />
        </svg>
    </div>
</template>
