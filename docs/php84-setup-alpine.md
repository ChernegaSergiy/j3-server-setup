# PHP 8.4 Setup on Legacy Termux (Android 7 / ARMv7) via Alpine Linux

This guide explains how to install a modern **PHP 8.4** environment on legacy Android devices (Android 7.0 / Termux 0.118) where official packages are outdated (PHP 7.3). We will use a manual **Alpine Linux** installation running via `proot` without needing root access.

## 1. Install Prerequisites

Open Termux and install the necessary tools (`proot` for virtualization, `wget` for downloading, `tar` for extraction).

```sh
pkg upgrade
pkg install proot wget tar
```

## 2. Download and Extract Alpine Linux

We will use the **ARMv7** version of Alpine Linux (Mini Root Filesystem).

1. Create a directory for the system:
   ```sh
   mkdir -p $HOME/alpine
   cd $HOME/alpine
   ```

2. Download the latest Alpine rootfs (v3.21):
   ```sh
   wget https://dl-cdn.alpinelinux.org/alpine/v3.21/releases/armv7/alpine-minirootfs-3.21.0-armv7.tar.gz
   ```

3. Extract it (excluding `/dev` to avoid permission errors):
   ```sh
   tar -xf alpine-minirootfs-3.21.0-armv7.tar.gz --exclude='dev'
   ```

4. Clean up the archive to save space:
   ```sh
   rm alpine-minirootfs-3.21.0-armv7.tar.gz
   ```

## 3. Create the Launcher Script

We will create a script named `php84` in the global `bin` directory. This script handles the virtual environment startup and automatically installs PHP 8.4 if it's missing.

Run this entire block in Termux:

```sh
cat << 'EOF' > $PREFIX/bin/php84
#!/data/data/com.termux/files/usr/bin/bash

# Configuration: Path to the Alpine directory
ALPINE_ROOT="$HOME/alpine"

# Check if directory exists
if [ ! -d "$ALPINE_ROOT" ]; then
    echo "Error: Alpine directory not found at $ALPINE_ROOT"
    exit 1
fi

# Unset variables that might conflict with Linux binaries
unset LD_PRELOAD

# Start the environment via PROOT
# -r: Root directory
# -0: Simulate root user
# -b: Bind system folders and user home
proot \
    -r $ALPINE_ROOT \
    -0 \
    -w /root \
    -b /dev \
    -b /proc \
    -b /sys \
    -b /data/data/com.termux/files/home:/root/home \
    /bin/sh -c "
        # 1. Setup Standard Paths
        export PATH=/bin:/usr/bin:/sbin:/usr/sbin

        # 2. Setup DNS (Required for Internet)
        echo 'nameserver 8.8.8.8' > /etc/resolv.conf

        # 3. Auto-Install PHP 8.4 (Self-Healing)
        # If the binary is missing, we configure Edge repos and install it.
        if [ ! -f /usr/bin/php84 ]; then
            echo ' First run detected: Installing PHP 8.4 (Edge)...'

            # Add Alpine Edge repositories
            echo 'http://dl-cdn.alpinelinux.org/alpine/edge/main' > /etc/apk/repositories
            echo 'http://dl-cdn.alpinelinux.org/alpine/edge/community' >> /etc/apk/repositories
            echo 'http://dl-cdn.alpinelinux.org/alpine/edge/testing' >> /etc/apk/repositories

            # Update and Install
            apk update && \
            apk add --no-cache \
                php84 php84-cli php84-phar \
                php84-mbstring php84-openssl php84-curl \
                php84-tokenizer php84-dom php84-xmlwriter \
                php84-sqlite3 php84-pdo_sqlite || exit 1
        fi

        # 4. Execute Command
        if [ \"\$1\" ]; then
            exec php84 \"\$@\"
        else
            exec /bin/sh -l
        fi
    " -- "$@"
EOF
```

## 4. Make it Executable

Grant execution permissions to the script:

```sh
chmod +x $PREFIX/bin/php84
```

## 5. First Run and Verification

Run the following command. The **first time** you run it, it will take about 1-2 minutes to download and install PHP packages.

```sh
php84 -v
```

**Expected Output:**
```
PHP 8.4.16 (cli) (built: Jan 13 2026 ...) (NTS)
Copyright (c) The PHP Group
...
```

## 6. PHP-FPM Configuration and User Setup

PHP-FPM (FastCGI Process Manager) requires proper user/group configuration to run. By default, PHP-FPM refuses to run as root for security reasons.

### Install PHP-FPM

Inside the Alpine environment, install PHP-FPM:

```sh
php84
apk add php84-fpm
```

### Create a Dedicated User

PHP-FPM needs a non-root user. Create the `www-data` user and group:

```sh
# Inside Alpine shell:
delgroup www-data 2>/dev/null || true
adduser -D -H -s /sbin/nologin www-data
```

Verify the user was created:

```sh
id www-data
```

Expected output:

```
uid=100(www-data) gid=101(www-data) groups=101(www-data)
```

### Configure PHP-FPM Pool

Edit the PHP-FPM pool configuration using `sed`:

```sh
sed -i 's/^;*user = .*/user = www-data/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^;*group = .*/group = www-data/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^;*listen = .*/listen = 127.0.0.1:9000/' /etc/php84/php-fpm.d/www.conf
```

Verify the configuration:

```sh
cat /etc/php84/php-fpm.d/www.conf | grep -E "^user|^group|^listen"
```

Expected output:

```
user = www-data
group = www-data
listen = 127.0.0.1:9000
```

### Set File Permissions

Grant the `www-data` user access to your web files:

```sh
# Assuming your web root is /root/home/www
chown -R www-data:www-data /root/home/www
find /root/home/www -type d -exec chmod 755 {} \;
find /root/home/www -type f -exec chmod 644 {} \;
```

### Start PHP-FPM

Start the PHP-FPM service:

```sh
php-fpm84 -D
```

Verify it's running:

```sh
ps aux | grep php-fpm
netstat -tlnp | grep 9000
```

### Alternative: Running as Root

If you encounter issues with the `www-data` user, you can run PHP-FPM as root (not recommended for production):

```sh
# Update config to use root
sed -i 's/^user = www-data/user = root/' /etc/php84/php-fpm.d/www.conf
sed -i 's/^group = www-data/group = root/' /etc/php84/php-fpm.d/www.conf

# Start with root flag
php-fpm84 -R -D
```

### Auto-start PHP-FPM

To automatically start PHP-FPM, add it to your Alpine profile:

```sh
echo 'pgrep -x php-fpm84 > /dev/null || php-fpm84 -D 2>/dev/null' >> /root/.profile
```

This will start PHP-FPM each time you enter the Alpine shell with `php84`.

---

## Usage

**Important:** Your Termux home directory (`~`) is mapped to `/root/home` inside the Alpine environment.

- **Run a PHP script:**
  You must specify the `home/` prefix because the default working directory inside Alpine is `/root`.
  ```sh
  php84 home/bot.php
  ```

- **Enter the Alpine Shell (to install more packages):**
  ```sh
  php84
  # Now you are inside Alpine (type 'exit' to leave)
  apk search swoole
  apk add package_name
  ```

- **Managing Files:**
  You can edit files in Termux as usual. When running them via `php84`, just remember they are located in the `home/` folder inside the virtual environment.
  
  *Example:* `~/project/script.php` in Termux → `home/project/script.php` in php84.
