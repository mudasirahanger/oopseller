# Amazon connector

**Auth:** OAuth 2.0 via Login with Amazon (SP-API website authorization workflow).
**Adapter:** `app/Services/Channels/AmazonChannelProvider.php` wrapping the full SP-API stack in `app/Services/Amazon/`.
**Identity:** ASIN (`products.asin`, mirrored into `products.external_id`); listings use the real Amazon marketplace ID (e.g. `A21TJRUUN4KGV`).

## Server setup

1. Register a Selling Partner API application in the Amazon Developer Console and note the LWA client ID/secret and the application ID.
2. Register the OAuth redirect URI exactly as configured here. Local default:
   `http://127.0.0.1:8000/api/v1/integrations/amazon/callback`
3. Set in `apps/api/.env`:

```dotenv
AMAZON_LWA_CLIENT_ID=
AMAZON_LWA_CLIENT_SECRET=
AMAZON_SPAPI_APPLICATION_ID=
AMAZON_REDIRECT_URI=http://127.0.0.1:8000/api/v1/integrations/amazon/callback
AMAZON_DEFAULT_REGION=eu
AMAZON_SPAPI_DRAFT=true     # keep true while the Amazon app is in Draft
AMAZON_SPAPI_SANDBOX=false
```

## Connecting an account

1. Integration hub → Amazon card → **Connect**, pick the client and Seller Central marketplace (this selects the regional consent URL).
2. Approve the consent screen with the seller account; the callback stores the refresh token encrypted and queues the initial listing sync.

## Implemented operations

Sellers marketplace participation, Catalog Items 2022-04-01, Listings Items 2021-08-01 (search/get/patch), Product Type Definitions 2020-09-01, and `VALIDATION_PREVIEW` before every publish. Orders, FBA inventory, Ads, and Brand Analytics are separate roadmap phases.
