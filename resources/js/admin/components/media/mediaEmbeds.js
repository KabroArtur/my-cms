export function buildEmbeddedMediaMarkup(item) {
    const url = escapeHtmlAttribute(String(item?.url || "").trim())
    const title = escapeHtml(String(item?.original_name || item?.title || "Файл"))
    const alt = escapeHtmlAttribute(String(item?.alt_text || item?.original_name || ""))
    const titleAttr = escapeHtmlAttribute(String(item?.title || item?.original_name || ""))
    const mimeType = String(item?.mime_type || "").toLowerCase()

    if (url === "") {
        return ""
    }

    if (mimeType.startsWith("image/")) {
        return `<p><img src="${url}" alt="${alt}" title="${titleAttr}"></p>`
    }

    if (mimeType.startsWith("video/")) {
        return `<video src="${url}" controls="true" preload="metadata"></video><p>${title}</p><p></p>`
    }

    return `<p><a href="${url}" target="_blank" rel="noopener" download>${title}</a></p>`
}

export function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;")
}

export function escapeHtmlAttribute(value) {
    return escapeHtml(value)
}