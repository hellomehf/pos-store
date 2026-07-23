import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

const notify = (msg) => window.__posToast?.error?.(msg)

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('pos_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  if (config.data instanceof FormData) {
    delete config.headers['Content-Type']
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (!error.response) {
      notify('Network error. Check your connection and try again.')
    } else {
      const status = error.response.status
      if (status === 401) {
        localStorage.removeItem('pos_token')
        localStorage.removeItem('pos_user')
        if (window.location.pathname !== '/login') {
          window.location.href = '/login'
        }
      } else if (status === 403) {
        notify('You do not have permission for this action.')
      } else if (status === 404) {
        notify('Requested resource not found.')
      } else if (status === 422) {
        const msg = error.response.data?.message
        if (msg) notify(msg)
      } else if (status >= 500) {
        notify('Server error. Please try again later.')
      }
    }
    return Promise.reject(error)
  }
)

export default api
