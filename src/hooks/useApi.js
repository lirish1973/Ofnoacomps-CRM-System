import { useState, useCallback } from 'react'

/**
 * Generic API hook — all calls go through the local proxy server at /api
 */
export function useApi(siteId) {
  const [loading, setLoading] = useState(false)
  const [error, setError]     = useState(null)

  const call = useCallback(async (method, path, body = null) => {
    setLoading(true)
    setError(null)
    try {
      const res = await fetch(`/api/sites/${siteId}/proxy${path}`, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
      })
      const data = await res.json()
      if (!res.ok) throw new Error(data.error || 'API error')
      return data
    } catch (e) {
      setError(e.message)
      throw e
    } finally {
      setLoading(false)
    }
  }, [siteId])

  const get    = (path)        => call('GET',    path)
  const post   = (path, body)  => call('POST',   path, body)
  const patch  = (path, body)  => call('PATCH',  path, body)
  const del    = (path)        => call('DELETE', path)

  return { get, post, patch, del, loading, error }
}
