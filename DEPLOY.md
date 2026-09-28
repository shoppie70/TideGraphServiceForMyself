# 本番デプロイ手順（シオヨミ / スターサーバー）

| 項目 | 値 |
| ---- | -- |
| 本番 URL | https://static.sho-tsukamoto.jp/tidegraph |
| レンタルサーバ | スターサーバー（star8） |
| デプロイ先パス | `/sho-tsukamoto.jp/public_html/static.sho-tsukamoto.jp/tidegraph` |
| 実装 | GitHub Actions → **FTPS** 同期（`.github/workflows/deploy.yml`） |

SSH でもデプロイ可能ですが、共有レンタルでは **サブ FTP + FTPS** が一般的なため実装は FTPS に絞っています。SSH 手順は末尾の代替案を参照。

## 自動デプロイ

| トリガー | 動作 |
| -------- | ---- |
| `main` へ push（PR マージ含む） | CI で `composer install --no-dev` → FTPS で上記パスへ同期 |
| Actions 手動実行 | `dry_run` でアップロード無し確認が可能 |

Environment 名: `production`（初回実行時に GitHub が作成を促します）

## vendor（Composer）方針

- **CI 上で** `composer install --no-dev --optimize-autoloader` を実行し、生成した `vendor/` をそのままアップロードする
- サーバ側で Composer は実行しない（スターサーバー上に composer が無くても動く）
- リポジトリの `.gitignore` で `vendor/` はコミット対象外のまま

## 必要な GitHub Secrets（必須）

**Settings → Secrets and variables → Actions**  
推奨: Environment `production` の Secrets に置く。**値はコミットしない。**

| Name | 説明 |
| ---- | ---- |
| `FTP_SERVER` | スターサーバーの FTP ホスト（パネル記載。例: `ftp**.star8.jp` やアカウント用ホスト名） |
| `FTP_USERNAME` | サブ FTP アカウントのユーザー名 |
| `FTP_PASSWORD` | サブ FTP アカウントのパスワード |

### 任意 Variables

| Name | 説明 | 既定 |
| ---- | ---- | ---- |
| `DEPLOY_REMOTE_DIR` | リモートパス上書き | `/sho-tsukamoto.jp/public_html/static.sho-tsukamoto.jp/tidegraph/` |
| `PROD_BASE_URL` | デプロイ後スモークの URL | `https://static.sho-tsukamoto.jp/tidegraph` |

パスは秘密ではないためワークフロー既定に埋め込んであります。変更時だけ Variable を設定してください。

## 初回セットアップ

1. スターサーバーで **サブ FTP アカウント**を発行（ホームまたは `tidegraph` 配下に制限できるならなお良い）
2. 上記 3 Secrets を GitHub に登録
3. Actions → **Deploy production** → `dry_run: true` で接続・差分を確認
4. `dry_run: false` で本番反映
5. ブラウザで https://static.sho-tsukamoto.jp/tidegraph を確認
6. 以降は `main` マージで自動デプロイ

## アップロードされるもの / されないもの

**される:** PHP（`index.php` / `chart.php` / `calendar.php` / `api/` 等）、`vendor/`、`assets/`、`llms.txt`、`manifest.webmanifest`、`sw.js`、`robots.txt`、`sitemap.xml` などサイト動作に必要な一式

**されない:** `.git` / `.github` / `mcp-server/` / Docker 関連 / Markdown（`DEPLOY.md` 等）

リモートの一括削除（`dangerous-clean-slate`）は **無効**です。

## サブパス・名称

- アプリ名: **シオヨミ**
- `/tidegraph` は `App\Support\Site` と `<base>` で解決
- WebMCP / JSON API / SEO / PWA はデプロイ対象に含む

## トラブルシュート

| 症状 | 確認 |
| ---- | ---- |
| Secret 未設定 | 必須 3 つが Environment / Repository に入っているか |
| SSL / 接続失敗 | ホスト名、FTPS（明示的 TLS・ポート 21）、サブ FTP の有効状態 |
| 画面は出るが CSS が 404 | リモートが `.../tidegraph/` 直下か（親の `public_html` に上げていないか） |
| API だけ失敗 | サーバの `allow_url_fopen` / 外向き HTTP。アプリ側の問題の場合あり |

## 代替案: SSH + rsync（未実装）

サブ SSH が使える場合の例（参考。秘密鍵は Secret のみ）:

| Secret | 説明 |
| ------ | ---- |
| `SSH_HOST` | SSH ホスト |
| `SSH_USERNAME` | SSH ユーザー |
| `SSH_PRIVATE_KEY` | 秘密鍵（パスフレーズ無し推奨、または別途対応） |
| `SSH_REMOTE_DIR` | 上記と同じ tidegraph パス |

```bash
rsync -az --delete --exclude '.git' ./ .deploy/ user@host:/sho-tsukamoto.jp/public_html/static.sho-tsukamoto.jp/tidegraph/
```

必要なら FTPS ワークフローを SSH 版に差し替え可能。鍵やパスワードの推測は行いません。
