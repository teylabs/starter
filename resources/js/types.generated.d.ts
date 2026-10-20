declare namespace Tey {
export namespace Kit {
export namespace Features {
export namespace Notes {
export namespace Data {
export type NoteChange = {
note_id: number,
workspace_id: number,
deleted: boolean,
record: Tey.Kit.Features.Notes.Data.NoteRecord | null,
};
export type NoteRecord = {
id: number,
workspace_id: number,
body: string,
version: number,
};
}
export namespace ViewModels {
export type IndexNoteViewModel = {
title: string,
notes: Tey.Kit.Features.Notes.Data.NoteRecord[],
can_write: boolean,
create_url: string,
create_intent: string,
actions: {
url: string,
update_intent: string,
delete_intent: string,
}[],
};
export type ShowNoteViewModel = {
title: string,
note: Tey.Kit.Features.Notes.Data.NoteRecord,
list_url: string,
};
}
}
export namespace SessionUi {
export namespace Data {
export type BreadcrumbData = {
label: string,
url: string | null,
current: boolean,
};
export type ShellData = {
product: {
name: string,
url: string,
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
settings: {
label: string,
current: boolean,
items: {
label: string,
url: string,
current: boolean,
group: string,
}[],
} | null,
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
breadcrumbs: Tey.Kit.Features.SessionUi.Data.BreadcrumbData[],
title: string,
};
}
export namespace ViewModels {
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
