import axios from 'axios'
import { adminApiPath } from '../utils/adminPath'

export async function fetchRecordSections() {
    const response = await axios.get(adminApiPath('blog/sections'))

    return response.data
}

export async function createRecordSection(payload) {
    const response = await axios.post(adminApiPath('blog/sections'), payload)

    return response.data
}

export async function updateRecordSection(slug, payload) {
    const response = await axios.put(adminApiPath(`blog/sections/${slug}`), payload)

    return response.data
}

export async function deleteRecordSection(slug) {
    const response = await axios.delete(adminApiPath(`blog/sections/${slug}`))

    return response.data
}

export async function fetchSectionRecords(sectionSlug) {
    const response = await axios.get(adminApiPath(`blog/sections/${sectionSlug}/records`))

    return response.data
}

export async function createSectionRecord(sectionSlug, payload) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/records`), payload)

    return response.data
}

export async function updateSectionRecord(sectionSlug, recordId, payload) {
    const response = await axios.put(adminApiPath(`blog/sections/${sectionSlug}/records/${recordId}`), payload)

    return response.data
}

export async function deleteSectionRecord(sectionSlug, recordId) {
    const response = await axios.delete(adminApiPath(`blog/sections/${sectionSlug}/records/${recordId}`))

    return response.data
}

export async function publishSectionRecord(sectionSlug, recordId) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/records/${recordId}/publish`))

    return response.data
}

export async function unpublishSectionRecord(sectionSlug, recordId) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/records/${recordId}/unpublish`))

    return response.data
}

export async function duplicateSectionRecord(sectionSlug, recordId) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/records/${recordId}/duplicate`))

    return response.data
}

export async function fetchSectionCategories(sectionSlug) {
    const response = await axios.get(adminApiPath(`blog/sections/${sectionSlug}/categories`))

    return response.data
}

export async function createSectionCategory(sectionSlug, payload) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/categories`), payload)

    return response.data
}

export async function updateSectionCategory(sectionSlug, categoryId, payload) {
    const response = await axios.put(adminApiPath(`blog/sections/${sectionSlug}/categories/${categoryId}`), payload)

    return response.data
}

export async function deleteSectionCategory(sectionSlug, categoryId) {
    const response = await axios.delete(adminApiPath(`blog/sections/${sectionSlug}/categories/${categoryId}`))

    return response.data
}

export async function fetchSectionTags(sectionSlug) {
    const response = await axios.get(adminApiPath(`blog/sections/${sectionSlug}/tags`))

    return response.data
}

export async function createSectionTag(sectionSlug, payload) {
    const response = await axios.post(adminApiPath(`blog/sections/${sectionSlug}/tags`), payload)

    return response.data
}

export async function updateSectionTag(sectionSlug, tagId, payload) {
    const response = await axios.put(adminApiPath(`blog/sections/${sectionSlug}/tags/${tagId}`), payload)

    return response.data
}

export async function deleteSectionTag(sectionSlug, tagId) {
    const response = await axios.delete(adminApiPath(`blog/sections/${sectionSlug}/tags/${tagId}`))

    return response.data
}
