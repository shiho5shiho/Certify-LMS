{{--
    410(招待リンクが無効 / 期限切れ / 使用済み)エラーページ。共通テンプレート errors._layout に表示内容を渡す。
    410 を返すのは招待の例外(InvalidInvitationTokenException)のみ。
    例外のメッセージが渡されていればそれを、なければ既定の説明文を表示する。静的表示のみ。
--}}
@php
    $customMessage = isset($exception) ? trim($exception->getMessage()) : '';
@endphp

@include('errors._layout', [
    'code' => '410',
    'heading' => '招待リンクが無効または期限切れです',
    'description' => $customMessage !== '' ? $customMessage : '招待リンクが無効または期限切れです。管理者へお問い合わせください。',
])
