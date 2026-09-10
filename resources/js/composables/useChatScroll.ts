import { onBeforeUnmount, onMounted, type Ref } from 'vue';

const documentHeight = (): number =>
    Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);

const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const behaviour = (smooth: boolean): ScrollBehavior =>
    smooth && !prefersReducedMotion() ? 'smooth' : 'auto';

/**
 * The element that actually scrolls the thread, or null when the window does.
 *
 * The app shell is `h-svh overflow-hidden`, so under the sidebar layout the
 * window never scrolls and the thread lives inside an `overflow-y-auto`
 * ancestor. Onboarding renders outside that shell and scrolls the window.
 * Asking the DOM which one it is keeps both working without either page
 * having to declare it.
 */
const scrollingAncestor = (element: HTMLElement | null): HTMLElement | null => {
    let node = element?.parentElement ?? null;

    while (node !== null && node !== document.body) {
        const overflowY = window.getComputedStyle(node).overflowY;

        if (overflowY === 'auto' || overflowY === 'scroll') {
            return node;
        }

        node = node.parentElement;
    }

    return null;
};

export const scrollWindowToBottom = (smooth = false): void => {
    if (typeof window === 'undefined') {
        return;
    }

    window.scrollTo({
        top: documentHeight(),
        left: 0,
        behavior: behaviour(smooth),
    });
};

/**
 * Chat pin-to-bottom: when the thread grows (new bubble, image, chips, or a
 * tool card revealing its next step), the view follows to the end — but ONLY
 * while the user is already parked near the bottom. If they have scrolled up
 * to read an earlier message, a resize (streaming tokens, an expanding preview
 * card) must NOT yank them back down. `scrollToBottom()` called explicitly
 * (e.g. right after the user sends a message) always jumps regardless.
 */
export const useChatScroll = (root: Ref<HTMLElement | null>) => {
    let observer: ResizeObserver | null = null;

    // Distance from the bottom (px) within which we still consider the user
    // "pinned" and keep following new content.
    const NEAR_BOTTOM_THRESHOLD = 120;

    const nearBottomOf = (scroller: HTMLElement | null): boolean => {
        if (scroller === null) {
            const scrolledBottom = window.innerHeight + window.scrollY;

            return documentHeight() - scrolledBottom <= NEAR_BOTTOM_THRESHOLD;
        }

        const distance =
            scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight;

        return distance <= NEAR_BOTTOM_THRESHOLD;
    };

    const scrollToBottom = (smooth = false): void => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const scroller = scrollingAncestor(root.value);

                if (scroller === null) {
                    scrollWindowToBottom(smooth);

                    return;
                }

                scroller.scrollTo({
                    top: scroller.scrollHeight,
                    left: 0,
                    behavior: behaviour(smooth),
                });
            });
        });
    };

    onMounted(() => {
        if (root.value === null || typeof ResizeObserver === 'undefined') {
            return;
        }

        observer = new ResizeObserver(() => {
            // Only follow the growing thread when the user hasn't scrolled away.
            if (nearBottomOf(scrollingAncestor(root.value))) {
                scrollToBottom();
            }
        });
        observer.observe(root.value);
        scrollToBottom();
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
    });

    return { scrollToBottom };
};
