<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
  <div
      class="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-cover bg-center p-6 md:p-10"
      style="background-image: url('/images/auth-bg.jpg')"
  >
    <div class="absolute inset-0 bg-black/50" />
    <div class="relative z-10 w-full max-w-sm rounded-xl bg-white p-8 shadow-lg">
      <div class="flex flex-col gap-8">
        <div class="flex flex-col items-center gap-4">
          <Link
              :href="home()"
              class="flex flex-col items-center gap-2 font-medium"
          >
            <div
                class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
            >
              <AppLogoIcon
                  class="size-9 fill-current text-[var(--foreground)]"
              />
            </div>
            <span class="sr-only">{{ title }}</span>
          </Link>
          <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium">{{ title }}</h1>
            <p class="text-center text-sm text-muted-foreground">
              {{ description }}
            </p>
          </div>
        </div>
        <slot />
      </div>
    </div>
  </div>
</template>
