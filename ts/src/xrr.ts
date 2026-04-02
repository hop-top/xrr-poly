/**
 * Core xrr interfaces — mirrors Go reference implementation.
 */

export type Mode = "record" | "replay" | "passthrough";

export const ErrCassetteMiss = new Error("xrr: cassette miss");

export interface Adapter<Req, Resp> {
  id: string;
  fingerprint(req: Req): Promise<string>;
  serializeReq(req: Req): unknown;
  serializeResp(resp: Resp): unknown;
  deserializeReq(data: unknown): Req;
  deserializeResp(data: unknown): Resp;
}

export interface Cassette {
  save(adapterID: string, fingerprint: string, req: unknown, resp: unknown): Promise<void>;
  load(adapterID: string, fingerprint: string): Promise<{ req: unknown; resp: unknown }>;
}

export interface Session {
  record<Req, Resp>(
    adapter: Adapter<Req, Resp>,
    req: Req,
    do_: () => Promise<Resp>
  ): Promise<Resp>;
}
