import { createContext, useContext, useState, useCallback } from 'react'

const ToastContext = createContext(null)

let toastId = 0

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])

  const addToast = useCallback((message, type = 'success', duration = 3000) => {
    const id = ++toastId
    setToasts(prev => [...prev, { id, message, type }])
    setTimeout(() => {
      setToasts(prev => prev.filter(t => t.id !== id))
    }, duration)
  }, [])

  const toast = {
    success: (msg) => addToast(msg, 'success'),
    error: (msg) => addToast(msg, 'error'),
    warning: (msg) => addToast(msg, 'warning'),
    info: (msg) => addToast(msg, 'info'),
  }

  if (typeof window !== 'undefined') window.__posToast = toast

  return (
    <ToastContext.Provider value={toast}>
      {children}
      <div className="toast-container" style={{ position: 'fixed', top: 20, right: 20, zIndex: 9999 }}>
        {toasts.map(t => (
          <div key={t.id} className={`toast-notification toast-${t.type}`}>
            <i className={`fas ${
              t.type === 'success' ? 'fa-check-circle' :
              t.type === 'error' ? 'fa-times-circle' :
              t.type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'
            }`} />
            <span>{t.message}</span>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) throw new Error('useToast must be used within ToastProvider')
  return context
}
