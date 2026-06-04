import Uppy from '@uppy/core'
import AwsS3 from '@uppy/aws-s3'
import { uploadsApi } from '@/api/uploads'
import type { MultipartUploadResult, UploadProgress } from '@/types/upload'

interface MultipartUploadOptions {
    bucket: string
    concurrency?: number
    signal?: AbortSignal
    onProgress?: (progress: UploadProgress) => void
}

const CHUNK_SIZE = 8 * 1024 * 1024

export async function multipartUpload(
    file: File,
    options: MultipartUploadOptions
): Promise<MultipartUploadResult> {
    const { bucket, concurrency = 4, signal, onProgress } = options

    const captured: { uploadId?: string; key?: string; location?: string } = {}

    const uppy = new Uppy({ autoProceed: false, allowMultipleUploads: false })

    uppy.use(AwsS3, {
        shouldUseMultipart: true,
        limit: concurrency,
        getChunkSize: () => CHUNK_SIZE,

        createMultipartUpload: async (uppyFile) => {
            const created = await uploadsApi.create({
                bucket,
                filename: uppyFile.name ?? file.name,
                type: uppyFile.type,
                size: uppyFile.size ?? file.size,
            })

            captured.uploadId = created.uploadId
            captured.key = created.key

            return { uploadId: created.uploadId, key: created.key }
        },

        listParts: async (_uppyFile, { uploadId, key }) => {
            return uploadsApi.listParts(uploadId, key)
        },

        signPart: async (_uppyFile, { uploadId, key, partNumber }) => {
            const signed = await uploadsApi.signPart(uploadId, key, partNumber)

            return {
                method: 'PUT',
                url: signed.url,
                headers: signed.headers,
            }
        },

        completeMultipartUpload: async (_uppyFile, { uploadId, key, parts }) => {
            const result = await uploadsApi.complete(
                uploadId,
                key,
                parts.map((part) => ({
                    PartNumber: part.PartNumber as number,
                    ETag: part.ETag as string,
                }))
            )

            captured.location = result.location

            return { location: result.location }
        },

        abortMultipartUpload: async (_uppyFile, { uploadId, key }) => {
            await uploadsApi.abort(uploadId, key)
        },
    })

    if (signal) {
        if (signal.aborted) {
            uppy.cancelAll()
        } else {
            signal.addEventListener('abort', () => uppy.cancelAll(), { once: true })
        }
    }

    uppy.on('upload-progress', (_uppyFile, progress) => {
        const total = progress.bytesTotal ?? file.size
        const loaded = progress.bytesUploaded ?? 0

        onProgress?.({
            loaded,
            total,
            percentage: total ? Math.min(100, Math.round((loaded / total) * 100)) : 0,
        })
    })

    uppy.addFile({
        name: file.name,
        type: file.type,
        data: file,
    })

    try {
        const result = await uppy.upload()

        if (result?.failed && result.failed.length > 0) {
            const error = result.failed[0].error

            throw new Error(typeof error === 'string' ? error : error?.message || 'Upload failed')
        }

        return {
            uploadId: captured.uploadId ?? '',
            key: captured.key ?? '',
            location: captured.location ?? '',
        }
    } finally {
        uppy.destroy()
    }
}
