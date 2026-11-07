<?php

declare(strict_types=1);

namespace Tests;

use App\Services\GoogleCalendarService;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * テスト中に外部 API へ実際に通信しないよう、外部 API ごとに通信を止める。
     * API ごとに通信の仕組みが違うため、止め方もそれぞれに合わせている。
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Gemini(Laravel の Http クライアント): Http::fake() で用意していない通信をエラーにする
        Http::preventStrayRequests();

        // Google カレンダー(Google のライブラリ): 応答を 1 件も用意していない通信部品を渡し、
        // モックしていない通信が起きたらエラー(Mock queue is empty)にする
        $this->app->bind(GoogleCalendarService::class, fn () => new GoogleCalendarService(
            new HttpClient(['handler' => HandlerStack::create(new MockHandler)]),
        ));

        // Stripe(Stripe SDK): シークレットキーを空にし、決済画面の作成を通信の前に失敗させる
        config(['services.stripe.secret' => null]);
    }
}
