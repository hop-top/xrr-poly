/**
 * FileCassette — reads/writes YAML envelope files.
 */
import fs from "node:fs/promises";
import path from "node:path";
import yaml from "js-yaml";
import { ErrCassetteMiss, type Cassette } from "./xrr.js";

interface Envelope {
  xrr: string;
  adapter: string;
  fingerprint: string;
  recorded_at: string;
  payload: unknown;
}

export class FileCassette implements Cassette {
  constructor(private readonly dir: string) {}

  async save(
    adapterID: string,
    fingerprint: string,
    req: unknown,
    resp: unknown
  ): Promise<void> {
    const now = new Date().toISOString().replace(/\.\d{3}Z$/, "Z");
    await this.write(adapterID, fingerprint, "req", now, req);
    await this.write(adapterID, fingerprint, "resp", now, resp);
  }

  private async write(
    adapterID: string,
    fingerprint: string,
    kind: "req" | "resp",
    recordedAt: string,
    payload: unknown
  ): Promise<void> {
    const env: Envelope = {
      xrr: "1",
      adapter: adapterID,
      fingerprint,
      recorded_at: recordedAt,
      payload,
    };
    const data = yaml.dump(env, { lineWidth: -1 });
    const filePath = path.join(this.dir, `${adapterID}-${fingerprint}.${kind}.yaml`);
    await fs.writeFile(filePath, data, "utf8");
  }

  async load(
    adapterID: string,
    fingerprint: string
  ): Promise<{ req: unknown; resp: unknown }> {
    const req = await this.read(adapterID, fingerprint, "req");
    const resp = await this.read(adapterID, fingerprint, "resp");
    return { req, resp };
  }

  private async read(
    adapterID: string,
    fingerprint: string,
    kind: "req" | "resp"
  ): Promise<unknown> {
    const filePath = path.join(this.dir, `${adapterID}-${fingerprint}.${kind}.yaml`);
    let data: string;
    try {
      data = await fs.readFile(filePath, "utf8");
    } catch (err: unknown) {
      if ((err as NodeJS.ErrnoException).code === "ENOENT") {
        throw ErrCassetteMiss;
      }
      throw err;
    }
    const env = yaml.load(data) as Envelope;
    if (!env || typeof env !== "object" || !("payload" in env)) {
      throw new Error(`xrr: missing payload in ${kind}`);
    }
    return env.payload;
  }
}
