import { fetchSiteSettings } from '../api/settings'

let cachedPayload = null
let pendingRequest = null

const jsDateFormats = {
    'd.m.Y': { day: '2-digit', month: '2-digit', year: 'numeric' },
    'Y-m-d': { year: 'numeric', month: '2-digit', day: '2-digit' },
    'd/m/Y': { day: '2-digit', month: '2-digit', year: 'numeric' },
}

const jsTimeFormats = {
    'H:i': { hour: '2-digit', minute: '2-digit', hour12: false },
    'H:i:s': { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false },
    'h:i A': { hour: '2-digit', minute: '2-digit', hour12: true },
}

export async function loadCmsSettings(force = false) {
    if (cachedPayload && !force) {
        return cachedPayload
    }

    if (pendingRequest && !force) {
        return pendingRequest
    }

    pendingRequest = fetchSiteSettings()
        .then((payload) => {
            cachedPayload = payload.data
            applyAdminPalette(cachedPayload.settings?.admin_palette?.variables ?? {})

            return cachedPayload
        })
        .finally(() => {
            pendingRequest = null
        })

    return pendingRequest
}

export function rememberCmsSettings(payload) {
    cachedPayload = payload
    applyAdminPalette(cachedPayload.settings?.admin_palette?.variables ?? {})

    return cachedPayload
}

export function formatCmsDateTime(value, settings = cachedPayload?.settings) {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return '—'
    }

    const dateOptions = jsDateFormats[settings?.date_format || 'd.m.Y'] || jsDateFormats['d.m.Y']
    const timeOptions = jsTimeFormats[settings?.time_format || 'H:i'] || jsTimeFormats['H:i']

    return new Intl.DateTimeFormat('ru-RU', {
        ...dateOptions,
        ...timeOptions,
    }).format(date)
}

function applyAdminPalette(variables) {
    Object.entries(variables).forEach(([key, value]) => {
        document.documentElement.style.setProperty(key, value)
    })
}