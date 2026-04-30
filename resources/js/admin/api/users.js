import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchUsers() {
    const response = await axios.get(adminApiPath('users'))

    return response.data
}

export async function createUser(payload) {
    const response = await axios.post(adminApiPath('users'), payload)

    return response.data
}

export async function updateUser(id, payload) {
    const response = await axios.put(adminApiPath(`users/${id}`), payload)

    return response.data
}

export async function deleteUser(id) {
    await axios.delete(adminApiPath(`users/${id}`))
}