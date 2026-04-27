import axios from 'axios'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common['Accept'] = 'application/json'

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

export async function fetchPages() {
    const response = await axios.get('/admin/api/pages')

    return response.data
}

export async function createPage(payload) {
    const response = await axios.post('/admin/api/pages', payload)

    return response.data
}

export async function updatePage(id, payload) {
    const response = await axios.put(`/admin/api/pages/${id}`, payload)

    return response.data
}

export async function deletePage(id) {
    await axios.delete(`/admin/api/pages/${id}`)
}