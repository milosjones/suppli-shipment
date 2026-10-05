import { ArrowRight } from 'lucide-react'
import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { useAuth } from '@/auth/useAuth'
import { Button } from '@/components/ui/button'
import { Field, FormError } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { PasswordInput } from '@/components/ui/password-input'
import { Wordmark } from '@/components/Wordmark'
import { ApiError } from '@/lib/api'

const YEAR = new Date().getFullYear()

export function LoginPage() {
  const { login, status } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string[]>([])
  const [submitting, setSubmitting] = useState(false)

  if (status === 'authenticated') {
    return <Navigate to="/" replace />
  }

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setSubmitting(true)
    setError([])

    try {
      await login(email, password)
      const from = (location.state as { from?: string } | null)?.from
      navigate(from && from !== '/login' ? from : '/', { replace: true })
    } catch (e) {
      setError(e instanceof ApiError ? e.messages() : ['Could not reach the server. Is the API running?'])
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-ink-900 px-4 py-12">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(31,152,164,0.35),transparent_45%),radial-gradient(circle_at_80%_85%,rgba(23,99,110,0.45),transparent_50%)]" />
      <div className="relative w-full max-w-sm">
        <p className="mb-8 text-center">
          <Wordmark className="text-3xl" />
        </p>
        <form onSubmit={submit} className="space-y-5 rounded-2xl bg-white p-8 shadow-2xl shadow-black/30">
          <div>
            <h1 className="text-xl font-semibold tracking-tight text-slate-900">Welcome back</h1>
            <p className="mt-1.5 text-sm text-slate-500">Sign in to follow your containers, update ETD and ETA, and plan customer deliveries.</p>
          </div>

          <FormError messages={error} />

          <Field label="Email">
            <Input
              type="email"
              autoComplete="username"
              placeholder="name@company.com"
              required
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </Field>
          <Field label="Password">
            <PasswordInput
              autoComplete="current-password"
              placeholder="Your password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>

          <Button type="submit" loading={submitting} className="w-full">
            Sign in <ArrowRight className="size-4" />
          </Button>

          <p className="border-t border-slate-100 pt-5 text-center text-xs text-slate-500">
            No account yet? Your company admin can add you.
          </p>
        </form>
        <p className="mt-8 text-center text-xs text-slate-400">© {YEAR} Suppli Shipments</p>
      </div>
    </div>
  )
}
