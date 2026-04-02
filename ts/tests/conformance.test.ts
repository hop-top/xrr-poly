import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import yaml from "js-yaml";
import { describe, expect, test } from "vitest";
import { FileCassette } from "../src/cassette.js";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixturesRoot = path.resolve(__dirname, "../../spec/fixtures");

interface Manifest {
  interactions: Array<{ adapter: string; fingerprint: string }>;
}

describe("conformance fixtures", () => {
  const entries = fs.readdirSync(fixturesRoot, { withFileTypes: true });
  const dirs = entries.filter((e) => e.isDirectory());

  expect(dirs.length).toBeGreaterThan(0);

  for (const entry of dirs) {
    const fixtureDir = path.join(fixturesRoot, entry.name);
    const manifestPath = path.join(fixtureDir, "manifest.yaml");

    test(entry.name, async () => {
      const raw = fs.readFileSync(manifestPath, "utf8");
      const manifest = yaml.load(raw) as Manifest;
      const cassette = new FileCassette(fixtureDir);

      for (const interaction of manifest.interactions) {
        await expect(
          cassette.load(interaction.adapter, interaction.fingerprint)
        ).resolves.not.toThrow();
      }
    });
  }
});
