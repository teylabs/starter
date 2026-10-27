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
title: string,
description: string,
label: string,
} | null,
key: string | null,
destructive: boolean,
intent: string | null,
group: string | null,
menu: boolean,
icon: string | null,
newTab: boolean | null,
};
export type Breadcrumb = {
label: string,
url: string | null,
current: boolean,
home: boolean,
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
};
export type TableData = {
rows: Record<string, any>[],
columns: Tey.Kit.Features.Core.Data.TableColumn[],
sort: Tey.Kit.Features.Core.Data.TableSort | null,
search: string | null,
filters: Record<string, string>,
tabs: Tey.Kit.Features.Core.Data.TableTab[],
pagination: Tey.Kit.Features.Core.Data.TablePagination,
emptyState: unknown | null,
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
export type ConnectionsViewModel = {
readonly title: string,
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
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
}
}
export namespace SessionUi {
export namespace Dashboard {
export type ActivityWidget = {
readonly title: string,
readonly component: string,
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly items: {
id: string,
actor: string,
event: string,
at: string,
}[],
readonly key: string,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Tey.Kit.Features.Core.Data.Action[],
};
export type FailedWidget = {
readonly title: string,
readonly component: string,
readonly state: string,
readonly key: string,
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
readonly actions: Tey.Kit.Features.Core.Data.Action[],
};
export type PeopleWidget = {
readonly title: string,
readonly component: string,
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
readonly list: string,
readonly actions: Array<any>,
readonly key: string,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
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
export type WidgetSpanData = {
md: number,
lg: number,
};
export type YouWidget = {
readonly title: string,
readonly component: string,
readonly span: Tey.Kit.Features.SessionUi.Dashboard.WidgetSpanData,
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
},
readonly actions: Array<any>,
readonly key: string,
readonly state: string,
readonly empty: Tey.Kit.Features.SessionUi.Dashboard.WidgetEmptyData | null,
readonly error: Tey.Kit.Features.SessionUi.Dashboard.WidgetErrorData | null,
};
}
export namespace Data {
export type FocusShellData = {
product: {
name: string,
url: string,
logomark: string | null,
logomark_dark: string | null,
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
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
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
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
export type WelcomeCardData = {
title: string,
body: string,
dismiss: Tey.Kit.Features.Core.Data.Action | null,
checklist: {
id: string,
label: string,
hint: string,
url: string,
state: string,
}[],
done: number,
total: number,
};
}
export namespace ViewModels {
export type ChallengeViewModel = {
readonly title: string,
readonly status: string | null,
readonly email: string | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
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
export type DashboardViewModel = {
readonly title: string,
readonly workspace: string,
readonly next_label: string,
readonly next_url: string,
readonly greeting: string,
readonly welcome: Tey.Kit.Features.SessionUi.Data.WelcomeCardData | null,
readonly widgets: {
key: string,
title: string,
component: string,
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
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type ErrorViewModel = {
readonly title: string,
readonly status: number,
readonly message: string,
readonly recovery_url: string,
readonly recovery_label: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type InvitationViewModel = {
readonly title: string,
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
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
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
readonly status: string | null,
readonly localLogin: {
url: string,
email: string,
} | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type NoWorkspaceViewModel = {
readonly title: string,
readonly email: string,
readonly invitations: Array<any>,
readonly explanation: string,
readonly create: Tey.Kit.Features.Core.Data.Action | null,
readonly account: Tey.Kit.Features.Core.Data.Action,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type PasswordViewModel = {
readonly title: string,
readonly update_url: string,
readonly notice: string | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type PeopleViewModel = {
readonly title: string,
readonly tab: string,
readonly table: Tey.Kit.Features.Core.Data.TableData,
readonly invite: Tey.Kit.Features.Core.Data.Action | null,
readonly roles: {
value: string,
label: string,
}[],
readonly invite_link: string | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type PlaceholderViewModel = {
readonly title: string,
readonly description: string,
readonly milestone: string | null,
readonly links: Tey.Kit.Features.Core.Data.Action[],
readonly width: string | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type ProfileViewModel = {
readonly title: string,
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
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type SectionsViewModel = {
readonly title: string,
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
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type SecurityViewModel = {
readonly title: string,
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
readonly notice: string | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
export type WelcomeStepViewModel = {
readonly title: string,
readonly heading: string,
readonly description: string,
readonly submit: Tey.Kit.Features.Core.Data.Action,
readonly skip: Tey.Kit.Features.Core.Data.Action | null,
readonly value: string | null,
readonly initials: string,
readonly max_kb: number,
readonly save: Tey.Kit.Features.Core.Data.Action | null,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
}
}
export namespace Settings {
export namespace Data {
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
export type EditSettingsViewModel = {
readonly title: string,
readonly settings: Tey.Kit.Features.Settings.Data.SettingsView,
readonly save: Tey.Kit.Features.Core.Data.Action | null,
readonly intent_key: string,
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
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
wordmark: string | null,
wordmark_dark: string | null,
lockup: boolean,
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
};
}
}
export namespace Workspaces {
export namespace ViewModels {
export type IndexWorkspaceViewModel = {
readonly title: string,
readonly workspaces: {
id: number,
name: string,
leave: Tey.Kit.Features.Core.Data.Action | null,
}[],
readonly layout: Tey.Kit.Features.Core.Data.PageLayout,
};
}
}
}
}
}
