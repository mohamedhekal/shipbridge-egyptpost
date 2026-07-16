<?php

declare(strict_types=1);

namespace Hekal\ShipBridge\EgyptPost\Support;

use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\ExchangeShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;
use Hekal\ShipBridge\DTOs\ReturnShipmentRequest;
use Hekal\ShipBridge\Exceptions\ShipBridgeException;

/**
 * Maps ShipBridge DTOs → generic partner-gateway JSON payloads.
 *
 * Egypt Post has no public merchant create API; this shape targets a private
 * B2B middleware your ERP or integrator exposes. Override fields via metadata.
 */
final class PayloadFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function create(CreateShipmentRequest $request): array
    {
        return array_merge([
            'carrier' => 'egyptpost',
            'reference' => $request->reference,
            'origin' => $this->address($request->origin, requirePhone: false),
            'destination' => $this->address($request->destination),
            'parcels' => $this->parcels($request->parcels),
            'service' => (string) ($request->metadata['service'] ?? $this->config['default_service'] ?? 'standard'),
            'cod' => (float) ($request->metadata['cod'] ?? 0),
            'notes' => (string) ($request->metadata['notes'] ?? ''),
        ], $this->metadataExtras($request->metadata));
    }

    /**
     * @return array<string, mixed>
     */
    public function returnShipment(ReturnShipmentRequest $request): array
    {
        $pickup = $request->pickupFrom ?? $request->returnTo;

        return array_merge([
            'carrier' => 'egyptpost',
            'type' => 'return',
            'original_shipment_id' => $request->originalShipmentId,
            'reason' => $request->reason,
            'pickup_from' => $this->address($pickup, requirePhone: false),
            'return_to' => $this->address($request->returnTo),
            'parcels' => $this->parcels($request->parcels ?? [new Parcel(weightKg: 1.0)]),
        ], $this->metadataExtras($request->metadata));
    }

    /**
     * @return array<string, mixed>
     */
    public function exchange(ExchangeShipmentRequest $request): array
    {
        return array_merge([
            'carrier' => 'egyptpost',
            'type' => 'exchange',
            'original_shipment_id' => $request->originalShipmentId,
            'reason' => $request->reason,
            'origin' => $this->address($request->origin, requirePhone: false),
            'destination' => $this->address($request->destination),
            'outbound_parcels' => $this->parcels($request->outboundParcels),
            'inbound_parcels' => $this->parcels($request->inboundParcels ?? []),
        ], $this->metadataExtras($request->metadata));
    }

    /**
     * @return array<string, mixed>
     */
    private function address(Address $address, bool $requirePhone = true): array
    {
        $phone = $address->phone ?? '';
        if ($requirePhone && $phone === '') {
            throw ShipBridgeException::carrierFailed('Egypt Post partner gateway requires destination phone (Address::$phone).');
        }

        return [
            'name' => $address->name,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postalCode,
            'country_code' => $address->countryCode,
            'phone' => $phone,
            'email' => $address->email,
        ];
    }

    /**
     * @param  list<Parcel>  $parcels
     * @return list<array<string, mixed>>
     */
    private function parcels(array $parcels): array
    {
        $rows = [];
        foreach ($parcels as $parcel) {
            $rows[] = [
                'weight_kg' => $parcel->weightKg,
                'length_cm' => $parcel->lengthCm,
                'width_cm' => $parcel->widthCm,
                'height_cm' => $parcel->heightCm,
                'description' => $parcel->description,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadataExtras(array $metadata): array
    {
        $extras = [];
        foreach (['governorate', 'district', 'building', 'floor', 'apartment', 'national_id', 'content_type', 'declared_value'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $extras[$key] = $metadata[$key];
            }
        }

        if (isset($metadata['gateway']) && is_array($metadata['gateway'])) {
            $extras['gateway'] = $metadata['gateway'];
        }

        return $extras;
    }
}
