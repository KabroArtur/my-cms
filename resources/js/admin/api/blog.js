import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchBlogPosts() {
    const response = await axios.get(adminApiPath('blog/posts'))

    return response.data
}

export async function createBlogPost(payload) {
    const response = await axios.post(adminApiPath('blog/posts'), payload)

    return response.data
}

export async function updateBlogPost(id, payload) {
    const response = await axios.put(adminApiPath(`blog/posts/${id}`), payload)

    return response.data
}

export async function deleteBlogPost(id) {
    const response = await axios.delete(adminApiPath(`blog/posts/${id}`))

    return response.data
}

export async function fetchBlogCategories() {
    const response = await axios.get(adminApiPath('blog/categories'))

    return response.data
}

export async function createBlogCategory(payload) {
    const response = await axios.post(adminApiPath('blog/categories'), payload)

    return response.data
}

export async function updateBlogCategory(id, payload) {
    const response = await axios.put(adminApiPath(`blog/categories/${id}`), payload)

    return response.data
}

export async function deleteBlogCategory(id) {
    const response = await axios.delete(adminApiPath(`blog/categories/${id}`))

    return response.data
}

export async function fetchBlogTags() {
    const response = await axios.get(adminApiPath('blog/tags'))

    return response.data
}

export async function createBlogTag(payload) {
    const response = await axios.post(adminApiPath('blog/tags'), payload)

    return response.data
}

export async function updateBlogTag(id, payload) {
    const response = await axios.put(adminApiPath(`blog/tags/${id}`), payload)

    return response.data
}

export async function deleteBlogTag(id) {
    const response = await axios.delete(adminApiPath(`blog/tags/${id}`))

    return response.data
}
