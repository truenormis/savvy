import { ReactNode } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2, Plug, UserCog, Waypoints, Copy } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
    FormDescription,
} from '@/components/ui/form'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { FormWrapper } from '@/components/shared/FormWrapper'
import { identityProviderSchema, IdentityProviderFormValues } from '@/schemas/sso'
import { useSsoPresets } from '@/hooks/use-sso'
import { BrandIcon, brandVars } from './BrandIcon'
import type { LucideIcon } from 'lucide-react'
import type { PresetField } from '@/types/sso'

interface IdentityProviderFormProps {
    defaultValues?: Partial<IdentityProviderFormValues>
    onSubmit: (data: IdentityProviderFormValues) => void
    isSubmitting?: boolean
    submitLabel?: string
    isEdit?: boolean
    presetLocked?: boolean
    previewPreset?: string
}

const TOGGLES: { name: keyof IdentityProviderFormValues; label: string; description: string }[] = [
    { name: 'enabled', label: 'Enabled', description: 'Show this provider on the login screen' },
    { name: 'allow_jit', label: 'Just-in-time provisioning', description: 'Create new accounts on first sign-in' },
    { name: 'link_by_email', label: 'Link by email', description: 'Attach to an existing account with the same email' },
    { name: 'sync_role_on_login', label: 'Sync role on login', description: 'Re-apply role mapping on every sign-in' },
]

function CopyableUrl({ label, value }: { label: string; value: string }) {
    return (
        <div className="space-y-1">
            <p className="text-xs font-medium text-muted-foreground">{label}</p>
            <div className="flex items-center gap-2 rounded-md border bg-background px-3 py-2">
                <code className="flex-1 truncate font-mono text-xs">{value}</code>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7 shrink-0 text-muted-foreground"
                    onClick={() => {
                        navigator.clipboard?.writeText(value)
                        toast.success('Copied to clipboard')
                    }}
                >
                    <Copy className="size-3.5" />
                </Button>
            </div>
        </div>
    )
}

function RedirectUrls({ slug, protocol }: { slug: string; protocol: string }) {
    const origin = typeof window !== 'undefined' ? window.location.origin : ''
    const base = `${origin}/api/auth/sso/${slug}`

    return (
        <div className="space-y-3 rounded-lg border border-dashed bg-muted/30 p-4">
            <p className="text-xs text-muted-foreground">Register these URLs in your identity provider.</p>
            {protocol === 'saml' ? (
                <>
                    <CopyableUrl label="ACS (Reply) URL" value={`${base}/acs`} />
                    <CopyableUrl label="SP Entity ID / Metadata" value={`${base}/metadata`} />
                </>
            ) : (
                <CopyableUrl label="Redirect / Callback URL" value={`${base}/callback`} />
            )}
        </div>
    )
}

function Section({ icon: Icon, title, description, children }: { icon: LucideIcon; title: string; description?: string; children: ReactNode }) {
    return (
        <section className="rounded-xl border bg-card shadow-sm">
            <header className="flex items-start gap-3 border-b px-5 py-4">
                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-muted-foreground">
                    <Icon className="size-4" />
                </div>
                <div className="space-y-0.5">
                    <h3 className="text-sm font-semibold leading-none">{title}</h3>
                    {description && <p className="text-xs text-muted-foreground">{description}</p>}
                </div>
            </header>
            <div className="space-y-5 p-5">{children}</div>
        </section>
    )
}

