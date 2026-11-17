# CourseHub

オンライン学習プラットフォーム。コーチがコースを作成し、受講生が学習・進捗管理する。

## 技術スタック

- PHP 8.2 / Laravel 10
- MySQL 8.0
- Blade + Tailwind CSS（Vite でビルド）
- Laravel Sail（Docker 開発環境）

## 開発環境

コマンドはホストではなく Sail 経由で実行する（ホストに PHP / Composer はない）。

```bash
./vendor/bin/sail up -d                  # 起動
./vendor/bin/sail artisan migrate        # マイグレーション
./vendor/bin/sail artisan db:seed        # シーディング
./vendor/bin/sail npm run dev            # フロントエンド開発サーバー
```

初回セットアップ手順は README.md の「環境構築」を参照。

## テスト

```bash
./vendor/bin/sail artisan test
```

## ユーザーロール

- admin: 管理者
- coach: コーチ（コース作成）
- student: 受講生

## ドメイン構造

```
Course（コーチが作成。Category に属し、Tag を複数持つ）
└── Chapter（order で並び順を管理）
    └── Lesson（order で並び順を管理、is_published で公開制御）
        └── Quiz（Lesson に 0 or 1）
            └── Question
                └── Option（is_correct で正解を表す）
```

受講・進捗：

- Enrollment：受講生とコースの受講登録（status: active / completed）
- LessonProgress：レッスンごとの進捗
- Submission：小テストの提出と得点
- 公開中のレッスンをすべて完了すると `CourseCompleted` イベントが発火し、Enrollment が completed になる

## コーディング規約・設計方針

README.md の「コーディング規約」「設計方針」セクションを参照。
Claude Code 向けの要点は `.claude/rules/coding.md`、Git のルールは `.claude/rules/git.md` にある。
