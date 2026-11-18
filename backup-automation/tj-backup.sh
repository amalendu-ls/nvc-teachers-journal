#!/bin/bash
# Teachers' Journal weekly backup -> Google Drive (via rclone)
#  - database dump, uploaded files (ojs-files), site public files/config/plugins
#  - keeps 12 weeks in Google Drive, last 2 runs on the server
set -euo pipefail
umask 077
H=/home2/<cpanel-user>
B=$H/tj-backup
OJS=$H/journal.nvc.ac.in
REMOTE="gdrive:TeachersJournal-Backups"
KEEP_DAYS=85            # ~12 weeks
STAMP=$(date +%Y-%m-%d)
WORK=$B/work/$STAMP
LOG=$B/backup.log
mkdir -p "$WORK"
exec >>"$LOG" 2>&1
echo "=== $(date) backup start"

# Read a key from the [database] section of OJS config.inc.php
cfg="$OJS/config.inc.php"
ini() {
  awk -v k="$1" -v q="'" '
    /^[ \t]*\[database\]/ { s = 1; next }
    /^[ \t]*\[/            { s = 0 }
    s && $0 ~ ("^[ \t]*" k "[ \t]*=") {
      sub(/^[^=]*=[ \t]*/, ""); sub(/[ \t\r]*$/, "")
      f = substr($0, 1, 1); l = substr($0, length($0), 1)
      if (length($0) >= 2 && f == l && (f == "\"" || f == q)) $0 = substr($0, 2, length($0) - 2)
      print; exit
    }' "$cfg"
}
DBH=$(ini host); DBU=$(ini username); DBP=$(ini password); DBN=$(ini name)

export MYSQL_PWD="$DBP"
if ! mysqldump -h "$DBH" -u "$DBU" --single-transaction --quick --no-tablespaces "$DBN" | gzip -9 > "$WORK/database-$DBN-$STAMP.sql.gz"; then
  has() { case "$DBP" in *"$1"*) echo y ;; *) echo n ;; esac; }
  echo "mysqldump failed (user=$DBU host=$DBH db=$DBN pwlen=${#DBP} dq=$(has '"') sq=$(has "'") hash=$(has '#') semi=$(has ';') bs=$(has '\'))"
  exit 1
fi
unset MYSQL_PWD
echo "database: $(du -h "$WORK/database-$DBN-$STAMP.sql.gz" | cut -f1)"

tar -czf "$WORK/ojs-files-$STAMP.tar.gz" -C "$H" --exclude='ojs-files/temp' ojs-files
echo "ojs-files: $(du -h "$WORK/ojs-files-$STAMP.tar.gz" | cut -f1)"

tar -czf "$WORK/site-public-config-plugins-$STAMP.tar.gz" -C "$OJS" public config.inc.php plugins .htaccess
echo "site: $(du -h "$WORK/site-public-config-plugins-$STAMP.tar.gz" | cut -f1)"

"$B/rclone" --config "$B/rclone.conf" copy "$WORK" "$REMOTE/$STAMP" --retries 3
echo "uploaded to $REMOTE/$STAMP"

"$B/rclone" --config "$B/rclone.conf" delete "$REMOTE" --min-age "${KEEP_DAYS}d" || true
"$B/rclone" --config "$B/rclone.conf" rmdirs "$REMOTE" --leave-root || true

# keep only the 2 newest local copies
ls -1d "$B"/work/*/ 2>/dev/null | sort | head -n -2 | xargs -r rm -rf
echo "=== $(date) backup OK"
