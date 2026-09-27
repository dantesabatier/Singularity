<?php

declare(strict_types=1);

/**
 * The `UserDefaults` keys behind the preferences, the values some of those keys are allowed to
 * hold, and the one environment variable read at launch.
 *
 * `UserDefaults` is untyped and its accessors do not complain on a mismatch: `UserDefaults::string()` answers
 * null and `UserDefaults::bool()` answers false. So each key's docblock names the accessor its value has to be
 * read with, which is the one fact the name cannot carry and the one a wrong guess hides.
 */

namespace App;

/** @var string The user's name or company, read with `UserDefaults::string()`. Also seeds the bundle identifier and the Composer vendor of a generated project. */
const CompanyNamePreferencesKey = "companyName";
/** @var string Whether deleting a project also deletes its folder on disk, read with `UserDefaults::bool()`. */
const AutomaticallyDeleteProjectFoldersPreferencesKey = "automaticallyDeleteProjectFolders";
/** @var string Whether deleting a mapping model also deletes the file it generated, read with `UserDefaults::bool()`. */
const AutomaticallyDeleteMappingModelFilesPreferencesKey = "automaticallyDeleteMappingModelFiles";
/** @var string Node positions in the mapping view, read with `UserDefaults::dictionary()`. A dictionary of project name to a dictionary of table name to position, so one key holds every project's layout. */
const EntityPositionsMappingPreferencesKey = "entityPositionsMapping";
/** @var string Which view the editor opens in, read with `UserDefaults::string()`. Holds one of `EditorTableViewValue` or `EditorGraphViewValue`, not any string. */
const EditorSelectedViewPreferencesKey = "editorSelectedView";
/** @var string The editor's three pane widths as percentages, read with `UserDefaults::array()`. Three numbers that a resize writes back; a fourth pane would need the shape changed, not just the value. */
const EditorSplitSizesPreferencesKey = "editorSplitSizes";
/** @var string Whether the copilot panel is available in the editor, read with `UserDefaults::bool()`. */
const EditorCopilotEnabledPreferencesKey = "editorCopilotEnabled";
/** @var string The value `EditorSelectedViewPreferencesKey` holds when the user has not chosen the graph view. */
const EditorTableViewValue = "tableView";
/** @var string The other value `EditorSelectedViewPreferencesKey` can hold, compared against by `EditorController::$isGraphViewSelected` through the class constant `EditorController::$graphViewValue`. */
const EditorGraphViewValue = "graphView";
/** @var string Referenced nowhere in the tree. Left in place pending a decision on whether the graph view is meant to persist state of its own. */
const GraphViewPreferencesKey = "graphViewPreferences";
/** @var string Whether an export carries the rows as well as the schema, read with `UserDefaults::bool()`. */
const ExportIncludeDataPreferencesKey = "exportIncludeData";
/** @var string Whether an export carries the documentation comments, read with `UserDefaults::bool()`. */
const ExportIncludeCommentsPreferencesKey = "exportIncludeComments";
/** @var string The directory the export panel opens at, read with `UserDefaults::string()`. */
const ExportLastDirectoryPreferencesKey = "exportLastDirectory";
/** @var string Referenced nowhere in the tree. Its PascalCase value marks it as a sentinel standing in for a string that was never chosen, which is why it does not read like the preference values beside it. */
const UndefinedStringValue = "UndefinedStringValue";
/** @var string Environment variable, not a preference: when set, `LatteRenderer` loads assets from the Vite dev server at this URL instead of the built manifest. Empty or unset means read `Build/.vite/manifest.json`. */
const ViteDevServerEnvironmentKey = "VITE_DEV_SERVER";
/** @var string The identifier of the provider the copilot calls, read with `UserDefaults::string()`. Must match the `$identifier` of a `Provider` in `LLMProvidersPreferencesKey`. */
const LLMProviderPreferencesKey = "llmProvider";
/** @var string Registered when the user has expressed no provider preference. Must match the `$identifier` of a `Provider`, or `Provider::client()` falls through to `StandardLLMClient`. */
const LLMProviderPreferencesDefault = "anthropic";
/** @var string The API identifier of the selected model, read with `UserDefaults::string()`. One of the selected provider's models, and the identifier rather than the display name the preferences view shows. */
const LLMModelPreferencesKey = "llmModel";
/** @var string Registered when the user has expressed no model preference. Must be the API identifier of one of the selected provider's models, not its display name. */
const LLMModelPreferencesDefault = "claude-opus-5";
/** @var string Every configured provider, read with `UserDefaults::array()`. An array of provider dictionaries, each carrying its own endpoint, credential and model list; the API key is stripped on the way to the browser by `Provider::$redactedDictionaryRepresentation`. */
const LLMProvidersPreferencesKey = "llmProviders";
