<?php

namespace App\Services;

use App\Enums\SocialProvider;
use App\Models\Employee;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialUser;

class SocialAuthService
{
    /** Placeholder stored when no real name is available from the provider or client. */
    private const PLACEHOLDER_NAME = 'User';

    /**
     * Authenticate a user via a social provider.
     *
     * 1. If a SocialAccount already exists for this provider+id → log in that user.
     * 2. If the email matches an existing User → link the social account and log in.
     * 3. Otherwise → create a new User (no password) + SocialAccount.
     *
     * The $name is only used when creating a brand-new user. Apple's identity
     * token carries no name, so the client forwards the name it received on the
     * first sign-in; providers that supply a name in the token take precedence.
     *
     * @return array{user: User, token: string, is_new: bool}
     */
    public function authenticateOrCreate(SocialProvider $provider, SocialUser $socialUser, ?string $name = null): array
    {
        return DB::transaction(function () use ($provider, $socialUser, $name) {
            // Provider name wins, then the client-forwarded Apple name, then nickname.
            $resolvedName = $socialUser->getName() ?? $name ?? $socialUser->getNickname();

            // 1. Check if this social account already exists
            $socialAccount = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                $user = User::withoutGlobalScope('company')->find($socialAccount->user_id);

                // Update avatar/email if changed
                $socialAccount->update([
                    'provider_email' => $socialUser->getEmail(),
                    'avatar_url' => $socialUser->getAvatar(),
                ]);

                $this->backfillName($user, $resolvedName);

                return [
                    'user' => $user,
                    'token' => $user->createToken('auth-token')->plainTextToken,
                    'is_new' => false,
                ];
            }

            // 2. Check if email matches an existing user → link
            $email = $socialUser->getEmail();
            $user = $email
                ? User::withoutGlobalScope('company')->where('email', $email)->first()
                : null;

            $isNew = false;

            if ($user) {
                // Ensure this provider isn't already linked to a different user
                $existingLink = SocialAccount::query()
                    ->where('provider', $provider)
                    ->where('user_id', '!=', $user->id)
                    ->where('provider_id', $socialUser->getId())
                    ->exists();

                if ($existingLink) {
                    abort(409, 'This social account is already linked to a different user.');
                }

                $this->backfillName($user, $resolvedName);
            } else {
                // 3. Create a brand-new user (no password)
                $user = User::withoutGlobalScope('company')->create([
                    'name' => $resolvedName ?? self::PLACEHOLDER_NAME,
                    'email' => $email,
                    'password' => null,
                    'onboarding_step' => 1,
                ]);
                $isNew = true;

                // If this email was invited as an employee, claim the invite so the
                // user joins that company and skips onboarding (mirrors registration).
                $this->claimPendingInvite($user, $email);
            }

            // Link the social account
            SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'provider_email' => $email,
                'avatar_url' => $socialUser->getAvatar(),
            ]);

            return [
                'user' => $user,
                'token' => $user->createToken('auth-token')->plainTextToken,
                'is_new' => $isNew,
            ];
        });
    }

    /**
     * Replace a blank or placeholder name with a real one when the provider or
     * client supplies it on a later sign-in. Never overwrites a real name.
     */
    private function backfillName(User $user, ?string $resolvedName): void
    {
        if (! $resolvedName || $resolvedName === self::PLACEHOLDER_NAME) {
            return;
        }

        if ($user->name === null || $user->name === '' || $user->name === self::PLACEHOLDER_NAME) {
            $user->update(['name' => $resolvedName]);
        }
    }

    /**
     * Link a freshly-created user to an unclaimed employee invite matching their
     * email, joining that company and completing onboarding.
     */
    private function claimPendingInvite(User $user, ?string $email): void
    {
        if (! $email) {
            return;
        }

        $employee = Employee::withoutGlobalScope('company')
            ->whereNull('user_id')
            ->where('email', $email)
            ->first();

        if (! $employee) {
            return;
        }

        $employee->update([
            'user_id' => $user->id,
            'invite_token' => null,
            'invite_sent_at' => null,
        ]);

        $user->update([
            'company_id' => $employee->company_id,
            'onboarding_step' => 5,
        ]);
    }
}
