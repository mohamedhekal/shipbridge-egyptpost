# دليل البريد المصري — شرح بسيط ومفصّل

## إيه الحقيقة؟

**البريد المصري (Egypt Post) ما عندوش API عام للتجار لإنشاء الشحنات من Laravel مباشرة.**

قناة التاجر الرسمية هي تطبيق **وصّلها (Wassalha)**. اللي متاح للعامة هو **تتبع الشحنة** على موقع الحكومة:

```
https://egyptpost.gov.eg/ar-eg/TrackTrace
```

الحزمة دي بتدعم حالتين:

| الوضع | إيه اللي يشتغل؟ |
|---|---|
| `track_only` | تتبع فقط عبر TrackTrace الرسمي |
| `partner` | إنشاء / ليبل / مرتجع عبر **بوابة شريك B2B** عندك (middleware خاص) |

---

## التثبيت

```bash
composer require mohamedhekal/shipbridge mohamedhekal/shipbridge-egyptpost
```

---

## تتبع فقط (بدون API إنشاء)

```env
SHIPBRIDGE_DRIVER=egyptpost
EGYPTPOST_MODE=track_only
```

```php
use Hekal\ShipBridge\Facades\ShipBridge;

$tracking = ShipBridge::driver('egyptpost')->track('EP123456789');

// الليبل = رابط التتبع العام
$label = ShipBridge::driver('egyptpost')->label('EP123456789');
echo $label->url; // https://egyptpost.gov.eg/ar-eg/TrackTrace?Barcode=...
```

لو حاولت `createShipment()` في `track_only` هتاخد رسالة واضحة إنك محتاج **وصّلها** أو **بوابة شريك**.

---

## وضع الشريك (partner gateway)

لو عندك عقد B2B مع مزوّد وسيط (ERP / integrator) بيقدّم REST API:

```env
SHIPBRIDGE_DRIVER=egyptpost
EGYPTPOST_MODE=partner
EGYPTPOST_API_KEY=your-partner-key
EGYPTPOST_BASE_URL=https://your-gateway.example/v1
```

```php
use Hekal\ShipBridge\DTOs\Address;
use Hekal\ShipBridge\DTOs\CreateShipmentRequest;
use Hekal\ShipBridge\DTOs\Parcel;

$shipment = ShipBridge::driver('egyptpost')->createShipment(new CreateShipmentRequest(
    origin: new Address('المخزن', 'شارع الصناعة', 'القاهرة', 'EG'),
    destination: new Address('العميل', '١٢ شارع النيل', 'الجيزة', 'EG', phone: '01000000000'),
    parcels: [new Parcel(weightKg: 1.2)],
    reference: 'ORD-42',
    metadata: [
        'cod' => 250,
        'governorate' => 'الجيزة',
        'district' => 'الدقي',
    ],
));
```

**ملاحظة:** شكل الـ JSON اللي بيتبعت لـ `{base_url}/shipments` عام ومتوافق مع scaffolds ShipBridge — عدّله عند بوابتك لو محتاج حقول إضافية عبر `metadata.gateway`.

---

## التتبع (دايمًا رسمي)

`track()` **دايمًا** بيكلم:

```
EGYPTPOST_TRACK_URL
  → https://egyptpost.gov.eg/ar-eg/TrackTrace/GetShipmentDetails?Barcode=...
```

مش بوابة الشريك. الحزمة بتفك الـ JSON/HTML بمرونة وبتطبّق `status_map`.

---

## متى أستخدم إيه؟

| السيناريو | الحل |
|---|---|
| عندك باركود من وصّلها أو فرع البريد | `track_only` + `track()` |
| عندك middleware B2B موقّع | `partner` + `EGYPTPOST_BASE_URL` |
| عايز تنشئ من Laravel بدون بوابة | استخدم وصّلها يدويًا أو اطلب عقد B2B |

---

## Troubleshooting

| المشكلة | الحل |
|---|---|
| `create` بيقول Wassalha | طبيعي — مفيش API عام للإنشاء |
| TrackTrace فاضي | تأكد من الباركود؛ الموقع أحيانًا بيرجع HTML |
| `destination phone` | لازم `Address::$phone` في وضع partner |
| Cloudflare على السيرفر | حط `EGYPTPOST_USER_AGENT` أو استخدم proxy داخلي |
