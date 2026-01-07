import { useState } from 'react'
import { Page, PageHeader } from '@/components/shared'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { Download, RotateCcw, Trash2, Plus, Upload, Loader2 } from 'lucide-react'
import { useBackups, useCreateBackup, useUploadBackup, useRestoreBackup, useDeleteBackup } from '@/hooks/use-backups'
import { backupsApi } from '@/api/backups'
import { Backup } from '@/types/backup'

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i]
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString()
}

export default function BackupsPage() {
    const { data: backups, isLoading } = useBackups()
    const createBackup = useCreateBackup()
    const uploadBackup = useUploadBackup()
    const restoreBackup = useRestoreBackup()
    const deleteBackup = useDeleteBackup()

    const [createDialogOpen, setCreateDialogOpen] = useState(false)
    const [uploadDialogOpen, setUploadDialogOpen] = useState(false)
    const [restoreDialogOpen, setRestoreDialogOpen] = useState(false)
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
    const [selectedBackup, setSelectedBackup] = useState<Backup | null>(null)
    const [note, setNote] = useState('')
    const [uploadFile, setUploadFile] = useState<File | null>(null)

    const handleCreate = () => {
        createBackup.mutate(note || undefined, {
            onSuccess: () => {
                setCreateDialogOpen(false)
                setNote('')
            },
        })
    }

    const handleUpload = () => {
        if (!uploadFile) return
        uploadBackup.mutate({ file: uploadFile, note: note || undefined }, {
            onSuccess: () => {
                setUploadDialogOpen(false)
                setNote('')
                setUploadFile(null)
            },
        })
    }

    const handleRestore = () => {
        if (!selectedBackup) return
        restoreBackup.mutate(selectedBackup.id, {
            onSuccess: () => {
                setRestoreDialogOpen(false)
                setSelectedBackup(null)
            },
        })
    }

    const handleDelete = () => {
        if (!selectedBackup) return
        deleteBackup.mutate(selectedBackup.id, {
            onSuccess: () => {
                setDeleteDialogOpen(false)
                setSelectedBackup(null)
            },
        })
    }

    const handleDownload = (backup: Backup) => {
        const token = localStorage.getItem('token')
        const url = backupsApi.download(backup.id)

        fetch(url, {
            headers: { Authorization: `Bearer ${token}` }
        })
            .then(res => res.blob())
            .then(blob => {
                const a = document.createElement('a')
                a.href = URL.createObjectURL(blob)
                a.download = backup.filename
                a.click()
                URL.revokeObjectURL(a.href)
            })
    }

    return (
        <Page title="Backups">
            <PageHeader
                title="Backups"
                description="Manage database backups"
            />

            <div className="flex gap-2 mb-6">
                <Button onClick={() => setCreateDialogOpen(true)}>
                    <Plus className="size-4 mr-2" />
                    Create Backup
                </Button>
                <Button variant="outline" onClick={() => setUploadDialogOpen(true)}>
                    <Upload className="size-4 mr-2" />
                    Upload Backup
                </Button>
            </div>

            <div className="border rounded-lg">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Size</TableHead>
                            <TableHead>Note</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            Array.from({ length: 3 }).map((_, i) => (
                                <TableRow key={i}>
                                    <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                                    <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                                    <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                                    <TableCell><Skeleton className="h-4 w-24 ml-auto" /></TableCell>
                                </TableRow>
                            ))
                        ) : backups?.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4} className="text-center text-muted-foreground py-8">
                                    No backups yet. Create your first backup to get started.
                                </TableCell>
                            </TableRow>
                        ) : (
                            backups?.map((backup) => (
                                <TableRow key={backup.id}>
                                    <TableCell>{formatDate(backup.createdAt)}</TableCell>
                                    <TableCell>{formatBytes(backup.size)}</TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {backup.note || '-'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleDownload(backup)}
                                                title="Download"
                                            >
                                                <Download className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    setSelectedBackup(backup)
                                                    setRestoreDialogOpen(true)
                                                }}
                                                title="Restore"
                                            >
                                                <RotateCcw className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => {
                                                    setSelectedBackup(backup)
                                                    setDeleteDialogOpen(true)
                                                }}
                                                title="Delete"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Create Backup Dialog */}
            <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Backup</DialogTitle>
                        <DialogDescription>
                            Create a new backup of the current database.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="note">Note (optional)</Label>
                            <Input
                                id="note"
                                placeholder="e.g., Before major update"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCreate} disabled={createBackup.isPending}>
                            {createBackup.isPending && <Loader2 className="size-4 mr-2 animate-spin" />}
                            Create
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Upload Backup Dialog */}
            <Dialog open={uploadDialogOpen} onOpenChange={setUploadDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upload Backup</DialogTitle>
                        <DialogDescription>
                            Upload a SQLite backup file.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="file">Backup File</Label>
                            <Input
                                id="file"
                                type="file"
                                accept=".sqlite,.db"
                                onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="upload-note">Note (optional)</Label>
                            <Input
                                id="upload-note"
                                placeholder="e.g., Backup from old device"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setUploadDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleUpload} disabled={!uploadFile || uploadBackup.isPending}>
                            {uploadBackup.isPending && <Loader2 className="size-4 mr-2 animate-spin" />}
                            Upload
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Restore Confirmation */}
            <AlertDialog open={restoreDialogOpen} onOpenChange={setRestoreDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Restore Backup?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will replace all current data with the backup from{' '}
                            {selectedBackup && formatDate(selectedBackup.createdAt)}.
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleRestore}
                            disabled={restoreBackup.isPending}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {restoreBackup.isPending && <Loader2 className="size-4 mr-2 animate-spin" />}
                            Restore
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Delete Confirmation */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Backup?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the backup from{' '}
                            {selectedBackup && formatDate(selectedBackup.createdAt)}.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            disabled={deleteBackup.isPending}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleteBackup.isPending && <Loader2 className="size-4 mr-2 animate-spin" />}
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Page>
    )
}
