# Egypt Post API reference

ShipBridge driver for Egypt Post with two integration surfaces.

## Reality

Egypt Post does **not** publish a public merchant create-shipment API. The merchant channel is the **Wassalha** app. This package therefore supports:

| Mode | Create / label / return | Track |
|---|---|---|
| `track_only` | Not available (clear exception) / public track URL | Official TrackTrace |
| `partner` | Your contracted B2B REST gateway | Official TrackTrace |

Default mode: `partner` when `EGYPTPOST_API_KEY` is set, otherwise `track_only`.

## Environment

```env
EGYPTPOST_MODE=track_only|partner
EGYPTPOST_API_KEY=
EGYPTPOST_BASE_URL=https://your-gateway.example/v1
EGYPTPOST_TRACK_URL=https://egyptpost.gov.eg/ar-eg/TrackTrace/GetShipmentDetails
EGYPTPOST_TRACK_PORTAL_URL=https://egyptpost.gov.eg/ar-eg/TrackTrace?Barcode={barcode}
```

## Official TrackTrace

Always used by `track()`:

```
GET {EGYPTPOST_TRACK_URL}?Barcode={trackingNumber}
```

The client parses JSON, embedded JSON in HTML, and common field aliases:

- Status keys: `Status`, `CurrentStatus`, `ShipmentStatus`, …
- Event lists: `Events`, `History`, `TrackInfo`, …
- Event fields: `Description`, `Date`, `Location`, …

Mapped through `status_map` + global `shipbridge.status_aliases`.

## Partner gateway (private B2B)

When `mode=partner`:

| Action | Method | Path |
|---|---|---|
| Create | `POST` | `{base_url}/shipments` |
| Track (driver) | — | Still uses official TrackTrace |
| Label | `GET` | `{base_url}/shipments/{id}/label?format=pdf` |
| Return | `POST` | `{base_url}/shipments/{id}/returns` |
| Exchange | `POST` | `{base_url}/shipments/{id}/exchanges` |

Auth: `Authorization: Bearer {api_key}` (optional basic auth via `username` / `password`).

### Create request body (generic scaffold)

```json
{
  "carrier": "egyptpost",
  "reference": "ORD-42",
  "origin": { "name": "...", "line1": "...", "city": "...", "phone": "" },
  "destination": { "name": "...", "phone": "01000000000", "city": "..." },
  "parcels": [{ "weight_kg": 1.2 }],
  "service": "standard",
  "cod": 0,
  "notes": ""
}
```

Pass extra partner-specific fields via `metadata.gateway` or documented metadata keys (`governorate`, `district`, …).

### Success response (expected)

```json
{
  "id": "EP-1",
  "tracking_number": "EP123456789",
  "status": "created",
  "label_url": "https://..."
}
```

### track_only label

`label()` returns:

```json
{
  "url": "https://egyptpost.gov.eg/ar-eg/TrackTrace?Barcode=EP123456789",
  "contents": "",
  "base64": false
}
```

## Errors

- `track_only` + create → `ShipBridgeException` mentioning Wassalha / partner gateway
- TrackTrace HTTP failure → `ShipBridgeException::carrierFailed`
- Partner HTTP failure → carrier message from response body
