/**
 * redis adapter — fingerprints on command + args joined.
 */
import { createHash } from "node:crypto";
import type { Adapter } from "../xrr.js";

export interface RedisRequest {
  command: string;
  args?: string[];
}

export interface RedisResponse {
  result: unknown;
}

export class RedisAdapter implements Adapter<RedisRequest, RedisResponse> {
  readonly id = "redis";

  async fingerprint(req: RedisRequest): Promise<string> {
    const parts = [req.command.toUpperCase(), ...(req.args ?? [])];
    const canonical = JSON.stringify(parts.join(" "));
    return createHash("sha256").update(canonical).digest("hex").slice(0, 8);
  }

  serializeReq(req: RedisRequest): unknown {
    return req;
  }

  serializeResp(resp: RedisResponse): unknown {
    return resp;
  }

  deserializeReq(data: unknown): RedisRequest {
    return data as RedisRequest;
  }

  deserializeResp(data: unknown): RedisResponse {
    return data as RedisResponse;
  }
}
