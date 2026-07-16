<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\EgyptPost;

use Hekal\ShipBridge\Contracts\CarrierDriver;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\ExchangeShipmentRequest;
use Hekal\ShipBridge\DTOs\LabelResult;
use Hekal\ShipBridge\DTOs\ReturnShipmentRequest;
use Hekal\ShipBridge\DTOs\ShipmentResult;
use Hekal\ShipBridge\DTOs\TrackingEvent;
use Hekal\ShipBridge\DTOs\TrackingResult;
use Hekal\ShipBridge\EgyptPost\Support\PayloadFactory;
use Hekal\ShipBridge\Enums\LabelFormat;
use Hekal\ShipBridge\Enums\ShipmentStatus;
use Hekal\ShipBridge\Support\StatusNormalizer;

/**
 * Egypt Post driver for ShipBridge (Egypt).
 *
 * - track(): official egyptpost.gov.eg TrackTrace (always).
 * - create/label/return/exchange: partner REST gateway when mode=partner.
 * - track_only mode: create throws a helpful exception; label returns public track URL.
 */
final class EgyptPostDriver implements CarrierDriver
{
    public function __construct(
        private readonly EgyptPostClient $client,
        private readonly PayloadFactory $payloads,
        private readonly StatusNormalizer $normalizer,
    ) {}

    public function createShipment(CreateShipmentRequest $request): ShipmentResult
    {
        $this->client->ensurePartnerMode('create-shipment');
        $payload = $this->payloads->create($request);
        $data = $this->client->createShipment($payload);

        return $this->shipmentFromPayload($data);
    }

    public function track(string $trackingNumber): TrackingResult
    {
        $payload = $this->client->trackOfficial($trackingNumber);
        $status = $this->normalizer->normalize((string) ($payload['status'] ?? 'exception'));

        /** @var list<TrackingEvent> $events */
        $events = [];
        foreach ((array) ($payload['events'] ?? []) as $event) {
            if (! is_array($event)) {
                continue;
            }

            $events[] = new TrackingEvent(
                status: $this->normalizer->normalize((string) ($event['status'] ?? $status->value)),
                description: (string) ($event['description'] ?? ''),
                occurredAt: isset($event['occurred_at']) ? (string) $event['occurred_at'] : null,
                location: isset($event['location']) ? (string) $event['location'] : null,
            );
        }

        return new TrackingResult(
            trackingNumber: (string) ($payload['tracking_number'] ?? $trackingNumber),
            status: $status,
            events: $events,
            raw: is_array($payload['raw'] ?? null) ? $payload['raw'] : $payload,
        );
    }

    public function label(string $shipmentId, LabelFormat $format = LabelFormat::Pdf): LabelResult
    {
        if ($this->client->mode() === 'track_only') {
            return new LabelResult(
                shipmentId: $shipmentId,
                format: $format,
                contents: '',
                base64Encoded: false,
                url: $this->client->publicTrackUrl($shipmentId),
            );
        }

        $payload = $this->client->label($shipmentId, $format->value);

        return new LabelResult(
            shipmentId: $shipmentId,
            format: $format,
            contents: (string) ($payload['contents'] ?? ''),
            base64Encoded: (bool) ($payload['base64'] ?? true),
            url: isset($payload['url']) ? (string) $payload['url'] : null,
        );
    }

    public function createReturn(ReturnShipmentRequest $request): ShipmentResult
    {
        $this->client->ensurePartnerMode('return');
        $payload = $this->payloads->returnShipment($request);
        $data = $this->client->createReturn($request->originalShipmentId, $payload);

        return $this->shipmentFromPayload($data);
    }

    public function createExchange(ExchangeShipmentRequest $request): ShipmentResult
    {
        $this->client->ensurePartnerMode('exchange');
        $payload = $this->payloads->exchange($request);
        $data = $this->client->createExchange($request->originalShipmentId, $payload);

        return $this->shipmentFromPayload($data);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function shipmentFromPayload(array $payload): ShipmentResult
    {
        $statusRaw = (string) ($payload['status'] ?? ShipmentStatus::Created->value);

        return new ShipmentResult(
            id: (string) ($payload['id'] ?? ''),
            trackingNumber: (string) ($payload['tracking_number'] ?? ''),
            status: $this->normalizer->normalize($statusRaw),
            carrier: 'egyptpost',
            labelUrl: isset($payload['label_url']) ? (string) $payload['label_url'] : null,
            raw: $payload,
        );
    }
}
