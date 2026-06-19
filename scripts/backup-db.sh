#!/usr/bin/env bash
# ============================================================
# Daily MySQL backup for MSME Tracker
#
# Crontab (runs at 02:00 IST = 20:30 UTC):
#   30 20 * * * /var/www/msme-tracker/scripts/backup-db.sh >> /var/log/backup-db.log 2>&1
#
# Restores:
#   mysql -u$DB_USER -p$DB_PASS $DB_NAME < /backup/msme-tracker/YYYY-MM-DD.sql.gz | gunzip
#
# Retention: 7 days local + 30 days on S3 (optional; needs awscli configured)
# ============================================================

set -euo pipefail

# Load .env for DB credentials (grep + export; avoids sourcing the whole file)
ENV_FILE="/var/www/msme-tracker/.env"
DB_HOST=$(grep -E '^DB_HOST=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_PORT=$(grep -E '^DB_PORT=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_NAME=$(grep -E '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_USER=$(grep -E '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
DB_PASS=$(grep -E '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')

BACKUP_DIR="/backup/msme-tracker"
DATE=$(date +%Y-%m-%d)
FILENAME="${BACKUP_DIR}/${DATE}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date -u '+%Y-%m-%d %H:%M:%S UTC')] Starting backup of ${DB_NAME}..."

# Dump with single-transaction for InnoDB (no table locks during dump)
mysqldump \
    --host="${DB_HOST:-127.0.0.1}" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    "$DB_NAME" | gzip -9 > "$FILENAME"

echo "[$(date -u '+%Y-%m-%d %H:%M:%S UTC')] Backup saved: ${FILENAME} ($(du -h "$FILENAME" | cut -f1))"

# Prune local backups older than 7 days
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +7 -delete
echo "[$(date -u '+%Y-%m-%d %H:%M:%S UTC')] Pruned local backups older than 7 days."

# Optional: upload to S3 (uncomment and set BACKUP_S3_BUCKET in .env)
# S3_BUCKET=$(grep -E '^BACKUP_S3_BUCKET=' "$ENV_FILE" | cut -d= -f2 | tr -d '"')
# if [ -n "${S3_BUCKET:-}" ]; then
#     aws s3 cp "$FILENAME" "s3://${S3_BUCKET}/daily/${DATE}.sql.gz" --storage-class STANDARD_IA
#     echo "[$(date -u '+%Y-%m-%d %H:%M:%S UTC')] Uploaded to s3://${S3_BUCKET}/daily/${DATE}.sql.gz"
# fi

echo "[$(date -u '+%Y-%m-%d %H:%M:%S UTC')] Backup complete."
