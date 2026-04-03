declare module "dagre" {
    const dagre: unknown
    export default dagre
}

declare module "cytoscape-dagre" {
    import type cytoscape from "cytoscape"

    const cytoscapeDagre: (cy: typeof cytoscape) => void
    export default cytoscapeDagre
}

declare module "cytoscape-node-html-label" {
    import type cytoscape from "cytoscape"

    const nodeHtmlLabel: (cy: typeof cytoscape) => void
    export default nodeHtmlLabel
}
