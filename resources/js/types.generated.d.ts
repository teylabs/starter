declare namespace Tey {
export namespace Kit {
export namespace Features {
export namespace Core {
export namespace Data {
export type Action = {
label: string,
url: string,
method: string,
disabled: boolean,
reason: string | null,
confirm: {
title?: string | null,
description: string,
label: string,
typed?: string,
} | null,
key: string | null,
destructive: boolean,
intent: string | null,
group: string | null,
menu: boolean,
icon: string | null,
newTab: boolean | null,
priority: 'primary' | 'secondary' | 'overflow' | null,
checked: boolean | null,
ariaLabel: string | null,
description: string | null,
};
export type ActivityEntryData = {
id: string,
kind: string,
headline: {
text: string,
} | {
html: string,
} | null,
occurredAt: string | null,
timestamp: string | null,
timestampTitle: string | null,
intent: string | null,
meta: {
label: string,
value: string,
}[],
count: number | null,
truncated: boolean,
children: Tey.Kit.Features.Core.Data.ActivityEntryData[],
day: {
date: string,
label: string,
} | null,
effectiveOn: string | null,
effectiveOnLabel: string | null,
};
export type Breadcrumb = {
label: string,
url: string | null,
current: boolean,
home: boolean,
title: string | null,
};
export type ChecklistAction = {
label: string,
url: string | null,
name: string | null,
};
export type ChecklistItem = {
key: string,
label: string,
state: 'pending' | 'active' | 'done' | 'failed' | 'skipped',
detail: string | null,
reason: string | null,
required: boolean,
action: Tey.Kit.Features.Core.Data.ChecklistAction | null,
};
export type NavGroup = {
label: string | null,
items: Tey.Kit.Features.Core.Data.NavItem[],
};
export type NavItem = {
label: string,
url: string,
current: boolean,
icon: string | null,
badge: string | null,
};
export type NavigationHeader = {
label: string,
url: string,
};
export type PageLayout = {
navigation: Tey.Kit.Features.Core.Data.NavGroup[],
title: string,
documentTitle: string,
breadcrumbs: Tey.Kit.Features.Core.Data.Breadcrumb[],
navigationHeader: Tey.Kit.Features.Core.Data.NavigationHeader | null,
};
export type TableColumn = {
key: string,
label: string,
sortable: boolean,
hideable: boolean,
hiddenByDefault: boolean,
align: string | null,
srOnlyLabel: boolean,
sortBy: string | null,
date: 'relative' | 'local' | 'datetime' | 'date' | 'time' | 'ymd' | null,
};
export type TableData = {
rows: Array<{ id: string | number; actions?: Tey.Kit.Features.Core.Data.Action[] } & Record<string, unknown>>,
columns: Tey.Kit.Features.Core.Data.TableColumn[],
sort: Tey.Kit.Features.Core.Data.TableSort | null,
search: string | null,
filters: Record<string, string>,
tabs: Tey.Kit.Features.Core.Data.TableTab[],
pagination: Tey.Kit.Features.Core.Data.TablePagination,
emptyState: 'first-use' | 'no-results' | null,
choices: Tey.Kit.Features.Core.Data.TableFilter[],
bulkActions: Tey.Kit.Features.Core.Data.Action[],
rowActions: { variant: 'split' | 'overflow' } | null,
noun: string | null,
};
export type TableFilter = {
key: string,
label: string,
options: {
value: string,
label: string,
}[],
};
export type TablePagination = {
page: number,
perPage: number,
total: number,
from: number | null,
to: number | null,
lastPage: number,
};
export type TableSort = {
column: string,
direction: string,
};
export type TableTab = {
key: string,
label: string,
url: string,
count: number,
current: boolean,
};
export type WorkbenchInspector = {
type: string,
label: string,
types: string[],
};
export type WorkbenchItem = {
id: string,
label: string,
type: string | null,
description: string | null,
actions: Tey.Kit.Features.Core.Data.Action[],
thumbnail: Tey.Kit.Features.Core.Data.WorkbenchThumbnail | null,
};
export type WorkbenchLayout = {
state: {
layout: Record<string, any> | null,
version: number,
} | null,
preference: {
url: string,
method: string,
} | null,
};
export type WorkbenchThumbnail = {
src: string,
alt: string,
};
}
export namespace Lists {
export type ListEmptyData = {
variant: string,
title: string,
description: string,
action: Tey.Kit.Features.Core.Data.Action | null,
};
export type ListErrorData = {
message: string,
retry: Tey.Kit.Features.Core.Data.Action | null,
};
export type ListItemData = {
id: string,
title: string,
url: string | null,
description: string | null,
meta: {
icon: string | null,
label: string,
url: string | null,
}[],
badges: {
label: string,
variant: string,
}[],
variant: string | null,
group: {
key: string,
label: string,
} | null,
actions: Tey.Kit.Features.Core.Data.Action[],
};
export type ScrollListData = {
key: string,
title: string,
state: string,
data: Tey.Kit.Features.Core.Lists.ListItemData[],
empty: Tey.Kit.Features.Core.Lists.ListEmptyData | null,
error: Tey.Kit.Features.Core.Lists.ListErrorData | null,
};
}
}
export namespace Deliver {
export namespace Data {
export type DeliverPreview = {
subject: string,
html: string,
attachment: 'attached' | 'linked' | 'none',
attachmentName: string | null,
attachmentBytes: number | null,
};
export type DeliverState = {
definition: string,
subject: string,
sendable: boolean,
reason: string | null,
defaultSubject: string,
defaultMessage: string,
recipientOptions: Tey.Kit.Features.Deliver.Data.RecipientOption[],
internalCopies: string[],
deliveries: Tey.Kit.Features.Deliver.Data.DeliveryView[],
revision: string,
preview: Tey.Kit.Features.Core.Data.Action | null,
send: Tey.Kit.Features.Core.Data.Action | null,
testSend: Tey.Kit.Features.Core.Data.Action | null,
attachmentUrl: string | null,
};
export type DeliveryRecipientView = {
role: 'to' | 'cc' | 'bcc' | 'copy',
address: string,
name: string | null,
status: 'pending' | 'sent' | 'delivered' | 'bounced' | 'complained',
};
export type DeliveryView = {
id: number,
state: 'scheduled' | 'sending' | 'sent' | 'failed' | 'unknown' | 'cancelled',
test: boolean,
by: string,
channel: string,
createdAt: string,
sendAt: string | null,
sentAt: string | null,
subject: string,
attachment: 'attached' | 'linked' | 'none',
recipients: Tey.Kit.Features.Deliver.Data.DeliveryRecipientView[],
stillFinal: boolean | null,
linkActive: boolean,
firstOpenedAt: string | null,
note: string | null,
resentFromId: number | null,
actions: Tey.Kit.Features.Core.Data.Action[],
};
export type RecipientOption = {
address: string,
name: string | null,
role: 'to' | 'cc',
};
}
}
export namespace Finalize {
export namespace Data {
export type FinalizationRecord = {
by: string,
at: string,
channel: string,
statementVersion: string,
unfinalize: Tey.Kit.Features.Core.Data.Action | null,
};
export type GateConfirmation = {
by: string,
at: string,
stale: boolean,
};
export type GateError = {
message: string,
field: string | null,
url: string | null,
};
export type GateProgress = {
total: number,
done: number,
required: number,
requiredDone: number,
complete: boolean,
currentKey: string | null,
revision: string,
};
export type GateState = {
flow: string,
subject: string,
steps: Tey.Kit.Features.Finalize.Data.GateStep[],
progress: Tey.Kit.Features.Finalize.Data.GateProgress,
statement: string,
statementVersion: string,
finalize: Tey.Kit.Features.Core.Data.Action | null,
finalization: Tey.Kit.Features.Finalize.Data.FinalizationRecord | null,
};
export type GateStep = {
key: string,
label: string,
kind: 'check' | 'confirmation' | 'certify',
state: 'pending' | 'active' | 'done' | 'skipped',
required: boolean,
detail: string | null,
reason: string | null,
errors: Tey.Kit.Features.Finalize.Data.GateError[],
actions: Tey.Kit.Features.Core.Data.Action[],
confirmation: Tey.Kit.Features.Finalize.Data.GateConfirmation | null,
warnings: Tey.Kit.Features.Finalize.Data.GateError[],
};
}
}
export namespace Mcp {
export namespace Data {
export type AssistantClient = {
key: string,
label: string,
summary: string,
install: Tey.Kit.Features.Core.Data.Action | null,
command: string | null,
steps: string[],
};
}
export namespace ViewModels {
export type AuthorizeViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly client: string,
readonly redirect_host: string,
readonly registered: boolean,
readonly grants: {
scope: string,
label: string,
explains: string,
checked: boolean,
}[],
readonly scopes: {
key: string,
label: string,
}[],
readonly workspaces: {
id: number,
name: string,
}[],
readonly workspace: number | null,
readonly approve_url: string,
readonly deny_url: string,
readonly auth_token: string,
readonly csrf_token: string,
};
export type ConnectionsViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly pending: {
id: string,
label: string,
}[],
readonly scopes: {
key: string,
description: string,
required: boolean,
}[],
readonly table: Tey.Kit.Features.Core.Data.TableData,
readonly approve: Tey.Kit.Features.Core.Data.Action | null,
readonly intent_key: string,
readonly server_url: string,
readonly assistants: Tey.Kit.Features.Mcp.Data.AssistantClient[],
};
}
}
export namespace Notes {
export namespace Data {
export type NoteChange = {
note_id: number,
workspace_id: number,
deleted: boolean,
note: Tey.Kit.Features.Notes.Data.NoteRecord | null,
};
export type NoteComposerData = {
submit: Tey.Kit.Features.Core.Data.Action,
placeholder: string,
colours: {
key: string,
label: string,
}[],
default_colour: string,
media: {
accept: string[],
max_files: number,
max_kb: number,
},
subject: {
type: string,
id: number,
} | null,
options: {
key: string,
label: string,
icon: string | null,
url: string | null,
}[],
subject_search: string | null,
};
export type NoteItemData = {
note: Tey.Kit.Features.Notes.Data.NoteRecord,
update: Tey.Kit.Features.Core.Data.Action | null,
delete: Tey.Kit.Features.Core.Data.Action | null,
id: string,
title: string,
url: string | null,
description: string | null,
meta: {
icon: string | null,
label: string,
url: string | null,
}[],
badges: {
label: string,
variant: string,
}[],
variant: string | null,
group: {
key: string,
label: string,
} | null,
actions: Tey.Kit.Features.Core.Data.Action[],
};
export type NoteRecord = {
id: number,
workspace_id: number,
author_id: number | null,
author: string | null,
body: string,
colour: string,
pinned: boolean,
version: number,
subject: {
type: string,
id: number,
label: string,
url: string | null,
} | null,
attachments: {
id: string,
name: string,
mime: string,
size: number,
url: string,
thumb_url: string | null,
thumb_pending: boolean,
}[],
created_at: string,
updated_at: string,
};
}
export namespace ViewModels {
export type NotesIndexViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly composer: Tey.Kit.Features.Notes.Data.NoteComposerData | null,
readonly colours: {
key: string,
label: string,
}[],
readonly colour: string | null,
readonly index_url: string,
readonly list: string,
};
export type NotesWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Array<any>,
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
readonly composer: Tey.Kit.Features.Notes.Data.NoteComposerData | null,
readonly notes: Tey.Kit.Features.Notes.Data.NoteRecord[],
};
}
}
export namespace SessionUi {
export namespace Dashboard {
export type ActivityWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Tey.Kit.Features.Core.Data.Action[],
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
readonly items: Tey.Kit.Features.Core.Data.ActivityEntryData[],
};
export type FailedWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Tey.Kit.Features.Core.Data.Action[],
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
};
export type PeopleWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Array<any>,
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
readonly list: string,
};
export type SetupWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Tey.Kit.Features.Core.Data.Action[],
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
readonly checklist: Tey.Kit.Features.Core.Data.ChecklistItem[],
};
export type Widget = object;
export type WidgetEmptyData = {
title: string,
description: string,
action: Tey.Kit.Features.Core.Data.Action | null,
};
export type WidgetErrorData = {
message: string,
retry: Tey.Kit.Features.Core.Data.Action | null,
};
export type WidgetSortData = {
label: string,
options: {
key: string,
label: string,
}[],
current: string,
url: string,
method: string,
reload: string[],
};
export type WidgetSpanData = {
md: number,
lg: number,
};
export type YouWidget = {
readonly title: string,
readonly component: string,
readonly key: string,
readonly kind: 'task' | 'feed' | 'status' | 'setup',
readonly region: 'main' | 'aside',
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Array<any>,
readonly sortBy: Tey.Kit.Features.SessionUi.Dashboard.WidgetSortData | null,
readonly person: {
name: string,
email: string,
initials: string,
avatar: {
url: string,
url_2x: string,
} | null,
role: string | null,
workspace: string,
timezone: string,
},
readonly checklist: Tey.Kit.Features.Core.Data.ChecklistItem[],
};
}
export namespace Data {
export type FocusShellData = {
product: {
name: string,
url: string,
logomark: string | null,
logomark_dark: string | null,
logomark_bar: string | null,
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
lockup_image: string | null,
},
user: {
name: string,
email: string,
initials: string,
avatar_url: string | null,
avatar_url_2x: string | null,
},
links: {
sign_out: string,
switch_account: string,
},
step: {
current: number,
total: number,
label: string,
} | null,
kind: undefined,
};
export type GuestShellData = {
product: {
name: string,
url: string,
logomark: string | null,
logomark_dark: string | null,
logomark_bar: string | null,
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
lockup_image: string | null,
},
footer: {
identity: string,
credit: string,
links: {
label: string,
url: string,
}[],
},
kind: undefined,
};
}
export namespace Search {
export type SearchResult = {
id: string,
label: string,
url: string | null,
description: string | null,
meta: string | null,
};
}
export namespace ViewModels {
export type ChallengeViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly status: string | null,
readonly email: string | null,
};
export type ChangeRoleViewModel = {
readonly title: string,
readonly role: string | null,
readonly roles: {
value: string,
label: string,
}[],
readonly submit: Tey.Kit.Features.Core.Data.Action,
};
export type ConfirmPasswordViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly cancel: string,
};
export type DeleteAccountViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly owned: string[],
readonly submit: Tey.Kit.Features.Core.Data.Action,
readonly confirms_password: boolean,
};
export type DeleteWorkspaceViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly workspace_name: string,
readonly members: number,
readonly submit: Tey.Kit.Features.Core.Data.Action,
readonly confirms_password: boolean,
readonly removes: string[],
};
export type ErrorViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly status: number,
readonly message: string,
readonly recovery_url: string,
readonly recovery_label: string,
};
export type InvitationViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly state: string,
readonly message: string,
readonly invitation: {
workspace: string,
inviter: string | null,
role: string,
email: string,
message: string | null,
} | null,
readonly signed_in_as: string | null,
readonly accept_url: string,
readonly register_url: string,
readonly login_url: string,
readonly workspace_url: string | null,
readonly other_email: boolean,
readonly verify_url: string | null,
};
export type InviteMemberViewModel = {
readonly title: string,
readonly roles: {
value: string,
label: string,
}[],
readonly submit: Tey.Kit.Features.Core.Data.Action,
};
export type LoginViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly status: string | null,
readonly localLogin: {
url: string,
email: string,
} | null,
readonly register: string | null,
readonly passkey: {
options_url: string,
login_url: string,
} | null,
};
export type NoWorkspaceViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly email: string,
readonly invitations: Array<any>,
readonly explanation: string,
readonly create: Tey.Kit.Features.Core.Data.Action | null,
readonly account: Tey.Kit.Features.Core.Data.Action,
};
export type PasswordViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly update_url: string,
readonly notice: string | null,
};
export type PeopleViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly tab: string,
readonly table: Tey.Kit.Features.Core.Data.TableData,
readonly invite: Tey.Kit.Features.Core.Data.Action | null,
readonly roles: {
value: string,
label: string,
}[],
readonly invite_link: string | null,
};
export type PlaceholderViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly description: string,
readonly milestone: string | null,
readonly links: Tey.Kit.Features.Core.Data.Action[],
readonly width: string | null,
};
export type ProfileViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly name: string,
readonly initials: string,
readonly avatar: {
url: string,
url_2x: string,
} | null,
readonly store_url: string,
readonly max_kb: number,
readonly email: string,
readonly email_verified: boolean,
readonly profile_url: string,
readonly notice: string | null,
readonly verify_url: string,
};
export type RegisterViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly heading: string,
readonly lead: string | null,
readonly action: string,
readonly submitLabel: string,
readonly email: string | null,
readonly emailHint: string | null,
readonly submit: Record<string, string>,
readonly throttleMessage: string | null,
readonly login: string | null,
readonly status: string | null,
};
export type ResetPasswordViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly token: string,
readonly email: string,
readonly status: string | null,
};
export type SearchViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly query: string,
readonly url: string,
readonly groups: {
key: string,
label: string,
results: Tey.Kit.Features.SessionUi.Search.SearchResult[],
}[],
readonly partial: boolean,
};
export type SectionsViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly groups: {
label: string,
sections: {
key: string,
label: string,
description: string,
icon: string,
badge: string | null,
url: string,
}[],
}[],
};
export type SecurityViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly two_factor: {
state: string,
qr_svg: string | null,
secret_key: string | null,
codes_left: number,
recovery_codes: string[],
},
readonly sessions: {
supported: boolean,
items: {
agent: string,
ip: string | null,
last_active: string,
current: boolean,
}[],
},
readonly events: {
type: string,
label: string,
ip: string | null,
agent: string,
at: string,
}[],
readonly two_factor_url: string,
readonly recovery_codes_url: string,
readonly sessions_url: string,
readonly passkeys: {
options_url: string,
store_url: string,
confirm_url: string | null,
url: string,
items: {
id: number,
name: string,
created: string,
last_used: string | null,
}[],
} | null,
readonly notice: string | null,
};
export type TransferOwnershipViewModel = {
readonly title: string,
readonly membership: number,
readonly recipient: string,
readonly workspace_name: string,
readonly demoted_to: string,
readonly submit: Tey.Kit.Features.Core.Data.Action,
};
export type WelcomeStepViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly heading: string,
readonly description: string,
readonly submit: Tey.Kit.Features.Core.Data.Action,
readonly skip: Tey.Kit.Features.Core.Data.Action | null,
readonly value: string | null,
readonly initials: string,
readonly max_kb: number,
readonly save: Tey.Kit.Features.Core.Data.Action | null,
};
export type WorkspaceDashboardViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly workspace: string,
readonly next_label: string,
readonly next_url: string,
readonly greeting: string,
readonly aside: 'narrow' | 'wide',
readonly widgets: {
key: string,
title: string,
component: string,
kind: 'task' | 'feed' | 'status' | 'setup',
region: 'main' | 'aside',
span: {
md: number,
lg: number,
},
state: string,
empty: {
title: string,
description: string,
action: Record<string, any> | null,
} | null,
error: {
message: string,
retry: Record<string, any> | null,
} | null,
actions: Record<string, any>[],
}[],
};
}
}
export namespace Settings {
export namespace Data {
export type MailIdentityView = {
workspace_id: number,
sender_name: string | null,
sender_name_default: string,
from_mode: 'platform' | 'domain',
domain: string | null,
from_local: string,
domain_status: 'none' | 'pending' | 'verified' | 'failed',
dns_records: {
type: string,
name: string,
value: string,
priority: number | null,
status: string,
}[],
checked_at: string | null,
reply_to_mode: 'member' | 'fixed' | 'custom',
reply_to_address: string | null,
platform_address: string,
fixed_reply_to: string | null,
effective_from: string,
version: number,
};
export type SettingsView = {
workspace_id: number,
display_name: string,
timezone: string,
navigation: {
label: string,
href: string,
}[],
version: number,
};
}
export namespace ViewModels {
export type EditMailIdentityViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly identity: Tey.Kit.Features.Settings.Data.MailIdentityView,
readonly save: Tey.Kit.Features.Core.Data.Action | null,
readonly check: Tey.Kit.Features.Core.Data.Action | null,
};
export type EditSettingsViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly settings: Tey.Kit.Features.Settings.Data.SettingsView,
readonly save: Tey.Kit.Features.Core.Data.Action | null,
readonly intent_key: string,
};
}
}
export namespace Shell {
export namespace Data {
export type ShellData = {
account: {
label: string,
url: string,
current: boolean,
},
product: {
name: string,
url: string,
logomark: string | null,
logomark_dark: string | null,
logomark_bar: string | null,
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
lockup_image: string | null,
},
workspace: {
id: number,
name: string,
url: string | null,
settings: Tey.Kit.Features.Core.Data.Action | null,
all: Tey.Kit.Features.Core.Data.Action | null,
leave: Tey.Kit.Features.Core.Data.Action | null,
} | null,
switcher: {
current: {
id: number,
name: string,
} | null,
choices: {
id: number,
name: string,
url: string | null,
current: boolean,
}[],
} | null,
navigation: {
label: string,
url: string,
current: boolean,
group: string,
}[],
user: {
name: string,
email: string,
initials: string,
avatar_url: string | null,
avatar_url_2x: string | null,
items: {
label: string,
url: string,
current: boolean,
group: string,
}[],
} | null,
links: {
home: string,
switcher: string,
sign_in: string,
sign_out: string | null,
},
footer: {
identity: string,
credit: string,
links: {
label: string,
url: string,
}[],
},
kind: undefined,
subNavigation: {
label: string,
items: {
label: string,
url: string,
current: boolean,
}[],
} | null,
search: {
url: string,
label: string,
placeholder: string,
} | null,
};
}
export namespace ViewModels {
export type PublicMarkdownViewModel = {
readonly title: string,
readonly description: string | null,
readonly updated: string | null,
readonly html: string,
};
}
}
export namespace Workspaces {
export namespace ViewModels {
export type IndexWorkspaceViewModel = {
readonly title: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
readonly workspaces: {
id: number,
name: string,
initials: string,
role: string | null,
members: number,
current: boolean,
leave: Tey.Kit.Features.Core.Data.Action | null,
}[],
readonly invitations: {
workspace: string,
inviter: string | null,
message: string | null,
expires: string | null,
}[],
readonly create: Tey.Kit.Features.Core.Data.Action | null,
};
}
}
}
}
}
