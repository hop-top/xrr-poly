import fs from "node:fs";
import { describe, expect, test } from "vitest";
import { FileCassette } from "../src/cassette.js";
import { ErrCassetteMiss } from "../src/xrr.js";

describe("FileCassette", () => {
  test("save and load roundtrip", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-cassette-");
    const c = new FileCassette(dir);
    await c.save("exec", "a3f9c1b2", { argv: ["gh", "pr"] }, { stdout: "ok", exit_code: 0 });
    const { req, resp } = await c.load("exec", "a3f9c1b2");
    expect(req).toEqual({ argv: ["gh", "pr"] });
    expect(resp).toEqual({ stdout: "ok", exit_code: 0 });
  });

  test("load missing file throws ErrCassetteMiss", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-cassette-");
    const c = new FileCassette(dir);
    await expect(c.load("exec", "deadbeef")).rejects.toThrow(ErrCassetteMiss);
  });

  test("saves two files per interaction", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-cassette-");
    const c = new FileCassette(dir);
    await c.save("exec", "a3f9c1b2", { argv: ["ls"] }, { stdout: "", exit_code: 0 });
    const files = fs.readdirSync(dir).sort();
    expect(files).toEqual(["exec-a3f9c1b2.req.yaml", "exec-a3f9c1b2.resp.yaml"]);
  });
});
