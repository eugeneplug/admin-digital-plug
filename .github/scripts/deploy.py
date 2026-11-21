"""Upload tracked application files and fail if SFTP or public verification fails."""
import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile


def run(*args, **kwargs):
    return subprocess.run(args, check=True, **kwargs)


def deploy():
    for name in ("SFTP_HOST", "SFTP_USERNAME", "SFTP_KEY"):
        if os.environ.get(name, "").strip() in ("", "1"):
            raise ValueError(f"Secret {name} is missing or contains the test value 1. Restore the real connection details.")
    host = os.environ["SFTP_HOST"]
    user = os.environ["SFTP_USERNAME"]
    remote = os.environ.get("REMOTE_PATH", "")
    site = os.environ.get("SITE_URL", "").rstrip("/")
    if not re.fullmatch(r"[a-zA-Z0-9][a-zA-Z0-9.-]*", host):
        raise ValueError("SFTP_HOST must be a hostname or IPv4 address without a protocol or path.")
    if not re.fullmatch(r"[a-zA-Z0-9_][a-zA-Z0-9_.-]*", user):
        raise ValueError("Invalid SFTP_USERNAME.")
    if not re.fullmatch(r"/[a-zA-Z0-9_./-]+", remote):
        raise ValueError("Invalid REMOTE_PATH.")
    if not site.startswith("https://"):
        raise ValueError("SITE_URL must use HTTPS.")

    with tempfile.TemporaryDirectory(prefix="plugy-deploy-") as temporary:
        stage = Path(temporary)
        key = stage / "key"
        key.write_text(os.environ.pop("SFTP_KEY").replace("\r", "").strip() + "\n", encoding="utf-8")
        key.chmod(0o600)
        result = subprocess.run(["ssh-keygen", "-y", "-P", "", "-f", str(key)],
                                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if result.returncode:
            raise ValueError("SFTP_KEY must be a valid private SSH key without a passphrase.")

        # Only tracked application files; never upload CI files, archives or local data.
        tracked = run("git", "ls-tree", "-rz", "--name-only", "HEAD", "--",
                      "index.php", "admin", "app", "site", stdout=subprocess.PIPE).stdout
        files = sorted(name.decode("utf-8") for name in tracked.split(b"\0") if name)
        if "index.php" not in files or "app/install.local.php" in files:
            raise ValueError("Invalid deployment manifest or tracked private configuration.")
        for name in files:
            if not re.fullmatch(r"[a-zA-Z0-9_./-]+", name) or ".." in Path(name).parts:
                raise ValueError("Unsupported path in deployment manifest.")
            destination = stage / "files" / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            destination.write_bytes(run("git", "show", f"HEAD:{name}", stdout=subprocess.PIPE).stdout)

        directories = sorted({str(parent).replace("\\", "/") for name in files
                              for parent in Path(name).parents if str(parent) != "."},
                             key=lambda name: (name.count("/"), name))
        commands = [f'cd "{remote}"', f'lcd "{(stage / "files").as_posix()}"']
        # Ignore mkdir failures for existing directories; all put/get errors are fatal.
        commands += [f'-mkdir "{name}"' for name in directories]
        commands += [f'put "{name}" "{name}"' for name in files if name != "index.php"]
        commands += ['put "index.php" "index.php"']
        assets = ("style.css", "code-editor.js")
        commands += [f'get "admin/assets/{name}" "{(stage / name).as_posix()}"' for name in assets]
        batch = stage / "upload.sftp"
        batch.write_text("\n".join(commands) + "\n", encoding="utf-8")
        print(f"Uploading {len(files)} application files. Existing configuration and data are preserved.", flush=True)
        run("sftp", "-b", str(batch), "-P", "22", "-i", str(key),
            "-oBatchMode=yes", "-oIdentitiesOnly=yes", "-oConnectTimeout=20",
            "-oServerAliveInterval=15", "-oServerAliveCountMax=3",
            "-oStrictHostKeyChecking=accept-new", f"-oUserKnownHostsFile={stage / 'known_hosts'}",
            f"{user}@{host}")

        for name in assets:
            expected = (stage / "files" / "admin" / "assets" / name).read_bytes()
            if (stage / name).read_bytes() != expected:
                raise ValueError(f"SFTP verification failed for {name}.")
            digest = hashlib.sha256(expected).hexdigest()
            downloaded = stage / f"http-{name}"
            run("curl", "--fail", "--silent", "--show-error", "--location",
                "--connect-timeout", "15", "--max-time", "60", "-H", "Cache-Control: no-cache",
                f"{site}/admin/assets/{name}?v={digest}", "-o", str(downloaded))
            if downloaded.read_bytes() != expected:
                raise ValueError(f"The domain serves a different {name}. Check the domain document root or HTTP cache.")
        print("Deployment verified: editor assets on the domain match this commit.")


if __name__ == "__main__":
    try:
        deploy()
    except (ValueError, OSError, subprocess.CalledProcessError) as error:
        # Do not print command arguments or secret values in the failure annotation.
        message = str(error) if isinstance(error, ValueError) else "Transfer or verification failed. See the command error above."
        print(f"::error::{message}", file=sys.stderr)
        sys.exit(1)
