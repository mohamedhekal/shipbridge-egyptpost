<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\EgyptPost;

use Hekal\ShipBridge\Exceptions\ShipBridgeException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Egypt Post HTTP client.
 *
 * - Official TrackTrace (egyptpost.gov.eg) for public tracking.
 * - Optional partner REST gateway for create / label / return / exchange.
 */
final class EgyptPostClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function mode(): string
    {
        $configured = $this->config['mode'] ?? null;
        if (is_string($configured) && in_array($configured, ['partner', 'track_only'], true)) {
            return $configured;
        }

        $token = $this->apiKey();

        return $token !== null ? 'partner' : 'track_only';
    }

    public function ensurePartnerMode(string $action): void
    {
        if ($this->mode() === 'partner') {
            return;
        }

        throw ShipBridgeException::carrierFailed(
            "Egypt Post does not publish a public merchant {$action} API. "
            .'Merchants typically use the Wassalha (وصّلها) app, or a contracted B2B partner gateway. '
            .'Set EGYPTPOST_API_KEY + EGYPTPOST_BASE_URL (mode=partner) to route create/label through your gateway.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function trackOfficial(string $barcode): array
    {
        $url = (string) ($this->config['track_url']
            ?? 'https://egyptpost.gov.eg/ar-eg/TrackTrace/GetShipmentDetails');

        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 20))
            ->acceptJson()
            ->withHeaders([
                'X-ShipBridge-Carrier' => 'egyptpost',
                'User-Agent' => (string) ($this->config['user_agent'] ?? 'ShipBridge-EgyptPost/0.2'),
            ])
            ->get($url, [
                'Barcode' => $barcode,
            ]);

        return $this->parseTrackResponse($response, $barcode);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array
    {
        $response = $this->partnerClient()->post('shipments', $payload);

        return $this->decodePartner($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createReturn(string $originalShipmentId, array $payload): array
    {
        $response = $this->partnerClient()->post("shipments/{$originalShipmentId}/returns", $payload);

        return $this->decodePartner($response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createExchange(string $originalShipmentId, array $payload): array
    {
        $response = $this->partnerClient()->post("shipments/{$originalShipmentId}/exchanges", $payload);

        return $this->decodePartner($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function label(string $shipmentId, string $format): array
    {
        $response = $this->partnerClient()->get("shipments/{$shipmentId}/label", [
            'format' => $format,
        ]);

        return $this->decodePartner($response);
    }

    public function publicTrackUrl(string $barcode): string
    {
        $template = (string) ($this->config['track_portal_url']
            ?? 'https://egyptpost.gov.eg/ar-eg/TrackTrace?Barcode={barcode}');

        return str_replace('{barcode}', rawurlencode($barcode), $template);
    }

    private function partnerClient(): PendingRequest
    {
        $baseUrl = (string) ($this->config['base_url'] ?? '');
        if ($baseUrl === '') {
            throw ShipBridgeException::carrierFailed(
                'Egypt Post partner mode requires EGYPTPOST_BASE_URL.'
            );
        }

        $pending = $this->http
            ->baseUrl(rtrim($baseUrl, '/'))
            ->timeout((int) ($this->config['timeout'] ?? 20))
            ->acceptJson()
            ->withHeaders([
                'X-ShipBridge-Carrier' => 'egyptpost',
            ]);

        $token = $this->apiKey();
        if ($token !== null) {
            $pending = $pending->withToken($token);
        }

        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        if (is_string($username) && is_string($password) && $username !== '' && $password !== '') {
            $pending = $pending->withBasicAuth($username, $password);
        }

        return $pending;
    }

    private function apiKey(): ?string
    {
        $token = $this->config['token'] ?? $this->config['api_key'] ?? $this->config['passkey'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePartner(Response $response): array
    {
        if (! $response->successful()) {
            throw ShipBridgeException::carrierFailed(
                (string) ($response->json('message') ?? $response->body()),
                $response->status(),
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTrackResponse(Response $response, string $barcode): array
    {
        if (! $response->successful()) {
            throw ShipBridgeException::carrierFailed(
                'Egypt Post TrackTrace request failed.',
                $response->status(),
            );
        }

        $body = $response->body();
        $decoded = $this->decodeFlexible($body);

        if ($decoded === []) {
            throw ShipBridgeException::carrierFailed(
                'Egypt Post TrackTrace returned an unparseable response.'
            );
        }

        $shipment = $this->extractShipmentNode($decoded);
        if ($shipment === []) {
            $shipment = $decoded;
        }

        $status = $this->pickString($shipment, [
            'Status', 'status', 'CurrentStatus', 'currentStatus', 'ShipmentStatus', 'shipmentStatus', 'LastStatus', 'lastStatus',
        ]);

        $resolvedBarcode = $this->pickString($shipment, [
            'Barcode', 'barcode', 'TrackingNumber', 'trackingNumber', 'TrackingNo', 'trackingNo',
        ]) ?? $barcode;

        /** @var list<array<string, mixed>> $rawEvents */
        $rawEvents = [];
        foreach ($this->eventSources($shipment, $decoded) as $source) {
            if (! is_array($source)) {
                continue;
            }
            foreach ($source as $event) {
                if (is_array($event)) {
                    $rawEvents[] = $event;
                }
            }
        }

        /** @var list<array<string, mixed>> $events */
        $events = [];
        foreach ($rawEvents as $event) {
            $events[] = [
                'status' => $this->pickString($event, [
                    'Status', 'status', 'EventStatus', 'eventStatus', 'StatusDescription', 'statusDescription',
                ]) ?? $status ?? 'unknown',
                'description' => $this->pickString($event, [
                    'Description', 'description', 'Details', 'details', 'StatusDescription', 'statusDescription', 'Event', 'event', 'Remark', 'remark',
                ]) ?? '',
                'occurred_at' => $this->pickString($event, [
                    'Date', 'date', 'OccurredAt', 'occurredAt', 'EventDate', 'eventDate', 'Time', 'time', 'CreatedAt', 'createdAt',
                ]),
                'location' => $this->pickString($event, [
                    'Location', 'location', 'Office', 'office', 'Branch', 'branch', 'City', 'city',
                ]),
            ];
        }

        if ($events === [] && $status !== null) {
            $events[] = [
                'status' => $status,
                'description' => $this->pickString($shipment, ['StatusDescription', 'statusDescription', 'Message', 'message']) ?? $status,
                'occurred_at' => $this->pickString($shipment, ['LastUpdate', 'lastUpdate', 'UpdatedAt', 'updatedAt']),
                'location' => $this->pickString($shipment, ['Location', 'location', 'Office', 'office']),
            ];
        }

        return [
            'tracking_number' => $resolvedBarcode,
            'status' => $status ?? 'unknown',
            'events' => $events,
            'raw' => $decoded,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeFlexible(string $body): array
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return [];
        }

        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            return $json;
        }

        if (preg_match('/\{[\s\S]*\}/', $trimmed, $matches) === 1) {
            $embedded = json_decode($matches[0], true);
            if (is_array($embedded)) {
                return $embedded;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function extractShipmentNode(array $decoded): array
    {
        foreach (['Data', 'data', 'Result', 'result', 'Shipment', 'shipment', 'ShipmentDetails', 'shipmentDetails'] as $key) {
            $node = $decoded[$key] ?? null;
            if (is_array($node)) {
                return $node;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @param  array<string, mixed>  $decoded
     * @return list<mixed>
     */
    private function eventSources(array $shipment, array $decoded): array
    {
        $keys = ['Events', 'events', 'History', 'history', 'TrackInfo', 'trackInfo', 'TrackingHistory', 'trackingHistory', 'Steps', 'steps'];
        $sources = [];

        foreach ($keys as $key) {
            if (isset($shipment[$key])) {
                $sources[] = $shipment[$key];
            }
        }

        if ($shipment !== $decoded) {
            foreach ($keys as $key) {
                if (isset($decoded[$key])) {
                    $sources[] = $decoded[$key];
                }
            }
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function pickString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
