# PHP 8.4 Setup on Legacy Termux (Android 7 / ARMv7) via Alpine Linux

This guide explains how to install a modern **PHP 8.4** environment on legacy Android devices (Android 7.0 / Termux 0.118) using a manual **Alpine Linux** installation via `proot`, without root access.

The architecture follows the UNIX principle — one tool, one job:
- **`alpine`** — the base launcher that boots the proot environment
- **`php84`** — a thin wrapper that calls `alpine` and runs the PHP interpreter

---

## 1. Install Prerequisites

```sh
pkg upgrade
pkg install proot wget tar
```

---

## 2. Download and Extract Alpine Linux

```sh
mkdir -p $HOME/alpine
cd $HOME/alpine
wget https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/armv7/alpine-minirootfs-3.21.0-armv7.tar.gz
tar -xf alpine-minirootfs-3.21.0-armv7.tar.gz --exclude='dev'
rm alpine-minirootfs-3.21.0-armv7.tar.gz
```

---

## 3. Create the Base `alpine` Launcher

This script is the core of the setup. It starts the proot environment. If called with arguments (e.g. `alpine apk add nano`), it executes them. If called without arguments, it opens a shell.

```sh
cat << 'EOF' > $PREFIX/bin/alpine
#!/data/data/com.termux/files/usr/bin/bash

ALPINE_ROOT="$HOME/alpine"

if [ ! -d "$ALPINE_ROOT" ]; then
    echo "Error: Alpine directory not found at $ALPINE_ROOT"
    exit 1
fi

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

This script calls `alpine` and adds self-healing logic: if PHP 8.4 is not yet installed, it installs it automatically on first run.

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

### Install PHP-FPM

Enter the Alpine shell and install:

```sh
alpine
apk add php84-fpm
exit
```

### Create a Dedicated User

PHP-FPM refuses to run as root by default. Inside the Alpine shell:

```sh
alpine
delgroup www-data 2>/dev/null || true
adduser -D -H -s /sbin/nologin www-data
id www-data
exit
```

Expected output of `id www-data`:
```
uid=100(www-data) gid=101(www-data) groups=101(www-data)
```

### Configure the FPM Pool

```sh
alpine sed -i 's/^;*user = .*/user = www-data/' /etc/php84/php-fpm.d/www.conf
alpine sed -i 's/^;*group = .*/group = www-data/' /etc/php84/php-fpm.d/www.conf
alpine sed -i 's/^;*listen = .*/listen = 127.0.0.1:9000/' /etc/php84/php-fpm.d/www.conf
```

Verify:

```sh
alpine grep -E "^user|^group|^listen" /etc/php84/php-fpm.d/www.conf
```

Expected:
```
user = www-data
group = www-data
listen = 127.0.0.1:9000
```

### Set File Permissions

```sh
alpine chown -R www-data:www-data /root/home/www
alpine find /root/home/www -type d -exec chmod 755 {} \;
alpine find /root/home/www -type f -exec chmod 644 {} \;
```

### Start PHP-FPM

```sh
alpine php-fpm84 -D
```

Verify it is running:

```sh
alpine ps aux | grep php-fpm
alpine netstat -tlnp | grep 9000
```

### Alternative: Running as Root (Not Recommended)

If user setup causes issues:

```sh
alpine sed -i 's/^user = www-data/user = root/' /etc/php84/php-fpm.d/www.conf
alpine sed -i 's/^group = www-data/group = root/' /etc/php84/php-fpm.d/www.conf
alpine php-fpm84 -R -D
```

---

## 7. Service Manager Integration

Since auto-starting via `.profile` is unreliable, use a `services.conf` to manage processes explicitly:

```sh
#######################################
# PHP-FPM Configuration
#######################################
CONF_PROCESS["php-fpm"]="php-fpm84"
CONF_PIDFILE["php-fpm"]="$HOME/alpine/run/php-fpm84.pid"
CONF_START["php-fpm"]="alpine php-fpm84"
CONF_REQUIRED["php-fpm"]="alpine"
```

Start PHP-FPM via the service manager, not via a shell profile.

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
alpine php-fpm84 -D
```

**File paths:** `~/project/script.php` in Termux → `home/project/script.php` inside Alpine.
