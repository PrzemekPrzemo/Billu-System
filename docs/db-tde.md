# MariaDB Transparent Data Encryption (TDE)

BiLLU stores PII (NIPs, addresses, contact data, invoice metadata) in
MySQL/MariaDB. The application does NOT encrypt PII at the column
level — encryption-at-rest is delegated to MariaDB's
`file_key_management` plugin. This keeps the data path transparent
(no application changes, indices still work) and the key material
out of the database files.

This guide covers the MariaDB 10.6+ deployment we ship to.

## Concepts

- **Data file encryption**: tablespace and `*.ibd` file pages are
  encrypted with AES-256.
- **Log file encryption**: `redo`, `undo`, binlog, relay log are
  encrypted with the same key store.
- **Key versioning**: MariaDB rotates by adding new key IDs to the key
  file; old data stays readable until rewritten.

## One-time setup

### 1. Generate the key file

```bash
sudo mkdir -p /etc/mysql/encryption
sudo openssl rand -hex 32 | awk 'BEGIN{n=1}{print n";"$0; n++}' \
  | sudo tee /etc/mysql/encryption/keyfile > /dev/null
sudo chown mysql:mysql /etc/mysql/encryption/keyfile
sudo chmod 600 /etc/mysql/encryption/keyfile
```

Each line is `<key_id>;<hex>`. We only need key id `1` for a fresh
install; rotation later appends `2;…`, `3;…`, etc.

### 2. Encrypt the key file

```bash
sudo openssl rand -hex 32 | sudo tee /etc/mysql/encryption/keyfile.key > /dev/null
sudo chown mysql:mysql /etc/mysql/encryption/keyfile.key
sudo chmod 400 /etc/mysql/encryption/keyfile.key

sudo openssl enc -aes-256-cbc -md sha1 -pbkdf2 \
  -pass file:/etc/mysql/encryption/keyfile.key \
  -in  /etc/mysql/encryption/keyfile \
  -out /etc/mysql/encryption/keyfile.enc

sudo rm /etc/mysql/encryption/keyfile
sudo chown mysql:mysql /etc/mysql/encryption/keyfile.enc
sudo chmod 400 /etc/mysql/encryption/keyfile.enc
```

The plaintext keyfile is removed; MariaDB only sees the encrypted form.

### 3. Configure MariaDB

`/etc/mysql/mariadb.conf.d/99-encryption.cnf`:

```ini
[mariadb]
# Plugin loader
plugin_load_add = file_key_management

# Key store
file_key_management_filename             = /etc/mysql/encryption/keyfile.enc
file_key_management_filekey              = FILE:/etc/mysql/encryption/keyfile.key
file_key_management_encryption_algorithm = aes_cbc

# Default-encrypt new tables
innodb_encrypt_tables            = ON
innodb_encrypt_temporary_tables  = ON
innodb_encrypt_log               = ON
innodb_encryption_threads        = 4
innodb_encryption_rotate_key_age = 7

# Binlog
encrypt_binlog = ON
aria_encrypt_tables = ON
```

Restart MariaDB:

```bash
sudo systemctl restart mariadb
```

### 4. Encrypt existing tables

New tables created after restart are encrypted automatically. Existing
tables need a one-time rewrite:

```sql
-- Connect as root
USE billu;
SELECT CONCAT('ALTER TABLE `', table_name, '` ENCRYPTED=YES;') AS sql_stmt
FROM information_schema.tables
WHERE table_schema = 'billu' AND engine = 'InnoDB';
```

Pipe the result back into the client. For very large tables run
during off-peak; `ALTER` rewrites the file.

### 5. Verify

```sql
SELECT space, name, encryption_state
FROM information_schema.innodb_tablespaces_encryption
WHERE encryption_state IS NOT NULL;
```

Every BiLLU tablespace should show `encryption_state = 1` (encrypted)
or `2` (in progress; will become 1 after the encryption thread
finishes).

## Backups

`mysqldump` produces plaintext SQL — encrypt it before storing:

```bash
mysqldump --single-transaction --routines --triggers billu \
  | gzip \
  | openssl enc -aes-256-cbc -salt -pbkdf2 \
      -pass file:/etc/mysql/backup/keyfile.key \
      -out /var/backups/billu-$(date +%F).sql.gz.enc
```

Use a SEPARATE keyfile for backups — losing both the live key and the
backup key together defeats the purpose of TDE.

## Operational notes

- Loss of `keyfile.key` = irrecoverable data loss. Back it up to a
  secrets manager (Vault, AWS KMS, 1Password) BEFORE encrypting any
  table.
- Rotation: append `key_id 2` to the plaintext keyfile, re-encrypt to
  `keyfile.enc`, restart, then `SET GLOBAL innodb_encrypt_tables = ON;`
  triggers background re-encryption with the newest key.
- TDE protects against offline disk theft, raw `*.ibd` exfiltration,
  and unencrypted backups. It does NOT protect against SQL injection,
  application bugs, or compromised DB credentials.
