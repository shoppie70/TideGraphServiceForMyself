# 本番デプロイ手順（シオヨミ / スターサーバー）

| 項目 | 値 |
| ---- | -- |
| 本番 URL | https://static.sho-tsukamoto.jp/tidegraph |
| レンタルサーバ | スターサーバー（star8） |
| デプロイ先パス | `/sho-tsukamoto.jp/public_html/static.sho-tsukamoto.jp/tidegraph` |
| 実装 | GitHub Actions → **SSH (port 10022) + rsync** |

## なぜ SFTP/SSH か

FTPS（port 21）の dry_run は `Timeout (control socket)` で失敗した。  
スターサーバー公式の SSH は次のとおり（[SSH設定](https://www.star.ne.jp/support/manual/man_server_ssh.php)）:

| 項目 | 値 |
| ---- | -- |
| ホスト | `サーバーID.stars.ne.jp`（または `sv***.star.ne.jp`） |
| ユーザー | サーバーID |
| ポート | **10022** |
| 認証 | **公開鍵のみ**（パスワード不可） |

そのためデプロイは **SSH + rsync** に切り替え済み。旧 FTP Secrets（`FTP_*`）は不要。

## 自動デプロイ

| トリガー | 動作 |
| -------- | ---- |
| `main` へ push | Variable `ENABLE_AUTO_DEPLOY=true` のときのみ本番同期 |
| `cursor/deploy-dry-run-446a` へ push | 常に **dry_run**（アップロード無し） |
| Actions 手動 | `dry_run` 入力で確認／本番 |

## vendor（Composer）方針

- **CI 上で** `composer install --no-dev --optimize-autoloader`
- 生成した `vendor/` を rsync でアップロード
- サーバ側で Composer は実行しない

## 必要な GitHub Secrets（必須・3つ）

Environment **`production`**（推奨）または Repository Secrets:

| Name | 説明 |
| ---- | ---- |
| `SSH_HOST` | 例: `あなたのサーバーID.stars.ne.jp` |
| `SSH_USERNAME` | サーバーID（例: `ss123456`） |
| `SSH_PRIVATE_KEY` | スターサーバーに登録した公開鍵に対応する **秘密鍵**（PEM 全文） |

### 任意 Variables

| Name | 説明 | 既定 |
| ---- | ---- | ---- |
| `DEPLOY_REMOTE_DIR` | リモートパス | `sho-tsukamoto.jp/public_html/static.sho-tsukamoto.jp/tidegraph/` |
| `SSH_PORT` | SSH ポート | `10022` |
| `PROD_BASE_URL` | スモーク URL | `https://static.sho-tsukamoto.jp/tidegraph` |
| `ENABLE_AUTO_DEPLOY` | main push で本番デプロイ | 未設定＝しない |

## 初回セットアップ（SSH）

1. サーバーパネル → **SSH設定** を有効（ON）
2. 公開鍵を登録（パネルで鍵ペア生成して秘密鍵をダウンロード、または手元で生成した公開鍵を登録）
3. GitHub に `SSH_HOST` / `SSH_USERNAME` / `SSH_PRIVATE_KEY` を登録  
   - 秘密鍵は `-----BEGIN ... PRIVATE KEY-----` から末尾までそのまま貼る  
   - パスフレーズ付き鍵は避ける（または CI 向けにパスフレーズ無し鍵を別途用意）
4. dry_run 実行（Actions 手動、または `cursor/deploy-dry-run-446a` へ push）
5. 成功後に本番デプロイ（手動 `dry_run=false`、または `ENABLE_AUTO_DEPLOY=true` 後に main マージ）

### 手元での接続確認例

```bash
ssh -p 10022 -i /path/to/private_key サーバーID@サーバーID.stars.ne.jp
```

## アップロードされるもの / されないもの

**される:** PHP 一式、`vendor/`、`assets/`、`llms.txt`、PWA/SEO 関連  
**されない:** `.git` / `.github` / `mcp-server/` / Docker / Markdown

`--delete` 付き rsync（dry_run 時は転送も削除も無し）。本番時はデプロイツリーに無いリモートファイルが消える点に注意。

## 旧 FTP Secrets について

| 旧 Name | 状態 |
| ------- | ---- |
| `FTP_SERVER` / `FTP_USERNAME` / `FTP_PASSWORD` | **未使用**。削除してよい |

FTP 制限（許可 IP）を入れていると GitHub Actions から FTPS は繋がりにくい。SSH 推奨。

## トラブルシュート

| 症状 | 確認 |
| ---- | ---- |
| Secret 未設定 | `SSH_HOST` / `SSH_USERNAME` / `SSH_PRIVATE_KEY` |
| Permission denied (publickey) | 公開鍵がサーバーに登録されているか、秘密鍵が対になっているか、SSH が ON か |
| Connection timed out | ホスト名・ポート **10022** |
| Host key 警告 | 初回は `accept-new` で登録。ホスト変更時は known_hosts 再取得 |

## サブパス・名称

- アプリ名: **シオヨミ**
- `/tidegraph` は `App\Support\Site` で解決
- WebMCP / JSON API / SEO / PWA はデプロイ対象
