function normalizeBasePath(value) {
    const cleaned = String(value || '/admin').trim().replace(/\/+$/, '')

    if (cleaned === '') {
        return '/admin'
    }

    return cleaned.startsWith('/') ? cleaned : `/${cleaned}`
}

const metaAdminBase = document.querySelector('meta[name="cms-admin-base"]')?.content

const ADMIN_BASE_PATH = normalizeBasePath(metaAdminBase)

export function adminBasePath() {
    return ADMIN_BASE_PATH
}

export function adminPath(path = '') {
    const tail = String(path).trim()

    if (tail === '') {
        return ADMIN_BASE_PATH
    }

    return `${ADMIN_BASE_PATH}/${tail.replace(/^\/+/, '')}`
}

export function adminApiPath(path = '') {
    const tail = String(path).trim()

    if (tail === '') {
        return `${ADMIN_BASE_PATH}/api`
    }

    return `${ADMIN_BASE_PATH}/api/${tail.replace(/^\/+/, '')}`
}

export function adminLoginPath() {
    return adminPath('login')
}
