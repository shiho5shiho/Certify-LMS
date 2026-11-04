# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません
- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` — コーチの Google カレンダー連携（面談予約）に使用します。未設定でも面談機能は動作し、連携ボタンを押したときのみ失敗します。設定手順は「Google カレンダー連携の設定」を参照してください
- `GEMINI_*` / `AI_CHAT_*` — 受講生の AI 相談（Gemini）に使用します。`GEMINI_API_KEY` が未設定でも他の機能は動作し、AI 相談のみ「現在ご利用いただけません」と案内されます。設定手順は「AI 相談（Gemini）の設定」を参照してください

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。

## Google カレンダー連携の設定

コーチが面談設定画面から自分の Google カレンダーを連携すると、予定の入っている時間が受講生の予約画面の空き枠から外れ、予約・キャンセルした面談がコーチのカレンダーに自動で登録・削除されます。

1. [Google Cloud Console](https://console.cloud.google.com/) でプロジェクトを作成（既存のものでも可）し、「API とサービス」→「ライブラリ」で **Google Calendar API** を有効にする
2. 「Google Auth Platform」で次を設定する
   - 対象: ユーザーの種類は「外部」、公開ステータスは「テスト」のまま。「テストユーザー」に動作確認に使う Google アカウントを追加する
   - データアクセス: スコープ `https://www.googleapis.com/auth/calendar.readonly` と `https://www.googleapis.com/auth/calendar.events` を追加する
   - クライアント: 種類「ウェブ アプリケーション」で作成し、承認済みのリダイレクト URI に `http://localhost:8000/settings/google-calendar/callback` を登録する
3. 発行されたクライアント ID・シークレットを `.env` に設定し、`sail artisan config:clear` を実行する
   ```
   GOOGLE_CLIENT_ID=  
   GOOGLE_CLIENT_SECRET=  
   GOOGLE_REDIRECT_URI="${APP_URL}/settings/google-calendar/callback"
   ```
4. コーチ（`coach@certify-lms.test`）でログインし、面談設定の「Googleカレンダーと連携する」から、手順 2 で追加したテストユーザーの Google アカウントで連携する

**初期データ**

| アカウント | 連携状態 | 用途 |
| --- | --- | --- |
| コーチ太郎（`coach@certify-lms.test`） | 未連携 | 手順 4 で各自の Google アカウントと連携し、実際の動作を確認する |
| コーチ花子（`coach2@certify-lms.test`） | 連携中（ダミー） | 連携状態の表示・解除の確認用。Google には通らないため、受講生の予約画面で空き枠が取得された時点で未連携に戻る |

**確認方法**（コーチ太郎を連携した状態で）

1. 連携した Google カレンダーに、コーチ太郎の面談可能時間枠（平日 19:00〜21:00）と重なる予定を入れる
2. 受講生花子（`student@certify-lms.test`）で面談予約画面を開き、その日を選ぶ → 予定と重なる時間が空き枠に表示されない（Google 側で「予定なし」にした予定は表示される）
3. 空いている枠を予約する → コーチ太郎の Google カレンダーに「【Certify LMS】面談：…」の予定が登録される
4. その面談をキャンセルする → Google カレンダーの予定が削除される

**補足**

- 公開ステータスが「テスト」のアプリでは、連携から 7 日で Google 側の認証情報が無効になり、LMS 上も未連携に戻ります。その場合はもう一度連携してください
- 認証情報（トークン）は DB に平文で保存しています。本番運用では暗号化して保存してください（例: Model の `encrypted` キャスト）

## AI 相談（Gemini）の設定

学習中の受講生が、画面右下のウィジェットまたは AI 相談画面から Gemini に学習相談できます。教材画面から相談すると閲覧中の教材が、それ以外では受講中の資格が、AI への入力に自動で添えられます。

