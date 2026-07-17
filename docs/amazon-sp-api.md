# Amazon SP-API integration

## Authorization flow

1. An authenticated agency user selects a client and marketplace.
2. Laravel creates a short-lived OAuth state record in cache.
3. The seller is redirected to the regional Seller Central consent page.
4. Amazon returns `state`, `spapi_oauth_code`, and `selling_partner_id` to the public callback.
5. Laravel exchanges the authorization code at the Login with Amazon token endpoint.
6. The refresh token is encrypted with Laravel's application key before storage.
7. The Sellers API discovers marketplace participations.
8. A listing synchronization job is queued for the selected marketplace.

## Runtime request flow

- A cached LWA access token is generated from the encrypted seller refresh token.
- Requests are sent to the proper North America, Europe, or Far East SP-API endpoint.
- `x-amz-access-token`, `x-amz-date`, and an application user agent are included.
- Amazon errors are converted to `AmazonSpApiException`, including request ID when returned.
- Listing imports are paginated and processed by a Laravel queue job.

## Catalog versus seller listings

Catalog Items supplies Amazon catalog information for an ASIN. Listings Items supplies the seller-contributed SKU listing, attributes, issues, offers, fulfilment availability, and product type. OopSeller stores both concepts separately as Product and Listing records.

## Safe listing publishing

OopSeller never publishes a newly edited listing directly. It first sends the exact JSON Patch payload in `VALIDATION_PREVIEW` mode. A hash of the listing, marketplace, product type, SKU, and patch payload is cached for 15 minutes. Publishing is allowed only for the exact successfully validated payload.

Amazon still processes accepted listing submissions asynchronously. After publishing, use Refresh from Amazon to retrieve current issues and state.

## Required environment variables

See `.env.example` for LWA client ID, LWA client secret, SP-API application ID, redirect URI, Draft mode, sandbox selection, timeout, retry count, and user agent.


## Product Type Definitions

Before a listing PATCH preview, OopSeller requests the current Product Type Definition for the seller, marketplace, and product type with `requirementsEnforced=NOT_ENFORCED`. The application exposes property-group mismatches as warnings and uses Amazon Listings Items `VALIDATION_PREVIEW` as the authoritative validation response.

The website authorization URL uses `/apps/authorize/consent/{applicationId}` with a short-lived `state`, the registered `redirect_uri`, and `version=beta` only for Draft applications.
