<template>
  <div class="diffuse-background"></div>
  <div class="app-container">
    <header class="app-header">
      <div class="app-title">
        <i class="fa-solid fa-server icon"></i>
        <h1>Apps</h1>
      </div>
      <div class="header-actions">
        <button class="btn btn-icon" @click="showConfigModal = true" title="Pengaturan">
          <i class="fa-solid fa-gear"></i>
        </button>
        <button class="btn btn-secondary" @click="refreshAll" :disabled="scanning">
          <i class="fa-solid fa-arrows-rotate" :class="{ 'fa-spin': scanning }"></i>
          <span>{{ scanning ? 'Imbas...' : 'Segar Semula' }}</span>
        </button>
        <router-link to="/add" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i> <span>Tambah Aplikasi</span>
        </router-link>
      </div>
    </header>

    <div class="stats-bar" v-if="stats">
      <div class="stat-item">
        <span class="stat-value">{{ stats.total }}</span> aplikasi
      </div>
      <div class="stat-item">
        <span class="stat-dot clean"></span>
        <span class="stat-value">{{ stats.clean }}</span> bersih
      </div>
      <div class="stat-item">
        <span class="stat-dot dirty"></span>
        <span class="stat-value">{{ stats.dirty }}</span> kotor
      </div>
      <div class="stat-item">
        <i class="fa-solid fa-clock text-muted"></i>
        Diimbas <span class="stat-value">{{ stats.lastScan }}</span>
      </div>
      <div class="stat-item" v-if="rootPath">
        <i class="fa-solid fa-folder text-muted"></i>
        <span class="stat-value" style="font-size: 0.8rem;">{{ rootPath }}</span>
      </div>
    </div>

    <router-view 
      :apps="apps" 
      :loading="loading" 
      @refresh="loadApps" 
      @filter-change="onFilterChange"
      @toggled="loadApps"
    />
  </div>

  <!-- Footer -->
  <footer class="app-footer">
    <div class="footer-content">
      <span>&copy; 2024-2026 Mohd Ilhammuddin Bin Mohd Fuead</span>
      <span>Mandryn PHP Team</span>
    </div>
  </footer>

  <!-- Config Modal -->
  <div v-if="showConfigModal" class="config-modal-overlay">
    <div class="config-modal">
      <h3>Pengaturan Lokasi Projek</h3>
      <p class="text-muted" style="margin: 0 0 16px 0; font-size: 14px;">
        Folder root di mana semua projek Laragon disimpan. Projek discan dari sini.
      </p>
      <label for="rootPath">Lokasi folder utama:</label>
      <input id="rootPath" v-model="rootPath" type="text" placeholder="C:/laragon/www" />
      <div class="modal-actions">
        <button class="btn btn-secondary" @click="showConfigModal = false">
          Batal
        </button>
        <button class="btn btn-primary" @click="saveConfig" :disabled="configLoading">
          <i class="fa-solid fa-save"></i> Simpan
        </button>
      </div>
      <div class="config-info" style="margin-top: 16px; font-size: 0.85rem; color: #6b7280;">
        <i class="fa-solid fa-info-circle"></i>
        &nbsp;Ganti lokasi untuk mengubaikan folder root scanner. Klik "Segar Semula" di dashboard untuk imbas semula.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { fetchApps, triggerScan, fetchConfig } from './api'

// Helper to update a config value
async function updateConfigValue(key, value) {
  const res = await fetch(`/api/config.php?key=${encodeURIComponent(key)}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ value })
  })
  if (!res.ok) throw new Error('Gagal menyimpan konfigurasi')
  return res.json()
}

const apps = ref([])
const loading = ref(true)
const scanning = ref(false)
const autoScanning = ref(false)
const stats = reactive({ total: 0, clean: 0, dirty: 0, lastScan: '' })
const showConfigModal = ref(false)
const configLoading = ref(false)
const rootPath = ref('C:/laragon/www')

// Check if config exists: localStorage -> DB -> modal
onMounted(async () => {
  // 1. Check localStorage first
  const cached = localStorage.getItem('app_manager_root_path')
  if (cached) {
    rootPath.value = cached
    await loadApps()
    return
  }

  // 2. Try to fetch from DB
  try {
    const cfg = await fetchConfig('root_path')
    if (cfg && cfg.value) {
      rootPath.value = cfg.value
      localStorage.setItem('app_manager_root_path', cfg.value)
      await loadApps()
      return
    }
  } catch (e) {
    // DB fetch failed, fall through to modal
  }

  // 3. Neither source had config → show modal
  showConfigModal.value = true
})

async function loadApps() {
  loading.value = true
  try {
    const data = await fetchApps()
    apps.value = data.apps
    if (data.last_scan_at) {
      stats.lastScan = formatTimeAgo(data.last_scan_at)
    } else {
      stats.lastScan = 'belum pernah'
    }
    updateStats(data.apps)
    // Auto-scan on first load if no apps yet (but only once)
    if (data.apps.length === 0 && !scanning.value && !autoScanning.value) {
      autoScanning.value = true
      await refreshAll()
    }
  } finally {
    loading.value = false
  }
}

async function refreshAll() {
  scanning.value = true
  try {
    await triggerScan()
    await loadApps()
  } finally {
    scanning.value = false
  }
}

function updateStats(list) {
  stats.total = list.length
  stats.clean = list.filter(a => a.scm_status === 'clean').length
  stats.dirty = list.filter(a => a.scm_status === 'dirty').length
}

function formatTimeAgo(input) {
  if (!input) return 'belum pernah'
  const then = new Date(input.replace(' ', 'T') + (input.endsWith('Z') ? '' : ''))
  const now = new Date()
  const diff = Math.floor((now - then) / 1000)
  if (diff < 0 || Number.isNaN(diff)) return 'sebentar tadi'
  if (diff < 60) return 'sebentar tadi'
  if (diff < 3600) return `${Math.floor(diff / 60)} minit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`
  return new Date(input).toLocaleDateString('ms-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}

function onFilterChange(filtered) {
  updateStats(filtered)
}

async function saveConfig() {
  configLoading.value = true
  try {
    // Save to DB first
    await updateConfigValue('root_path', rootPath.value)
    localStorage.setItem('app_manager_root_path', rootPath.value)
    showConfigModal.value = false
    // Auto-refresh after saving path change
    await refreshAll()
  } catch (e) {
    // Fallback to localStorage only
    localStorage.setItem('app_manager_root_path', rootPath.value)
    showConfigModal.value = false
    await refreshAll()
  } finally {
    configLoading.value = false
  }
}
</script>

<style scoped>
/* Scoped styles */
</style>

<style>
/* Modal styles (global so it overlays everything) */
.config-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.config-modal {
  background: #fff;
  border-radius: 8px;
  padding: 24px;
  min-width: 400px;
  max-width: 500px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.config-modal h3 {
  margin: 0 0 16px 0;
  color: #333;
}
.config-modal label {
  display: block;
  margin-bottom: 8px;
  font-weight: 500;
  color: #555;
}
.config-modal input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 20px;
}
.config-modal .modal-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}
.config-modal .btn {
  padding: 8px 16px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
}
.config-modal .btn-primary {
  background: #4f46e5;
  color: #fff;
}
.config-modal .btn-secondary {
  background: #e5e7eb;
  color: #374151;
}
</style>