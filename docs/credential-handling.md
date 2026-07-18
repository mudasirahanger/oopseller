# Credential handling

How OopSeller stores and handles third-party credentials (Amazon LWA/SP-API,
Flipkart, Meesho, Snapdeal). This is the reference for the Amazon Solution
Provider Profile security-controls question:

> "Are credentials (passwords, encryption keys, secret access keys) stored
> securely? … does your organization avoid keeping credentials in public
> repositories, sharing credentials, or hard coding credentials into
> applications?"

## Controls in place

1. **No hard-coded credentials.** Application code never contains secret values.
   All credentials are read through Laravel config, which resolves them from the
   environment or a mounted secret file — see `config/services.php` and
   `app/Support/secrets.php` (`secret_env()`).
2. **No credentials in source control.** `.env` files are git-ignored
   (`.gitignore`) and excluded from container images (`.dockerignore`). The
   repository history contains no LWA secret (verified with `git log -p`). The
   source repository is private.
3. **Secret-manager-friendly loading.** In production, set `<KEY>_FILE` to a path
   provided by a secret manager instead of putting the value in `.env`:

   ```dotenv
   AMAZON_LWA_CLIENT_SECRET_FILE=/run/secrets/amazon_lwa_secret
   AMAZON_SPAPI_APPLICATION_ID_FILE=/run/secrets/amazon_app_id
   ```

   `secret_env()` reads the file when present and falls back to the plain
   variable otherwise. This works with Docker secrets, Kubernetes secrets, and
   AWS Secrets Manager / SSM mounted via a sidecar or CSI driver.
4. **Seller tokens encrypted at rest.** Amazon refresh tokens and per-account
   API credentials for the other channels are stored encrypted in the database
   (`channel_accounts.refresh_token` uses Laravel encryption; `credentials` uses
   the `encrypted:array` cast) and are hidden from API responses.
5. **Least privilege / rotation.** API tokens (Sanctum) expire and are pruned;
   application access is role-scoped (owner/admin/member/viewer). LWA secrets are
   rotated in the Amazon Developer Console on suspicion of exposure.

## Provisioning secrets (production)

1. Create the secret in your secret manager (e.g. AWS Secrets Manager entry
   `oopseller/amazon_lwa_secret`).
2. Mount it to the app container as a file (Docker/K8s secret, or CSI driver).
3. Point `AMAZON_LWA_CLIENT_SECRET_FILE` at that path. Do **not** put the value
   in `.env`, image layers, CI logs, or config caches committed to source.
4. Restart the app (or run `php artisan config:cache` on the host after the
   secret file is mounted).

## Rotation runbook

1. Generate a new secret in the Amazon Developer Console (LWA application →
   rotate client secret). The old value is invalidated.
2. Update the secret in the secret manager (or local `.env` for development).
3. Redeploy / restart; clear the LWA access-token cache
   (`amazon_lwa_access_token:*`) so new tokens are minted.
4. Rotate immediately if a secret is ever exposed (committed, logged, shared).

## Incident note (2026-07)

An LWA client secret had previously been distributed inside a project ZIP with a
committed `.env`. Remediation: the value was removed from the working tree, the
loader was moved to `secret_env()`, and the repository/history were verified
clean. **The exposed secret must be rotated in the Amazon Developer Console** —
storing the old value securely does not undo the exposure.
