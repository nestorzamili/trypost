<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <main data-slot="sidebar-inset" :class="cn(
    'bg-background text-foreground relative flex w-full flex-1 flex-col min-h-0 overflow-hidden',
    'md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-lg md:peer-data-[variant=inset]:border-2 md:peer-data-[variant=inset]:border-foreground md:peer-data-[variant=inset]:shadow-sm',
    props.class,
  )">
    <slot />
  </main>
</template>
