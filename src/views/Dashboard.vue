<template>
  <div>
    <!-- Search & Filter -->
    <div class="search-filter-bar">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" v-model="search" placeholder="Cari aplikasi, stack, cawangan..." @input="applyFilters">
      </div>
      <div class="filter-chips">
        <button class="chip" :class="{ active: activeFilter === 'all' }" @click="setFilter('all')">Semua</button>
        <button class="chip" :class="{ active: activeFilter === 'php' }" @click="setFilter('php')">PHP</button>
        <button class="chip" :class="{ active: activeFilter === 'node' }" @click="setFilter('node')">Node</button>
        <button class="chip" :class="{ active: activeFilter === 'vue' }" @click="setFilter('vue')">Vue</button>
        <button class="chip" :class="{ active: activeFilter === 'dirty' }" @click="setFilter('dirty')">Kotor</button>
        <button class="chip" :class="{ active: activeFilter === 'clean' }" @click="setFilter('clean')">Bersih</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="scanning-overlay">
      <div class="spinner"></div>
      <p>Memuatkan aplikasi...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredApps.length === 0" class="empty-state">
      <i class="fa-solid fa-folder-open"></i>
      <h3>Tiada aplikasi sepadan</h3>
      <p>Cuba istilah carian lain atau kosongkan penapis.</p>
      <button class="btn btn-secondary" @click="clearFilters">Kosongkan penapis</button>
    </div>

    <!-- App Grid -->
    <div v-else class="app-grid">
      <!-- Active apps -->
      <template v-for="app in activeApps" :key="app.id">
        <router-link
          :to="`/app/${encodeURIComponent(app.name)}`"
          class="app-card"
          :class="{ inactive: !app.is_active }"
        >
          <div class="app-card-header">
            <span class="app-card-name">
              <i v-if="app.is_pinned" class="fa-solid fa-thumbtack app-pin-icon" title="Disematkan"></i>
              {{ app.name }}
              <span v-if="!app.folder_exists" class="folder-not-found" title="Folder tidak ditemunnya"> Tidak Ditemui</span>
            </span>
            <div class="app-card-header-right">
              <button
                class="btn-icon app-active-btn"
                :class="{ active: app.is_active }"
                :title="app.is_active ? 'Sembunyikan dari dashboard' : 'Tunjukkan di dashboard'"
                @click.prevent.stop="toggleActive(app)"
              >
                <i :class="app.is_active ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
              </button>
              <button
                class="btn-icon app-pin-btn"
                :class="{ active: app.is_pinned }"
                :title="app.is_pinned ? 'Nyahsemat' : 'Semat ke atas'"
                @click.prevent.stop="togglePin(app)"
              >
                <i class="fa-solid fa-thumbtack"></i>
              </button>
              <span
                class="app-card-status"
                :class="app.scm_status"
                :data-tooltip="statusTooltip(app)"
              ></span>
            </div>
          </div>

          <div class="badge-row">
            <span v-for="s in app.stacks" :key="s.type" class="badge" :class="s.type">
              {{ stackLabel(s) }}
            </span>
          </div>

          <div class="app-card-meta">
            <div class="meta-row" v-if="app.branch">
              <i class="fa-solid fa-code-branch"></i>
              <span class="branch">{{ app.branch }}</span>
            </div>
            <div class="meta-row" v-if="app.last_commit_date">
              <i class="fa-solid fa-clock"></i>
              <span>Komit terakhir {{ timeAgo(app.last_commit_date) }}</span>
            </div>
            <div class="meta-row" v-if="app.remote_url">
              <i class="fa-brands fa-gitlab"></i>
              <span class="text-muted">{{ shortRemote(app.remote_url) }}</span>
            </div>
            <div class="meta-row" v-if="app.notes?.length">
              <i class="fa-solid fa-note-sticky"></i>
              <span class="note-preview" :title="app.notes[0].content">
                {{ notePreview(app.notes[0]) }}
              </span>
              <span class="note-preview-time" v-if="app.notes[0].created_at">
                {{ noteTime(app.notes[0].created_at) }}
              </span>
            </div>
          </div>

          <div class="app-card-actions" @click.prevent.stop>
            <button class="btn btn-sm btn-secondary" @click.stop="openEditor(app.path)">
              <i class="fa-solid fa-code"></i> Buka di Editor
            </button>
            <button class="btn btn-sm btn-secondary" @click.stop="openBrowser(app.name)">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ app.name }}.test
            </button>
          </div>
        </router-link>
      </template>

      <!-- Inactive separator (only shown when there are inactive apps) -->
      <div v-if="inactiveApps.length" class="inactive-separator">
        <span>Aplikasi Tidak Aktif</span>
      </div>

      <!-- Inactive apps -->
      <template v-for="app in inactiveApps" :key="app.id">
        <router-link
          :to="`/app/${encodeURIComponent(app.name)}`"
          class="app-card"
          :class="{ inactive: !app.is_active }"
        >
          <div class="app-card-header">
            <span class="app-card-name">
              <i v-if="app.is_pinned" class="fa-solid fa-thumbtack app-pin-icon" title="Disematkan"></i>
              {{ app.name }}
              <span v-if="!app.folder_exists" class="folder-not-found" title="Folder tidak ditemunnya"> Tidak Ditemui</span>
            </span>
            <div class="app-card-header-right">
              <button
                class="btn-icon app-active-btn"
                :class="{ active: app.is_active }"
                :title="'Aktifkan semula di dashboard'"
                @click.prevent.stop="toggleActive(app)"
              >
                <i :class="app.is_active ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
              </button>
              <span
                class="app-card-status"
                :class="app.scm_status"
                :data-tooltip="statusTooltip(app)"
              ></span>
            </div>
          </div>

          <div class="badge-row">
            <span v-for="s in app.stacks" :key="s.type" class="badge" :class="s.type">
              {{ stackLabel(s) }}
            </span>
          </div>

          <div class="app-card-meta">
            <div class="meta-row" v-if="app.branch">
              <i class="fa-solid fa-code-branch"></i>
              <span class="branch">{{ app.branch }}</span>
            </div>
            <div class="meta-row" v-if="app.last_commit_date">
              <i class="fa-solid fa-clock"></i>
              <span>Komit terakhir {{ timeAgo(app.last_commit_date) }}</span>
            </div>
            <div class="meta-row" v-if="app.remote_url">
              <i class="fa-brands fa-gitlab"></i>
              <span class="text-muted">{{ shortRemote(app.remote_url) }}</span>
            </div>
          </div>

          <div class="app-card-actions" @click.prevent.stop>
            <button class="btn btn-sm btn-secondary" @click.stop="openEditor(app.path)">
              <i class="fa-solid fa-code"></i> Buka di Editor
            </button>
            <button class="btn btn-sm btn-secondary" @click.stop="openBrowser(app.name)">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ app.name }}.test
            </button>
          </div>
        </router-link>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { buildEditorUrl, buildBrowserUrl } from '../config.js'
