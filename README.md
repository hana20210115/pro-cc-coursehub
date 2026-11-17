# CourseHub

オンライン学習プラットフォーム。コーチがコースを作成し、受講生が学習・進捗管理できるシステム。

## 環境構築

### 前提条件

- Docker Desktop がインストールされていること

### セットアップ手順

1. リポジトリをクローン

```bash
git clone https://github.com/coachtech-material/pro-cc-coursehub.git
cd pro-cc-coursehub
```

2. `.env` ファイルを作成

```bash
cp .env.example .env
```

3. `.env` の DB 設定を変更

アプリは Docker コンテナ内で動作するため、`DB_HOST` には `127.0.0.1` ではなく MySQL のサービス名 `mysql` を指定します。

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

4. Composer パッケージのインストール

ホストに PHP / Composer がなくても、Docker 経由でインストールできます。`vendor/` がないと Sail のコンテナをビルドできないため、Docker 起動前に実行してください。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

5. Docker を起動

```bash
./vendor/bin/sail up -d
```

初回はイメージのビルドに数分〜10分程度かかります。

6. アプリケーションキーの生成

```bash
./vendor/bin/sail artisan key:generate
```

7. マイグレーション・シーディング

MySQL コンテナの起動完了（`./vendor/bin/sail ps` で `healthy`）を待ってから実行してください。

```bash
./vendor/bin/sail artisan migrate --seed
```

8. フロントエンドのビルド

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

開発中に変更を即時反映したい場合は `./vendor/bin/sail npm run dev` を使用します。

> **Note:** `php artisan` などのコマンドはホストではなく、`./vendor/bin/sail artisan ...` のようにコンテナ内で実行します。

### トラブルシューティング

#### `port is already allocated` で起動できない

他のプロジェクトのコンテナなどが同じポート（80 / 3306 / 5173 / 8080）を使用しています。以下のいずれかで対応してください。

- 他のプロジェクトのコンテナを停止する（`docker ps` で確認し、`docker stop <コンテナ名>`）
- `.env` で CourseHub 側のポートを変更する

```
APP_PORT=8000
FORWARD_DB_PORT=33060
FORWARD_PHPMYADMIN_PORT=8081
VITE_PORT=5174
```

### キュー設定

本番環境では Redis を使用します。`.env` に以下を設定:

```
QUEUE_CONNECTION=redis
```

### アクセス

- アプリケーション: http://localhost
- phpMyAdmin: http://localhost:8080

## テスト

```bash
./vendor/bin/sail artisan test
```

## コーディング規約

### バリデーション
- Controller ではバリデーションに Form Request を使用する
- Form Request は `app/Http/Requests/` に配置する

### 命名規則
- 変数名・メソッド名は camelCase
- テーブル名・カラム名は snake_case
- Controller 名は単数形 + Controller（例: CourseController）

### エラーハンドリング
- Blade 画面はリダイレクトでエラーを返す
- フラッシュメッセージで結果を通知する

### テスト
- 新機能には Feature テストを書く
- テストメソッド名は `test_` プレフィックス

## 設計方針

### Controller
- Controller は薄く保つ。ビジネスロジックが複雑な場合は Service クラスに切り出す

### Service クラス
- 複数モデルにまたがる処理は Service クラスに集約する（例: EnrollmentService）
- `app/Services/` に配置する

### Policy
- リソースのアクセス制御は Policy で実装する
- Controller で `$this->authorize()` を使用する

### イベント
- 重要なドメインイベントは Event/Listener パターンで実装する

## 技術スタック

- PHP 8.2
- Laravel 10
- Laravel Sail（Docker 開発環境）
- MySQL 8.0
- Tailwind CSS
