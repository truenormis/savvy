import { useState, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Stepper, Step } from '@/components/ui/stepper'
import { ArrowLeft, ArrowRight, Upload, Loader2, AlertCircle } from 'lucide-react'
import { useParseCSV, usePreviewImport, useExecuteImport } from '@/hooks/use-csv-import'
import { UploadStep } from './steps/UploadStep'
import { MappingStep } from './steps/MappingStep'
import { PreviewStep } from './steps/PreviewStep'
import { ResultStep } from './steps/ResultStep'
import type {
    ImportStep,
    CsvParseResult,
    ColumnMapping,
    ImportOptions,
    ImportPreviewResult,
    ImportResult,
} from '@/types/import'

const STEPS: { id: ImportStep; label: string }[] = [
    { id: 'upload', label: 'Upload' },
    { id: 'mapping', label: 'Mapping' },
    { id: 'preview', label: 'Preview' },
    { id: 'result', label: 'Result' },
]

export function CsvImportWizard() {
    const [step, setStep] = useState<ImportStep>('upload')
    const [parseResult, setParseResult] = useState<CsvParseResult | null>(null)
    const [previewResult, setPreviewResult] = useState<ImportPreviewResult | null>(null)
    const [importResult, setImportResult] = useState<ImportResult | null>(null)
    const [mapping, setMapping] = useState<ColumnMapping | null>(null)
    const [options, setOptions] = useState<ImportOptions | null>(null)
    const [error, setError] = useState<string | null>(null)

    const parseMutation = useParseCSV()
    const previewMutation = usePreviewImport()
    const importMutation = useExecuteImport()

    const currentStepIndex = STEPS.findIndex((s) => s.id === step)

    const handleFileSelect = useCallback(async (file: File) => {
        setError(null)
        try {
            const result = await parseMutation.mutateAsync(file)
            setParseResult(result)
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to parse file')
        }
    }, [parseMutation])

    const handleMappingSubmit = useCallback(async (newMapping: ColumnMapping, newOptions: ImportOptions) => {
        if (!parseResult) return

        setMapping(newMapping)
        setOptions(newOptions)
        setError(null)

        try {
            const result = await previewMutation.mutateAsync({
                importId: parseResult.importId,
                mapping: newMapping,
                options: newOptions,
            })
            setPreviewResult(result)
            setStep('preview')
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to preview import')
        }
    }, [parseResult, previewMutation])

    const handleImport = useCallback(async () => {
        if (!parseResult || !mapping || !options) return

        setError(null)

        try {
            const result = await importMutation.mutateAsync({
                importId: parseResult.importId,
                mapping,
                options,
            })
            setImportResult(result)
            setStep('result')
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Failed to execute import')
        }
    }, [parseResult, mapping, options, importMutation])

    const handleBack = useCallback(() => {
        if (step === 'mapping') {
            setStep('upload')
        } else if (step === 'preview') {
            setStep('mapping')
        }
    }, [step])

    const handleNext = useCallback(() => {
        if (step === 'upload' && parseResult) {
            setStep('mapping')
        }
    }, [step, parseResult])

    const reset = useCallback(() => {
        setStep('upload')
        setParseResult(null)
        setPreviewResult(null)
        setImportResult(null)
        setMapping(null)
        setOptions(null)
        setError(null)
    }, [])

    const isLoading = parseMutation.isPending || previewMutation.isPending || importMutation.isPending

    const canGoToStep = (targetIndex: number): boolean => {
        if (targetIndex === 0) return true
        if (targetIndex === 1) return !!parseResult
        if (targetIndex === 2) return !!previewResult
        if (targetIndex === 3) return !!importResult
        return false
    }

    const handleStepChange = (targetIndex: number) => {
        if (canGoToStep(targetIndex) && !isLoading) {
            setStep(STEPS[targetIndex].id)
        }
    }

    return (
        <div className="space-y-8">
            <Stepper
                activeStep={currentStepIndex}
                onStepChange={handleStepChange}
                className="w-full"
            >
                {STEPS.map((s, index) => (
                    <Step key={s.id} disabled={!canGoToStep(index) || isLoading}>
                        {s.label}
                    </Step>
                ))}
            </Stepper>

            <div className="min-h-[400px]">
                {step === 'upload' && (
                    <UploadStep
                        onFileSelect={handleFileSelect}
                        parseResult={parseResult}
                        isLoading={parseMutation.isPending}
                        error={error}
                    />
                )}

                {step === 'mapping' && parseResult && (
                    <MappingStep
                        parseResult={parseResult}
                        onSubmit={handleMappingSubmit}
                        isLoading={previewMutation.isPending}
                    />
                )}

                {step === 'preview' && previewResult && (
                    <PreviewStep
                        previewResult={previewResult}
                        isLoading={importMutation.isPending}
                    />
                )}

                {step === 'result' && importResult && (
                    <ResultStep result={importResult} />
                )}
            </div>

            {/* Error Display */}
            {error && step !== 'upload' && (
                <Alert variant="destructive">
                    <AlertCircle className="size-4" />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {/* Navigation Buttons */}
            {step !== 'result' && (
                <div className="flex justify-between pt-6 border-t">
                    <div>
                        {step !== 'upload' && (
                            <Button
                                variant="outline"
                                onClick={handleBack}
                                disabled={isLoading}
                            >
                                <ArrowLeft className="size-4 mr-2" />
                                Back
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {step === 'upload' && (
                            <Button
                                onClick={handleNext}
                                disabled={!parseResult || isLoading}
                            >
                                Next
                                <ArrowRight className="size-4 ml-2" />
                            </Button>
                        )}
                        {step === 'mapping' && (
                            <Button
                                type="submit"
                                form="mapping-form"
                                disabled={isLoading}
                            >
                                {previewMutation.isPending ? (
                                    <>
                                        <Loader2 className="size-4 mr-2 animate-spin" />
                                        Loading...
                                    </>
                                ) : (
                                    <>
                                        Preview
                                        <ArrowRight className="size-4 ml-2" />
                                    </>
                                )}
                            </Button>
                        )}
                        {step === 'preview' && (
                            <Button
                                onClick={handleImport}
                                disabled={isLoading || (previewResult?.summary.willCreate ?? 0) === 0}
                            >
                                {importMutation.isPending ? (
                                    <>
                                        <Loader2 className="size-4 mr-2 animate-spin" />
                                        Importing...
                                    </>
                                ) : (
                                    <>
                                        <Upload className="size-4 mr-2" />
                                        Import {previewResult?.summary.willCreate ?? 0} Transactions
                                    </>
                                )}
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {/* Reset Button for Result Step */}
            {step === 'result' && (
                <div className="flex justify-center pt-6 border-t">
                    <Button variant="outline" onClick={reset}>
                        Import Another File
                    </Button>
                </div>
            )}
        </div>
    )
}
