/**
 * Generate a RFC-4122 v4 UUID that works in EVERY context.
 *
 * `crypto.randomUUID()` only exists in a SECURE context (HTTPS, or localhost).
 * A self-hosted install reached over plain `http://<ip>:<port>` is NOT secure,
 * so `crypto.randomUUID` is `undefined` there and calling it throws
 * "crypto.randomUUID is not a function", crashing pages that mint client ids
 * (the chat conversation id, generation/regeneration ids, chunked-upload ids).
 *
 * This prefers the native generator when available, then the still-widely
 * available `crypto.getRandomValues` (present in any modern browser regardless
 * of secure context), and only as a last resort a Math.random fallback. The
 * ids are client-side correlation handles, not security tokens, so the fallback
 * is acceptable when no crypto source exists at all.
 */
export function uuid(): string {
    const cryptoObj: Crypto | undefined =
        typeof globalThis !== 'undefined'
            ? (globalThis.crypto as Crypto | undefined)
            : undefined;

    if (typeof cryptoObj?.randomUUID === 'function') {
        return cryptoObj.randomUUID();
    }

    if (typeof cryptoObj?.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        cryptoObj.getRandomValues(bytes);

        // Per RFC 4122 §4.4: set version (4) and variant (10xx) bits.
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        const hex = Array.from(bytes, (b) =>
            b.toString(16).padStart(2, '0'),
        ).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    // No crypto at all — non-cryptographic fallback (correlation id only).
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}
