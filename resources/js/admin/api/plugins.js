import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchPlugins() {
    const response = await axios.get(adminApiPath('plugins'))

    return response.data
}

export async function installPlugin(slug) {
    const response = await axios.post(adminApiPath(`plugins/${slug}/install`))

    return response.data
}

export async function enablePlugin(slug) {
    const response = await axios.post(adminApiPath(`plugins/${slug}/enable`))

    return response.data
}

export async function disablePlugin(slug) {
    const response = await axios.post(adminApiPath(`plugins/${slug}/disable`))

    return response.data
}

export async function deletePlugin(slug, payload) {
    const response = await axios.delete(adminApiPath(`plugins/${slug}`), { data: payload })

    return response.data
}
