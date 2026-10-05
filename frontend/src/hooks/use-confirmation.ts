import { useState } from 'react'

export function useConfirmation<T>() {
  const [state, setState] = useState<{ target: T; open: boolean } | null>(null)

  return {
    target: state?.target ?? null,
    open: state?.open ?? false,
    ask: (target: T) => setState({ target, open: true }),
    close: () => setState((current) => current && { ...current, open: false }),
  }
}
