import { createContext, useContext } from 'react'
import type { CurrentUser } from '@/lib/types'

export interface AuthContextValue {
  user: CurrentUser | null
  status: 'loading' | 'authenticated' | 'guest'
  companyId: number | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  selectCompany: (id: number | null) => void
  refresh: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
