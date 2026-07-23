import { createContext, useContext, useState, useEffect } from 'react'
import api from '../api/api'

const StoreContext = createContext(null)

export function StoreProvider({ children }) {
  const [storeName, setStoreName] = useState('POS Store')
  const [storeLogo, setStoreLogo] = useState(null)
  const [loading, setLoading] = useState(true)

  const fetchSettings = async () => {
    const token = localStorage.getItem('pos_token')
    if (!token) {
      setLoading(false)
      return
    }

    try {
      const res = await api.get('/settings/store')
      setStoreName(res.data.store_name || 'POS Store')
      setStoreLogo(res.data.store_logo || null)
    } catch {
      // Settings not available yet, use defaults
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    fetchSettings()
  }, [])

  const updateSettings = (name, logo) => {
    setStoreName(name)
    if (logo !== undefined) setStoreLogo(logo)
  }

  return (
    <StoreContext.Provider value={{ storeName, storeLogo, loading, updateSettings, fetchSettings }}>
      {children}
    </StoreContext.Provider>
  )
}

export function useStore() {
  const context = useContext(StoreContext)
  if (!context) throw new Error('useStore must be used within StoreProvider')
  return context
}
