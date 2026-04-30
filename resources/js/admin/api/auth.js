import axios from 'axios'
import { adminApiPath, adminPath } from '../utils/adminPath'

export async function fetchCurrentUser() {
    const response = await axios.get(adminApiPath('me'))

    return response.data
}

export async function logout() {
    await axios.post(adminPath('logout'))
}