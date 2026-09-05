<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { IconDots, IconPencil, IconPlus, IconTrash } from '@tabler/icons-vue';
import { computed, nextTick, ref } from 'vue';

import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveUrl } from '@/composables/useActiveUrl';
import date from '@/date';
import dayjs from '@/dayjs';
import { chat } from '@/routes/app';
import { destroy, show, update } from '@/routes/app/chat';
import type { ChatConversationSummary } from '@/types/chat';

const props = defineProps<{
    conversations?: ChatConversationSummary[];
    activeId?: string | null;
}>();

const emit = defineEmits<{
    navigate: [];
}>();

const { urlIsActive } = useActiveUrl();

const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);
const editingId = ref<string | null>(null);
const draft = ref('');
const renameInput = ref<HTMLInputElement | null>(null);

type GroupKey =
    | 'today'
    | 'yesterday'
    | 'last_7_days'
    | 'last_30_days'
    | 'older';

const GROUP_ORDER: GroupKey[] = [
    'today',
    'yesterday',
    'last_7_days',
    'last_30_days',
    'older',
];

/**
 * Grouped client-side, not by the backend, so the boundaries fall on the
 * user's own local day — `dayjs` is already timezone-aware app-wide via
 * `@/dayjs`, and a server-computed group would use the server's timezone
 * instead.
 */
const groupFor = (updatedAt: string | null): GroupKey => {
    const date = dayjs(updatedAt ?? undefined);
    const startOfToday = dayjs().startOf('day');

    if (date.isSame(startOfToday, 'day')) {
        return 'today';
    }

    if (date.isSame(startOfToday.subtract(1, 'day'), 'day')) {
        return 'yesterday';
    }

    if (date.isAfter(startOfToday.subtract(7, 'day'))) {
        return 'last_7_days';
    }

    if (date.isAfter(startOfToday.subtract(30, 'day'))) {
        return 'last_30_days';
    }

    return 'older';
};

const groupedConversations = computed<
    { key: GroupKey; items: ChatConversationSummary[] }[]
>(() => {
    const buckets = new Map<GroupKey, ChatConversationSummary[]>();

    for (const conversation of props.conversations ?? []) {
        const key = groupFor(conversation.updated_at);
        buckets.set(key, [...(buckets.get(key) ?? []), conversation]);
    }

    return GROUP_ORDER.filter((key) => buckets.has(key)).map((key) => ({
        key,
        items: buckets.get(key) ?? [],
    }));
});

const startRename = (conversation: ChatConversationSummary): void => {
    editingId.value = conversation.id;
    draft.value = conversation.title ?? '';

    nextTick(() => {
        renameInput.value?.focus();
        renameInput.value?.select();
    });
};

const cancelRename = (): void => {
    editingId.value = null;
};

const commitRename = (conversation: ChatConversationSummary): void => {
    const title = draft.value.trim();

    if (editingId.value === null) {
        return;
    }

    if (title === '' || title === conversation.title) {
        editingId.value = null;

        return;
    }

    router.patch(
        update.url({ conversation: conversation.id }),
        { title },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
        },
    );
};

const askDelete = (conversation: ChatConversationSummary): void => {
    // Deleting the open conversation follows the backend to a blank chat;
    // deleting any other one stays put so the thread being read survives.
    const stay = props.activeId !== conversation.id;

    deleteModal.value?.open({
        url: stay
            ? destroy.url(
                  { conversation: conversation.id },
                  { query: { stay: true } },
              )
            : destroy.url({ conversation: conversation.id }),
    });
};
</script>

<template>
    <div
        class="flex min-h-0 flex-1 flex-col px-2"
        data-testid="chat-history"
        dusk="chat-history"
    >
        <div class="mb-3 px-1">
            <Link
                :href="chat.url()"
                class="flex w-full items-center justify-center gap-2 rounded-md border-2 border-foreground bg-card px-3 py-2 text-sm font-semibold text-foreground shadow-2xs hover:bg-accent"
                data-testid="chat-history-new"
                dusk="chat-history-new"
                @click="emit('navigate')"
            >
                <IconPlus class="size-4" />
                {{ $t('sidebar.new_chat') }}
            </Link>
        </div>

        <p
            class="mb-2 px-1 text-xs font-bold tracking-wide text-muted-foreground uppercase"
        >
            {{ $t('sidebar.chat_history') }}
        </p>

        <div
            v-if="!conversations?.length"
            class="px-1 py-6 text-sm text-muted-foreground"
            data-testid="chat-history-empty"
        >
            {{ $t('sidebar.no_chats') }}
        </div>

        <div v-else class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto">
            <div
                v-for="group in groupedConversations"
                :key="group.key"
                class="space-y-1"
            >
                <p
                    class="px-1 text-[10px] font-bold tracking-widest text-muted-foreground uppercase"
                >
                    {{ $t(`chat.groups.${group.key}`) }}
                </p>

                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="conversation in group.items"
                        :key="conversation.id"
                    >
                        <input
                            v-if="editingId === conversation.id"
                            ref="renameInput"
                            v-model="draft"
                            type="text"
                            maxlength="250"
                            :aria-label="$t('chat.history.rename')"
                            :placeholder="$t('chat.history.rename')"
                            class="h-8 w-full rounded-md border-2 border-foreground bg-card px-2 text-sm text-foreground shadow-2xs outline-none"
                            data-testid="chat-history-rename-input"
                            :dusk="`chat-history-rename-input-${conversation.id}`"
                            @keydown.enter="commitRename(conversation)"
                            @keydown.esc="cancelRename"
                            @blur="commitRename(conversation)"
                        />
                        <div v-else class="group flex items-center gap-0.5">
                            <SidebarMenuButton
                                as-child
                                :is-active="
                                    urlIsActive(
                                        show.url({
                                            conversation: conversation.id,
                                        }),
                                    )
                                "
                                :tooltip="conversation.title ?? ''"
                                class="min-w-0 flex-1"
                            >
                                <Link
                                    :href="
                                        show.url({
                                            conversation: conversation.id,
                                        })
                                    "
                                    class="flex w-full items-baseline gap-2"
                                    data-testid="chat-history-item"
                                    :dusk="`chat-history-item-${conversation.id}`"
                                    @click="emit('navigate')"
                                >
                                    <span class="min-w-0 flex-1 truncate">{{
                                        conversation.title
                                    }}</span>
                                    <span
                                        v-if="conversation.updated_at"
                                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            date.formatMonthDay(
                                                conversation.updated_at,
                                            )
                                        }}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100"
                                        :aria-label="$t('chat.history.options')"
                                        data-testid="chat-history-menu"
                                        :dusk="`chat-history-menu-${conversation.id}`"
                                    >
                                        <IconDots class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        data-testid="chat-history-rename"
                                        :dusk="`chat-history-rename-${conversation.id}`"
                                        @click="startRename(conversation)"
                                    >
                                        <IconPencil class="size-4" />
                                        {{ $t('chat.history.rename') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        data-testid="chat-history-delete"
                                        :dusk="`chat-history-delete-${conversation.id}`"
                                        @click="askDelete(conversation)"
                                    >
                                        <IconTrash class="size-4" />
                                        {{ $t('chat.history.delete') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </div>
        </div>

        <ConfirmDeleteModal
            ref="deleteModal"
            :title="$t('chat.history.delete_title')"
            :description="$t('chat.history.delete_description')"
            :action="$t('chat.history.delete')"
            :cancel="$t('common.cancel')"
        />
    </div>
</template>
