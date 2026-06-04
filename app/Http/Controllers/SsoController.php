<?php

namespace App\Http\Controllers;

use App\Exceptions\SsoException;
use App\Http\Concerns\IssuesAuthSession;
use App\Http\Resources\PublicProviderResource;
use App\Models\IdentityProvider;
use App\Services\Auth\TwoFactorChallengeService;
use App\Services\Sso\ConnectorFactory;
use App\Services\Sso\Connectors\SamlConnector;
use App\Services\Sso\ProviderResolver;
use App\Services\Sso\ProvisioningService;
use App\Services\Sso\SsoTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SsoController extends Controller
{
    use IssuesAuthSession;

    public function __construct(
        private ProviderResolver $resolver,
        private ConnectorFactory $connectors,
        private ProvisioningService $provisioning,
        private SsoTicketService $tickets,
        private TwoFactorChallengeService $challenges,
    ) {}

    /**
     * Enabled providers for the login screen. The frontend renders the buttons.
     */
    public function providers(): JsonResponse
    {
        $providers = IdentityProvider::enabled()->get();

        return response()->json(PublicProviderResource::collection($providers));
    }

    public function redirect(string $slug, Request $request): RedirectResponse
    {
        try {
            $provider = $this->resolver->enabled($slug);

            return $this->connectors->for($provider)->redirect($provider, $request->query('redirect'));
        } catch (SsoException $e) {
            Log::warning('SSO redirect rejected', ['slug' => $slug, 'error' => $e->errorCode, 'message' => $e->getMessage()]);

            return $this->toLogin($e->errorCode);
        } catch (\Throwable $e) {
            Log::error('SSO redirect failed', ['slug' => $slug, 'error' => $e->getMessage()]);

            return $this->toLogin('sso_error');
        }
    }

    public function callback(string $slug, Request $request): RedirectResponse
    {
        return $this->complete($slug, $request);
    }

    public function acs(string $slug, Request $request): RedirectResponse
    {
        return $this->complete($slug, $request);
    }

    public function metadata(string $slug): Response
    {
        $provider = IdentityProvider::where('slug', $slug)->firstOrFail();
        $xml = app(SamlConnector::class)->metadata($provider);

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate(['ticket' => ['required', 'string']]);

        $ticket = $this->tickets->consume($data['ticket']);

        if (! $ticket) {
            return response()->json(['message' => 'Login ticket is invalid or expired.', 'error' => 'invalid_ticket'], 410);
        }

        if ($ticket->requires_2fa) {
            return response()->json([
                'requires_2fa' => true,
                'two_factor_token' => $this->challenges->issue($ticket->user),
            ]);
        }

        return $this->issueSession($ticket->user, $request);
    }

    private function complete(string $slug, Request $request): RedirectResponse
    {
        try {
            $provider = $this->resolver->enabled($slug);
            $identity = $this->connectors->for($provider)->handleCallback($provider, $request);
            $result = $this->provisioning->resolve($provider, $identity);
            $ticket = $this->tickets->issue($result);

            return redirect()->away(url('/auth/sso/callback').'?'.http_build_query(['ticket' => $ticket]));
        } catch (SsoException $e) {
            Log::warning('SSO callback rejected', ['slug' => $slug, 'error' => $e->errorCode, 'message' => $e->getMessage()]);

            return $this->toSpaError($e->errorCode);
        } catch (\Throwable $e) {
            Log::error('SSO callback failed', ['slug' => $slug, 'error' => $e->getMessage()]);

            return $this->toSpaError('sso_error');
        }
    }

    private function toSpaError(string $code): RedirectResponse
    {
        return redirect()->away(url('/auth/sso/callback').'?'.http_build_query(['error' => $code]));
    }

    private function toLogin(string $code): RedirectResponse
    {
        return redirect()->away(url('/login').'?'.http_build_query(['sso_error' => $code]));
    }
}
