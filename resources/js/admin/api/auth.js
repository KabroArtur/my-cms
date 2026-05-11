import axios from 'axios'
import { adminApiPath, adminPath } from '../utils/adminPath'

const ADMIN_USER_STORAGE_KEY = 'cms.admin.currentUser'

let pendingRequest = null
let cachedCurrentUser = readBootstrappedUser() ?? readStoredUser()

export function getCurrentUserSnapshot() {
    return cachedCurrentUser
}

export function getBootstrappedSiteName() {
    if (typeof window !== 'undefined' && window.__CMS_ADMIN_BOOTSTRAP?.siteName) {
        return window.__CMS_ADMIN_BOOTSTRAP.siteName
    }

    if (typeof document !== 'undefined') {
        return document.querySelector('meta[name="cms-site-name"]')?.content || 'My CMS'
    }

    return 'My CMS'
}

export async function fetchCurrentUser(force = false) {
    if (cachedCurrentUser && !force) {
        return { data: cachedCurrentUser }
    }

    if (pendingRequest && !force) {
        return pendingRequest
    }

    pendingRequest = axios.get(adminApiPath('me'))
        .then((response) => {
            const payload = response.data?.data ?? response.data
            cachedCurrentUser = payload
            persistUser(payload)

            return { data: payload }
        })
        .finally(() => {
            pendingRequest = null
        })

    return pendingRequest
}

export async function logout() {
    cachedCurrentUser = null
    clearStoredUser()
    await axios.post(adminPath('logout'))
}

function readBootstrappedUser() {
    if (typeof window === 'undefined') {
        return null
    }

    return window.__CMS_ADMIN_BOOTSTRAP?.currentUser ?? null
}

function readStoredUser() {
    if (typeof window === 'undefined') {
        return null
    }

    try {
        const raw = window.sessionStorage.getItem(ADMIN_USER_STORAGE_KEY)

        return raw ? JSON.parse(raw) : null
    } catch (error) {
        return null
    }
}

function persistUser(user) {
    if (typeof window === 'undefined') {
        return
    }

    window.sessionStorage.setItem(ADMIN_USER_STORAGE_KEY, JSON.stringify(user))
}

function clearStoredUser() {
    if (typeof window === 'undefined') {
        return
    }

    window.sessionStorage.removeItem(ADMIN_USER_STORAGE_KEY)
}