export function IdentityProviderForm({
    defaultValues,
    onSubmit,
    isSubmitting,
    submitLabel = 'Save',
    isEdit = false,
    presetLocked = false,
    previewPreset,
}: IdentityProviderFormProps) {
    const { data: presets } = useSsoPresets()

    const form = useForm<IdentityProviderFormValues>({
        resolver: zodResolver(identityProviderSchema),
        defaultValues: {
            name: '',
            slug: '',
            preset: '',
            enabled: true,
            fields: {},
            role_mapping: [],
            default_role: 'read-only',
            allow_jit: true,
            sync_role_on_login: false,
            link_by_email: true,
            ...defaultValues,
        },
    })

    const { fields: ruleFields, append, remove } = useFieldArray({
        control: form.control,
        name: 'role_mapping',
    })

    const selectedKey = form.watch('preset')
    const selectedPreset = presets?.find((p) => p.key === selectedKey)
    const previewName = form.watch('name')
    const previewEnabled = form.watch('enabled')
    const slug = form.watch('slug')

    const renderField = (field: PresetField) => (
        <FormField
            key={field.key}
            control={form.control}
            name={`fields.${field.key}` as const}
            render={({ field: f }) => (
                <FormItem>
                    <FormLabel>{field.label}</FormLabel>
                    <FormControl>
                        {field.type === 'textarea' ? (
                            <Textarea
                                placeholder={field.placeholder}
                                rows={4}
                                {...f}
                                value={f.value ?? ''}
                            />
                        ) : (
                            <Input
                                type={field.secret ? 'password' : field.type === 'url' ? 'url' : 'text'}
                                placeholder={isEdit && field.secret ? 'Leave blank to keep current' : field.placeholder}
                                {...f}
                                value={f.value ?? ''}
                            />
                        )}
                    </FormControl>
                    {field.help && <FormDescription>{field.help}</FormDescription>}
                    <FormMessage />
                </FormItem>
            )}
        />
    )

    const formBody = (
        <FormWrapper>
            <Form {...form}>
                <form onSubmit={form.handleSubmit(onSubmit)} className={`space-y-6 ${previewPreset ? 'w-full' : 'max-w-2xl'}`}>
                    <Section icon={Plug} title="Connection" description="How Savvy talks to your identity provider">
                        <FormField
                            control={form.control}
                            name="name"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Display name</FormLabel>
                                    <FormControl>
                                        <Input placeholder="Company Okta" {...field} />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="slug"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Slug</FormLabel>
                                    <FormControl>
                                        <Input placeholder="company-okta" {...field} disabled={isEdit} />
                                    </FormControl>
                                    <FormDescription>Used in the sign-in URL. Cannot be changed later.</FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        {!presetLocked && (
                            <FormField
                                control={form.control}
                                name="preset"
                                render={({ field }) => (
                                    <FormItem>
                                        <FormLabel>Provider type</FormLabel>
                                        <Select value={field.value} onValueChange={field.onChange} disabled={isEdit}>
                                            <FormControl>
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder="Select a provider type" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                {presets?.map((preset) => (
                                                    <SelectItem key={preset.key} value={preset.key}>
                                                        {preset.label} ({preset.protocol.toUpperCase()})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                )}
                            />
                        )}

                        {selectedPreset && selectedPreset.fields.map(renderField)}

                        {selectedPreset && slug && (
                            <RedirectUrls slug={slug} protocol={selectedPreset.protocol} />
                        )}
                    </Section>

                    <Section icon={UserCog} title="Provisioning" description="What happens when someone signs in through this provider">
                        <FormField
                            control={form.control}
                            name="default_role"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Default role</FormLabel>
                                    <Select value={field.value} onValueChange={field.onChange}>
                                        <FormControl>
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="read-only">Read-Only</SelectItem>
                                            <SelectItem value="read-write">Read-Write</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormDescription>Admin can only be granted through a role-mapping rule.</FormDescription>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <div className="divide-y rounded-lg border">
                            {TOGGLES.map((toggle) => (
                                <FormField
                                    key={toggle.name}
                                    control={form.control}
                                    name={toggle.name}
                                    render={({ field }) => (
                                        <FormItem className="flex items-center justify-between gap-4 px-4 py-3">
                                            <div className="space-y-0.5">
                                                <FormLabel className="cursor-pointer">{toggle.label}</FormLabel>
                                                <FormDescription>{toggle.description}</FormDescription>
                                            </div>
                                            <FormControl>
                                                <Switch checked={Boolean(field.value)} onCheckedChange={field.onChange} />
                                            </FormControl>
                                        </FormItem>
                                    )}
                                />
                            ))}
                        </div>
                    </Section>

                    <Section icon={Waypoints} title="Role mapping" description="Assign roles from IdP claims — the first matching rule wins">
                        {ruleFields.length > 0 && (
                            <div className="hidden grid-cols-[1fr_140px_1fr_160px_auto] gap-2 px-1 text-xs font-medium text-muted-foreground sm:grid">
                                <span>Claim</span>
                                <span>Condition</span>
                                <span>Value</span>
                                <span>Role</span>
                                <span />
                            </div>
                        )}

                        {ruleFields.map((rule, index) => (
                            <div
                                key={rule.id}
                                className="grid grid-cols-1 gap-2 rounded-lg border bg-muted/30 p-2 sm:grid-cols-[1fr_140px_1fr_160px_auto] sm:items-center sm:bg-transparent sm:border-0 sm:p-0"
                            >
                                <Input
                                    placeholder="groups"
                                    className="font-mono text-sm"
                                    {...form.register(`role_mapping.${index}.claim`)}
                                />
                                <Select
                                    value={form.watch(`role_mapping.${index}.operator`)}
                                    onValueChange={(v) => form.setValue(`role_mapping.${index}.operator`, v as 'equals' | 'contains' | 'one_of')}
                                >
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="equals">equals</SelectItem>
                                        <SelectItem value="contains">contains</SelectItem>
                                        <SelectItem value="one_of">one of</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    placeholder="value"
                                    className="font-mono text-sm"
                                    {...form.register(`role_mapping.${index}.value`)}
                                />
                                <Select
                                    value={form.watch(`role_mapping.${index}.role`)}
                                    onValueChange={(v) => form.setValue(`role_mapping.${index}.role`, v as 'admin' | 'read-write' | 'read-only')}
                                >
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="read-write">Read-Write</SelectItem>
                                        <SelectItem value="read-only">Read-Only</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="justify-self-end text-muted-foreground hover:text-destructive"
                                    onClick={() => remove(index)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}

                        {ruleFields.length === 0 && (
                            <p className="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground">
                                No rules yet — everyone gets the default role above.
                            </p>
                        )}

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-full sm:w-auto"
                            onClick={() => append({ claim: 'groups', operator: 'contains', value: '', role: 'read-only' })}
                        >
                            <Plus className="mr-2 size-4" />
                            Add rule
                        </Button>
                    </Section>

                    <Button type="submit" disabled={isSubmitting} className="w-full">
                        {isSubmitting ? 'Saving...' : submitLabel}
                    </Button>
                </form>
            </Form>
        </FormWrapper>
    )

    if (!previewPreset) {
        return formBody
    }

    return (
        <div className="grid max-w-5xl gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
            {formBody}
            <Card className="lg:sticky lg:top-6">
                <CardHeader>
                    <CardTitle className="text-sm">Login button preview</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div
                        style={brandVars(previewPreset)}
                        className="rounded-lg border bg-[color-mix(in_oklab,var(--brand)_5%,var(--background))] p-4"
                    >
                        <div className="inline-flex h-10 w-full items-center justify-center gap-2.5 rounded-md border bg-background px-4 text-sm font-medium shadow-sm">
                            <BrandIcon preset={previewPreset} className="size-4" />
                            Continue with {previewName || 'Provider'}
                        </div>
                    </div>
                    <div className="flex items-center gap-2 text-xs">
                        <span className={`size-1.5 rounded-full ${previewEnabled ? 'bg-emerald-500' : 'bg-muted-foreground/40'}`} />
                        <span className="text-muted-foreground">
                            {previewEnabled ? 'Visible on the sign-in screen' : 'Hidden — enable to show on login'}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>
    )
}
