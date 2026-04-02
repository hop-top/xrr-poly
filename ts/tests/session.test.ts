import fs from "node:fs";
import { describe, expect, test, vi } from "vitest";
import { FileCassette } from "../src/cassette.js";
import { FileSession } from "../src/session.js";
import { ErrCassetteMiss, type Adapter } from "../src/xrr.js";

interface FakeReq { key: string }
interface FakeResp { out: string }

function makeAdapter(id = "exec", fp = "testfp01"): Adapter<FakeReq, FakeResp> {
  return {
    id,
    async fingerprint() { return fp; },
    serializeReq: (r) => r,
    serializeResp: (r) => r,
    deserializeReq: (d) => d as FakeReq,
    deserializeResp: (d) => d as FakeResp,
  };
}

describe("FileSession — record", () => {
  test("calls do_() and writes cassette files", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-session-");
    const session = new FileSession("record", new FileCassette(dir));
    const adapter = makeAdapter();
    const do_ = vi.fn(async () => ({ out: "hello\n" }));

    const resp = await session.record(adapter, { key: "k" }, do_);
    expect(do_).toHaveBeenCalledOnce();
    expect(resp).toEqual({ out: "hello\n" });

    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(2);
  });
});

describe("FileSession — replay", () => {
  test("loads from cassette; do_() NOT called", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-session-");
    const cassette = new FileCassette(dir);
    await cassette.save("exec", "a3f9c1b2", { key: "k" }, { out: "replayed" });

    const session = new FileSession("replay", cassette);
    const adapter = makeAdapter("exec", "a3f9c1b2");
    const do_ = vi.fn(async () => ({ out: "should-not-run" }));

    const resp = await session.record(adapter, { key: "k" }, do_);
    expect(do_).not.toHaveBeenCalled();
    expect(resp).toEqual({ out: "replayed" });
  });

  test("throws ErrCassetteMiss when not found", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-session-");
    const session = new FileSession("replay", new FileCassette(dir));
    const adapter = makeAdapter("exec", "deadbeef");

    await expect(
      session.record(adapter, { key: "k" }, async () => ({ out: "" }))
    ).rejects.toThrow(ErrCassetteMiss);
  });
});

describe("FileSession — passthrough", () => {
  test("calls do_() and does NOT write cassette", async () => {
    const dir = fs.mkdtempSync(fs.realpathSync("/tmp") + "/xrr-session-");
    const session = new FileSession("passthrough", new FileCassette(dir));
    const adapter = makeAdapter();
    const do_ = vi.fn(async () => ({ out: "live" }));

    const resp = await session.record(adapter, { key: "k" }, do_);
    expect(do_).toHaveBeenCalledOnce();
    expect(resp).toEqual({ out: "live" });

    const files = fs.readdirSync(dir);
    expect(files).toHaveLength(0);
  });
});
