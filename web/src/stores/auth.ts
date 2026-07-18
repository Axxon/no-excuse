import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiRequest, type AuthPayload, type DemoSessionPayload, type MfaRequired, type User } from '../api'

const storedToken = sessionStorage.getItem('no-excuse-token') ?? ''

export const useAuthStore = defineStore('auth', () => {
  const token = ref(storedToken)
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => token.value !== '')

  function accept(payload: AuthPayload): void {
    token.value = payload.token
    user.value = payload.user
    sessionStorage.setItem('no-excuse-token', payload.token)
  }

  async function login(email: string, password: string, mfaCode?: string): Promise<boolean> {
    const payload = await apiRequest<AuthPayload | MfaRequired>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password, ...(mfaCode ? { mfa_code: mfaCode } : {}) }) })
    if ('mfa_required' in payload) return true
    accept(payload); return false
  }

  async function setup(companyName: string, name: string, email: string, password: string): Promise<void> {
    accept(await apiRequest<AuthPayload>('/setup', { method: 'POST', body: JSON.stringify({ company_name: companyName, name, email, password, password_confirmation: password }) }))
  }

  async function startDemo(accessToken?: string): Promise<string> {
    const visitorReference = localStorage.getItem('no-excuse-demo-visitor') ?? ''
    const payload = await apiRequest<DemoSessionPayload>('/demo/sessions', {
      method: 'POST', headers: visitorReference ? { 'X-Demo-Visitor': visitorReference } : undefined,
      body: accessToken ? JSON.stringify({ access_token: accessToken }) : undefined,
    })
    accept(payload)
    return payload.demo.offer_uuid
  }

  async function loadUser(): Promise<void> {
    if (!token.value || user.value) return
    const payload = await apiRequest<{ user: User }>('/auth/me', {}, token.value)
    user.value = payload.user
  }

  function clearSession(): void {
    token.value = ''
    user.value = null
    sessionStorage.removeItem('no-excuse-token')
  }

  async function logout(): Promise<void> {
    try {
      if (token.value) await apiRequest('/auth/logout', { method: 'POST' }, token.value)
    } finally {
      clearSession()
    }
  }

  async function releaseDemo(): Promise<void> {
    if (token.value) await apiRequest('/auth/logout', { method: 'POST' }, token.value)
    clearSession()
  }

  return { token, user, isAuthenticated, accept, login, setup, startDemo, loadUser, logout, releaseDemo }
})
