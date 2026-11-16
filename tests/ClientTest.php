<?php

declare(strict_types=1);

namespace Digitalize\Sms\Tests;

use Digitalize\Sms\Client;
use Digitalize\Sms\Exceptions\AccountSuspendedException;
use Digitalize\Sms\Exceptions\ApiException;
use Digitalize\Sms\Exceptions\AuthenticationException;
use Digitalize\Sms\Exceptions\ConflictException;
use Digitalize\Sms\Exceptions\InsufficientPointsException;
use Digitalize\Sms\Exceptions\NotFoundException;
use Digitalize\Sms\Exceptions\ProviderException;
use Digitalize\Sms\Exceptions\RateLimitException;
use Digitalize\Sms\Exceptions\ServerException;
use Digitalize\Sms\Exceptions\SmsException;
use Digitalize\Sms\Exceptions\TransportException;
use Digitalize\Sms\Exceptions\ValidationException;
use Digitalize\Sms\Models\Message;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const KEY = 'sms_0123456789abcdef0123456789abcdef0123456789abcdef';

    private FakeTransport $http;
    /** @var list<float> */
    private array $slept = [];

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->slept = [];
    }

    private function client(array $options = []): Client
    {
        return new Client(self::KEY, $options + [
            'transport' => $this->http,
            'sleep' => function (float $seconds): void { $this->slept[] = $seconds; },
        ]);
    }

    private static function message(array $overrides = []): array
    {
        return $overrides + [
            'id' => 5821, 'template_id' => 12, 'phone' => '249912345678', 'status' => 'submitted', 'parts' => 1,
            'points_charged' => 3.5, 'error' => null, 'idempotency_key' => 'k', 'created_at' => '2026-11-16T08:24:07+00:00',
            'sent_at' => '2026-11-16T08:24:08+00:00',
        ];
    }

    private static function error(string $code, string $message = 'خطأ'): array
    {
        return ['success' => false, 'error' => ['code' => $code, 'message' => $message]];
    }

    public function test_send_posts_json_with_the_key_and_a_generated_idempotency_key(): void
    {
        $this->http->push(201, ['success' => true, 'data' => self::message()]);

        $message = $this->client()->messages()->send(12, '0912345678', ['code' => '482913']);

        $request = $this->http->last();
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://digitalize.sd/api/v1/sms/messages', $request['url']);
        $this->assertSame('Bearer ' . self::KEY, $request['headers']['Authorization']);
        $this->assertSame('application/json', $request['headers']['Content-Type']);
        $this->assertStringStartsWith('digitalize-sms-php/', $request['headers']['User-Agent']);
        $this->assertMatchesRegularExpression('/^sdk_[0-9a-f]{32}$/', $request['headers']['Idempotency-Key']);
        $this->assertSame(['template_id' => 12, 'phone' => '0912345678', 'variables' => ['code' => '482913']], json_decode($request['body'], true));

        $this->assertSame(5821, $message->id);
        $this->assertSame(3.5, $message->pointsCharged);
        $this->assertTrue($message->isSent());
        $this->assertFalse($message->replayed);
        $this->assertSame('2026-11-16T08:24:08+00:00', $message->sentAt?->format(DATE_ATOM));
    }

    public function test_arabic_values_are_sent_as_utf8_not_escaped(): void
    {
        $this->http->push(201, ['success' => true, 'data' => self::message()]);

        $this->client()->messages()->send(15, '0912345678', ['name' => 'أحمد']);

        $this->assertStringContainsString('أحمد', $this->http->last()['body']);
    }

    public function test_a_given_idempotency_key_is_used_and_a_replay_is_reported(): void
    {
        $this->http->push(200, ['success' => true, 'data' => self::message()], ['Idempotent-Replayed' => 'true']);

        $message = $this->client()->messages()->sendOtp(12, '0912345678', '482913', 'order-1001');

        $this->assertSame('order-1001', $this->http->last()['headers']['Idempotency-Key']);
        $this->assertSame(['code' => '482913'], json_decode($this->http->last()['body'], true)['variables']);
        $this->assertTrue($message->replayed);
    }

    public static function errors(): array
    {
        return [
            [401, 'invalid_api_key', AuthenticationException::class],
            [402, 'insufficient_points', InsufficientPointsException::class],
            [403, 'account_suspended', AccountSuspendedException::class],
            [404, 'template_not_found', NotFoundException::class],
            [409, 'idempotency_key_reused', ConflictException::class],
            [400, 'invalid_idempotency_key', ValidationException::class],
            [422, 'invalid_phone', ValidationException::class],
            [502, 'provider_error', ProviderException::class],
        ];
    }

    #[DataProvider('errors')]
    public function test_api_errors_become_typed_exceptions(int $status, string $code, string $class): void
    {
        $this->http->push($status, self::error($code, 'رسالة'));

        try {
            $this->client()->messages()->send(12, '0912345678');
            $this->fail('No exception thrown');
        } catch (ApiException $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertInstanceOf(SmsException::class, $e);
            $this->assertSame($code, $e->getErrorCode());
            $this->assertSame($status, $e->getHttpStatus());
            $this->assertSame('رسالة', $e->getMessage());
        }
        $this->assertCount(1, $this->http->requests, 'final errors are not retried');
    }

    public function test_validation_errors_expose_the_fields(): void
    {
        $this->http->push(422, ['success' => false, 'error' => ['code' => 'validation_failed', 'message' => 'x', 'details' => ['phone' => ['مطلوب']]]]);

        try {
            $this->client()->messages()->send(12, '');
        } catch (ValidationException $e) {
            $this->assertSame(['phone' => ['مطلوب']], $e->getErrors());
        }
    }

    public function test_a_provider_failure_carries_the_failed_message(): void
    {
        $this->http->push(502, self::error('provider_error') + ['data' => self::message(['status' => 'failed', 'points_charged' => 0])]);

        try {
            $this->client()->messages()->send(12, '0912345678');
        } catch (ProviderException $e) {
            $this->assertSame(Message::STATUS_FAILED, $e->getSmsMessage()?->status);
            $this->assertTrue($e->getSmsMessage()->isFailed());
        }
    }

    public function test_server_errors_are_retried_with_the_same_idempotency_key(): void
    {
        $this->http->push(503, self::error('http_error'))->push(500, self::error('server_error'))
            ->push(201, ['success' => true, 'data' => self::message()]);

        $this->client()->messages()->send(12, '0912345678');

        $this->assertCount(3, $this->http->requests);
        $keys = array_unique(array_map(fn ($r) => $r['headers']['Idempotency-Key'], $this->http->requests));
        $this->assertCount(1, $keys);
        $this->assertCount(2, $this->slept);
    }

    public function test_timeouts_are_retried_then_surface_as_transport_exceptions(): void
    {
        $this->http->fail()->push(201, ['success' => true, 'data' => self::message()]);
        $this->client()->messages()->send(12, '0912345678');
        $this->assertCount(2, $this->http->requests);

        $this->http->fail()->fail()->fail();
        $this->expectException(TransportException::class);
        $this->client()->account()->get();
    }

    public function test_retries_stop_after_max_retries(): void
    {
        $this->http->push(503, self::error('http_error'))->push(503, self::error('http_error'));

        $this->expectException(ServerException::class);
        try {
            $this->client(['max_retries' => 1])->account()->get();
        } finally {
            $this->assertCount(2, $this->http->requests);
        }
    }

    public function test_rate_limits_wait_for_retry_after_when_short(): void
    {
        $this->http->push(429, self::error('rate_limited'), ['Retry-After' => '3'])
            ->push(200, ['success' => true, 'data' => ['points_balance' => 35, 'points_per_message' => 3.5, 'messages_available' => 10, 'sms_suspended' => false]]);

        $account = $this->client()->account()->get();

        $this->assertSame([3.0], $this->slept);
        $this->assertSame(10, $account->messagesAvailable);
        $this->assertSame(3.5, $account->pointsPerMessage);
    }

    public function test_rate_limits_with_a_long_wait_are_thrown_with_retry_after(): void
    {
        $this->http->push(429, self::error('rate_limited'), ['Retry-After' => '1800']);

        try {
            $this->client()->messages()->send(12, '0912345678');
            $this->fail('No exception thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(1800, $e->getRetryAfter());
            $this->assertSame([], $this->slept);
        }
    }

    public function test_a_request_still_in_progress_is_retried(): void
    {
        $this->http->push(409, self::error('idempotency_request_in_progress'))->push(200, ['success' => true, 'data' => self::message()]);

        $this->assertSame(5821, $this->client()->messages()->send(12, '0912345678', [], 'order-1001')->id);
    }

    public function test_a_non_json_error_page_is_reported_clearly(): void
    {
        $this->http->pushRaw(404, '<html>Not Found</html>');

        try {
            $this->client()->account()->get();
        } catch (NotFoundException $e) {
            $this->assertSame('invalid_response', $e->getErrorCode());
            $this->assertStringContainsString('HTTP 404', $e->getMessage());
        }
    }

    public function test_listing_messages_formats_filters_and_reads_the_page(): void
    {
        $this->http->push(200, ['success' => true, 'data' => [self::message()], 'meta' => ['current_page' => 1, 'per_page' => 25, 'total' => 1, 'last_page' => 1]]);

        $page = $this->client()->messages()->list([
            'status' => 'failed', 'from' => new \DateTimeImmutable('2026-11-01T00:00:00+00:00'), 'phone' => null,
        ]);

        $this->assertSame('https://digitalize.sd/api/v1/sms/messages?status=failed&from=2026-11-01T00%3A00%3A00%2B00%3A00', $this->http->last()['url']);
        $this->assertCount(1, $page);
        $this->assertFalse($page->hasMorePages());
        $this->assertSame(5821, iterator_to_array($page)[0]->id);
    }

    public function test_all_walks_every_page(): void
    {
        $meta = fn (int $page) => ['current_page' => $page, 'per_page' => 100, 'total' => 3, 'last_page' => 2];
        $this->http->push(200, ['success' => true, 'data' => [self::message(['id' => 1]), self::message(['id' => 2])], 'meta' => $meta(1)])
            ->push(200, ['success' => true, 'data' => [self::message(['id' => 3])], 'meta' => $meta(2)]);

        $ids = array_map(fn (Message $m) => $m->id, iterator_to_array($this->client()->messages()->all(['status' => 'submitted']), false));

        $this->assertSame([1, 2, 3], $ids);
        $this->assertStringContainsString('page=2', $this->http->last()['url']);
        $this->assertStringContainsString('per_page=100', $this->http->last()['url']);
    }

    public function test_templates(): void
    {
        $template = ['id' => 12, 'name' => 'رمز الدخول', 'type' => 'otp', 'status' => 'active', 'usable' => true,
            'body' => 'رمز التحقق الخاص بك هو {code}', 'variables' => ['code'], 'sender' => 'Authy', 'approved_at' => null, 'created_at' => null];
        $this->http->push(200, ['success' => true, 'data' => [$template]])->push(200, ['success' => true, 'data' => $template]);

        $list = $this->client()->templates()->list(['type' => 'otp']);
        $this->assertStringEndsWith('/templates?type=otp', $this->http->last()['url']);
        $this->assertTrue($list[0]->isOtp());
        $this->assertSame(['code'], $list[0]->variables);

        $this->assertSame('رمز الدخول', $this->client()->templates()->get(12)->name);
        $this->assertStringEndsWith('/templates/12', $this->http->last()['url']);
    }

    public function test_a_custom_base_url_is_used(): void
    {
        $this->http->push(200, ['success' => true, 'data' => self::message()]);

        $this->client(['base_url' => 'https://staging.digitalize.sd/api/v1/sms/'])->messages()->get(7);

        $this->assertSame('https://staging.digitalize.sd/api/v1/sms/messages/7', $this->http->last()['url']);
    }

    public function test_a_wrong_key_is_caught_before_any_request(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('pk_live_oops');
    }

    public function test_an_idempotency_key_outside_the_api_format_is_refused_before_sending(): void
    {
        foreach (["order-1\r\nX-Injected: 1", "short", str_repeat("a", 101), "has space 123"] as $bad) {
            try {
                $this->client()->messages()->send(12, "0912345678", [], $bad);
                $this->fail("Accepted " . json_encode($bad));
            } catch (\InvalidArgumentException) {
            }
        }
        $this->assertSame([], $this->http->requests);
    }

    public function test_a_key_with_line_breaks_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client("sms_abc\r\nX-Injected: 1");
    }

    public function test_curl_transport_refuses_header_values_with_line_breaks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new \Digitalize\Sms\Http\CurlTransport())->send("GET", "https://127.0.0.1:1/", ["X-Test" => "a\nb"], null, 1, 1);
    }

    public function test_the_key_does_not_appear_in_stack_traces(): void
    {
        $transport = new class implements \Digitalize\Sms\Http\Transport {
            public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, float $timeout, float $connectTimeout): \Digitalize\Sms\Http\Response
            {
                throw new TransportException("down");
            }
        };

        try {
            (new Client(self::KEY, ["transport" => $transport, "max_retries" => 0]))->account()->get();
        } catch (TransportException $e) {
            $this->assertStringNotContainsString(self::KEY, print_r($e->getTrace(), true));
        }
    }

    public function test_the_key_is_hidden_from_dumps(): void
    {
        $this->assertStringNotContainsString(self::KEY, print_r($this->client(), true));
    }
}
