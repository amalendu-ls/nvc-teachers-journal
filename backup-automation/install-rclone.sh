#!/bin/bash
# One-time: download rclone (official build) into ~/tj-backup and verify its SHA-256 checksum.
set -euo pipefail
B=/home2/<cpanel-user>/tj-backup
LOG=$B/install.log
exec >>"$LOG" 2>&1
echo "=== $(date) install start (arch: $(uname -m))"
cd "$B"
VER=$(curl -fsSL https://downloads.rclone.org/version.txt | awk '{print $2}')
echo "latest: $VER"
ZIP="rclone-${VER}-linux-amd64.zip"
curl -fsSL -o "$ZIP" "https://downloads.rclone.org/${VER}/${ZIP}"
curl -fsSL -o SHA256SUMS "https://downloads.rclone.org/${VER}/SHA256SUMS"
WANT=$(grep " ${ZIP}\$" SHA256SUMS | awk '{print $1}')
HAVE=$(sha256sum "$ZIP" | awk '{print $1}')
echo "sha256 want=$WANT have=$HAVE"
if [ -z "$WANT" ] || [ "$WANT" != "$HAVE" ]; then echo "CHECKSUM MISMATCH - aborting"; rm -f "$ZIP"; exit 1; fi
if command -v unzip >/dev/null 2>&1; then
  unzip -o -j "$ZIP" "*/rclone" -d "$B" >/dev/null
else
  python3 - "$ZIP" "$B" <<'PY'
import sys, zipfile
z = zipfile.ZipFile(sys.argv[1])
for n in z.namelist():
    if n.endswith('/rclone'):
        open(sys.argv[2] + '/rclone', 'wb').write(z.read(n))
PY
fi
chmod 700 "$B/rclone"
rm -f "$ZIP" SHA256SUMS
"$B/rclone" version | head -1
echo "=== install OK"
