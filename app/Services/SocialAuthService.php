<?php

namespace App\Services;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialUser;

class SocialAuthService
{
    /**
     * Authenticate a user via a social provider.
     *
     * 1. If a SocialAccount already exists for this provider+id → log in that user.
     * 2. If the email matches an existing User → link the social account and log in.
     * 3. Otherwise → create a new User (no password) + SocialAccount.
     *
     * @return array{user: User, token: string, is_new: bool}
     */
    public function authenticateOrCreate(SocialProvider $provider, SocialUser $socialUser): array
    {
        return DB::transaction(function () use ($provider, $socialUser) {
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
            } else {
                // 3. Create a brand-new user (no password)
                $user = User::withoutGlobalScope('company')->create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $email,
                    'password' => null,
                ]);
                $isNew = true;
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
}
