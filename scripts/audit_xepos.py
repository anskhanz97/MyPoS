r"""Read-only inventory and fingerprint generator for T001.

The script reads C:\XEPOS and writes evidence only under C:\MyPoS\docs\research\evidence.
It deliberately excludes environments, dependencies, runtime storage, generated builds,
Git internals, and filenames likely to contain credentials.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
import sys
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path

SOURCE = Path(r"C:\XEPOS")
OUT = Path(r"C:\MyPoS\docs\research\evidence")
PROJECTS = [
    "api-retail-project",
    "portal-retail-xepos",
    "xepos-hq-hospitality",
    "api-loyalty-xepos",
    "loyalty-portal",
]
EXCLUDED_DIRS = {
    ".git", "vendor", "node_modules", "storage", "dist", "build",
    ".cache", ".idea", ".vscode", "coverage", ".next",
}
EXCLUDED_NAMES = {".env", ".env.local", ".env.production", ".env.testing"}
SECRET_NAME_RE = re.compile(r"(^|[._-])(secret|credential|private[-_]?key|token)([._-]|$)", re.I)
TEXT_EXTENSIONS = {
    ".php", ".vue", ".js", ".ts", ".tsx", ".json", ".md", ".sql",
    ".yml", ".yaml", ".xml", ".css", ".scss", ".html", ".txt",
}


def run(*args: str, cwd: Path | None = None) -> tuple[int, str]:
    proc = subprocess.run(args, cwd=cwd, text=True, encoding="utf-8",
                          errors="replace", capture_output=True, check=False)
    return proc.returncode, proc.stdout.strip()


def allowed(path: Path, root: Path) -> bool:
    rel = path.relative_to(root)
    if any(part in EXCLUDED_DIRS for part in rel.parts[:-1]):
        return False
    if path.name in EXCLUDED_NAMES or path.name.startswith(".env."):
        return False
    if SECRET_NAME_RE.search(path.name):
        return False
    return path.is_file()


def source_files(root: Path) -> list[Path]:
    """Walk without entering excluded trees; Path.rglob would still traverse them."""
    result: list[Path] = []
    for current, dirs, names in os.walk(root):
        dirs[:] = [name for name in dirs if name not in EXCLUDED_DIRS]
        current_path = Path(current)
        for name in names:
            path = current_path / name
            if allowed(path, root):
                result.append(path)
    return sorted(result)


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def read_text(path: Path) -> str:
    if path.suffix.lower() not in TEXT_EXTENSIONS:
        return ""
    try:
        return path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return ""


def list_relative(root: Path, pattern: str) -> list[str]:
    return sorted(p.relative_to(root).as_posix() for p in root.glob(pattern) if p.is_file())


def classify_file(rel: str) -> str:
    rel_lower = rel.lower()
    if "/routes/" in f"/{rel_lower}" or rel_lower.startswith("routes/"):
        return "routes"
    if "/database/migrations/" in f"/{rel_lower}" or rel_lower.startswith("database/migrations/"):
        return "migrations"
    if "/tests/" in f"/{rel_lower}" or rel_lower.startswith("tests/"):
        return "tests"
    if "/models/" in f"/{rel_lower}":
        return "models"
    if "/controllers/" in f"/{rel_lower}":
        return "controllers"
    if "/requests/" in f"/{rel_lower}":
        return "requests"
    if "/middleware/" in f"/{rel_lower}":
        return "middleware"
    if "/services/" in f"/{rel_lower}":
        return "services"
    if "/observers/" in f"/{rel_lower}":
        return "observers"
    if "/jobs/" in f"/{rel_lower}":
        return "jobs"
    if "/commands/" in f"/{rel_lower}":
        return "commands"
    if rel_lower.endswith((".vue", ".tsx")) and any(x in rel_lower for x in ("/views/", "/pages/", "/components/")):
        return "screens_components"
    if "/router/" in f"/{rel_lower}":
        return "frontend_routes"
    return "other"


def extract_route_lines(root: Path) -> list[dict]:
    entries: list[dict] = []
    candidates = list((root / "routes").glob("*.php")) if (root / "routes").exists() else []
    route_re = re.compile(r"Route::(get|post|put|patch|delete|match|any|apiResource|resource)\s*\((.*)")
    prefix_re = re.compile(r"(?:prefix|middleware)\s*\(\s*['\"]([^'\"]+)['\"]")
    for path in sorted(candidates):
        text = read_text(path)
        for lineno, line in enumerate(text.splitlines(), 1):
            if "Route::" not in line:
                continue
            method_match = route_re.search(line)
            prefix_match = prefix_re.search(line)
            entries.append({
                "file": path.relative_to(root).as_posix(),
                "line": lineno,
                "method": method_match.group(1) if method_match else "group_or_other",
                "prefix_or_middleware": prefix_match.group(1) if prefix_match else None,
                "text": line.strip()[:600],
            })
    return entries


def extract_frontend_paths(root: Path) -> list[dict]:
    entries: list[dict] = []
    files = []
    for candidate in (root / "src", root / "resources" / "js"):
        if candidate.exists():
            files.extend(candidate.rglob("*.js"))
            files.extend(candidate.rglob("*.ts"))
            files.extend(candidate.rglob("*.tsx"))
    path_re = re.compile(r"\bpath\s*:\s*['\"]([^'\"]+)['\"]")
    for path in sorted(set(files)):
        if not any(part.lower() in {"router", "routes"} for part in path.parts) and path.name not in {"App.tsx", "App.jsx"}:
            continue
        for lineno, line in enumerate(read_text(path).splitlines(), 1):
            match = path_re.search(line)
            if match:
                entries.append({"file": path.relative_to(root).as_posix(), "line": lineno, "path": match.group(1)})
    return entries


def extract_migration_tables(root: Path) -> list[dict]:
    entries: list[dict] = []
    mig = root / "database" / "migrations"
    if not mig.exists():
        return entries
    create_re = re.compile(r"Schema::create\(\s*['\"]([^'\"]+)")
    table_re = re.compile(r"Schema::table\(\s*['\"]([^'\"]+)")
    rename_re = re.compile(r"Schema::rename\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)")
    drop_re = re.compile(r"Schema::drop(?:IfExists)?\(\s*['\"]([^'\"]+)")
    for path in sorted(mig.glob("*.php")):
        text = read_text(path)
        creates = create_re.findall(text)
        alters = table_re.findall(text)
        renames = rename_re.findall(text)
        drops = drop_re.findall(text)
        entries.append({
            "file": path.relative_to(root).as_posix(), "creates": creates,
            "alters": alters, "renames": renames, "drops": drops,
        })
    return entries


def inspect_project(name: str) -> dict:
    root = SOURCE / name
    files = source_files(root)
    fingerprints = []
    category_counts = Counter()
    extension_counts = Counter()
    for path in files:
        rel = path.relative_to(root).as_posix()
        digest = sha256_file(path)
        fingerprints.append({"path": rel, "sha256": digest, "bytes": path.stat().st_size})
        category_counts[classify_file(rel)] += 1
        extension_counts[path.suffix.lower() or "[none]"] += 1
    aggregate = hashlib.sha256()
    for entry in fingerprints:
        aggregate.update(f"{entry['path']}\0{entry['sha256']}\0{entry['bytes']}\n".encode())

    git_root_code, git_root = run("git", "rev-parse", "--show-toplevel", cwd=root)
    head_code, head = run("git", "rev-parse", "HEAD", cwd=root)
    branch_code, branch = run("git", "branch", "--show-current", cwd=root)
    # Tracked changes are sufficient for the reproducible Git baseline; the
    # separate fingerprint covers non-ignored application files without asking
    # Git to enumerate large untracked dependency trees.
    status_code, status = run("git", "status", "--short", "--untracked-files=no", cwd=root)
    remote_code, remote = run("git", "remote", "get-url", "origin", cwd=root)

    model_dir = root / "app" / "Models"
    models = sorted(p.stem for p in model_dir.glob("*.php")) if model_dir.exists() else []
    classes: dict[str, list[str]] = {}
    class_locations = {
        "controllers": root / "app" / "Http" / "Controllers",
        "requests": root / "app" / "Http" / "Requests",
        "middleware": root / "app" / "Http" / "Middleware",
        "services": root / "app" / "Services",
        "observers": root / "app" / "Observers",
        "jobs": root / "app" / "Jobs",
        "commands": root / "app" / "Console" / "Commands",
    }
    for category, folder in class_locations.items():
        classes[category] = sorted(p.relative_to(root).as_posix() for p in folder.rglob("*.php")) if folder.exists() else []
    screens = []
    for folder in (root / "src", root / "resources" / "js"):
        if folder.exists():
            screens.extend(p.relative_to(root).as_posix() for p in folder.rglob("*.vue") if any(x.lower() in {"views", "pages"} for x in p.parts))
            screens.extend(p.relative_to(root).as_posix() for p in folder.rglob("*.tsx") if any(x.lower() in {"views", "pages"} for x in p.parts))
    tests = sorted(p.relative_to(root).as_posix() for p in (root / "tests").rglob("*Test.php")) if (root / "tests").exists() else []

    return {
        "project": name,
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "git": {
            "root": git_root if git_root_code == 0 else None,
            "head": head if head_code == 0 else None,
            "branch": branch if branch_code == 0 else None,
            "status_short": status.splitlines() if status_code == 0 and status else [],
            "origin": remote if remote_code == 0 else None,
        },
        "fingerprint": {"aggregate_sha256": aggregate.hexdigest(), "file_count": len(fingerprints), "files": fingerprints},
        "category_counts": dict(sorted(category_counts.items())),
        "extension_counts": dict(extension_counts.most_common()),
        "models": models,
        "classes": classes,
        "backend_routes": extract_route_lines(root),
        "frontend_routes": extract_frontend_paths(root),
        "migrations": extract_migration_tables(root),
        "screens": sorted(set(screens)),
        "tests": tests,
    }


def main() -> None:
    if not SOURCE.is_dir():
        raise SystemExit(f"Source directory not found: {SOURCE}")
    OUT.mkdir(parents=True, exist_ok=True)
    result = {
        "scope": str(SOURCE),
        "projects": [inspect_project(name) for name in PROJECTS],
        "exclusions": sorted(EXCLUDED_DIRS),
        "secret_filename_policy": SECRET_NAME_RE.pattern,
    }
    label = sys.argv[1].strip().lower() if len(sys.argv) > 1 else ""
    if label and not re.fullmatch(r"[a-z0-9_-]+", label):
        raise SystemExit("Label may contain only lowercase letters, digits, underscores and hyphens.")
    suffix = f"-{label}" if label else ""
    output = OUT / f"source-inventory-and-fingerprint{suffix}.json"
    output.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
    summary = {
        "generated_at_utc": datetime.now(timezone.utc).isoformat(),
        "projects": [{
            "project": p["project"], "git": p["git"],
            "aggregate_sha256": p["fingerprint"]["aggregate_sha256"],
            "file_count": p["fingerprint"]["file_count"],
            "category_counts": p["category_counts"],
            "models": len(p["models"]), "backend_route_statements": len(p["backend_routes"]),
            "frontend_paths": len(p["frontend_routes"]), "migrations": len(p["migrations"]),
            "screens": len(p["screens"]), "tests": len(p["tests"]),
        } for p in result["projects"]]
    }
    (OUT / f"source-baseline-summary{suffix}.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
