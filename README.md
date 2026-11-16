# Digitalize SMS — PHP

حزمة PHP للربط مع [API الرسائل القصيرة من ديجيتالايز لاب](https://digitalize.sd/developers/sms): أرسل رموز التحقق (OTP) ورسائل التنبيه لعملائك من قوالب معتمدة، بسطر واحد.

```php
$sms = new Digitalize\Sms\Client(getenv('DIGITALIZE_SMS_KEY'));

$sms->messages()->sendOtp(12, '0912345678', '482913');
```

- تعمل مع أي مشروع PHP 8.1 أو أحدث، ولا تحتاج أي مكتبة خارجية.
- دعم كامل لـ Laravel: إعداد من `.env`، و Facade، وقناة إشعارات (Notification channel).
- تعيد المحاولة تلقائياً عند انقطاع الاتصال أو ضغط الخادم، **بدون أن تُرسل الرسالة مرتين أبداً**.
- كل خطأ له Exception خاص به، فتتعامل مع "الرصيد لا يكفي" بشكل مختلف عن "الرقم غير صحيح".

## المحتويات

- [التثبيت](#التثبيت)
- [قبل أن تبدأ](#قبل-أن-تبدأ)
- [الاستخدام في أي مشروع PHP](#الاستخدام-في-أي-مشروع-php)
- [الاستخدام في Laravel](#الاستخدام-في-laravel)
- [منع تكرار الإرسال](#منع-تكرار-الإرسال)
- [التعامل مع الأخطاء](#التعامل-مع-الأخطاء)
- [سجل الرسائل والقوالب والرصيد](#سجل-الرسائل-والقوالب-والرصيد)
- [الإعدادات](#الإعدادات)

## التثبيت

```bash
composer require digitalize/sms
```

المتطلبات: PHP 8.1 أو أحدث، مع الإضافتين `curl` و `json` (موجودتان في كل استضافة تقريباً).

## قبل أن تبدأ

من [لوحة التحكم](https://digitalize.sd/dashboard):

1. **اشترِ باقة نقاط** من صفحة الباقات.
2. **سجّل اسم مرسل** إن كنت سترسل تنبيهات باسم جهتك. رسائل رمز التحقق لا تحتاج اسم مرسل.
3. **أنشئ قالباً** واكتب المتغيّرات بين قوسين، مثل `رمز التحقق الخاص بك هو {code}`. يصبح صالحاً بعد اعتماده، وستحتاج رقمه (`template_id`).
4. **أنشئ مفتاح API** من صفحة [مفاتيح API](https://digitalize.sd/sms/api-keys)، وضعه في متغيّرات البيئة:

```dotenv
DIGITALIZE_SMS_KEY=sms_...
```

> المفتاح يسحب من رصيدك. لا تضعه في الكود ولا في تطبيق جوال ولا في صفحة ويب. إن تسرّب، ألغِه من لوحة التحكم وأنشئ غيره.

## الاستخدام في أي مشروع PHP

```php
require 'vendor/autoload.php';

use Digitalize\Sms\Client;

$sms = new Client(getenv('DIGITALIZE_SMS_KEY'));
```

### إرسال رمز تحقق

```php
$code = (string) random_int(100000, 999999);

$message = $sms->messages()->sendOtp(
    templateId: 12,
    phone: $user->phone,   // أي صيغة: 0912345678 أو +249912345678 أو 00249912345678 أو ٠٩١٢٣٤٥٦٧٨
    code: $code,
);

echo $message->id;              // 5821
echo $message->status;          // submitted
echo $message->pointsCharged;   // 3.5
```

### إرسال تنبيه

```php
$sms->messages()->send(
    templateId: 15,                                          // "طلبك رقم {order} جاهز للاستلام"
    phone: '0912345678',
    variables: ['order' => 'A-1017'],
    idempotencyKey: 'order-A-1017-ready',                    // اختياري، انظر "منع تكرار الإرسال"
);
```

يجب أن ترسل قيمة لكل متغيّر في القالب، بنفس أسمائها، بلا زيادة ولا نقصان.

## الاستخدام في Laravel

الحزمة تُكتشف تلقائياً. أضف المفتاح في `.env`:

```dotenv
DIGITALIZE_SMS_KEY=sms_...
```

### عبر الـ Facade أو حقن الاعتماديات

```php
use Digitalize\Sms\Laravel\DigitalizeSms;

DigitalizeSms::messages()->sendOtp(12, $user->phone, $code);
```

```php
use Digitalize\Sms\Client;

public function __construct(private Client $sms) {}
```

### كقناة إشعارات

```php
use Digitalize\Sms\Laravel\DigitalizeSmsChannel;
use Digitalize\Sms\Laravel\DigitalizeSmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LoginCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $code) {}

    public function via($notifiable): array
    {
        return [DigitalizeSmsChannel::class];   // أو 'digitalize-sms'
    }

    public function toDigitalizeSms($notifiable): DigitalizeSmsMessage
    {
        return DigitalizeSmsMessage::otp(12, $this->code);

        // أو لقالب تنبيه:
        // return DigitalizeSmsMessage::template(15)->with('order', $this->order->number);
    }
}
```

وفي الموديل الذي يستقبل الإشعار:

```php
class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForDigitalizeSms($notification): ?string
    {
        return $this->phone;
    }
}
```

ثم:

```php
$user->notify(new LoginCode($code));
```

إذا أُعيدت محاولة الإشعار من الـ Queue، لا تُرسل الرسالة مرة ثانية: القناة تستخدم معرّف الإشعار كمفتاح لمنع التكرار.

لنشر ملف الإعدادات (اختياري):

```bash
php artisan vendor:publish --tag=digitalize-sms-config
```

## منع تكرار الإرسال

إذا انقطع الاتصال بعد إرسال الطلب، لا تعرف هل خرجت الرسالة أم لا. لذلك كل طلب إرسال يحمل "مفتاح منع تكرار" (Idempotency-Key)، والخادم لا يرسل الرسالة نفسها مرتين.

- **دون أن تفعل شيئاً:** الحزمة تولّد مفتاحاً لكل استدعاء، فإعاداتها التلقائية آمنة.
- **إن كان كودك نفسه قد يعيد الاستدعاء** (Job في Queue يُعاد، أو المستخدم يضغط الزر مرتين): مرّر مفتاحاً ثابتاً مرتبطاً بالحدث، مثل رقم الطلب:

```php
$message = $sms->messages()->send(15, $phone, ['order' => $order->number], "order-{$order->id}-ready");

if ($message->replayed) {
    // أُرسلت من قبل؛ هذه نفس الرسالة، ولم تُخصم نقاط مرة ثانية
}
```

استخدام نفس المفتاح لرسالة مختلفة (رقم أو قيم مختلفة) يرمي `ConflictException`.

## التعامل مع الأخطاء

كل الأخطاء ترث من `Digitalize\Sms\Exceptions\SmsException`. أخطاء الـ API ترث من `ApiException`، وفيها:

- `getErrorCode()`: رمز ثابت تعتمد عليه، مثل `insufficient_points`.
- `getMessage()`: نص عربي جاهز للعرض.
- `getHttpStatus()`: حالة HTTP للرد.

```php
use Digitalize\Sms\Exceptions\InsufficientPointsException;
use Digitalize\Sms\Exceptions\RateLimitException;
use Digitalize\Sms\Exceptions\SmsException;
use Digitalize\Sms\Exceptions\ValidationException;

try {
    $sms->messages()->sendOtp(12, $phone, $code);
} catch (ValidationException $e) {
    // invalid_phone, variables_missing, template_inactive…
    return back()->withErrors(['phone' => $e->getMessage()]);
} catch (InsufficientPointsException $e) {
    report($e);   // نبّه فريقك لشحن الرصيد
    throw $e;
} catch (RateLimitException $e) {
    return back()->withErrors(['phone' => 'حاول مرة أخرى بعد ' . $e->getRetryAfter() . ' ثانية']);
} catch (SmsException $e) {
    report($e);
    throw $e;
}
```

| Exception | HTTP | متى |
|---|---|---|
| `AuthenticationException` | 401 | المفتاح غير صحيح أو أُلغي |
| `InsufficientPointsException` | 402 | الرصيد لا يكفي. بعد الشحن يمكنك إعادة نفس المفتاح |
| `AccountSuspendedException` | 403 | خدمة الرسائل موقوفة للحساب |
| `NotFoundException` | 404 | القالب أو الرسالة غير موجودة في حسابك |
| `ConflictException` | 409 | مفتاح منع التكرار استُخدم لطلب مختلف |
| `ValidationException` | 400, 422 | رقم أو متغيّرات غير صحيحة، أو قالب غير مفعّل. `getErrors()` للأخطاء لكل حقل |
| `RateLimitException` | 429 | تجاوزت الحدود. `getRetryAfter()` بالثواني |
| `ProviderException` | 502 | رفض المزوّد الرسالة ولم تُخصم نقاط. `getSmsMessage()` للرسالة الفاشلة |
| `ServerException` | 5xx | خطأ في الخادم بعد استنفاد المحاولات |
| `TransportException` | — | لم يصل رد (اتصال أو مهلة) بعد استنفاد المحاولات |

القائمة الكاملة لرموز `getErrorCode()` في [التوثيق](https://digitalize.sd/developers/sms#errors).

### الإعادة التلقائية

تعيد الحزمة المحاولة مرتين (افتراضياً) عند: انقطاع الاتصال أو انتهاء المهلة، أو `429` بانتظار قصير، أو `500` و`503` و`504`. تنتظر ما يطلبه الخادم في `Retry-After` إن كان 10 ثوانٍ أو أقل، وإلا ترمي `RateLimitException` فوراً بدل أن تعطّل طلبك. لا تعيد المحاولة في الأخطاء النهائية مثل الرقم غير الصحيح أو الرصيد غير الكافي.

## سجل الرسائل والقوالب والرصيد

```php
// الرصيد
$account = $sms->account()->get();
$account->pointsBalance;       // 350.0
$account->messagesAvailable;   // 100

// رسالة واحدة
$message = $sms->messages()->get(5821);

// صفحة من السجل مع تصفية
$page = $sms->messages()->list([
    'status' => 'failed',
    'from' => new DateTimeImmutable('-7 days'),
    'per_page' => 50,
]);
foreach ($page as $message) { /* ... */ }
$page->total;  $page->hasMorePages();

// كل النتائج، والصفحات تُجلب تلقائياً
foreach ($sms->messages()->all(['template_id' => 12]) as $message) { /* ... */ }

// القوالب
foreach ($sms->templates()->list(['status' => 'active']) as $template) {
    echo $template->id, ' ', $template->name, ' ', implode(',', $template->variables);
}
```

### كائن الرسالة `Message`

| الخاصية | النوع | |
|---|---|---|
| `id` | `int` | رقم الرسالة عندنا |
| `templateId` | `int` | |
| `phone` | `string` | بالصيغة الدولية `249912345678` |
| `status` | `string` | `pending`، `submitted`، `delivered`، `undelivered`، `failed`، `unknown` |
| `parts` | `int` | عدد أجزاء الرسالة |
| `pointsCharged` | `float` | صفر إذا فشل الإرسال |
| `error` | `?string` | سبب الفشل |
| `idempotencyKey` | `?string` | |
| `createdAt`، `sentAt` | `?DateTimeImmutable` | |
| `replayed` | `bool` | `true` إذا أعاد الخادم رسالة سابقة بنفس المفتاح |

ودوال مساعدة: `isSent()`، `isDelivered()`، `isFailed()`.

## الإعدادات

```php
$sms = new Client(getenv('DIGITALIZE_SMS_KEY'), [
    'timeout' => 15,           // ثوانٍ للطلب كله
    'connect_timeout' => 5,    // ثوانٍ للاتصال
    'max_retries' => 2,        // 0 لإيقاف الإعادة التلقائية
    'max_retry_wait' => 10,    // أطول انتظار لـ Retry-After قبل رمي RateLimitException
]);
```

في Laravel تُضبط من `config/digitalize-sms.php` بعد نشره، أو من `.env`: `DIGITALIZE_SMS_KEY` و `DIGITALIZE_SMS_TIMEOUT`.

### الاختبار في مشروعك

لا ترسل رسائل حقيقية من اختباراتك. مرّر `transport` خاصاً بك يطبّق `Digitalize\Sms\Http\Transport`، أو في Laravel استبدل الـ Client بـ mock:

```php
$this->mock(\Digitalize\Sms\Client::class);
```

## سياسة الاستخدام

الخدمة لرموز التحقق والتنبيهات فقط. **الرسائل الإعلانية والتسويقية ممنوعة**، وكل قالب يُراجع قبل اعتماده.

## الدعم

- التوثيق الكامل: https://digitalize.sd/developers/sms
- مواصفة OpenAPI: https://digitalize.sd/developers/sms/openapi.json
- الدعم الفني: https://digitalize.sd/contact

## الترخيص

MIT. انظر [LICENSE](LICENSE).
