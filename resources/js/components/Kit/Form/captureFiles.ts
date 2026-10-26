// The shared intake path behind BulkDrop: files from a paste, a drop or the picker all land in the one native file input.

/** Files of an accepted MIME type from a paste. Ordinary text pastes are left alone. */
export function capturePaste(event: ClipboardEvent, accept: readonly string[]): File[] {
    const files = Array.from(event.clipboardData?.files ?? []).filter(file => accept.includes(file.type));
    if (files.length) event.preventDefault();
    return files;
}

/** The files from a drop (preventing the browser from opening them). */
export function captureDrop(event: DragEvent): File[] {
    const files = Array.from(event.dataTransfer?.files ?? []);
    if (files.length) event.preventDefault();
    return files;
}

/** Puts `files` into the native input as its value and fires `change`, so a form posts them like a picked selection. */
export function setCaptureFiles(input: HTMLInputElement, files: File[]): void {
    const transfer = new DataTransfer();
    files.forEach(file => transfer.items.add(file));
    input.files = transfer.files;
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

/** Why a selection was refused: too many files, or the first file with an unaccepted type or over the size limit. */
export type SelectionProblem = { kind: 'count' } | { kind: 'type' | 'size'; name: string };

type SelectionLimits = { accept: readonly string[]; maxBytes: number; maxFiles?: number };

/** Null when a selection fits: at most `maxFiles` (one by default), every type accepted and within `maxBytes`. Otherwise the first problem. A client hint only: the server checks again. */
export function checkSelection(files: readonly Pick<File, 'name' | 'type' | 'size'>[], limits: SelectionLimits): SelectionProblem | null {
    if (files.length > (limits.maxFiles ?? 1)) return { kind: 'count' };
    for (const file of files) {
        if (!limits.accept.includes(file.type)) return { kind: 'type', name: file.name };
        if (file.size > limits.maxBytes) return { kind: 'size', name: file.name };
    }
    return null;
}

const megabytes = (bytes: number) => { const value = bytes / 1_000_000; return Number.isInteger(value) ? String(value) : value.toFixed(1); };

/** The alert sentence for a refused selection, naming the file and the reason ("setup.exe isn't an accepted type"). */
export function describeProblem(problem: SelectionProblem, limits: SelectionLimits): string {
    if (problem.kind === 'count') return `Only ${limits.maxFiles ?? 1} ${(limits.maxFiles ?? 1) === 1 ? 'file' : 'files'} at once`;
    return problem.kind === 'type' ? `${problem.name} isn't an accepted type` : `${problem.name} is over ${megabytes(limits.maxBytes)} MB`;
}

/** "Up to 10 MB each": the fallback hint when the host supplies none. */
export function sizeHint(maxBytes: number): string {
    return `Up to ${megabytes(maxBytes)} MB each`;
}
