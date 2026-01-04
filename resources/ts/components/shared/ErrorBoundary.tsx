import { Component, ReactNode } from 'react'
import { Button } from '@/components/ui/button'
import { AlertTriangle, RefreshCw } from 'lucide-react'

interface Props {
    children: ReactNode
    fallback?: ReactNode
}

interface State {
    hasError: boolean
    error?: Error
}

export class ErrorBoundary extends Component<Props, State> {
    constructor(props: Props) {
        super(props)
        this.state = { hasError: false }
    }

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error }
    }

    componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
        console.error('Error caught by boundary:', error, errorInfo)
    }

    handleReset = () => {
        this.setState({ hasError: false, error: undefined })
    }

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback
            }

            return (
                <div className="flex flex-col items-center justify-center min-h-[400px] p-8">
                    <div className="rounded-full bg-destructive/10 p-4 mb-4">
                        <AlertTriangle className="h-8 w-8 text-destructive" />
                    </div>
                    <h2 className="text-xl font-semibold mb-2">Something went wrong</h2>
                    <p className="text-muted-foreground text-center mb-4 max-w-md">
                        An unexpected error occurred. Please try again or contact support if the problem persists.
                    </p>
                    {this.state.error && (
                        <pre className="text-xs bg-muted p-3 rounded-md mb-4 max-w-md overflow-auto">
                            {this.state.error.message}
                        </pre>
                    )}
                    <Button onClick={this.handleReset}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Try again
                    </Button>
                </div>
            )
        }

        return this.props.children
    }
}
