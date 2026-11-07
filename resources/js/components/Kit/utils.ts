import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

// The kit's own class merger (clsx, then tailwind-merge), so its components do not depend on the host having `@/lib/utils`.
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
