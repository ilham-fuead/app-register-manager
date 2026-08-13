const BASE = '/app-manager/api'

async function req(url, options = {}) {
  const res = await fetch(BASE + url, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({ error: res.statusText }))
    throw new Error(err.error || `HTTP ${res.status}`)
  }
  return res.json()
}

export function fetchApps(params = {}) {
  const qs = new URLSearchParams(params).toString()
  return req(`/apps.php${qs ? '?' + qs : ''}`)
}

export function fetchApp(name) {
  return req(`/apps.php?name=${encodeURIComponent(name)}`)
}

export function addApp(name, path, notes) {
  return req('/apps.php', {
    method: 'POST',
    body: JSON.stringify({ name, path, notes }),
  })
}

export function updateApp(name, data) {
  return req(`/apps.php?name=${encodeURIComponent(name)}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  })
}

export function triggerScan(path) {
  const qs = path ? `?path=${encodeURIComponent(path)}` : ''
  return req(`/scan.php${qs}`)
}

export function addNote(name, note) {
  return req(`/apps.php?name=${encodeURIComponent(name)}`, {
    method: 'POST',
    body: JSON.stringify({ note }),
  })
}

export function deleteNote(name, noteId) {
  return req(`/apps.php?name=${encodeURIComponent(name)}&note_id=${noteId}`, {
    method: 'DELETE',
  })
}

// Config API
export function fetchConfig(key) {
  const qs = key ? `?key=${encodeURIComponent(key)}` : ''
  return req(`/config.php${qs}`)
}

export function updateConfig(key, value) {
  return req(`/config.php?key=${encodeURIComponent(key)}`, {
    method: 'PUT',
    body: JSON.stringify({ value }),
  })
}
