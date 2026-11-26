// Sama dengan aturan `max:2048` (KB) di Form Request backend.
export const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

/**
 * @param {File | null | undefined} file
 */
export function exceedsUploadLimit(file) {
    return Boolean(file && file.size > MAX_UPLOAD_BYTES);
}
