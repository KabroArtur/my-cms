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

export async function fetchLanguages() {
    const response = await axios.get(adminApiPath('languages'))

    return response.data
}

export async function createLanguage(payload) {
    const response = await axios.post(adminApiPath('languages'), payload)

    return response.data
}

export async function updateLanguage(id, payload) {
    const response = await axios.put(adminApiPath(`languages/${id}`), payload)

    return response.data
}

export async function deleteLanguage(id) {
    const response = await axios.delete(adminApiPath(`languages/${id}`))

    return response.data
}