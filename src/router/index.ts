import type { RouteRecordRaw } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import AdminPage from '../pages/AdminPage.vue'
import ListPage from '../pages/ListPage.vue'

export const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: HomePage
  },
  {
    path: '/list/:id',
    name: 'list',
    component: ListPage
  },
  {
    path: '/admin',
    name: 'admin',
    component: AdminPage
  }
]
