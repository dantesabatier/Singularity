import "../scss/main.scss"
import * as bootstrap from "bootstrap"
import cytoscape from "cytoscape"
import cytoscapeDagre from "cytoscape-dagre"
import registerNodeHtmlLabel from "cytoscape-node-html-label"
import "db-viewer-component"
import Split from "split.js"
import * as dagre from "dagre"
import {Application} from "./Application/Application"

cytoscape.use(cytoscapeDagre)
registerNodeHtmlLabel(cytoscape)
window.cytoscape = cytoscape
window.cytoscapeDagre = cytoscapeDagre
window.dagre = dagre
window.bootstrap = bootstrap
window.Split = Split
new Application().start()
