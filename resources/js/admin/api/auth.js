import axios from 'axios'

export async function fetchCurrentUser() {
    const response = await axios.get('/admin/api/me')

    return response.data
}

export async function logout() {
    await axios.post('/logout')
}