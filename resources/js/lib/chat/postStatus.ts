import { isToolUIPart, type UIMessage } from 'ai';

/** The payload `App\Events\PostPlatformStatusUpdated::broadcastWith()` sends. */
export interface PostPlatformStatusPayload {
    post_id?: unknown;
    status?: unknown;
    published_at?: unknown;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

/**
 * Keys that mark an `{ id, status }` node as a post snapshot rather than some
 * other entity that happens to share the shape. `ChatPostResource` always
 * emits all four (nullable), while the only other `{ id }` payload in chat —
 * `DeletePostTool`'s bare `{ id, deleted }` — has none of them.
 */
const POST_NODE_KEYS = ['content', 'platforms', 'scheduled_at', 'published_at'];

/**
 * Patch every post snapshot naming `postId` in place. Covers single-post
 * cards (`{ data: post }`), lists (`{ data: post[] }`) and resolved
 * generations (`{ data: { post } }`) with one walk, since all three embed the
 * same `ChatPostResource` shape.
 */
const patchNode = (
    node: unknown,
    postId: string,
    status: string,
    publishedAt: string | null,
): boolean => {
    if (Array.isArray(node)) {
        let touched = false;

        for (const entry of node) {
            if (patchNode(entry, postId, status, publishedAt)) {
                touched = true;
            }
        }

        return touched;
    }

    if (!isRecord(node)) {
        return false;
    }

    let touched = false;

    if (
        node.id === postId &&
        typeof node.status === 'string' &&
        POST_NODE_KEYS.some((key) => key in node)
    ) {
        if (node.status !== status) {
            node.status = status;
            touched = true;
        }

        if (publishedAt !== null && node.published_at !== publishedAt) {
            node.published_at = publishedAt;
            touched = true;
        }
    }

    for (const value of Object.values(node)) {
        if (patchNode(value, postId, status, publishedAt)) {
            touched = true;
        }
    }

    return touched;
};

/**
 * Fold a post status broadcast into the thread's tool outputs, so a card the
 * server rendered while publishing flips to published the moment the queue
 * job lands — instead of freezing on the snapshot the tool returned when it
 * dispatched the job.
 *
 * Returns a new message array with fresh objects along every patched path
 * (the thread renders from object identity), or null when nothing named the
 * post. Only `output-available` tool parts are touched: live or paused parts
 * belong to the turn still running, and the server's own history — what the
 * next turn reasons over — is untouched, since the client transcript is never
 * sent back.
 */
export const patchPostStatus = (
    messages: UIMessage[],
    payload: PostPlatformStatusPayload,
): UIMessage[] | null => {
    const { post_id: postId, status } = payload;
    const publishedAt =
        typeof payload.published_at === 'string' ? payload.published_at : null;

    if (
        typeof postId !== 'string' ||
        postId === '' ||
        typeof status !== 'string' ||
        status === ''
    ) {
        return null;
    }

    let touched = false;

    const next = messages.map((message) => {
        if (message.role !== 'assistant') {
            return message;
        }

        let messageTouched = false;

        const parts = message.parts.map((part) => {
            if (
                !isToolUIPart(part) ||
                part.state !== 'output-available' ||
                typeof part.output !== 'string' ||
                part.output === ''
            ) {
                return part;
            }

            let value: unknown;

            try {
                value = JSON.parse(part.output);
            } catch {
                return part;
            }

            if (!patchNode(value, postId, status, publishedAt)) {
                return part;
            }

            messageTouched = true;

            return { ...part, output: JSON.stringify(value) };
        });

        if (!messageTouched) {
            return message;
        }

        touched = true;

        return { ...message, parts };
    });

    return touched ? next : null;
};
