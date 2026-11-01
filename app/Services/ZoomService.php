<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

class ZoomService
{
    protected function getAccessToken()
    {
        $response = Http::withBasicAuth(config('services.zoom.client_id'), config('services.zoom.client_secret'))
            ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.config('services.zoom.account_id'));

        if ($response->failed()) {
            throw new Exception('Zoom Token Error: '.$response->body());
        }

        return $response->json('access_token');
    }

    public function createMeeting($topic, $startTime, $duration = 60, $hostEmail = 'me')
    {
        $token = $this->getAccessToken();
        $formattedTime = Carbon::parse($startTime)->format('Y-m-d\TH:i:s');

        $response = Http::withToken($token)
            ->post("https://api.zoom.us/v2/users/{$hostEmail}/meetings", [
                'topic' => $topic,
                'type' => 2,
                'start_time' => $formattedTime,
                'duration' => (int) $duration,
                'settings' => [
                    'join_before_host' => true,
                    'waiting_room' => false,
                ],
            ]);

        if ($response->failed()) {
            throw new Exception('Zoom API Error: '.$response->body());
        }

        return $response->json();
    }

    public function generateSignature($meetingNumber, $role = 0)
    {
        $sdkKey = config('services.zoom.client_id');
        $sdkSecret = config('services.zoom.client_secret');

        $iat = time() - 30;
        $exp = $iat + 60 * 60 * 2;

        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'sdkKey' => $sdkKey,
            'mn' => $meetingNumber,
            'role' => $role,
            'iat' => $iat,
            'exp' => $exp,
            'appKey' => $sdkKey,
            'tokenExp' => $exp,
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader.'.'.$base64UrlPayload, $sdkSecret, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader.'.'.$base64UrlPayload.'.'.$base64UrlSignature;
    }
}
