import { useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, session } from '@/lib/api'
import type { CurrentUser } from '@/lib/types'
import { AuthContext, type AuthContextValue } from '@/auth/useAuth'

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [user, setUser] = useState<CurrentUser | null>(null)
  const [status, setStatus] = useState<AuthContextValue['status']>(session.token() ? 'loading' : 'guest')
  const [selectedCompany, setSelectedCompany] = useState<number | null>(() => Number(session.companyId()) || null)

  const loadUser = useCallback(async () => {
    try {
      const { user } = await api<{ user: CurrentUser }>('/auth/me')
      setUser(user)
      setStatus('authenticated')

      if (!user.permissions.select_company) {
        session.setCompanyId(null)
        setSelectedCompany(null)
      }
    } catch {
      session.setToken(null)
      setUser(null)
      setStatus('guest')
    }
  }, [])

  useEffect(() => {
    if (session.token()) void loadUser()
  }, [loadUser])

  useEffect(() => {
    const onExpired = () => {
      setUser(null)
      setStatus('guest')
      queryClient.clear()
    }

    window.addEventListener('auth:expired', onExpired)
    return () => window.removeEventListener('auth:expired', onExpired)
  }, [queryClient])

  const login = useCallback(
    async (email: string, password: string) => {
      const response = await api<{ token: string; user: CurrentUser }>('/auth/login', {
        method: 'POST',
        body: { email, password },
      })

      session.setToken(response.token)
      queryClient.clear()
      setUser(response.user)
      setStatus('authenticated')

      if (!response.user.permissions.select_company) {
        session.setCompanyId(null)
        setSelectedCompany(null)
      }
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' })
    } catch {
    }

    session.setToken(null)
    queryClient.clear()
    setUser(null)
    setStatus('guest')
  }, [queryClient])

  const selectCompany = useCallback(
    (id: number | null) => {
      session.setCompanyId(id)
      setSelectedCompany(id)
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== 'admin' })
    },
    [queryClient],
  )

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      status,
      companyId: user?.permissions.select_company ? selectedCompany : (user?.company?.id ?? null),
      login,
      logout,
      selectCompany,
      refresh: loadUser,
    }),
    [user, status, selectedCompany, login, logout, selectCompany, loadUser],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
