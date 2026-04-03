// noinspection JSUnusedGlobalSymbols

declare module "*.scss" {
    const stylesheetURL: string;
    export default stylesheetURL;
}

interface BootstrapPopover {
    hide(): void
    show(): void
    setContent(content: Record<string, string>): void
}

interface BootstrapTooltip {
    hide(): void
}

interface BootstrapStatic {
    Popover: new (element: Element, options?: object) => BootstrapPopover
    Tooltip: new (element: Element, options?: object) => BootstrapTooltip
}

interface Window {
    bootstrap: BootstrapStatic
}
