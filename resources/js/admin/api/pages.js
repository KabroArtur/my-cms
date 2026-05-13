import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

export async function fetchPages(params = {}) {
    const response = await axios.get(adminApiPath('pages'), { params })

    return response.data
}

export async function fetchPage(id) {
    const response = await axios.get(adminApiPath(`pages/${id}`))

    return response.data
}

export async function fetchPageTree(params = {}) {
    const response = await axios.get(adminApiPath('pages-tree'), { params })

    return response.data
}

export async function fetchTrashedPages(params = {}) {
    const response = await axios.get(adminApiPath('pages-trash'), { params })

    return response.data
}

export async function createPage(payload) {
    const response = await axios.post(adminApiPath('pages'), payload)

    return response.data
}

export async function updatePage(id, payload) {
    const response = await axios.put(adminApiPath(`pages/${id}`), payload)

    return response.data
}

export async function deletePage(id) {
    await axios.delete(adminApiPath(`pages/${id}`))
}

export async function restorePage(id) {
    const response = await axios.post(adminApiPath(`pages/${id}/restore`))

    return response.data
}

export async function permanentlyDeletePage(id) {
    await axios.delete(adminApiPath(`pages/${id}/force`))
}

export async function savePageTree(tree) {
    await axios.put(adminApiPath('pages-tree'), { tree })
}