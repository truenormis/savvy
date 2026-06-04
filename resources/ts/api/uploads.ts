import { apiClient } from './client'
import type { CreatedMultipartUpload, SignedPart, CompletedPart } from '@/types/upload'

const BASE = '/s3/multipart'

export const uploadsApi = {
    create: async (params: {
        bucket: string
        filename: string
        type?: string | null
        size?: number
    }): Promise<CreatedMultipartUpload> => {
        const response = await apiClient.post<CreatedMultipartUpload>(BASE, {
            bucket: params.bucket,
            filename: params.filename,
            type: params.type ?? null,
            size: params.size ?? null,
        })

        return response.data
    },

    listParts: async (
        uploadId: string,
        key: string
    ): Promise<Array<{ PartNumber: number; Size: number; ETag: string }>> => {
        const response = await apiClient.get(`${BASE}/${uploadId}`, { params: { key } })

        return response.data
    },

    signPart: async (uploadId: string, key: string, partNumber: number): Promise<SignedPart> => {
        const response = await apiClient.get<SignedPart>(`${BASE}/${uploadId}/${partNumber}`, {
            params: { key },
        })

        return response.data
    },

    complete: async (
        uploadId: string,
        key: string,
        parts: CompletedPart[]
    ): Promise<{ location: string; key: string; uploadId: string }> => {
        const response = await apiClient.post(
            `${BASE}/${uploadId}/complete`,
            { parts },
            { params: { key } }
        )

        return response.data
    },

    abort: async (uploadId: string, key: string): Promise<void> => {
        await apiClient.delete(`${BASE}/${uploadId}`, { params: { key } })
    },
}
