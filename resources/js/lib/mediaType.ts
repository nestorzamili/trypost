/**
 * Single source of truth for media-type detection on the frontend — the mirror
 * of the backend `App\Enums\Media\Type`. Every "is this an image / video / PDF?"
 * check goes through here so the answer can't drift between components.
 */

export const MediaType = {
    Image: 'image',
    Video: 'video',
    Document: 'document',
} as const;

export type MediaType = (typeof MediaType)[keyof typeof MediaType];

/**
 * Camera RAW / DNG extensions accepted on upload — mirrors Type::RAW_EXTENSIONS.
 * The OS rarely reports a specific RAW MIME, so these feed the file picker's
 * `accept` list as extensions; the server decodes them to JPEG at ingest.
 */
export const RAW_EXTENSIONS = [
    'dng',
    'cr2',
    'cr3',
    'crw',
    'nef',
    'nrw',
    'arw',
    'rw2',
    'orf',
    'raf',
    'pef',
    'srw',
] as const;

/** MIME allow-list we accept on upload — mirrors Type::allowedMimeTypes(). */
export const ALLOWED_MIME_TYPES: Record<MediaType, readonly string[]> = {
    [MediaType.Image]: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    [MediaType.Video]: ['video/mp4', 'video/quicktime'],
    [MediaType.Document]: ['application/pdf'],
};

// Broader than the upload allow-list so already-stored files in legacy formats
// still resolve — mirrors Type::fromExtension() on the backend.
const IMAGE_EXTENSIONS = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'bmp',
    'svg',
    'heic',
    'heif',
    ...RAW_EXTENSIONS,
];
const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'm4v'];

const GIF_MIME = 'image/gif';
const PDF_MIME = 'application/pdf';

/**
 * The `accept` attribute value for a file input that takes any media we allow.
 * Includes RAW as dotted extensions (`.dng`, …) alongside the MIME types, since
 * browsers cannot match RAW by MIME (the OS reports it generically).
 */
export const acceptAttribute = (): string =>
    [
        ...Object.values(ALLOWED_MIME_TYPES).flat(),
        ...RAW_EXTENSIONS.map((ext) => `.${ext}`),
    ].join(',');

/** The structural shape every classifiable media item satisfies. */
interface ClassifiableMedia {
    type?: string | null;
    mime_type?: string | null;
    original_filename?: string | null;
    path?: string | null;
}

/** Resolve a MediaType from a raw MIME string (e.g. a browser `File.type`). */
export const fromMimeType = (
    mime: string | null | undefined,
): MediaType | null => {
    const value = mime ?? '';

    if (value.startsWith('image/')) return MediaType.Image;
    if (value.startsWith('video/')) return MediaType.Video;
    if (value === PDF_MIME) return MediaType.Document;

    return null;
};

/** Resolve a MediaType from a filename or path extension. */
export const fromExtension = (
    nameOrPath: string | null | undefined,
): MediaType | null => {
    if (!nameOrPath) return null;

    const ext = nameOrPath.split('.').pop()?.toLowerCase() ?? '';

    if (IMAGE_EXTENSIONS.includes(ext)) return MediaType.Image;
    if (VIDEO_EXTENSIONS.includes(ext)) return MediaType.Video;
    if (ext === 'pdf') return MediaType.Document;

    return null;
};

/**
 * Classify a media item. Trusts the server-assigned `type` first, then the MIME,
 * then falls back to the filename extension so already-stored items still
 * resolve. Returns null only when nothing identifies the item.
 */
export const classify = (
    item: ClassifiableMedia | null | undefined,
): MediaType | null => {
    if (!item) return null;

    const explicit = item.type;
    if (
        explicit === MediaType.Image ||
        explicit === MediaType.Video ||
        explicit === MediaType.Document
    ) {
        return explicit;
    }

    return (
        fromMimeType(item.mime_type) ??
        fromExtension(item.original_filename ?? item.path)
    );
};

export const isImage = (item: ClassifiableMedia | null | undefined): boolean =>
    classify(item) === MediaType.Image;

export const isVideo = (item: ClassifiableMedia | null | undefined): boolean =>
    classify(item) === MediaType.Video;

export const isDocument = (
    item: ClassifiableMedia | null | undefined,
): boolean => classify(item) === MediaType.Document;

/** Whether the item is an animated GIF — several platforms treat it specially. */
export const isGif = (item: ClassifiableMedia | null | undefined): boolean =>
    (item?.mime_type ?? '') === GIF_MIME;
