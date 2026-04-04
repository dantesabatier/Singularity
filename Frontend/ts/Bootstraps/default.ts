import {Application} from "@/Application/Application"

export const bootstrapDefault = (): void => {
    new Application(
        () => [],
        () => [],
    ).start()
}
