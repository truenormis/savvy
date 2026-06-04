<?php

namespace App\Services\Auth\Webauthn;

use App\Models\User;
use App\Models\WebauthnCredential;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManager;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebauthnService
{
    private ?SerializerInterface $serializer = null;

    private ?CeremonyStepManager $creationCeremony = null;

    private ?CeremonyStepManager $requestCeremony = null;

    public function creationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->email,
            (string) $user->id,
            $user->name,
        );

        $exclude = $user->webauthnCredentials()
            ->get(['credential_id', 'transports'])
            ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decodeNoPadding($c->credential_id),
                $c->transports ?? [],
            ))
            ->all();

        return PublicKeyCredentialCreationOptions::create(
            $this->rpEntity(),
            $userEntity,
            random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $exclude,
            timeout: (int) config('webauthn.timeout'),
        );
    }

    public function requestOptions(?User $user = null): PublicKeyCredentialRequestOptions
    {
        $allow = [];

        if ($user !== null) {
            $allow = $user->webauthnCredentials()
                ->get(['credential_id', 'transports'])
                ->map(fn (WebauthnCredential $c) => PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    Base64UrlSafe::decodeNoPadding($c->credential_id),
                    $c->transports ?? [],
                ))
                ->all();
        }

        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: config('webauthn.rp_id'),
            allowCredentials: $allow,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: (int) config('webauthn.timeout'),
        );
    }

    public function serialize(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options): string
    {
        return $this->serializer()->serialize($options, 'json');
    }

    public function verifyRegistration(User $user, string $optionsJson, string $responseJson, ?string $name): WebauthnCredential
    {
        $options = $this->serializer()->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');
        $credential = $this->deserializeCredential($responseJson);

        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw WebauthnException::verificationFailed();
        }

        try {
            $record = AuthenticatorAttestationResponseValidator::create($this->creationCeremony())
                ->check($credential->response, $options, config('webauthn.rp_id'));
        } catch (Throwable $e) {
            report($e);
            throw WebauthnException::verificationFailed();
        }

        return $this->persist($user, $record, $name);
    }

    public function verifyAuthentication(string $optionsJson, string $responseJson): WebauthnCredential
    {
        $options = $this->serializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
        $credential = $this->deserializeCredential($responseJson);

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw WebauthnException::verificationFailed();
        }

        $stored = WebauthnCredential::where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId))->first();

        if ($stored === null) {
            throw WebauthnException::unknownCredential();
        }

        $record = $this->serializer()->deserialize($stored->record, CredentialRecord::class, 'json');

        try {
            $updated = AuthenticatorAssertionResponseValidator::create($this->requestCeremony())
                ->check(
                    $record,
                    $credential->response,
                    $options,
                    config('webauthn.rp_id'),
                    $record->userHandle,
                );
        } catch (Throwable $e) {
            report($e);
            throw WebauthnException::verificationFailed();
        }

        $stored->forceFill([
            'record' => $this->serializer()->serialize($updated, 'json'),
            'counter' => $updated->counter,
            'last_used_at' => now(),
        ])->save();

        return $stored;
    }

    private function persist(User $user, CredentialRecord $record, ?string $name): WebauthnCredential
    {
        return WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            'name' => $name !== null ? mb_substr($name, 0, 100) : null,
            'aaguid' => $record->aaguid->__toString(),
            'record' => $this->serializer()->serialize($record, 'json'),
            'transports' => $record->transports,
            'counter' => $record->counter,
        ]);
    }

    private function deserializeCredential(string $responseJson): PublicKeyCredential
    {
        try {
            return $this->serializer()->deserialize($responseJson, PublicKeyCredential::class, 'json');
        } catch (Throwable $e) {
            throw WebauthnException::verificationFailed();
        }
    }

    private function rpEntity(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(config('webauthn.rp_name'), config('webauthn.rp_id'));
    }

    private function serializer(): SerializerInterface
    {
        return $this->serializer ??= (new WebauthnSerializerFactory($this->attestationManager()))->create();
    }

    private function attestationManager(): AttestationStatementSupportManager
    {
        return new AttestationStatementSupportManager([new NoneAttestationStatementSupport]);
    }

    private function ceremonyFactory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $secured = config('webauthn.secured_rp_ids');

        if (! empty($secured)) {
            $factory->setSecuredRelyingPartyId($secured);
        }

        return $factory;
    }

    private function creationCeremony(): CeremonyStepManager
    {
        return $this->creationCeremony ??= $this->ceremonyFactory()->creationCeremony();
    }

    private function requestCeremony(): CeremonyStepManager
    {
        return $this->requestCeremony ??= $this->ceremonyFactory()->requestCeremony();
    }
}
