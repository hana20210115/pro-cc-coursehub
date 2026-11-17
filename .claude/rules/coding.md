---
description: コーディング規約・設計方針
paths:
  - "app/**/*.php"
  - "routes/**/*.php"
  - "tests/**/*.php"
  - "resources/views/**/*.blade.php"
---

詳細は README.md の「コーディング規約」「設計方針」セクションを参照。

## 既存コードとの乖離について

既存コードには規約に従っていない箇所がある（Controller 内の `$request->validate()`、Controller に処理を詰め込んだ Fat Controller など）。

- 新規コード・修正するメソッドでは、既存コードに合わせず規約に従う
- 頼まれていない既存箇所のリファクタリングは行わない（気づいた点は報告にとどめる）

## バリデーション

- バリデーションは Form Request で行い、`app/Http/Requests/` に配置する（`$request->validate()` は使わない）
- 命名は `Store{Model}Request` / `Update{Model}Request`

## Controller / Service

- Controller は薄く保つ（リクエスト受け取り・認可・Service 呼び出し・レスポンス返却のみ）
- 複数モデルにまたがる処理やビジネスロジックは `app/Services/` の Service クラスに置く（例: `EnrollmentService`）

## 認可

- リソースのアクセス制御は Policy で実装し、Controller で `$this->authorize()` を呼ぶ
- 新しい Policy は `AuthServiceProvider` に登録する
- ネストしたルート（例: `courses/{course}/chapters/{chapter}`）では、子リソースが親に属していることも確認する

## エラーハンドリング

- Blade 画面はリダイレクトでエラーを返し、フラッシュメッセージで結果を通知する

## イベント

- 重要なドメインイベントは Event/Listener パターンで実装する（例: `CourseCompleted`）

## 命名・モデル

- 変数名・メソッド名は camelCase、テーブル名・カラム名は snake_case
- Controller 名は単数形 + Controller（例: `CourseController`）
- モデルのリレーションは戻り値の型付きで明示的に定義する

## テスト

- 新機能には Feature テストを書く
- テストメソッド名は `test_` プレフィックス
