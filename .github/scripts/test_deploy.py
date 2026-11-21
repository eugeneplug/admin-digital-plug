import importlib.util
import os
from pathlib import Path
import subprocess
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("deploy", Path(__file__).with_name("deploy.py"))
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)
REAL_RUN = subprocess.run


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.environment = patch.dict(os.environ, {
            "SFTP_HOST": "example.test", "SFTP_USERNAME": "tester", "SFTP_KEY": "fixture-key",
            "REMOTE_PATH": "/example/public_html", "SITE_URL": "https://example.test",
        })
        self.environment.start()
        self.addCleanup(self.environment.stop)
        self.commands = []
        self.stage = None
        self.failure = None

    def execute(self, args, **kwargs):
        self.commands.append(args[0])
        if args[0] == "git":
            return REAL_RUN(args, **kwargs)
        if args[0] == "ssh-keygen":
            return subprocess.CompletedProcess(args, 1 if self.failure == "key" else 0)
        if args[0] == "sftp":
            self.stage = Path(args[args.index("-b") + 1]).parent
            batch = (self.stage / "upload.sftp").read_text()
            self.assertNotIn('put ".github/', batch)
            self.assertNotIn('put "test', batch)
            self.assertNotIn("install.local.php", batch)
            self.assertNotIn("rm ", batch)
            self.assertIn('put "app/.htaccess"', batch)
            if self.failure == "sftp":
                raise subprocess.CalledProcessError(255, args)
            for name in ("style.css", "code-editor.js"):
                content = (self.stage / "files/admin/assets" / name).read_bytes()
                (self.stage / name).write_bytes(content if self.failure != "readback" else b"wrong")
        elif args[0] == "curl":
            if self.failure == "http":
                raise subprocess.CalledProcessError(22, args)
            destination = Path(args[args.index("-o") + 1])
            name = destination.name.removeprefix("http-")
            content = (self.stage / "files/admin/assets" / name).read_bytes()
            destination.write_bytes(content if self.failure != "stale" else b"old assets")
        return subprocess.CompletedProcess(args, 0)

    def test_missing_and_placeholder_secrets_stop_before_network(self):
        for name in ("SFTP_HOST", "SFTP_USERNAME", "SFTP_KEY"):
            for value in ("", "1"):
                with self.subTest(name=name, value=value), patch.dict(os.environ, {name: value}), \
                        patch.object(deploy.subprocess, "run") as command:
                    with self.assertRaises(ValueError):
                        deploy.deploy()
                    command.assert_not_called()

    def test_successful_transfer_and_domain_verification(self):
        with patch.object(deploy.subprocess, "run", side_effect=self.execute):
            deploy.deploy()
        self.assertEqual(self.commands.count("curl"), 2)
        self.assertFalse(self.stage.exists())

    def test_failures_are_propagated(self):
        for failure in ("key", "sftp", "readback", "http", "stale"):
            with self.subTest(failure=failure), patch.dict(os.environ, {"SFTP_KEY": "fixture-key"}), \
                    patch.object(deploy.subprocess, "run", side_effect=self.execute):
                self.failure = failure
                with self.assertRaises((ValueError, subprocess.CalledProcessError)):
                    deploy.deploy()
                if self.stage:
                    self.assertFalse(self.stage.exists())


if __name__ == "__main__":
    unittest.main()
