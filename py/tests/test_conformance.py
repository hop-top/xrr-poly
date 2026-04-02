"""Conformance tests: load all fixture cassettes from spec/fixtures/."""
from __future__ import annotations

from pathlib import Path

import pytest
import yaml

from xrr.cassette import FileCassette

# Resolve path relative to this file: tests/ -> py/ -> spec/fixtures/
_FIXTURES_DIR = Path(__file__).resolve().parent.parent.parent / "spec" / "fixtures"


def _fixture_dirs() -> list[Path]:
    if not _FIXTURES_DIR.exists():
        return []
    return [p for p in _FIXTURES_DIR.iterdir() if p.is_dir()]


@pytest.mark.parametrize("fixture_dir", _fixture_dirs(), ids=lambda p: p.name)
def test_conformance_fixture(fixture_dir: Path):
    manifest_path = fixture_dir / "manifest.yaml"
    assert manifest_path.exists(), f"missing manifest.yaml in {fixture_dir}"

    manifest = yaml.safe_load(manifest_path.read_text())
    interactions = manifest.get("interactions", [])
    assert interactions, f"no interactions in {manifest_path}"

    cassette = FileCassette(str(fixture_dir))
    for item in interactions:
        adapter = item["adapter"]
        fingerprint = item["fingerprint"]
        # Must not raise CassetteMiss
        req, resp = cassette.load(adapter, fingerprint)
        assert req is not None
        assert resp is not None
