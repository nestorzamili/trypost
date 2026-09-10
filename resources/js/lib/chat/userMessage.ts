export interface ParsedUserMessage {
    text: string;
    attachment: {
        filename: string;
    } | null;
}

const ATTACHMENT_PATTERN = /\n\nAttached CSV "([^"]+)":\n```csv\n[\s\S]*?\n```$/;

/**
 * Split a user chat message into its visible prompt instruction and an
 * optional attached file reference, hiding the raw CSV data from the UI.
 */
export function parseUserMessage(content: string): ParsedUserMessage {
    const match = content.match(ATTACHMENT_PATTERN);

    if (match && match[1]) {
        return {
            text: content.slice(0, match.index).trim(),
            attachment: {
                filename: match[1],
            },
        };
    }

    return {
        text: content,
        attachment: null,
    };
}
