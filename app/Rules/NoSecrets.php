<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects text that contains obvious secrets (private keys, cloud/API tokens).
 * This is a safety net, not a substitute for a secret manager.
 */
class NoSecrets implements ValidationRule
{
    /** @var array<string, string> */
    private const PATTERNS = [
        'a private key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'an AWS access key ID' => '/\b(AKIA|ASIA)[0-9A-Z]{16}\b/',
        'a GitHub token' => '/\b(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{36,}\b|\bgithub_pat_[A-Za-z0-9_]{50,}\b/',
        'a Slack token' => '/\bxox[abposr]-[A-Za-z0-9-]{10,}\b/',
        'a Stripe secret key' => '/\b(sk|rk)_live_[A-Za-z0-9]{20,}\b/',
        'a Google API key' => '/\bAIza[0-9A-Za-z_-]{35}\b/',
        'a credential in a URL' => '#\b[a-z][a-z0-9+.-]*://[^\s/:@]+:[^\s/@]+@#i',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        foreach (self::PATTERNS as $label => $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $fail("The :attribute appears to contain {$label}. Store secrets in the secret manager and reference them instead.");

                return;
            }
        }
    }
}
