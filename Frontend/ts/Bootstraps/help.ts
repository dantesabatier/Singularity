import {Application} from "@/Application/Application"
import {HelpController} from "@/Controllers/HelpController"

export const bootstrapHelp = (): void => {
    new Application(
        () => [],
        (context) => [new HelpController(context)],
    ).start()
}
