import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
axios.defaults.headers.common.Accept = 'application/json'

if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken
}

export async function fetchFieldGroups() {
    const response = await axios.get(adminApiPath('field-groups'))

    return response.data
}

export async function fetchApplicableAdditionalFields(params = {}) {
    const response = await axios.get(adminApiPath('field-groups/applicable'), { params })

    return response.data
}

export async function createFieldGroup(payload) {
    const response = await axios.post(adminApiPath('field-groups'), payload)

    return response.data
}

export async function updateFieldGroup(id, payload) {
    const response = await axios.put(adminApiPath(`field-groups/${id}`), payload)

    return response.data
}

export async function deleteFieldGroup(id) {
    await axios.delete(adminApiPath(`field-groups/${id}`))
}
