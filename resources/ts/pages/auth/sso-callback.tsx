import { useEffect, useRef } from 'react'
import { useSearchParams, useNavigate } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { ssoApi } from '@/api/sso'
import { useAuthStore } from '@/stores/auth'

const SSO_ERROR_MESSAGES: Record<string, string> = {
    invalid_issuer: 'The provider’s token issuer did not match. Check the tenant / issuer setting.',
    invalid_audience: 'The token was issued for a different application. Check the Client ID.',
    invalid_nonce: 'Sign-in could not be verified (nonce mismatch). Please try again.',
    invalid_id_token: 'The identity token signature could not be verified.',
    invalid_state: 'The sign-in session expired or was tampered with. Please try again.',
    token_exchange_failed: 'The provider rejected the authorization code. Check the Client Secret and Redirect URL.',
    jwks_failed: 'Could not load the provider’s signing keys.',
    metadata_unreachable: 'Could not reach the provider’s metadata endpoint.',
    idp_error: 'The identity provider returned an error.',
    no_email: 'The provider did not return an email address.',
    email_in_use: 'An account with this email already exists. Ask an admin to enable email linking.',
    signup_disabled: 'This account is not provisioned. Contact an administrator.',
    no_admin: 'Complete the initial setup before using SSO.',
}

function ssoErrorMessage(code: string): string {
    return SSO_ERROR_MESSAGES[code] ?? `Single sign-on failed (${code}).`
}

export default function SsoCallbackPage() {
    const [params] = useSearchParams()
    const navigate = useNavigate()
    const setUser = useAuthStore((state) => state.setUser)
    const ran = useRef(false)

    useEffect(() => {
        if (ran.current) return
        ran.current = true

        const error = params.get('error')
        const ticket = params.get('ticket')

        if (error || !ticket) {
            toast.error(error ? ssoErrorMessage(error) : 'Single sign-on failed. Please try again.')
            navigate('/login', { replace: true })
            return
        }

        ssoApi
            .exchange(ticket)
            .then((res) => {
                if ('requires_2fa' in res) {
                    navigate('/login', { replace: true, state: { twoFactorToken: res.two_factor_token } })
                    return
                }

                setUser(res.user)
                toast.success('Welcome back!')
                navigate('/', { replace: true })
            })
            .catch(() => {
                toast.error('Single sign-on failed. Please try again.')
                navigate('/login', { replace: true })
            })
    }, [params, navigate, setUser])

    return (
        <div className="min-h-screen flex items-center justify-center bg-background">
            <Loader2 className="size-8 animate-spin text-muted-foreground" />
        </div>
    )
}
