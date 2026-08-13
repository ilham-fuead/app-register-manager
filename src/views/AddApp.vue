<template>
  <div>
    <div class="detail-header">
      <router-link to="/" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Kembali ke semua aplikasi
      </router-link>
    </div>

    <div class="form-container">
      <div style="margin-bottom: 28px;">
        <h2 style="font-size: 1.4rem; font-weight: 700;">Tambah Aplikasi</h2>
        <p class="text-muted text-sm mt-2">Daftar projek secara manual. Stack dan SCM akan dikesan secara automatik dari laluan projek.</p>
      </div>

      <div class="form-card">
        <div class="form-group">
          <label for="appName">Nama Aplikasi <span class="text-muted">*</span></label>
          <p class="hint">Nama ringkas untuk mengenal pasti projek ini dalam papan pemuka.</p>
          <input type="text" id="appName" v-model="name" placeholder="cth. projek-baru" @input="previewDetect">
        </div>

        <div class="form-group">
          <label for="appPath">Laluan Projek <span class="text-muted">*</span></label>
          <p class="hint">Laluan folder tempatan. Stack dan SCM akan dibaca dari composer.json, package.json, dan .git di sini.</p>
          <input type="text" id="appPath" v-model="path" placeholder="cth. C:\laragon\www\projek-baru" @input="previewDetect">
        </div>

        <div class="form-group">
          <label>Nota (Pilihan)</label>
          <textarea v-model="notes" placeholder="Nota tambahan tentang projek ini..." style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-size: 0.9rem; font-family: inherit; background: var(--bg-secondary); min-height: 80px; resize: vertical;"></textarea>
        </div>

        <div class="form-group">
          <label>Pratonton Pengesanan Automatik</label>
          <div class="preview-box">
            <div v-if="!path" class="text-muted">Masukkan laluan projek di atas untuk melihat apa yang akan dikesan.</div>
            <div v-else>
              <div style="font-weight: 600; margin-bottom: 8px; color: var(--text-primary);">{{ name || 'Projek' }}</div>
              <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                <span v-if="looksLikePHP" class="badge php">PHP</span>
                <span v-if="looksLikeLaravel" class="badge laravel">Laravel</span>
                <span v-if="looksLikeNode" class="badge node">Node</span>
                <span v-if="looksLikeVue" class="badge vue">Vue</span>
                <span v-if="!looksLikePHP && !looksLikeNode" class="text-muted">Stack tidak dapat dikesan dari nama</span>
              </div>
              <div style="margin-top: 8px; font-size: 0.8rem;" v-if="path">
                <span v-if="path.includes('laragon')" style="color: var(--accent); font-weight: 600;">
                  <i class="fa-solid fa-check"></i> Dalam direktori Laragon
                </span>
                <span v-else style="color: var(--warning);">
                  <i class="fa-solid fa-triangle-exclamation"></i> Di luar direktori Laragon
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <router-link to="/" class="btn btn-secondary">Batal</router-link>
          <button class="btn btn-primary" @click="save" :disabled="saving || !name || !path">
            <i class="fa-solid fa-check"></i> {{ saving ? 'Menyimpan...' : 'Daftar & Imbas' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { addApp, triggerScan } from '../api'
import { APP_CONFIG } from '../config.js'

const router = useRouter()
const name = ref('')
const path = ref('')
const notes = ref('')
const saving = ref(false)

const looksLikePHP = computed(() => /php|laravel|symfony|slim|cakephp|codeigniter/i.test(name.value + path.value))
const looksLikeLaravel = computed(() => /laravel/i.test(name.value + path.value))
const looksLikeNode = computed(() => /node|vue|react|next|nuxt|svelte|angular|express|nest/i.test(name.value + path.value))
const looksLikeVue = computed(() => /vue/i.test(name.value + path.value))

function previewDetect() {
  // Reactive — computed above
}

async function save() {
  if (!name.value.trim() || !path.value.trim()) return
  saving.value = true
  try {
    await addApp(name.value.trim(), path.value.trim(), notes.value.trim() || undefined)
    await triggerScan(path.value.trim())
    router.push('/')
  } catch (e) {
    alert('Gagal mendaftar: ' + e.message)
  } finally {
    saving.value = false
  }
}
</script>
