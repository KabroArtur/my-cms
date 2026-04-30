import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchRoles() {
    const response = await axios.get(adminApiPath('roles'))

    return response.data
}

export async function createRole(payload) {
    const response = await axios.post(adminApiPath('roles'), payload)

    return response.data
}

export async function updateRole(id, payload) {
    const response = await axios.put(adminApiPath(`roles/${id}`), payload)

    return response.data
}