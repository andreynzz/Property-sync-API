# Property API contract

Property Sync API requests a paginated, read-only endpoint using bearer-token
authentication.

```http
GET /properties?page=1&per_page=50
Accept: application/json
Authorization: Bearer <token>
```

Every successful response must use a `2xx` status and this envelope:

```json
{
  "data": [
    {
      "external_id": "PROP-1001",
      "title": "Modern apartment downtown",
      "description": "Two-bedroom apartment close to public transport.",
      "price": "485000.00",
      "property_type": "apartment",
      "city": "Sao Paulo",
      "neighborhood": "Pinheiros",
      "bedrooms": 2,
      "bathrooms": 2,
      "area": "78.50",
      "status": "available",
      "image_url": "https://example.com/images/prop-1001.jpg",
      "updated_at": "2026-09-01T14:30:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 50,
    "total": 1,
    "total_pages": 1,
    "next_page": null
  }
}
```

`data` must be an array of objects. `page`, `per_page`, and `total_pages` are
positive JSON integers; `total` is a non-negative JSON integer. `next_page` is
either `null` or a positive integer greater than the current page and no larger
than `total_pages`.

The plugin limits requests to 50 items per page and 100 pages per run. It
rejects malformed JSON, malformed envelopes, non-2xx responses, and pagination
loops before handing items to the normalization stage.

## Local mock scenarios

The Docker mock API expects `Authorization: Bearer demo-token`. It supports
normal pagination and these deterministic failure cases after authentication:

| URL query | Behaviour |
| --- | --- |
| `scenario=server-error` | Returns HTTP 500. |
| `scenario=invalid-json` | Returns HTTP 200 with malformed JSON. |
| `scenario=slow` | Delays for 16 seconds to exceed the client timeout. |

Any unsupported route returns HTTP 404. A missing or incorrect bearer token
returns HTTP 401.
