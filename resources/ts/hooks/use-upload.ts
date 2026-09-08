import { useCallback, useRef, useState } from 'react'
import { multipartUpload } from '@/lib/multipart-upload'
import type { MultipartUploadResult, UploadProgress } from '@/types/upload'

interface UploadArgs {
    bucket: string
    file: File
}

export function useMultipartUpload() {
    const [progress, setProgress] = useState<UploadProgress | null>(null)
    const [isUploading, setIsUploading] = useState(false)
    const controllerRef = useRef<AbortController | null>(null)

    const upload = useCallback(async ({ bucket, file }: UploadArgs): Promise<MultipartUploadResult> => {
        const controller = new AbortController()
        controllerRef.current = controller
        setIsUploading(true)
        setProgress({ loaded: 0, total: file.size, percentage: 0 })

        try {
            return await multipartUpload(file, {
                bucket,
                signal: controller.signal,
                onProgress: setProgress,
            })
        } finally {
            setIsUploading(false)
            controllerRef.current = null
        }
    }, [])

    const cancel = useCallback(() => {
        controllerRef.current?.abort()
    }, [])

    const reset = useCallback(() => {
        setProgress(null)
        setIsUploading(false)
    }, [])

    return { upload, cancel, reset, progress, isUploading }
}
