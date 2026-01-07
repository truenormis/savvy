import * as React from 'react'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

interface StepperContextValue {
    activeStep: number
    setActiveStep: (step: number) => void
    totalSteps: number
    orientation: 'horizontal' | 'vertical'
}

const StepperContext = React.createContext<StepperContextValue | null>(null)

function useStepper() {
    const context = React.useContext(StepperContext)
    if (!context) {
        throw new Error('useStepper must be used within a Stepper')
    }
    return context
}

interface StepperProps extends React.HTMLAttributes<HTMLDivElement> {
    activeStep: number
    onStepChange?: (step: number) => void
    orientation?: 'horizontal' | 'vertical'
    children: React.ReactNode
}

function Stepper({
    activeStep,
    onStepChange,
    orientation = 'horizontal',
    children,
    className,
    ...props
}: StepperProps) {
    const steps = React.Children.toArray(children).filter(
        (child) => React.isValidElement(child) && child.type === Step
    )

    return (
        <StepperContext.Provider
            value={{
                activeStep,
                setActiveStep: onStepChange ?? (() => {}),
                totalSteps: steps.length,
                orientation,
            }}
        >
            <div
                className={cn(
                    'flex',
                    orientation === 'horizontal' ? 'flex-row items-center' : 'flex-col',
                    className
                )}
                {...props}
            >
                {React.Children.map(children, (child, index) => {
                    if (React.isValidElement(child) && child.type === Step) {
                        return React.cloneElement(child as React.ReactElement<StepProps>, {
                            index,
                            isLast: index === steps.length - 1,
                        })
                    }
                    return child
                })}
            </div>
        </StepperContext.Provider>
    )
}

interface StepProps extends React.HTMLAttributes<HTMLDivElement> {
    index?: number
    isLast?: boolean
    disabled?: boolean
    children?: React.ReactNode
}

function Step({
    index = 0,
    isLast = false,
    disabled = false,
    children,
    className,
    ...props
}: StepProps) {
    const { activeStep, setActiveStep, orientation } = useStepper()

    const isActive = index === activeStep
    const isCompleted = index < activeStep
    const isClickable = !disabled && (isCompleted || index === activeStep)

    return (
        <div
            className={cn(
                'flex',
                orientation === 'horizontal' ? 'flex-row items-center' : 'flex-col',
                !isLast && (orientation === 'horizontal' ? 'flex-1' : ''),
                className
            )}
            {...props}
        >
            <div
                className={cn(
                    'flex items-center gap-2',
                    isClickable && 'cursor-pointer',
                    disabled && 'opacity-50 cursor-not-allowed'
                )}
                onClick={() => isClickable && setActiveStep(index)}
            >
                <StepIndicator
                    step={index + 1}
                    isActive={isActive}
                    isCompleted={isCompleted}
                />
                {children && (
                    <span
                        className={cn(
                            'text-sm font-medium whitespace-nowrap',
                            isActive && 'text-foreground',
                            !isActive && !isCompleted && 'text-muted-foreground',
                            isCompleted && 'text-foreground'
                        )}
                    >
                        {children}
                    </span>
                )}
            </div>

            {!isLast && <StepSeparator isCompleted={isCompleted} />}
        </div>
    )
}

interface StepIndicatorProps {
    step: number
    isActive: boolean
    isCompleted: boolean
}

function StepIndicator({ step, isActive, isCompleted }: StepIndicatorProps) {
    return (
        <div
            className={cn(
                'flex items-center justify-center size-8 rounded-full text-sm font-medium transition-colors shrink-0',
                isCompleted && 'bg-primary text-primary-foreground',
                isActive && !isCompleted && 'bg-primary text-primary-foreground',
                !isActive && !isCompleted && 'bg-muted text-muted-foreground border border-border'
            )}
        >
            {isCompleted ? <Check className="size-4" /> : step}
        </div>
    )
}

interface StepSeparatorProps {
    isCompleted: boolean
}

function StepSeparator({ isCompleted }: StepSeparatorProps) {
    const { orientation } = useStepper()

    return (
        <div
            className={cn(
                'transition-colors',
                orientation === 'horizontal'
                    ? 'h-0.5 flex-1 mx-2 min-w-[2rem]'
                    : 'w-0.5 h-8 ml-4 my-1',
                isCompleted ? 'bg-primary' : 'bg-border'
            )}
        />
    )
}

export { Stepper, Step, useStepper }
