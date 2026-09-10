<script setup lang="ts">
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';
import { computed } from 'vue';

import { Button } from '@/components/ui/button';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    siblingCount?: number;
}>();

const emit = defineEmits<{
    change: [page: number];
}>();

const DOTS = '...' as const;

type PageItem = number | typeof DOTS;

const pages = computed<PageItem[]>(() => {
    const total = props.lastPage;
    const current = props.currentPage;
    const siblings = props.siblingCount ?? 1;

    const maxVisible = siblings * 2 + 5;

    if (total <= maxVisible) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const leftSibling = Math.max(current - siblings, 1);
    const rightSibling = Math.min(current + siblings, total);

    const showLeftDots = leftSibling > 2;
    const showRightDots = rightSibling < total - 1;

    const items: PageItem[] = [1];

    if (showLeftDots) {
        items.push(DOTS);
    } else {
        for (let page = 2; page < leftSibling; page++) {
            items.push(page);
        }
    }

    for (let page = leftSibling; page <= rightSibling; page++) {
        if (page !== 1 && page !== total) {
            items.push(page);
        }
    }

    if (showRightDots) {
        items.push(DOTS);
    } else {
        for (let page = rightSibling + 1; page < total; page++) {
            items.push(page);
        }
    }

    items.push(total);

    return items;
});

const canPrev = computed(() => props.currentPage > 1);
const canNext = computed(() => props.currentPage < props.lastPage);

const go = (page: number): void => {
    if (page < 1 || page > props.lastPage || page === props.currentPage) {
        return;
    }

    emit('change', page);
};
</script>

<template>
    <nav
        v-if="lastPage > 1"
        class="flex items-center justify-center gap-1"
        :aria-label="$t('common.pagination.label')"
        data-testid="pagination"
    >
        <Button
            variant="outline"
            size="icon"
            class="size-8"
            :disabled="!canPrev"
            :aria-label="$t('common.pagination.previous')"
            data-testid="pagination-prev"
            @click="go(currentPage - 1)"
        >
            <IconChevronLeft class="size-4" />
        </Button>

        <template v-for="(item, index) in pages" :key="`${item}-${index}`">
            <span
                v-if="item === '...'"
                class="inline-flex size-8 items-center justify-center text-sm text-muted-foreground"
                aria-hidden="true"
                >…</span
            >
            <Button
                v-else
                :variant="item === currentPage ? 'default' : 'outline'"
                size="icon"
                class="size-8"
                :aria-current="item === currentPage ? 'page' : undefined"
                :aria-label="
                    $t('common.pagination.go_to_page', { page: String(item) })
                "
                :data-testid="`pagination-page-${item}`"
                @click="go(item)"
            >
                {{ item }}
            </Button>
        </template>

        <Button
            variant="outline"
            size="icon"
            class="size-8"
            :disabled="!canNext"
            :aria-label="$t('common.pagination.next')"
            data-testid="pagination-next"
            @click="go(currentPage + 1)"
        >
            <IconChevronRight class="size-4" />
        </Button>
    </nav>
</template>
