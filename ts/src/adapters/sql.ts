/**
 * sql adapter — fingerprints on normalized query + args.
 */
import { createHash } from "node:crypto";
import type { Adapter } from "../xrr.js";

export interface SqlRequest {
  query: string;
  args?: unknown[];
}

export interface SqlResponse {
  rows?: Record<string, unknown>[];
  affected?: number;
}

function normalizeQuery(q: string): string {
  return q.toLowerCase().replace(/\s+/g, " ").trim();
}

function sortedKeys(obj: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.keys(obj)
      .sort()
      .map((k) => [k, obj[k]])
  );
}

export class SqlAdapter implements Adapter<SqlRequest, SqlResponse> {
  readonly id = "sql";

  async fingerprint(req: SqlRequest): Promise<string> {
    const canonical = JSON.stringify(
      sortedKeys({ args: req.args ?? null, query: normalizeQuery(req.query) })
    );
    return createHash("sha256").update(canonical).digest("hex").slice(0, 8);
  }

  serializeReq(req: SqlRequest): unknown {
    return req;
  }

  serializeResp(resp: SqlResponse): unknown {
    return resp;
  }

  deserializeReq(data: unknown): SqlRequest {
    return data as SqlRequest;
  }

  deserializeResp(data: unknown): SqlResponse {
    return data as SqlResponse;
  }
}
