<template>
  <div v-if="loading" class="scanning-overlay">
    <div class="spinner"></div>
    <p>Memuatkan butiran...</p>
  </div>

  <div v-else-if="!app" class="empty-state">
    <i class="fa-solid fa-circle-exclamation"></i>
    <h3>Aplikasi tidak dijumpai</h3>
    <router-link to="/" class="btn btn-secondary mt-4">Kembali</router-link>
  </div>

  <div v-else>
    <!-- Back -->
    <div class="detail-header">
      <router-link to="/" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke semua aplikasi
      </router-link>
    </div>

    <!-- Title -->
    <div style="margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
      <h2 style="font-size: 1.4rem; font-weight: 700;">{{ app.name }}</h2>
      <button
        class="btn-icon app-active-btn"
        :class="{ active: app.is_active }"
        :title="app.is_active ? 'Sembunyikan dari dashboard' : 'Tunjukkan di dashboard'"
        @click="toggleActive"
        style="width: 32px; height: 32px; font-size: 0.8rem;"
      >
        <i :class="app.is_active ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash'"></i>
      </button>
      <span v-if="app.scm_status === 'dirty'" class="status-indicator dirty" style="padding: 4px 12px; font-size: 0.8rem;">
        <span class="stat-dot dirty" style="width: 8px; height: 8px; border-radius: 50%; background: var(--warning);"></span>
        {{ app.changed_files_count || 0 }} perubahan belum disimpan
      </span>
      <span v-else-if="app.scm_status === 'clean'" class="status-indicator clean" style="padding: 4px 12px; font-size: 0.8rem;">
        <span class="stat-dot clean" style="width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></span>
        Bersih
      </span>
      <span v-else class="status-indicator" style="padding: 4px 12px; font-size: 0.8rem; color: var(--text-muted);">
        <i class="fa-solid fa-circle-notch"></i> Tiada aktiviti Git
      </span>
    </div>

    <!-- Badges -->
    <div class="badge-row" style="margin-bottom: 32px;">
      <span v-for="s in app.stacks" :key="s.type" class="badge" :class="s.type">
        {{ s.language_version || s.type.toUpperCase() }} {{ s.framework ? '· ' + s.framework : '' }}
      </span>
    </div>

    <!-- Two-panel grid -->
    <div class="detail-grid">
      <!-- Left: Stack -->
      <div class="detail-panel">
        <div class="panel-title">
          <i class="fa-solid fa-layer-group"></i> Stack
        </div>
        <div v-if="app.stacks.length === 0" class="text-muted text-sm">Tiada stack dikesan.</div>
        <div v-for="s in app.stacks" :key="s.type" class="stack-section">
          <div class="stack-label">{{ s.type === 'php' ? 'composer.json' : 'package.json' }}</div>
          <div class="stack-item" v-if="s.language_version">
            <span class="key">Versi {{ s.type === 'php' ? 'PHP' : 'Node' }}</span>
            <span class="value">{{ s.language_version }}</span>
          </div>
          <div class="stack-item" v-if="s.framework">
            <span class="key">Framework</span>
            <span class="value">{{ s.framework }}</span>
          </div>
          <div class="stack-item" v-for="(ver, pkg) in s.dependencies" :key="pkg">
            <span class="key">{{ pkg }}</span>
            <span class="value">{{ ver }}</span>
          </div>
        </div>
      </div>

      <!-- Right: SCM + Services -->
      <div>
        <!-- SCM -->
        <div class="detail-panel" style="margin-bottom: 24px;">
          <div class="panel-title">
            <i class="fa-solid fa-code-branch"></i> SCM
          </div>
          <div v-if="app.scm_status === 'no_git' || !app.remote_url" class="text-muted text-sm">
            Tiada repositori Git dikesan.
          </div>
          <div v-else>
            <div class="scm-section">
              <div class="stack-label">Remote</div>
              <div class="scm-remote">
                <i class="fa-brands" :class="app.remote_url.includes('gitlab') ? 'fa-gitlab' : 'fa-github'"></i>
                {{ app.remote_url }}
              </div>
            </div>
            <div class="scm-section" v-if="app.branch">
              <div class="stack-label">Cawangan Semasa</div>
              <div style="font-family: monospace; font-size: 0.9rem; font-weight: 600; color: var(--accent);">
                {{ app.branch }}
              </div>
            </div>
            <div class="scm-section" v-if="app.scm_status === 'dirty' && app.changed_files?.length">
              <div class="stack-label">Fail Diubah</div>
              <div v-for="f in app.changed_files" :key="f.file_path" style="font-size: 0.82rem; padding: 2px 0; display: flex; gap: 8px;">
                <span :style="{ color: f.status === 'A' ? 'var(--success)' : 'var(--warning)', fontWeight: 600 }">{{ f.status }}</span>
                <span style="color: var(--text-secondary);">{{ f.file_path }}</span>
              </div>
            </div>
            <div class="scm-section" v-if="app.last_commit_hash">
              <div class="stack-label">Komit Terakhir</div>
              <div class="commit-card">
                <div class="commit-hash">{{ app.last_commit_hash?.substring(0, 8) }}</div>
                <div class="commit-msg">{{ app.last_commit_message }}</div>
                <div class="commit-meta">
                  <span><i class="fa-regular fa-user"></i> {{ app.last_commit_author }}</span>
                  <span><i class="fa-regular fa-calendar"></i> {{ formatDate(app.last_commit_date) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Services -->
        <div class="detail-panel">
          <div class="panel-title">
            <i class="fa-solid fa-plug"></i> Perkhidmatan Pihak Ketiga
            <button class="btn btn-sm btn-secondary" style="margin-left: auto;" @click="showServiceForm = !showServiceForm">
              <i class="fa-solid fa-pen-to-square"></i> Edit
            </button>
          </div>
          <div v-if="!app.services?.length && !showServiceForm" class="text-muted text-sm">
            Tiada perkhidmatan didaftarkan. Klik "Edit" untuk menambah.
          </div>
          <div v-for="svc in app.services" :key="svc.id" class="stack-item">
            <span class="key">{{ svc.service_name }}</span>
            <span class="value" style="text-transform: capitalize;">{{ svc.service_type }}</span>
          </div>

          <!-- Inline Service Editor -->
          <div v-if="showServiceForm" style="margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <div v-for="(svc, i) in serviceList" :key="i" style="margin-bottom: 12px; padding: 12px; background: var(--bg-primary); border-radius: var(--radius-sm);">
              <div style="display: flex; gap: 8px; margin-bottom: 6px;">
                <input v-model="svc.service_name" placeholder="Nama perkhidmatan" style="flex: 1; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: inherit;">
                <select v-model="svc.service_type" style="padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: inherit;">
                  <option v-for="t in serviceTypes" :key="t" :value="t">{{ t }}</option>
                </select>
                <button class="btn-icon" @click="removeService(i)" style="color: var(--danger);"><i class="fa-solid fa-xmark"></i></button>
              </div>
              <input v-model="svc.provider" placeholder="Pembekal (cth. Firebase)" style="width: 100%; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: inherit; margin-bottom: 4px;">
              <input v-model="svc.notes" placeholder="Nota" style="width: 100%; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.82rem; font-family: inherit;">
            </div>
            <div style="display: flex; gap: 8px;">
              <button class="btn btn-sm btn-secondary" @click="addService"><i class="fa-solid fa-plus"></i> Tambah</button>
              <button class="btn btn-sm btn-primary" @click="saveServices" :disabled="saving">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
            </div>
          </div>
        </div>

        <!-- Notes / Remarks journal -->
        <div class="detail-panel">
          <div class="panel-title">
            <i class="fa-solid fa-note-sticky"></i> Catatan & Ulasan
          </div>

          <!-- Add form (on top) -->
          <div class="notes-editor">
            <textarea
              v-model="noteDraft"
              placeholder="Tulis catatan atau ulasan... (disimpan dengan tarikh & masa)"
              style="width: 100%; padding: 12px 14px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.9rem; font-family: inherit; min-height: 100px; resize: vertical; background: var(--bg-secondary); color: var(--text-primary);"
            ></textarea>
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px;">
              <button class="btn btn-sm btn-primary" @click="saveNote" :disabled="savingNote || !noteDraft.trim()">
                <i class="fa-solid" :class="savingNote ? 'fa-spinner fa-spin' : 'fa-plus'"></i>
                {{ savingNote ? 'Menyimpan...' : 'Tambah Catatan' }}
              </button>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="!app.notes?.length" class="text-muted text-sm" style="margin-top: 16px;">
            Tiada catatan lagi.
          </div>

          <!-- Journal entries with pagination (20 per page) -->
          <div v-if="app.notes?.length" style="margin-top: 16px;">
            <div v-for="n in paginatedNotes" :key="n.id" class="note-entry">
              <div class="note-meta">
                <span class="note-date">
                  <i class="fa-regular fa-calendar"></i>
                  {{ formatNoteDate(n.created_at) }}
                </span>
                <span v-if="n.updated_at !== n.created_at" class="note-edited">
                  (disunting {{ formatNoteDate(n.updated_at) }})
                </span>
                <button class="btn-icon note-delete" @click="removeNote(n.id)">
                  <i class="fa-solid fa-trash-can"></i>
                </button>
              </div>
              <div class="note-content">{{ n.content }}</div>
            </div>

            <!-- Pagination controls -->
            <div v-if="totalPages > 1" class="pagination" style="margin-top: 16px; display: flex; justify-content: center; gap: 8px;">
              <button 
                class="btn btn-sm btn-secondary" 
                @click="currentPage--"
                :disabled="currentPage === 1"
              >
                <i class="fa-solid fa-angle-left"></i>
              </button>
              <span style="align-self: center; font-size: 0.85rem;">
                Halaman {{ currentPage }} dari {{ totalPages }}
              </span>
              <button 
                class="btn btn-sm btn-secondary" 
                @click="currentPage++"
                :disabled="currentPage === totalPages"
              >
                <i class="fa-solid fa-angle-right"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Ulasan table - shows work done per app -->
        <div class="detail-panel" v-if="app.ulasan?.length">
          <div class="panel-title">
            <i class="fa-solid fa-list"></i> Ulasan
          </div>
          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
              <thead>
                <tr style="background: var(--bg-secondary);">
                  <th style="padding: 10px 12px; text-align: left; border: 1px solid var(--border-color);">Tarikh</th>
                  <th style="padding: 10px 12px; text-align: left; border: 1px solid var(--border-color);">Kerja yang Dilaksanakan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(u, idx) in ulasanRows" :key="u.id || idx">
                  <td style="padding: 10px 12px; border: 1px solid var(--border-color);">
                    {{ formatNoteDate(u.tarikh) }}
                  </td>
                  <td style="padding: 10px 12px; border: 1px solid var(--border-color); color: var(--text-primary);">
                    {{ u.kerja }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    <!-- Actions -->
    <div class="detail-actions">
      <button class="btn btn-primary" @click="openEditor(app.path)">
        <i class="fa-solid fa-code"></i> Buka di Editor
      </button>
      <button class="btn btn-secondary" @click="openBrowser(app.name)">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> {{ app.name }}.test
      </button>
      <button class="btn btn-secondary" @click="refreshSingle">
        <i class="fa-solid fa-arrows-rotate"></i> Imbas Semula
      </button>
    </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchApp, updateApp, triggerScan, addNote, deleteNote } from '../api'
import { buildEditorUrl, buildBrowserUrl } from '../config.js'

const route = useRoute()
const app = ref(null)
const loading = ref(true)
const showServiceForm = ref(false)
const serviceList = ref([])
const saving = ref(false)
const noteDraft = ref('')
const savingNote = ref(false)
const togglingActive = ref(false)
const currentPage = ref(1)
const itemsPerPage = 20

const serviceTypes = ['auth', 'database', 'cache', 'storage', 'email', 'payment', 'sms', 'api', 'monitoring', 'search', 'queue', 'cdn', 'other']

// Pagination for notes
const totalPages = computed(() => {
  const notes = app.value?.notes?.length || 0
  return Math.ceil(notes / itemsPerPage)
})
const paginatedNotes = computed(() => {
  const notes = (app.value?.notes || []).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  const start = (currentPage.value - 1) * itemsPerPage
  return notes.slice(start, start + itemsPerPage)
})

onMounted(async () => {
  try {
    app.value = await fetchApp(route.params.name)
    serviceList.value = (app.value.services || []).map(s => ({ ...s }))
  } finally {
    loading.value = false
  }
})

function addService() {
  serviceList.value.push({ service_name: '', service_type: 'api', provider: '', notes: '' })
}

function removeService(i) {
  serviceList.value.splice(i, 1)
}

async function saveServices() {
  saving.value = true
  try {
    const cleaned = serviceList.value.filter(s => s.service_name.trim())
    const result = await updateApp(route.params.name, { services: cleaned })
    app.value = result.app
    showServiceForm.value = false
  } catch (e) {
    alert('Gagal menyimpan: ' + e.message)
  } finally {
    saving.value = false
  }
}

async function refreshSingle() {
  loading.value = true
  try {
    await triggerScan(app.value.path)
    app.value = await fetchApp(route.params.name)
  } finally {
    loading.value = false
  }
}

async function toggleActive() {
  if (!app.value || togglingActive.value) return
  togglingActive.value = true
  const next = !app.value.is_active
  try {
    const result = await updateApp(route.params.name, { active: next })
    app.value = result.app
  } catch (e) {
    alert('Gagal menukar status: ' + e.message)
  } finally {
    togglingActive.value = false
  }
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('ms-MY', {
    day: 'numeric', month: 'long', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function openEditor(path) {
  const url = buildEditorUrl(path)
  if (!url) return
  window.open(url, '_blank')
}

function openBrowser(name) {
  window.open(buildBrowserUrl(name), '_blank')
}

function formatNoteDate(input) {
  if (!input) return ''
  return new Date(input.replace(' ', 'T')).toLocaleString('ms-MY', {
    day: 'numeric', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

async function saveNote() {
  const content = noteDraft.value.trim()
  if (!content) return
  savingNote.value = true
  try {
    await addNote(route.params.name, content)
    app.value = await fetchApp(route.params.name)
    currentPage.value = 1
    noteDraft.value = ''
  } catch (e) {
    alert('Gagal menyimpan catatan: ' + e.message)
  } finally {
    savingNote.value = false
  }
}

async function removeNote(noteId) {
  if (!window.confirm('Padam catatan ini?')) return
  try {
    await deleteNote(route.params.name, noteId)
    app.value = await fetchApp(route.params.name)
    currentPage.value = 1
  } catch (e) {
    alert('Gagal memadam catatan: ' + e.message)
  }
}

// Ulasan rows - transform api.ulasan to table format
const ulasanRows = computed(() => {
  const data = app.value?.ulasan || []
  return data.map(u => ({
    id: u.id,
    tarikh: u.tarikh || u.created_at,
    kerja: u.kerja || u.task || u.description || 'Tiada butiran'
  }))
})
</script>
