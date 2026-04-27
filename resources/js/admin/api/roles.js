import axios from 'axios'

export async function fetchRoles() {
    const response = await axios.get('/admin/api/roles')

    return response.data
}

export async function createRole(payload) {
    const response = await axios.post('/admin/api/roles', payload)

    return response.data
}

export async function updateRole(id, payload) {
    const response = await axios.put(`/admin/api/roles/${id}`, payload)

    return response.data
}