// ShellData and ShellKind moved from session-ui to the shell feature (Tey.Kit.Features.Shell.Data). The old names stay aliases of the
// generated types, so a layout typed against them still checks. New code names the shell's.
declare namespace Tey.Kit.Features.SessionUi.Data {
    export type ShellData = Tey.Kit.Features.Shell.Data.ShellData;
    export type ShellKind = Tey.Kit.Features.Shell.Data.ShellKind;
}
