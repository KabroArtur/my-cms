import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchSiteSettings() {
    const response = await axios.get(adminApiPath('settings'))

    return response.data
}

export async function updateSiteSettings(payload) {
    const response = await axios.put(adminApiPath('settings'), payload)

    return response.data
}

export async function clearCmsCache() {
    const response = await axios.post(adminApiPath('settings/cache/clear'))

    return response.data
}