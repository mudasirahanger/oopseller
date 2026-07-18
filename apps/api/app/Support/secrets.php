<?php

use Illuminate\Support\Env;

if (! function_exists('secret_env')) {
    /**
     * Resolve a secret from a mounted secret file when available, else the
     * plain environment variable.
     *
     * Secret managers (AWS Secrets Manager / SSM via a sidecar, Docker secrets,
     * Kubernetes secrets) expose values as files. Setting `<KEY>_FILE` to that
     * path keeps the secret out of committed .env files and process listings.
     * Example: AMAZON_LWA_CLIENT_SECRET_FILE=/run/secrets/amazon_lwa_secret
     *
     * Falls back to `<KEY>` so local development and tests are unchanged.
     */
    function secret_env(string $key, mixed $default = null): mixed
    {
        $path = Env::get($key.'_FILE');

        if (is_string($path) && $path !== '' && is_readable($path)) {
            $value = trim((string) file_get_contents($path));

            if ($value !== '') {
                return $value;
            }
        }

        return Env::get($key, $default);
    }
}
