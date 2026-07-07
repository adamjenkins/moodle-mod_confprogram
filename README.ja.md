# mod_confprogram

**Conference Program**（査読プラグイン）— [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) の応募を査読ワークフローに通し、採択されたプログラムを公開する Moodle アクティビティモジュール。

*ドキュメント: [English](README.md) · 日本語（このファイル）*

[Conference Tools](https://github.com/adamjenkins/moodle-conference-tools) スイートの一部です:

- [mod_confsubmissions](https://github.com/adamjenkins/moodle-mod_confsubmissions) — 発表募集
- **mod_confprogram**（本プラグイン）— 査読ワークフロー＋公開プログラム
- [mod_confscheduler](https://github.com/adamjenkins/moodle-mod_confscheduler) — ドラッグ＆ドロップのブロックスケジュール
- [mod_confcheckin](https://github.com/adamjenkins/moodle-mod_confcheckin) — チケット・バッジ・QR チェックイン

## 機能概要

アクティビティは2つのフェーズで動作し、編集モードから切り替えます。

**審査フェーズ**

- 各応募に**査読者を割り当て**ます。個別に割り当てるほか、グループ査読モードでは査読者グループを一括で割り当てられます。
- Moodle のアドバンスト評定 API を用いた**ルーブリックで査読**します。任意で**ブラインド**（査読者と著者の氏名を相互に非表示）にできます。
- 基調講演やパネルは**「査読対象外」**に設定して、査読から除外できます。
- 絞り込み可能な**判定レポート**で、採択／却下／再提出／補欠の**判定を記録**します。1件ずつでも一括でも行えます。*再提出*は査読コメントを表示したまま応募を再び開き、新しいラウンドを開始します。**新しいラウンドを開始**リンクから一括再割り当てへ直接移動できます。
- 判定レポートは `mod_confsubmissions` の編集画面へリンクしており、「すべての応募を編集」権限を持つユーザーはワークフローを離れずに応募内容を修正できます。

**表示フェーズ**

- 採択された応募を、レスポンシブで絞り込み可能な日別リストとして表示し、**お気に入り**の星を備えます。Conference Scheduler の時刻・会場と「マイタイムテーブル」の状態と同期します。`?trackid=X` で1つのトラックに絞り込めます（スケジューラーのトラックピルのリンク先です）。

**両フェーズ共通**

- **判定通知** — 採択／却下／補欠の際に各発表者へメールを送信しますが、送信されるのはインスタンスが表示フェーズに達した後のみです（`mod_confsubmissions` へのステータス同期と同じ公開制限（エンバーゴ）に従います）。テンプレートは編集可能で、アクティビティごとにオフにできます。
- **バックアップ／リストアとコースリセット** — 完全対応。リセットは査読・判定・お気に入り・査読対象外フラグを消去し、インスタンスを審査フェーズに戻します。表示設定とテンプレートは残ります。

## 要件

- Moodle 5.2（`2026042000`）以降。
- 同じコースに mod_confsubmissions がインストールされていること。

## インストール

```
git clone https://github.com/adamjenkins/moodle-mod_confprogram.git mod/confprogram
php admin/cli/upgrade.php
```

## ライセンス

GNU GPL v3 以降。[LICENSE](LICENSE) を参照してください。

## 作者

Adam Jenkins <adam@wisecat.net>
