export interface CreatedMultipartUpload {
    uploadId: string
    key: string
    bucket: string
    partSize: number
    expiresAt: string | null
}

export interface SignedPart {
    url: string
    method: string
    headers: Record<string, string>
    expires: number
}

export interface CompletedPart {
    PartNumber: number
    ETag: string
}

export interface MultipartUploadResult {
    uploadId: string
    key: string
    location: string
}

export interface UploadProgress {
    loaded: number
    total: number
    percentage: number
}
