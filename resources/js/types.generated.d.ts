declare namespace Tey {
export namespace Kit {
export namespace Features {
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
export type LoginViewModel = {
title: string,
};
export type SectionsViewModel = {
title: string,
groups: {
label: string,
sections: {
label: string,
description: string,
url: string,
}[],
}[],
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