import { updateApp } from '../api'

const props = defineProps({
  apps: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['filter-change', 'toggled'])

const search = ref('')
const activeFilter = ref('all')

const filteredApps = computed(() => {
  let list = props.apps
  const q = search.value.toLowerCase()
  const f = activeFilter.value

  if (f === 'php') list = list.filter(a => a.stack_tags?.includes('php'))
  if (f === 'node') list = list.filter(a => a.stack_tags?.includes('node'))
  if (f === 'vue') list = list.filter(a => a.stack_tags?.includes('vue'))
  if (f === 'dirty') list = list.filter(a => a.scm_status === 'dirty')
  if (f === 'clean') list = list.filter(a => a.scm_status === 'clean')

  if (q) {
    list = list.filter(a => {
      const matchName = a.name.toLowerCase().includes(q)
      const matchStack = a.stack_tags?.some(t => t.includes(q))
      const matchBranch = a.branch?.toLowerCase().includes(q)
      return matchName || matchStack || matchBranch
    })
  }

  emit('filter-change', list)
  return list
})

// Split into active and inactive — inactive cards render under a separator at the bottom
const activeApps = computed(() => filteredApps.value.filter(a => a.is_active))
const inactiveApps = computed(() => filteredApps.value.filter(a => !a.is_active))

function setFilter(f) {
  activeFilter.value = f
}

function clearFilters() {
  search.value = ''
  activeFilter.value = 'all'
}

function stackLabel(s) {
  const parts = []
  if (s.language_version) parts.push(s.language_version)
  if (s.framework) parts.push(s.framework)
  else parts.push(s.type.toUpperCase())
  return parts.join(' ')
}

function statusTooltip(app) {
  if (app.scm_status === 'dirty') return `${app.changed_files_count || 0} perubahan belum disimpan`
  if (app.scm_status === 'clean') return 'Salinan kerja bersih'
  return 'Tiada Git'
}

function timeAgo(dateStr) {
  const then = new Date(dateStr)
  const now = new Date()
  const diff = Math.floor((now - then) / 1000)
  if (diff < 60) return 'sebentar tadi'
  if (diff < 3600) return `${Math.floor(diff / 60)}m lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)}j lalu`
  if (diff < 604800) return `${Math.floor(diff / 86400)}h lalu`
  return `${Math.floor(diff / 604800)}m lalu`
}

function shortRemote(url) {
  if (!url) return ''
  const cleaned = url.replace(/^git@/, '').replace(/\.git$/, '').replace(':', '/')
  const parts = cleaned.split('/')
  return parts.slice(-2).join('/')
}

// Latest catatan preview — single line, truncated with ellipsis
function notePreview(note) {
  const content = (note.content || '').replace(/\s+/g, ' ').trim()
  return content.length > 60 ? content.slice(0, 60) + '…' : content
}

// Short timestamp for the note, e.g. "12 Ogos · 11:30"
function noteTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr.replace(' ', 'T'))
  return d.toLocaleString('ms-MY', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).replace(',', ' ·')
}

function openEditor(path) {
  const url = buildEditorUrl(path)
  if (!url) return
  window.open(url, '_blank')
}

function openBrowser(name) {
  window.open(buildBrowserUrl(name), '_blank')
}

async function togglePin(app) {
  const next = !app.is_pinned
  try {
    await updateApp(app.name, { pinned: next })
    app.is_pinned = next
    app.pinned_at = next ? new Date().toISOString() : null
    sortByPin() // re-apply the dashboard sort order
  } catch (e) {
    alert('Gagal menyemat: ' + e.message)
  }
}

async function toggleActive(app) {
  const next = !app.is_active
  try {
    await updateApp(app.name, { active: next })
    app.is_active = next
    emit('toggled')  // Notify parent to refresh
  } catch (e) {
    alert('Gagal menukar status: ' + e.message)
  }
}

function sortByPin() {
  props.apps.sort((x, y) => {
    if (!!x.is_pinned !== !!y.is_pinned) return x.is_pinned ? -1 : 1
    return 0
  })
}
</script>
