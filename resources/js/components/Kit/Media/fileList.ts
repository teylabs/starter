// Pure helpers for FileList: the badge shown when a file has no thumbnail, and a byte count in words. No DOM, so they are tested directly.

/** The file's extension in capitals (at most four letters), or '' when the name has none. */
export function fileExtension(name: string): string {
    const dot = name.lastIndexOf('.');

    return dot <= 0 || dot === name.length - 1
        ? ''
        : name.slice(dot + 1, dot + 5).toUpperCase();
}

/** A size for people: a string is shown as given (the server's own wording); a byte count becomes B, KB, MB or GB, one decimal below 10. */
export function fileSize(size: string | number | null | undefined): string {
    if (typeof size === 'string') {
        return size;
    }
    if (typeof size !== 'number' || !Number.isFinite(size) || size < 0) {
        return '';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = size;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }
    const digits = unit === 0 || value >= 10 ? 0 : 1;

    return `${value.toFixed(digits)} ${units[unit]}`;
}
