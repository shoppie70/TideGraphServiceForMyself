# シオヨミ MCP サーバ

JSON API（`api/places.php` / `api/tide.php` / `api/calendar.php`）をラップする薄い stdio MCP サーバ。

## セットアップ

```bash
cd mcp-server
npm install
```

## 起動

```bash
SHIOYOMI_BASE_URL=http://127.0.0.1:8080 npm start
# 本番例
SHIOYOMI_BASE_URL=https://static.sho-tsukamoto.jp/tidegraph npm start
```

## Cursor / Claude Desktop 設定例

```json
{
  "mcpServers": {
    "shioyomi": {
      "command": "node",
      "args": ["/absolute/path/to/TideGraphServiceForMyself/mcp-server/index.js"],
      "env": {
        "SHIOYOMI_BASE_URL": "https://static.sho-tsukamoto.jp/tidegraph"
      }
    }
  }
}
```

## ツール

| ツール | 説明 |
| ------ | ---- |
| `list_places` | 登録港一覧 |
| `get_tide` | 1日の潮汐・天気（`prefecture`+`code` または `place`, `date`） |
| `get_calendar` | 月間カレンダー |
