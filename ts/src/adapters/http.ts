/**
 * http adapter — fingerprints on method + path+query + sha256(body)[:8].
 */
import { createHash } from "node:crypto";
import type { Adapter } from "../xrr.js";

export interface HttpRequest {
  method: string;
  url: string;
  headers?: Record<string, string>;
  body?: string;
}

export interface HttpResponse {
  status: number;
  headers?: Record<string, string>;
  body?: string;
}

function sortedKeys(obj: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.keys(obj)
      .sort()
      .map((k) => [k, obj[k]])
  );
}

export class HttpAdapter implements Adapter<HttpRequest, HttpResponse> {
  readonly id = "http";

  async fingerprint(req: HttpRequest): Promise<string> {
    const u = new URL(req.url);
    const pathQuery = u.search ? `${u.pathname}${u.search}` : u.pathname;
    const bodyHash = createHash("sha256")
      .update(req.body ?? "")
      .digest("hex")
      .slice(0, 8);
    const canonical = JSON.stringify(
      sortedKeys({ body_hash: bodyHash, method: req.method, path: pathQuery })
    );
    return createHash("sha256").update(canonical).digest("hex").slice(0, 8);
  }

  serializeReq(req: HttpRequest): unknown {
    return req;
  }

  serializeResp(resp: HttpResponse): unknown {
    return resp;
  }

  deserializeReq(data: unknown): HttpRequest {
    return data as HttpRequest;
  }

  deserializeResp(data: unknown): HttpResponse {
    return data as HttpResponse;
  }
}
