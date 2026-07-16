# ShipBridge · Egypt Post


[![CI](https://github.com/mohamedhekal/shipbridge-egyptpost/actions/workflows/tests.yml/badge.svg)](https://github.com/mohamedhekal/shipbridge-egyptpost/actions)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/mohamedhekal/shipbridge-egyptpost.svg)](https://packagist.org/packages/mohamedhekal/shipbridge-egyptpost)

**Egypt Post** shipping driver for [ShipBridge](https://github.com/mohamedhekal/shipbridge) · Region: **Egypt** / **مصر**

---

## بالعربي — في ٣ خطوات

### ١) ثبّت الحزمتين
```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-egyptpost
```

### ٢) حط مفاتيح Egypt Post في `.env`
```env
SHIPBRIDGE_DRIVER=egyptpost
EGYPTPOST_API_KEY=your-key-here
EGYPTPOST_BASE_URL=https://api.egyptpost.org/v1
```
> التفاصيل الكاملة للمفاتيح في `config/egyptpost.php`.

### ٣) ابعت شحنة
```php
use Hekal\ShipBridge\Facades\ShipBridge;
use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;

$shipment = ShipBridge::driver('egyptpost')->createShipment(new CreateShipmentRequest(
    origin: new Address('المخزن', 'شارع ١', 'القاهرة', 'EG'),
    destination: new Address('العميل', 'شارع النيل', 'الجيزة', 'EG', phone: '01000000000'),
    parcels: [new Parcel(weightKg: 1.2)],
    reference: 'ORD-42',
));

echo $shipment->trackingNumber;
```

تتبع / ليبل / مرتجع:
```php
ShipBridge::driver('egyptpost')->track($shipment->trackingNumber);
ShipBridge::driver('egyptpost')->label($shipment->id);
```

---

## English — Quick start

```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-egyptpost
```

```env
SHIPBRIDGE_DRIVER=egyptpost
EGYPTPOST_API_KEY=your-key-here
EGYPTPOST_BASE_URL=https://api.egyptpost.org/v1
```

```php
ShipBridge::driver('egyptpost')->createShipment(...);
ShipBridge::driver('egyptpost')->track('TRACKING');
ShipBridge::driver('egyptpost')->label('SHIPMENT_ID');
```

## How it fits

```
Your Laravel app
      │
      ▼
 ShipBridge  (one API for all carriers)
      │
      ▼
 shipbridge-egyptpost  ← this package (Egypt Post)
```

## Testing

```bash
composer install && composer test
```

## License

MIT © Mohamed Hekal
