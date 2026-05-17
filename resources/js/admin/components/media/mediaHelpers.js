export const DEFAULT_MEDIA_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp'
export const DEFAULT_MEDIA_LIBRARY_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp,image/avif,image/bmp,image/svg+xml,application/pdf,text/plain,text/markdown,text/csv,application/json,application/xml,text/xml,.txt,.md,.csv,.json,.xml,.log,application/zip,application/x-zip-compressed,application/vnd.rar,application/x-rar-compressed,application/x-7z-compressed,.zip,.rar,.7z,video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo,video/x-matroska,.mp4,.webm,.ogv,.mov,.avi,.mkv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,.doc,.docx,.xls,.xlsx,.ppt,.pptx'

const mimeByExtension = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    gif: 'image/gif',
    webp: 'image/webp',
    avif: 'image/avif',
    bmp: 'image/bmp',
    svg: 'image/svg+xml',
    pdf: 'application/pdf',
    txt: 'text/plain',
    md: 'text/markdown',
    csv: 'text/csv',
    json: 'application/json',
    xml: 'application/xml',
    log: 'text/plain',
    zip: 'application/zip',
    rar: 'application/vnd.rar',
    '7z': 'application/x-7z-compressed',
    mp4: 'video/mp4',
    webm: 'video/webm',
    ogv: 'video/ogg',
    mov: 'video/quicktime',
    avi: 'video/x-msvideo',
    mkv: 'video/x-matroska',
    doc: 'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    xls: 'application/vnd.ms-excel',
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ppt: 'application/vnd.ms-powerpoint',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
}

export function normalizeToArray(value) {
    if (Array.isArray(value)) {
        return value
    }

    if (value === null || value === undefined || value === '') {
        return []
    }

    return [value]
}

export function isNumericLike(value) {
    return value !== null && value !== '' && Number.isFinite(Number(value))
}

export function toNumericId(value) {
    return isNumericLike(value) ? Number(value) : null
}

export function getExtension(filename = '') {
    const value = String(filename)
    const lastDot = value.lastIndexOf('.')

    if (lastDot <= 0 || lastDot === value.length - 1) {
        return ''
    }

    return value.slice(lastDot + 1).toLowerCase()
}

export function stripExtension(filename = '') {
    const value = String(filename)
    const lastDot = value.lastIndexOf('.')

    if (lastDot <= 0) {
        return value
    }

    return value.slice(0, lastDot)
}

export function buildFilename(baseName, extension) {
    const normalizedBase = String(baseName ?? '').trim()
    const normalizedExtension = String(extension ?? '').trim().replace(/^\./, '')

    if (normalizedBase === '') {
        return ''
    }

    return normalizedExtension === '' ? normalizedBase : `${normalizedBase}.${normalizedExtension}`
}

export function resolveMimeTypeByExtension(extension = '') {
    return mimeByExtension[String(extension).toLowerCase()] ?? 'application/octet-stream'
}

export function formatBytes(size) {
    const bytes = Number(size)

    if (!Number.isFinite(bytes) || bytes < 0) {
        return '0 B'
    }

    const units = ['B', 'KB', 'MB', 'GB']
    let index = 0
    let value = bytes

    while (value >= 1024 && index < units.length - 1) {
        value /= 1024
        index += 1
    }

    return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`
}

export function basenameFromUrl(url = '') {
    const fallback = 'image'

    try {
        const pathname = new URL(String(url), window.location.origin).pathname
        const segments = pathname.split('/').filter(Boolean)

        return decodeURIComponent(segments.at(-1) || fallback)
    } catch {
        const segments = String(url).split('/').filter(Boolean)

        return segments.at(-1) || fallback
    }
}

export function createMediaSelection(file = {}) {
    const id = toNumericId(file.id ?? file.value)
    const url = String(file.url ?? '')
    const originalName = String(file.original_name ?? file.filename ?? basenameFromUrl(url))
    const label = String(file.label ?? originalName ?? 'Изображение')

    return {
        ...file,
        id,
        value: id,
        label,
        url,
        preview_url: file.preview_url || url,
        original_name: originalName,
        title: file.title ?? null,
        alt_text: file.alt_text ?? null,
        caption: file.caption ?? null,
        folder_id: toNumericId(file.folder_id),
        folder_name: file.folder_name ?? null,
        size: Number.isFinite(Number(file.size)) ? Number(file.size) : null,
        size_human: file.size_human ?? formatBytes(file.size),
        width: Number.isFinite(Number(file.width)) ? Number(file.width) : null,
        height: Number.isFinite(Number(file.height)) ? Number(file.height) : null,
        created_at: file.created_at ?? null,
        extension: file.extension ?? getExtension(originalName),
        mime_type: file.mime_type ?? resolveMimeTypeByExtension(getExtension(originalName)),
        variants: file.variants ?? {},
    }
}

export function createExternalMediaReference(url) {
    const normalizedUrl = String(url ?? '').trim()
    const name = basenameFromUrl(normalizedUrl)

    return createMediaSelection({
        id: null,
        url: normalizedUrl,
        preview_url: normalizedUrl,
        original_name: name,
        title: stripExtension(name),
        label: name,
    })
}

export function withMediaCacheBust(file, token = Date.now()) {
    const appendToken = (url) => {
        const normalizedUrl = String(url ?? '').trim()

        if (normalizedUrl === '') {
            return ''
        }

        const separator = normalizedUrl.includes('?') ? '&' : '?'

        return `${normalizedUrl}${separator}v=${encodeURIComponent(String(token))}`
    }

    return createMediaSelection({
        ...file,
        url: appendToken(file?.url),
        preview_url: appendToken(file?.preview_url ?? file?.url),
    })
}

export function normalizeAcceptList(accept = DEFAULT_MEDIA_ACCEPT) {
    return String(accept)
        .split(',')
        .map((item) => item.trim().toLowerCase())
        .filter(Boolean)
}

export function isAcceptedUpload(file, accept = DEFAULT_MEDIA_ACCEPT) {
    const accepted = normalizeAcceptList(accept)

    if (accepted.length === 0) {
        return true
    }

    const fileMimeType = String(file?.type ?? '').toLowerCase()
    const extension = getExtension(file?.name ?? '')
    const inferredMimeType = resolveMimeTypeByExtension(extension)

    return accepted.some((rule) => {
        if (rule.endsWith('/*')) {
            const prefix = rule.slice(0, -1)

            return fileMimeType.startsWith(prefix) || inferredMimeType.startsWith(prefix)
        }

        if (rule.startsWith('.')) {
            return extension === rule.slice(1)
        }

        return fileMimeType === rule || inferredMimeType === rule
    })
}