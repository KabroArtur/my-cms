import axios from 'axios'

export async function fetchSiteSettings() {
    const response = await axios.get('/admin/api/settings')

    return response.data
}

export async function updateSiteSettings(payload) {
    const response = await axios.put('/admin/api/settings', payload)

    return response.data
}