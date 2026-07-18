import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiRequest, type AuthPayload, type DemoSessionPayload, type User } from '../api'

const storedToken = localStorage.getItem('no-excuse-token') ?? ''

export const useAuthStore = defineStore('auth', () => {
  const token = ref(storedToken)
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => token.value !== '')

  function accept(payload: AuthPayload): void {
    token.value = payload.token
    user.value = payload.user
    localStorage.setItem('no-excuse-token', payload.token)
  }

  async function login(email: string, password: string): Promise<void> {
    accept(await apiRequest<AuthPayload>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }))
  }

  async function setup(companyName: string, name: string, email: string, password: string): Promise<void> {
    accept(await apiRequest<AuthPayload>('/setup', { method: 'POST', body: JSON.stringify({ company_name: companyName, name, email, password, password_confirmation: password }) }))
  }

  async function startDemo(): Promise<string> {
    const payload = await apiRequest<DemoSessionPayload>('/demo/sessions', { method: 'POST' })
    accept(payload)
    return payload.demo.offer_uuid
  }

  async function loadUser(): Promise<void> {
    if (!token.value || user.value) return
    const payload = await apiRequest<{ user: User }>('/auth/me', {}, token.value)
    user.value = payload.user
  }

  async function logout(): Promise<void> {
    if (token.value) await apiRequest('/auth/logout', { method: 'POST' }, token.value)
    token.value = ''
    user.value = null
    localStorage.removeItem('no-excuse-token')
  }

  return { token, user, isAuthenticated, login, setup, startDemo, loadUser, logout }
})
