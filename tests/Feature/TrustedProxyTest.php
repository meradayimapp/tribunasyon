<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_cloudflare_proxy_forwards_the_real_visitor_ip(): void
    {
        Route::get('/_test/client-ip', fn (Request $request) => $request->ip());

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '172.69.250.186',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('/_test/client-ip');

        $response->assertOk()->assertSeeText('203.0.113.10');
    }

    public function test_untrusted_origin_cannot_spoof_the_visitor_ip(): void
    {
        Route::get('/_test/client-ip', fn (Request $request) => $request->ip());

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '198.51.100.20',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ])
            ->get('/_test/client-ip');

        $response->assertOk()->assertSeeText('198.51.100.20');
    }
}
