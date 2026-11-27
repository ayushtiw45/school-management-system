<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/** Sends one text message through the configured SMS or WhatsApp provider. */
class MessageGateway
{
    /** @return array{status: string, response: ?string} status is sent, failed or logged */
    public function send(string $channel, string $to, string $message): array
    {
        $cfg = config("services.$channel");

        if (($cfg['driver'] ?? 'log') !== 'http' || empty($cfg['url'])) {
            return ['status' => 'logged', 'response' => 'No gateway configured. Message saved, not sent.'];
        }

        $replace = fn (string $tpl, bool $json) => strtr($tpl, [
            '{to}' => $to,
            '{message}' => $json ? substr(json_encode($message, JSON_UNESCAPED_UNICODE), 1, -1) : rawurlencode($message),
        ]);

        try {
            $req = Http::timeout(15);
            if (! empty($cfg['token'])) {
                $req = $req->withToken($cfg['token']);
            }

            $res = strtolower($cfg['method']) === 'post'
                ? $req->withBody($replace($cfg['body'] ?: '{"to":"{to}","message":"{message}"}', true), 'application/json')
                    ->post(strtr($cfg['url'], ['{to}' => $to, '{message}' => rawurlencode($message)]))
                : $req->get($replace($cfg['url'], false));

            return [
                'status' => $res->successful() ? 'sent' : 'failed',
                'response' => 'HTTP '.$res->status().': '.mb_substr($res->body(), 0, 500),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'response' => mb_substr($e->getMessage(), 0, 500)];
        }
    }

    /** 10-digit Indian mobile → 91XXXXXXXXXX. Returns null for invalid numbers. */
    public static function normalize(?string $phone, string $channel = 'sms'): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $cc = config("services.$channel.country_code", '91');

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return $cc.$digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, $cc)) {
            return $digits;
        }

        return null;
    }
}
