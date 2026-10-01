/**
 * シオヨミ Imperative WebMCP ツール登録
 * ChatGPT Site tools / Chrome WebMCP 向け。未対応ブラウザでは何もしない。
 */
(function () {
  "use strict";

  function getModelContext() {
    if (typeof document !== "undefined" && document.modelContext && typeof document.modelContext.registerTool === "function") {
      return document.modelContext;
    }
    if (typeof navigator !== "undefined" && navigator.modelContext && typeof navigator.modelContext.registerTool === "function") {
      return navigator.modelContext;
    }
    return null;
  }

  function getCtx() {
    if (window.__SHIOYOMI__) return window.__SHIOYOMI__;
    var el = document.getElementById("shioyomi-data");
    if (!el) return null;
    try {
      window.__SHIOYOMI__ = JSON.parse(el.textContent);
      return window.__SHIOYOMI__;
    } catch (e) {
      return null;
    }
  }

  function encodePlace(place) {
    return encodeURIComponent(String(place.prefecture) + "&" + String(place.code));
  }

  function resolvePlace(input, places) {
    if (!input) return null;
    if (input.place_name) {
      return (places || []).find(function (p) {
        return p.name === input.place_name;
      }) || null;
    }
    if (input.prefecture != null && input.code != null) {
      return (
        (places || []).find(function (p) {
          return String(p.prefecture) === String(input.prefecture) && String(p.code) === String(input.code);
        }) || {
          name: null,
          prefecture: String(input.prefecture),
          code: String(input.code),
        }
      );
    }
    return null;
  }

  function jsonResult(data) {
    return {
      content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
      structuredContent: data,
    };
  }

  async function registerTool(modelContext, tool) {
    try {
      await modelContext.registerTool(tool);
    } catch (e) {
      // 重複登録などは無視
      console.debug("[shioyomi-webmcp]", tool.name, e);
    }
  }

  async function registerShioyomiTools() {
    var modelContext = getModelContext();
    if (!modelContext) return;

    var ctx = getCtx();
    if (!ctx) return;

    var page = ctx.page || "unknown";
    var places = ctx.places || [];

    await registerTool(modelContext, {
      name: "list_places",
      description: "シオヨミに登録されている港一覧を返す。",
      inputSchema: { type: "object", properties: {}, additionalProperties: false },
      annotations: { readOnlyHint: true, openWorldHint: false },
      execute: async function () {
        return jsonResult({ places: places.length ? places : ctx.places || [] });
      },
    });

    if (page === "chart") {
      await registerTool(modelContext, {
        name: "get_tide_summary",
        description: "現在表示中の港・日付の潮回り、満潮干潮、転流、日の出入、天気、bite_score の要約を返す。",
        inputSchema: { type: "object", properties: {}, additionalProperties: false },
        annotations: { readOnlyHint: true, openWorldHint: false },
        execute: async function () {
          var current = getCtx() || ctx;
          var bite = current.bite_score || null;
          var turn = current.current_turn || null;
          return jsonResult({
            place: current.place,
            date: current.date,
            timezone: current.timezone,
            moon: current.moon,
            sun: current.sun,
            flood: current.flood,
            edd: current.edd,
            current_turn: turn
              ? {
                  available: !!turn.available,
                  source: turn.source,
                  source_label: turn.source_label,
                  note: turn.note,
                  events: turn.events || [],
                }
              : null,
            weather: current.weather
              ? {
                  label: current.weather.label,
                  temp_max: current.weather.temp_max,
                  temp_min: current.weather.temp_min,
                }
              : null,
            bite_score: bite
              ? {
                  name: bite.name,
                  description: bite.description,
                  scale: bite.scale,
                  day_peak: bite.day_peak,
                  peak_hours: bite.peak_hours || [],
                }
              : null,
            summary_text: current.summary_text,
          });
        },
      });

      await registerTool(modelContext, {
        name: "get_tide_series",
        description: "現在表示中の20分間隔潮位時系列と、必要なら時間別風速・bite_score を返す。",
        inputSchema: {
          type: "object",
          properties: {
            include_wind: { type: "boolean", description: "風速(m/s)配列を含めるか" },
            include_bite_score: {
              type: "boolean",
              description: "bite_score（時間帯ごと）を含めるか。省略時 true",
            },
          },
          additionalProperties: false,
        },
        annotations: { readOnlyHint: true, openWorldHint: false },
        execute: async function (args) {
          var current = getCtx() || ctx;
          var includeWind = !!(args && args.include_wind);
          var includeBite =
            !args || args.include_bite_score === undefined || args.include_bite_score === null
              ? true
              : !!args.include_bite_score;
          var result = {
            date: current.date,
            place: current.place,
            tide: current.tide || [],
          };
          if (includeWind && current.weather) {
            result.wind_speed = current.weather.wind_speed_ms || [];
          }
          if (includeBite && current.bite_score) {
            result.bite_score = current.bite_score;
          }
          return jsonResult(result);
        },
      });

      await registerTool(modelContext, {
        name: "get_map_link",
        description: "現在の港の Google Map URL を返す。",
        inputSchema: { type: "object", properties: {}, additionalProperties: false },
        annotations: { readOnlyHint: true, openWorldHint: false },
        execute: async function () {
          var current = getCtx() || ctx;
          return jsonResult({
            url: current.map_url,
            lat: current.place && current.place.lat,
            lng: current.place && current.place.lng,
          });
        },
      });

      await registerTool(modelContext, {
        name: "set_place_and_date",
        description: "場所と日付を変更して潮見表チャートを再表示する（ページ遷移あり）。",
        inputSchema: {
          type: "object",
          properties: {
            place_name: { type: "string", description: "港名（例: 明石）" },
            prefecture: { type: "string", description: "都道府県コード" },
            code: { type: "string", description: "港コード" },
            date: { type: "string", description: "YYYY-MM-DD" },
          },
          additionalProperties: false,
        },
        annotations: { readOnlyHint: false, openWorldHint: false },
        execute: async function (args) {
          var current = getCtx() || ctx;
          var target = resolvePlace(args || {}, current.places || places) || current.place;
          var date = (args && args.date) || current.date;
          if (!target || !date) {
            return jsonResult({ ok: false, error: "place と date が必要です" });
          }
          var url = "chart.php?place=" + encodePlace(target) + "&date=" + encodeURIComponent(date);
          window.location.href = url;
          return jsonResult({ ok: true, navigated_to: url });
        },
      });
    }

    if (page === "calendar") {
      await registerTool(modelContext, {
        name: "get_month_overview",
        description: "表示中の月の日別潮回り・天気サマリを返す。",
        inputSchema: { type: "object", properties: {}, additionalProperties: false },
        annotations: { readOnlyHint: true, openWorldHint: false },
        execute: async function () {
          var current = getCtx() || ctx;
          return jsonResult({
            year: current.year,
            month: current.month,
            place: current.place,
            days: current.days,
            summary_text: current.summary_text,
          });
        },
      });

      await registerTool(modelContext, {
        name: "open_day_chart",
        description: "指定日の潮見表チャートへ遷移する。",
        inputSchema: {
          type: "object",
          properties: {
            date: { type: "string", description: "YYYY-MM-DD" },
          },
          required: ["date"],
          additionalProperties: false,
        },
        annotations: { readOnlyHint: false, openWorldHint: false },
        execute: async function (args) {
          var current = getCtx() || ctx;
          var date = args && args.date;
          if (!date || !current.place) {
            return jsonResult({ ok: false, error: "date が必要です" });
          }
          var url = "chart.php?place=" + encodePlace(current.place) + "&date=" + encodeURIComponent(date);
          window.location.href = url;
          return jsonResult({ ok: true, navigated_to: url });
        },
      });
    }

    if (page === "index") {
      await registerTool(modelContext, {
        name: "open_tide_chart",
        description: "場所と日付を受け取り潮見表チャートへ遷移する。",
        inputSchema: {
          type: "object",
          properties: {
            place_name: { type: "string" },
            prefecture: { type: "string" },
            code: { type: "string" },
            date: { type: "string", description: "YYYY-MM-DD。省略時は今日" },
          },
          additionalProperties: false,
        },
        annotations: { readOnlyHint: false, openWorldHint: false },
        execute: async function (args) {
          var current = getCtx() || ctx;
          var target = resolvePlace(args || {}, current.places || places);
          if (!target) {
            return jsonResult({ ok: false, error: "place_name または prefecture+code が必要です" });
          }
          var date = (args && args.date) || new Date().toISOString().slice(0, 10);
          var url = "chart.php?place=" + encodePlace(target) + "&date=" + encodeURIComponent(date);
          window.location.href = url;
          return jsonResult({ ok: true, navigated_to: url });
        },
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      registerShioyomiTools();
    });
  } else {
    registerShioyomiTools();
  }
})();
