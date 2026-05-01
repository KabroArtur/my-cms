import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchMediaLibrary(folderId = null) {
    const response = await axios.get(adminApiPath('media'), {
        params: folderId ? { folder_id: folderId } : undefined,
    })

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
        headers: {
            'Content-Type': 'multipart/form-data',
        },
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

export async function deleteMediaFile(id) {
    await axios.delete(adminApiPath(`media/files/${id}`))
}