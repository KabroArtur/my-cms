import axios from 'axios'

export async function fetchUsers() {
    const response = await axios.get('/admin/api/users')

    return response.data
}

export async function createUser(payload) {
    const response = await axios.post('/admin/api/users', payload)

    return response.data
}

export async function updateUser(id, payload) {
    const response = await axios.put(`/admin/api/users/${id}`, payload)

    return response.data
}

export async function deleteUser(id) {
    await axios.delete(`/admin/api/users/${id}`)
}