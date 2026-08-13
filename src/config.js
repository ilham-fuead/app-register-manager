// Runtime config for the App Manager frontend.
// Edit these to match your local setup; they get baked into dist/ at build time.

export const APP_CONFIG = {
  // URI scheme for "Open in Editor". Examples:
  //   'cursor'      → cursor://file/<path>     (Cursor)
  //   'vscode'      → vscode://file/<path>     (VS Code)
  //   'vscode-insiders' → vscode-insiders://file/<path>
  //   'phpstorm'    → phpstorm://open?file=<path>
  //   'sublime'     → subl://open?url=file://<path>
  //   'idea'        → idea://open?file=<path>
  //   ''            → disables the button
  editorScheme: 'vscode',

  // TLD used to build the "Open in browser" URL from app.name.
  // Set to '' to use http://localhost/<name>/ instead (works under Laragon
  // even when .test TLDs are blocked by corporate gateways).
  browserTld: 'test',
}

export function buildEditorUrl(path) {
  const scheme = APP_CONFIG.editorScheme
  if (!scheme) return null
  // Use forward slashes for URI; path may come in with backslashes on Windows.
  const fwd = String(path || '').replace(/\\/g, '/')
  return `${scheme}://file/${fwd}`
}

export function buildBrowserUrl(name) {
  const tld = APP_CONFIG.browserTld
  if (!tld) return `http://localhost/${name}/`
  return `http://${name}.${tld}`
}
