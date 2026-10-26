import { Banknote, Check, Copy, CreditCard, FileText, MessageSquare, Pencil, Plus, Send, type LucideIcon } from '@lucide/vue';

// The icons an action descriptor may name (`icon`, kebab case). An unknown name renders no icon rather than an unrelated one.
export const actionIcons: Record<string, LucideIcon> = { 'banknote': Banknote, 'check': Check, 'copy': Copy, 'credit-card': CreditCard, 'file-text': FileText, 'message-square': MessageSquare, 'pencil': Pencil, 'plus': Plus, 'send': Send };
