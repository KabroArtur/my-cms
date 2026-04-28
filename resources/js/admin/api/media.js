import axios from 'axios'

export async function fetchMediaLibrary(folderId = null) {
    const response = await axios.get('/admin/api/media', {
        params: folderId ? { folder_id: folderId } : undefined,
    })

    return response.data
}

export async function createMediaFolder(payload) {
    const response = await axios.post('/admin/api/media/folders', payload)

    return response.data
}

export async function updateMediaFolder(id, payload) {
    const response = await axios.put(`/admin/api/media/folders/${id}`, payload)

    return response.data
}

export async function deleteMediaFolder(id) {
    await axios.delete(`/admin/api/media/folders/${id}`)
}

export async function uploadMediaFile({ folderId, file }) {
    const formData = new FormData()

    if (folderId) {
        formData.append('folder_id', folderId)
    }

    formData.append('file', file)

    const response = await axios.post('/admin/api/media/files', formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    })

    return response.data
}

export async function moveMediaFile(id, payload) {
    const response = await axios.put(`/admin/api/media/files/${id}/move`, payload)

    return response.data
}

export async function updateMediaFile(id, payload) {
    const response = await axios.put(`/admin/api/media/files/${id}`, payload)

    return response.data
}

export async function deleteMediaFile(id) {
    await axios.delete(`/admin/api/media/files/${id}`)
}