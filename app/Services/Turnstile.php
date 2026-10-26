<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cloudflare Turnstile check for the staff sign-in form. Off unless both keys
 * are set, so local development and tests need no Cloudflare account.
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return filled(config('examprep.turnstile.site_key')) && filled(config('examprep.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('examprep.turnstile.site_key');
    }

    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $result = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('examprep.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ])->json();
        } catch (Throwable) {
            return false; // fail closed: staff can retry, bots cannot slip through
        }

        return ($result['success'] ?? false) === true;
    }
}
