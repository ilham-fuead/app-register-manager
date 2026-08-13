import { createRouter, createWebHashHistory } from 'vue-router'
import Dashboard from '../views/Dashboard.vue'
import Detail from '../views/Detail.vue'
import AddApp from '../views/AddApp.vue'

const routes = [
  { path: '/', name: 'dashboard', component: Dashboard },
  { path: '/app/:name', name: 'detail', component: Detail, props: true },
  { path: '/add', name: 'add', component: AddApp },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

export default router