1. [Google AI Studio](https://aistudio.google.com/apikey) に Google アカウントでログインし、「API キーを作成」を押す
2. 「プロジェクトを作成」で本アプリ用のプロジェクトを作成し、そのプロジェクトで API キーを作成する（請求先アカウントは設定せず、無料枠のまま使用する）
3. 発行された API キーを `.env` に設定し、`sail artisan config:clear` を実行する
   ```
   GEMINI_API_KEY=
   GEMINI_MODEL=gemini-3.6-flash
   GEMINI_TIMEOUT=60
   AI_CHAT_ENABLED=true
   AI_CHAT_DAILY_LIMIT=30
   AI_CHAT_HISTORY_LIMIT=10
   AI_CHAT_AUTO_TITLE=true
   ```
4. 受講生（`student@certify-lms.test`）でログインし、画面右下の AI ボタンまたはサイドバーの「AI 相談」から相談する

| 環境変数 | 既定値 | 内容 |
| --- | --- | --- |
| `GEMINI_API_KEY` | （空） | Gemini の API キー。未設定の場合、AI 相談は「現在ご利用いただけません」と案内され、Gemini へのリクエストは行われない |
| `GEMINI_MODEL` | `gemini-3.6-flash` | 使用するモデル |
| `GEMINI_TIMEOUT` | `60` | Gemini の応答を待つ秒数。超えた場合は AI 応答の失敗として扱う |
| `AI_CHAT_ENABLED` | `true` | AI 相談機能全体の ON / OFF。`false` にすると、AI 相談の画面・サイドバー・ウィジェットがすべて利用できなくなる |
| `AI_CHAT_DAILY_LIMIT` | `30` | 受講生 1 人あたりの 1 日の送信上限（日本時間 0:00 にリセット。AI 応答に失敗した分も数える） |
| `AI_CHAT_HISTORY_LIMIT` | `10` | AI に引き継ぐ直近の会話履歴の件数（エラーになったやり取りは除く） |
| `AI_CHAT_AUTO_TITLE` | `true` | 会話タイトルの自動付け直し（最初の AI 応答の成功時に 1 回）の ON / OFF |

**初期データ**

| アカウント | 会話 | 用途 |
| --- | --- | --- |
| 受講生花子（`student@certify-lms.test`） | 教材（Section）から始めた会話（今日） | 教材の文脈バッジの表示、同じ教材から開いたときに既存の会話が続きとして使われることの確認用 |
| 〃 | AI 応答がエラーになった会話（昨日） | エラー表示と、同じ内容を送り直しての再質問の確認用 |
| 〃 | 教材によらない会話（全般相談、3 日前・12 日前） | 過去の相談の再開と、履歴の「今日 / 過去 7 日 / 過去 30 日」の表示の確認用 |

**確認方法**

1. 受講生花子で教材（Section）画面を開き、右下の AI ボタンから質問する → 教材名のバッジが表示され、教材の内容を踏まえた回答が返る
2. 初期データの教材の会話と同じ教材（「1.1 2 進数の表現」）から質問する → 新しい会話は作られず、その会話の続きとして保存される
3. ダッシュボードでウィジェットから質問し、面談予約などの別の画面へ移動する → ウィジェットの開閉状態と表示中の会話が引き継がれる（教材画面から別の画面へ移動した場合は、教材の会話から切り替わる）
4. ウィジェットの「フル画面で開く」を押す → AI 相談画面に移り、左の履歴から過去の会話を開ける
5. 初期データのエラーの会話で、最後の質問と同じ内容を送り直す → 回答が返る
6. 会話のタイトルを編集・削除する → 履歴に反映される
7. `.env` で `GEMINI_API_KEY` を空にして `sail artisan config:clear` を実行し、AI 相談画面を開く → 「AI 相談は現在ご利用いただけません。」が表示され、送信してもチャット内にエラーが表示される
8. `.env` で `AI_CHAT_ENABLED=false` にして `sail artisan config:clear` を実行する → サイドバーの「AI 相談」とウィジェットが消え、`/ai-chat` を開くと 404 になる

**補足**

- 無料枠には 1 分あたり・1 日あたりのリクエスト上限があります。上限は AI Studio の「レート制限」で確認できます。上限に達すると、チャット内に「Gemini API のリクエスト制限に達しました」と表示されます
- 無料枠では、入力した内容が Google のサービス改善に利用される場合があります。動作確認では個人情報を入力しないでください
- AI 応答のモデル名・トークン数・応答時間は運用確認用に DB に記録しており、受講生の画面には表示されません
- API キーは `.env` にのみ記載し、リポジトリにコミットしないでください
