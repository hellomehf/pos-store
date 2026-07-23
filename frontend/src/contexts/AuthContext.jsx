import { createContext, useContext, useState, useEffect } from 'react'
import api from '../api/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(localStorage.getItem('pos_token'))
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (token) {
      api.get('/me')
        .then(res => {
          setUser(res.data)
          localStorage.setItem('pos_user', JSON.stringify(res.data))
        })
        .catch(() => {
          logout()
        })
        .finally(() => setLoading(false))
    } else {
      setLoading(false)
    }
  }, [])

  const login = async (email, password) => {
    const res = await api.post('/login', { email, password })
    const { user: userData, token: authToken } = res.data
    localStorage.setItem('pos_token', authToken)
    localStorage.setItem('pos_user', JSON.stringify(userData))
    setToken(authToken)
    setUser(userData)
    return userData
  }

  const logout = async () => {
    try {
      if (token) await api.post('/logout')
    } catch {}
    localStorage.removeItem('pos_token')
    localStorage.removeItem('pos_user')
    setToken(null)
    setUser(null)
  }

  return (
    <AuthContext.Provider value={{ user, token, loading, login, logout }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used within AuthProvider')
  return context
}
