<script setup lang="ts">
import { TOTP } from 'otpauth';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    secret: string;
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
const dashOffset = computed(
    () => CIRCUMFERENCE * (1 - secondsLeft.value / 30),
);

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 500);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
});
</script>

<template>
    <button
        type="button"
        class="flex items-center gap-2 rounded-md border border-input bg-transparent px-3 py-2 font-mono text-lg tracking-widest hover:bg-accent"
        :disabled="!code"
        @click="code && emit('copy', code)"
    >
        <span>{{ displayCode }}</span>
        <svg viewBox="0 0 22 22" class="size-5 -rotate-90">
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
        <span class="sr-only">Copy one-time code</span>
    </button>
</template>
