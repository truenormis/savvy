import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { FileQuestion, Home } from 'lucide-react'

export default function NotFoundPage() {
    return (
        <div className="flex flex-col items-center justify-center min-h-[60vh] p-8">
            <div className="rounded-full bg-muted p-6 mb-6">
                <FileQuestion className="h-12 w-12 text-muted-foreground" />
            </div>
            <h1 className="text-4xl font-bold mb-2">404</h1>
            <h2 className="text-xl text-muted-foreground mb-4">Page not found</h2>
            <p className="text-muted-foreground text-center mb-6 max-w-md">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <Button asChild>
                <Link to="/">
                    <Home className="mr-2 h-4 w-4" />
                    Back to Dashboard
                </Link>
            </Button>
        </div>
    )
}
