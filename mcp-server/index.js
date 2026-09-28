#!/usr/bin/env node
/**
 * シオヨミ JSON API をラップする薄い stdio MCP サーバ。
 *
 * 環境変数:
 *   SHIOYOMI_BASE_URL  例: https://static.sho-tsukamoto.jp/tidegraph
 *                      または http://127.0.0.1:8080
 */
import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";

const BASE_URL = (process.env.SHIOYOMI_BASE_URL || "http://127.0.0.1:8080").replace(/\/$/, "");

async function fetchJson(path, query = {}) {
  const url = new URL(path.startsWith("http") ? path : `${BASE_URL}/${path.replace(/^\//, "")}`);
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, String(value));
    }
  }
  const res = await fetch(url, {
    headers: { Accept: "application/json" },
  });
  const text = await res.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`JSON 以外の応答 (${res.status}): ${text.slice(0, 200)}`);
  }
  if (!res.ok || data.ok === false) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

function toolText(data) {
  return {
    content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
  };
}

const server = new Server(
  { name: "shioyomi", version: "1.0.0" },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: "list_places",
      description: "シオヨミ登録港一覧を返す",
      inputSchema: { type: "object", properties: {}, additionalProperties: false },
    },
    {
      name: "get_tide",
      description: "指定港・日付の潮汐・天気サマリ（時系列含む）を返す",
      inputSchema: {
        type: "object",
        properties: {
          prefecture: { type: "string", description: "都道府県コード（例: 28）" },
          code: { type: "string", description: "港コード（例: 9）" },
          place: { type: "string", description: "互換: 28&9 形式" },
          date: { type: "string", description: "YYYY-MM-DD" },
          include_series: {
            type: "boolean",
            description: "潮位時系列を含める（既定 true）",
          },
        },
        additionalProperties: false,
      },
    },
    {
      name: "get_calendar",
      description: "指定港・年月の月間潮回りカレンダーを返す",
      inputSchema: {
        type: "object",
        properties: {
          prefecture: { type: "string" },
          code: { type: "string" },
          place: { type: "string" },
          year: { type: "integer" },
          month: { type: "integer" },
        },
        additionalProperties: false,
      },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const name = request.params.name;
  const args = request.params.arguments || {};

  try {
    if (name === "list_places") {
      return toolText(await fetchJson("api/places.php"));
    }
    if (name === "get_tide") {
      const query = {
        prefecture: args.prefecture,
        code: args.code,
        place: args.place,
        date: args.date,
      };
      if (args.include_series === false) {
        query.include_series = "0";
      }
      return toolText(await fetchJson("api/tide.php", query));
    }
    if (name === "get_calendar") {
      return toolText(
        await fetchJson("api/calendar.php", {
          prefecture: args.prefecture,
          code: args.code,
          place: args.place,
          year: args.year,
          month: args.month,
        })
      );
    }
    return {
      isError: true,
      content: [{ type: "text", text: `Unknown tool: ${name}` }],
    };
  } catch (err) {
    return {
      isError: true,
      content: [{ type: "text", text: String(err && err.message ? err.message : err) }],
    };
  }
});

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
