declare namespace Tey {
export namespace Kit {
export namespace Features {
export namespace Mcp {
export namespace ViewModels {
export type ConnectionsViewModel = {
title: string,
tokens: {
id: string,
label: string,
}[],
scopes: {
key: string,
description: string,
required: boolean,
}[],
connections: {
id: number,
revoked: boolean,
expires_at: string | null,
endpoint: string,
revoke_url: string,
}[],
create_url: string,
intent_key: string,
};
}
}
export namespace SessionUi {
export namespace Data {
export type ShellData = {
product: {
name: string,
url: string,
logomark: string | null,
},
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
},
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
env: string | null,
footer: {
identity: string,
credit: string,
links: {
label: string,
url: string,
}[],
},
};
}
export namespace ViewModels {
export type ChallengeViewModel = {
title: string,
status: string | null,
email: string | null,
};
export type DashboardViewModel = {
title: string,
workspace: string,
next_label: string,
next_url: string,
};
export type ErrorViewModel = {
title: string,
status: number,
message: string,
recovery_url: string,
recovery_label: string,
};
export type InvitationViewModel = {
title: string,
state: string,
message: string,
invitation: {
workspace: string,
inviter: string | null,
role: string,
email: string,
} | null,
signed_in_as: string | null,
accept_url: string,
register_url: string,
login_url: string,
workspace_url: string | null,
};
export type LoginViewModel = {
title: string,
};
export type PasswordViewModel = {
title: string,
update_url: string,
notice: string | null,
};
export type PeopleViewModel = {
title: string,
members: {
id: number,
name: string,
email: string,
initials: string,
avatar: {
url: string,
url_2x: string,
} | null,
role: string,
role_label: string,
joined_at: string | null,
is_you: boolean,
manageable: boolean,
update_url: string,
destroy_url: string,
transferable: boolean,
}[],
invitations: {
id: string,
email: string,
role: string,
role_label: string,
expires_at: string,
expired: boolean,
invited_by: string | null,
resend_url: string,
revoke_url: string,
}[],
roles: {
value: string,
label: string,
}[],
can_manage: boolean,
invite_url: string,
search: string,
sort: string,
invite_link: string | null,
can_transfer: boolean,
transfer_url: string,
};
export type ProfileViewModel = {
title: string,
name: string,
initials: string,
avatar: {
url: string,
url_2x: string,
} | null,
store_url: string,
max_kb: number,
email: string,
email_verified: boolean,
profile_url: string,
notice: string | null,
verify_url: string,
};
export type SectionsViewModel = {
title: string,
groups: {
label: string,
sections: {
key: string,
label: string,
description: string,
icon: string,
url: string,
}[],
}[],
};
export type SecurityViewModel = {
title: string,
two_factor: {
state: string,
qr_svg: string | null,
secret_key: string | null,
codes_left: number,
recovery_codes: string[],
},
sessions: {
supported: boolean,
items: {
agent: string,
ip: string | null,
last_active: string,
current: boolean,
}[],
},
events: {
type: string,
label: string,
ip: string | null,
agent: string,
at: string,
}[],
two_factor_url: string,
recovery_codes_url: string,
sessions_url: string,
notice: string | null,
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
title: string,
settings: Tey.Kit.Features.Settings.Data.SettingsView,
can_write: boolean,
update_url: string,
intent_key: string,
};
}
}
export namespace Workspaces {
export namespace ViewModels {
export type IndexWorkspaceViewModel = {
title: string,
workspaces: {
id: number,
name: string,
}[],
};
}
}
}
}
}
