<?php

declare(strict_types=1);

namespace Digitalize\Sms\Http;

use Digitalize\Sms\Exceptions\TransportException;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, #[\SensitiveParameter] array $headers, ?string $body, float $timeout, float $connectTimeout): Response
    {
        $responseHeaders = [];
        $lines = [];
        foreach ($headers as $name => $value) {
            if (preg_match("/[\r\n\0]/", $name . $value)) {
                throw new \InvalidArgumentException("HTTP header {$name} contains a line break or NUL byte.");
            }
            $lines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($connectTimeout * 1000),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($handle);

        if ($result === false) {
            $error = curl_error($handle);
            $errno = curl_errno($handle);
            throw new TransportException("Could not reach the Digitalize SMS API: {$error} (curl {$errno})", $errno);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new Response($status, $responseHeaders, (string) $result);
    }
}
