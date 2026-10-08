#!/usr/bin/env python3
"""Check publishable files without printing secret values. Run from any directory.

With Git, checks tracked and untracked non-ignored files. With no Git metadata,
checks the complete directory, so only run against a cleaned release tree.
"""
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
EXAMPLES = {'api/.example.env', 'web/.env.example', 'deploy/.env.example'}
ALLOWED_BINARIES = {
    'api/public/favicon.ico',
    'web/public/icons/icon-192.png', 'web/public/icons/icon-512.png',
    'web/public/icons/apple-touch-icon.png',
    'docs/screenshots/desktop-home.png', 'docs/screenshots/desktop-project.png',
    'docs/screenshots/mobile-home.png', 'docs/screenshots/mobile-note.png',
    # Contact image explicitly approved by the maintainer for public display.
    'docs/images/wechat-contact.jpg',
}
BLOCKED_PARTS = {
    '.signing', '.claude', '.codex', '.playwright-cli', 'node_modules', 'vendor',
    'output', 'backups', 'dist', 'dev-dist', 'build', '.idea', '.vscode',
}
BLOCKED_SUFFIXES = {'.pem', '.key', '.jks', '.keystore', '.p12', '.pfx', '.db',
                    '.sqlite', '.sqlite3', '.log', '.har', '.apk', '.aab', '.zip', '.gz'}
PATTERNS = {
    'private key': re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----'),
    'cloud credential': re.compile(r'\b(?:AKIA[A-Z0-9]{16}|AKID[A-Za-z0-9]{28,40})\b'),
    'service token': re.compile(r'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{50,}|sk-[A-Za-z0-9_-]{24,})\b'),
    'JWT': re.compile(r'\beyJ[A-Za-z0-9_-]{12,}\.eyJ[A-Za-z0-9_-]{12,}\.[A-Za-z0-9_-]{12,}'),
    'personal local path': re.compile(r'/(?:Users|Volumes/work)/[^\s\"\'<>]+'),
}

def public_files():
    if (ROOT / '.git').exists():
        result = subprocess.run(['git', '-C', str(ROOT), 'ls-files', '-z',
                                 '--cached', '--others', '--exclude-standard'],
                                check=True, capture_output=True)
        return [ROOT / name.decode() for name in sorted(set(result.stdout.split(b'\0'))) if name]
    return [p for p in ROOT.rglob('*') if p.is_file() or p.is_symlink()]

def main():
    errors = []
    files = public_files()
    for p in files:
        name = p.relative_to(ROOT).as_posix()
        if p.is_symlink():
            errors.append((name, 'symlink is not allowed in a release'))
            continue
        if p.name == '.DS_Store' or BLOCKED_PARTS.intersection(p.relative_to(ROOT).parts):
            errors.append((name, 'private/generated directory or file'))
        if p.suffix.lower() in BLOCKED_SUFFIXES:
            errors.append((name, 'private/generated file extension'))
        if ('.env' in p.name or p.name == 'auth.json') and name not in EXAMPLES:
            errors.append((name, 'environment or authentication file'))
        if any(name.startswith(prefix) for prefix in ['api/runtime/', 'api/storage/uploads/', 'deploy/certs/']) and p.name not in {'.gitignore', '.gitkeep'}:
            errors.append((name, 'runtime, upload or certificate content'))
        if p.suffix == '.sql' and name != 'api/database/schema.sql':
            errors.append((name, 'unexpected SQL export'))
        if name in ALLOWED_BINARIES:
            continue
        try:
            content = p.read_text(encoding='utf-8')
        except (UnicodeError, OSError):
            errors.append((name, 'unexpected binary or unreadable file'))
            continue
        for label, pattern in PATTERNS.items():
            if pattern.search(content):
                errors.append((name, label))
        if name in EXAMPLES:
            for line in content.splitlines():
                match = re.match(r'^\s*([A-Z_]+)\s*=\s*(.*?)\s*$', line)
                if match and re.search(r'(SECRET|PASSWORD|DB_PASS|API_KEY|ACCESS_KEY|APP_KEY|VAPID_PRIVATE)', match[1]) and match[2]:
                    errors.append((name, 'non-empty credential placeholder: ' + match[1]))
        if name == 'api/database/schema.sql':
            if re.search(r'(?im)^\s*(?:INSERT|REPLACE|UPDATE|DELETE|LOAD\s+DATA)\b', content):
                errors.append((name, 'SQL contains data statements'))
    for name, reason in errors:
        print(f'FAIL {name}: {reason}')
    if errors:
        return 1
    print(f'PASS: {len(files)} publishable files checked; no blocked files or matching secret patterns.')
    return 0

if __name__ == '__main__':
    sys.exit(main())
