"""Tests for FileCassette."""
import pytest
from xrr.cassette import CassetteMiss, FileCassette


def test_save_load_roundtrip(tmp_path):
    c = FileCassette(str(tmp_path))
    c.save("exec", "a3f9c1b2", {"argv": ["gh", "pr"]}, {"stdout": "ok", "exit_code": 0})
    req, resp = c.load("exec", "a3f9c1b2")
    assert req == {"argv": ["gh", "pr"]}
    assert resp == {"stdout": "ok", "exit_code": 0}


def test_load_missing_raises(tmp_path):
    c = FileCassette(str(tmp_path))
    with pytest.raises(CassetteMiss):
        c.load("exec", "deadbeef")


def test_envelope_fields_present(tmp_path):
    """Saved files must contain xrr, adapter, fingerprint, recorded_at, payload."""
    import yaml

    c = FileCassette(str(tmp_path))
    c.save("exec", "a3f9c1b2", {"argv": ["ls"]}, {"stdout": ""})
    req_file = tmp_path / "exec-a3f9c1b2.req.yaml"
    data = yaml.safe_load(req_file.read_text())
    assert data["xrr"] == "1"
    assert data["adapter"] == "exec"
    assert data["fingerprint"] == "a3f9c1b2"
    assert "recorded_at" in data
    assert data["payload"] == {"argv": ["ls"]}
