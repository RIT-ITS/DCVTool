<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Service for validating security tokens
 */
class TokenValidator
{
    /**
     * Validate a token against an expected environment variable
     */
    public function validate(string $token, string $envVarName): bool
    {
        if (empty($token)) {
            return false;
        }

        // Get the environment variable value
        $expectedToken = $_ENV[$envVarName] ?? null;

        if (empty($expectedToken)) {
            return false;
        }

        // Use constant-time comparison to prevent timing attacks
        return hash_equals($expectedToken, $token);
    }
}
