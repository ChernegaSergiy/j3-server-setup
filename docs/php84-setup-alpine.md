# PHP 8.4 Setup on Legacy Termux (Android 7 / ARMv7) via Alpine Linux

This guide explains how to install a modern **PHP 8.4** environment on legacy Android devices (Android 7.0 / Termux 0.118) where official packages are outdated (PHP 7.3). We use a manual **Alpine Linux** installation running via `proot`, without root access.

The architecture follows the UNIX principle — one tool, one job:
- **`alpine`** — the base launcher that boots the proot environment
- **`php84`** — a thin wrapper that calls `alpine` and runs the PHP interpreter

---

## 1. Install Prerequisites

Install the necessary tools: `proot` for virtualization, `wget` for downloading, `tar` for extraction.

```sh
pkg upgrade
pkg install proot wget tar
```

---

## 2. Download and Extract Alpine Linux

We use the **ARMv7** Mini Root Filesystem of Alpine Linux.

Create the directory:
```sh
mkdir -p $HOME/alpine
cd $HOME/alpine
```

Download the rootfs:
```sh
wget https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/armv7/alpine-minirootfs-3.21.0-armv7.tar.gz
```

Extract it (`--exclude='dev'` avoids permission errors):
```sh
tar -xf alpine-minirootfs-3.21.0-armv7.tar.gz --exclude='dev'
```

Remove the archive to save space:
```sh
rm alpine-minirootfs-3.21.0-armv7.tar.gz
```

---

## 3. Create the Base `alpine` Launcher

This script is the core of the setup. It starts the proot environment, binds system folders and your Termux home, and sets up DNS. If called with arguments (e.g. `alpine apk add nano`), it executes them inside Alpine. If called without arguments, it opens an interactive shell.

```sh
cat << 'EOF' > $PREFIX/bin/alpine
#!/data/data/com.termux/files/usr/bin/bash
ALPINE_ROOT="$HOME/alpine"

if [ ! -d "$ALPINE_ROOT" ]; then
    echo "Error: Alpine directory not found at $ALPINE_ROOT"
    exit 1
fi

# Unset variables that may conflict with Linux binaries
unset LD_PRELOAD

proot \
    -r $ALPINE_ROOT \
    -0 \
    -w /root \
    -b /dev \
    -b /proc \
    -b /sys \
    -b /data/data/com.termux/files/home:/root/home \
    /bin/sh -c "
        export PATH=/bin:/usr/bin:/sbin:/usr/sbin
        echo 'nameserver 8.8.8.8' > /etc/resolv.conf
        if [ -n \"\$1\" ]; then
            exec \"\$@\"
        else
            exec /bin/sh -l
        fi
    " -- "$@"
EOF
chmod +x $PREFIX/bin/alpine
```

---

## 4. Create the `php84` Wrapper

This script calls `alpine` and adds self-healing logic: if PHP 8.4 is not yet installed, it configures the Alpine Edge repositories and installs it automatically on the first run.

```sh
cat << 'EOF' > $PREFIX/bin/php84
#!/data/data/com.termux/files/usr/bin/bash
exec alpine /bin/sh -c "
    if [ ! -f /usr/bin/php84 ]; then
        echo 'First run detected: Installing PHP 8.4 (Edge)...'
        echo 'http://dl-cdn.alpinelinux.org/alpine/edge/main' > /etc/apk/repositories
        echo 'http://dl-cdn.alpinelinux.org/alpine/edge/community' >> /etc/apk/repositories
        echo 'http://dl-cdn.alpinelinux.org/alpine/edge/testing' >> /etc/apk/repositories
        apk update && apk add --no-cache \
            php84 php84-cli php84-phar \
            php84-mbstring php84-openssl php84-curl \
            php84-tokenizer php84-dom php84-xmlwriter \
            php84-sqlite3 php84-pdo_sqlite || exit 1
    fi
    exec php84 \"\$@\"
" -- "$@"
EOF
chmod +x $PREFIX/bin/php84
```

---

## 5. First Run and Verification

The first run downloads and installs PHP packages (~1–2 minutes):

```sh
php84 -v
```

**Expected output:**
```
PHP 8.4.x (cli) (built: ...)
Copyright (c) The PHP Group
...
```

---

## 6. PHP-FPM Setup

PHP-FPM requires a dedicated non-root user and a configured pool. All of the following steps are done **inside the Alpine shell** — enter it once and run everything there.

```sh
alpine
```

### Install PHP-FPM

```sh
apk add php84-fpm
```

### Create a Dedicated User

PHP-FPM refuses to run as root by default. Create the `www-data` user and group:

```sh
delgroup www-data 2>/dev/null || true
adduser -D -H -s /sbin/nologin www-data
```

Verify:
```sh
id www-data
```

Expected output:
```
uid=100(www-data) gid=101(www-data) groups=101(www-data)
```

### Configure the FPM Pool

```sh
sed -i 's/^;*user = .*/user = www-data/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^;*group = .*/group = www-data/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^;*listen = .*/listen = 127.0.0.1:9000/' /etc/php84/php-fpm.d/www.conf
```

Verify:
```sh
grep -E "^user|^group|^listen" /etc/php84/php-fpm.d/www.conf
```

Expected:
```
user = www-data
group = www-data
listen = 127.0.0.1:9000
```

### Set File Permissions

Grant `www-data` access to your web files (assuming your web root is `/root/home/www`):

```sh
chown -R www-data:www-data /root/home/www
find /root/home/www -type d -exec chmod 755 {} \;
find /root/home/www -type f -exec chmod 644 {} \;
```

### Start PHP-FPM

```sh
php-fpm84 -D
```

Verify it's running:
```sh
ps aux | grep php-fpm
netstat -tlnp | grep 9000
```

Now exit the Alpine shell:
```sh
exit
```

### Alternative: Running as Root (Not Recommended)

If user setup causes issues, you can run as root — but avoid this in production:

```sh
alpine
sed -i 's/^user = www-data/user = root/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^group = www-data/group = root/' /etc/php84/php-fpm.d/www.conf
php-fpm84 -R -D
exit
```

---

## 7. Service Manager Integration

Since auto-starting services via `.profile` is unreliable (it fires on every shell entry, not on boot), use a `services.conf` to manage processes explicitly:

```sh
#######################################
# PHP-FPM Configuration
#######################################
CONF_PROCESS["php-fpm"]="php-fpm84"
CONF_PIDFILE["php-fpm"]="$HOME/alpine/run/php-fpm84.pid"
CONF_START["php-fpm"]="alpine php-fpm84"
CONF_REQUIRED["php-fpm"]="alpine"
```

Start PHP-FPM through the service manager, not via a shell profile.

---

## Usage

Your Termux home (`~`) is mapped to `/root/home` inside Alpine.

**Run a PHP script:**
```sh
php84 home/bot.php
```

**Enter the Alpine shell** (to install packages, inspect the system, etc.):
```sh
alpine
# You are now inside Alpine. Type 'exit' to leave.
apk search swoole
apk add package_name
```

**Run a one-off Alpine command from Termux:**
```sh
alpine apk update
```

**File paths:** `~/project/script.php` in Termux → `home/project/script.php` inside Alpine.
