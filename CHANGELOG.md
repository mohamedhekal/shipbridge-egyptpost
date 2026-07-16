# Changelog

## v0.2.0 — 2026-07-16

- Dual-mode driver: `partner` (B2B gateway) and `track_only` (official tracking only)
- `track()` uses Egypt Post public TrackTrace (`GetShipmentDetails`) with flexible JSON/HTML parsing
- `createShipment` / `createReturn` / `createExchange` via configurable partner REST gateway (`POST /shipments`, etc.)
- `track_only` create throws a helpful exception referencing Wassalha and partner gateway setup
- `label()` returns public track portal URL in `track_only`; partner label endpoint in `partner` mode
- Added `EgyptPostClient`, `Support\PayloadFactory`, expanded `status_map` (incl. Arabic hints)
- Docs: `docs/GUIDE_AR.md`, `docs/API.md`; README updated with honest API limitations
- Pest tests with `Http::fake` for TrackTrace + partner flows

## v0.1.0 — 2026-07-16

- Initial Egypt Post driver for ShipBridge
- Create / track / label / return / exchange
- Status map for common Egypt Post codes
- Pest + Pint + PHPStan CI
