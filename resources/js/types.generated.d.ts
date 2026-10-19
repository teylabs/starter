declare namespace App {
namespace Starter {
namespace Notes {
namespace Data {
export type NoteChange = {
note_id: number,
workspace_id: number,
deleted: boolean,
record: App.Starter.Notes.Data.NoteRecord | null,
};
export type NoteRecord = {
id: number,
workspace_id: number,
body: string,
version: number,
};
}
}
namespace SessionUi {
namespace Data {
export type ShellData = {
brand: string,
user: {
id: number,
name: string,
email: string,
} | null,
workspace: {
id: number,
name: string,
} | null,
navigation: {
label: string,
href: string,
current: boolean,
}[],
workspaces: {
id: number,
name: string,
href: string | null,
}[],
logout_url: string | null,
};
}
}
namespace Settings {
namespace Data {
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
}
}
}
