/**
 * exec adapter — fingerprints on argv + stdin.
 */
import { createHash } from "node:crypto";
import type { Adapter } from "../xrr.js";

export interface ExecRequest {
  argv: string[];
  stdin?: string;
  env?: Record<string, string>;
}

export interface ExecResponse {
  stdout: string;
  stderr?: string;
  exit_code: number;
  duration_ms?: number;
}

function sortedKeys(obj: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.keys(obj)
      .sort()
      .map((k) => [k, obj[k]])
  );
}

export class ExecAdapter implements Adapter<ExecRequest, ExecResponse> {
  readonly id = "exec";

  async fingerprint(req: ExecRequest): Promise<string> {
    const canonical = JSON.stringify(
      sortedKeys({ argv: req.argv, stdin: req.stdin ?? "" })
    );
    return createHash("sha256").update(canonical).digest("hex").slice(0, 8);
  }

  serializeReq(req: ExecRequest): unknown {
    return req;
  }

  serializeResp(resp: ExecResponse): unknown {
    return resp;
  }

  deserializeReq(data: unknown): ExecRequest {
    return data as ExecRequest;
  }

  deserializeResp(data: unknown): ExecResponse {
    return data as ExecResponse;
  }
}
