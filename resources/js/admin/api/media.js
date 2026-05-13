import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

export async function fetchMediaLibrary(folderId = null, options = {}) {
    const response = await axios.get(adminApiPath('media'), {
        params: {
            ...(folderId ? { folder_id: folderId } : {}),
            ...(options.scope ? { scope: options.scope } : {}),
        },
    })

    return response.data
}

export async function fetchMediaFile(id) {
    const response = await axios.get(adminApiPath(`media/files/${id}`))

    return response.data
}

export async function createMediaFolder(payload) {
    const response = await axios.post(adminApiPath('media/folders'), payload)

    return response.data
}

export async function updateMediaFolder(id, payload) {
    const response = await axios.put(adminApiPath(`media/folders/${id}`), payload)

    return response.data
}

export async function deleteMediaFolder(id) {
    await axios.delete(adminApiPath(`media/folders/${id}`))
}

export async function uploadMediaFile({ folderId, file }) {
    const formData = new FormData()

    const name = arguments[0]?.name
    const onUploadProgress = arguments[0]?.onUploadProgress

    if (folderId) {
        formData.append('folder_id', folderId)
    }

    if (name) {
        formData.append('name', name)
    }

    formData.append('file', file)

    const response = await axios.post(adminApiPath('media/files'), formData, {
        onUploadProgress,
    })

    return response.data
}

export async function moveMediaFile(id, payload) {
    const response = await axios.put(adminApiPath(`media/files/${id}/move`), payload)

    return response.data
}

export async function updateMediaFile(id, payload) {
    const response = await axios.put(adminApiPath(`media/files/${id}`), payload)

    return response.data
}

export async function replaceMediaFile(id, { file }) {
    const formData = new FormData()

    formData.append('file', file)

    const response = await axios.post(adminApiPath(`media/files/${id}/replace`), formData)

    return response.data
}

export async function transformMediaFile(id, payload) {
    const response = await axios.post(adminApiPath(`media/files/${id}/transform`), payload)

    return response.data
}

export async function deleteMediaFile(id) {
    await axios.delete(adminApiPath(`media/files/${id}`))
}