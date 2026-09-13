# Cloudflare R2 media rollout

This repository supports a migration-safe R2 media disk. Existing unprefixed keys remain on the `public` disk, while new uploads use R2 when `MEDIA_DISK=r2`.

## Target configuration

- Bucket: `tribunasyon-media`
- Public delivery: `https://img.tribunasyon.com`
- S3 endpoint: `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
- Laravel disk: `r2`
- Legacy disk: `public`
- New R2 key prefix: `r2/v1`
- Production switch: set `MEDIA_DISK=r2` only after verification
- Do not enable the `r2.dev` development URL; the custom domain is the only public path.

R2 credentials must be stored only in the production secret manager or environment. Never commit them. The public delivery URL is not an upload endpoint.

## CORS policy

Apply this exact-origin policy to the bucket. Remove local origins that are not used by the development workflow.

```json
[
  {
    "AllowedOrigins": [
      "https://tribunasyon.com",
      "https://www.tribunasyon.com",
      "http://127.0.0.1:8000",
      "http://localhost:8000",
      "http://127.0.0.1:5173",
      "http://localhost:5173"
    ],
    "AllowedMethods": ["GET", "HEAD", "PUT"],
    "AllowedHeaders": ["Content-Type", "Cache-Control", "x-amz-checksum-sha256"],
    "ExposeHeaders": ["ETag", "Content-Length", "Content-Type", "cf-cache-status"],
    "MaxAgeSeconds": 3600
  }
]
```

## Immutable object keys and caching

The current upload path uses Laravel's random `hashName()` behavior under a bounded directory such as `posts/`, `avatars/`, or `branding/`. A replacement creates a new key; application code must never overwrite an existing key. This makes one-year immutable browser/CDN caching safe:

`Cache-Control: public, max-age=31536000, immutable`

Database rows store only the object key. `MediaUrlResolver` sends versioned `r2/v1/...` keys to R2 and keeps unprefixed legacy keys on the `public` disk. Changing the delivery hostname does not require rewriting database rows.

Create a Cloudflare Cache Rule scoped only to `img.tribunasyon.com`, eligible for cache, with origin cache-control respected. Do not cache authenticated HTML/API traffic on `tribunasyon.com`.

## Direct-upload phase (not implemented yet)

1. The authenticated Laravel endpoint validates authorization, MIME type, size, media count, and the requested logical directory.
2. Laravel generates the final random immutable object key and a short-lived R2 presigned `PUT` URL (target: 5 minutes).
3. The browser uploads directly to R2 with the signed `Content-Type`, checksum, and immutable `Cache-Control` headers.
4. The browser sends the key, size, checksum, and ETag back to Laravel.
5. Laravel verifies the object with `HEAD`, then commits the media database row inside the existing transaction boundary.
6. Unclaimed keys are removed later by a conservative age-based cleanup job; they are never removed inline based only on a client report.

Keep upload credentials server-side. Do not generate a broad R2 token in the browser, and do not accept a client-supplied bucket/key outside the server-created prefix.

## Production rollout gates

Before setting `MEDIA_DISK=r2`:

1. Confirm `img.tribunasyon.com` is Active on the bucket and `r2.dev` is disabled.
2. Upload a disposable test object and verify `200`, HTTPS, CORS preflight, `ETag`, `Content-Type`, and `Cache-Control`.
3. Confirm a second GET produces an appropriate Cloudflare cache status.
4. Run the application test suite with `MEDIA_DISK=public` and a focused R2 smoke test with non-production credentials.
5. Do not move or delete existing production media. Plan a separate copy-and-verify migration with a manifest and rollback window.
6. Keep the Google OAuth callback unchanged at `https://tribunasyon.com/auth/google/callback`; Cloudflare proxying must not rewrite its host or path.
