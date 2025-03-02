# Detailed Installation Guide

This guide provides comprehensive instructions for setting up the j3-server environment on an Android device with limited Termux compatibility (particularly Android 5-6 devices).

## Prerequisites

Before starting the installation, ensure you have:

- Downloaded both APK files from the `apk` directory
- At least 1GB of free storage space
- Enabled "Install from Unknown Sources" in your Android settings

## Step 1: Install the Modified TermOnePlus APK

1. Browse to the `apk` directory in this repository
2. Download and install `termoneplus-custom.apk`
3. Install the font package `termoneplus-font-dejavu-nerd.apk`
4. Open the newly installed TermOnePlus application

## Step 2: Extract the Bootstrap Files

1. Download `termux-bootstrap.zip` from the `setup` directory to your device
2. Extract the archive to your internal storage (preferably to `/storage/emulated/0/Download/`)
3. In TermOnePlus, run:

```bash
cp -r /storage/emulated/0/Download/data/data/com.termux/files /data/data/com.termux/
```

## Step 3: Configure the Environment

Set up the necessary environment variables:

```bash
export PATH=/data/data/com.termux/files/usr/bin:$PATH
export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib:$LD_LIBRARY_PATH
export PREFIX=/data/data/com.termux/files/usr
```

## Step 4: Create Required Directories

Create the necessary directory structure:

```bash
mkdir -p \
  /data/data/com.termux/files/usr/var/lib/dpkg \
  /data/data/com.termux/files/usr/var/log/apt \
  /data/data/com.termux/files/usr/var/cache/apt/archives \
  /data/data/com.termux/files/usr/etc/apt/sources.list.d \
  /data/data/com.termux/files/usr/etc/apt/apt.conf.d \
  /data/data/com.termux/files/usr/etc/apt/preferences.d \
  /data/data/com.termux/files/usr/tmp && \
touch \
  /data/data/com.termux/files/usr/var/lib/dpkg/status \
  /data/data/com.termux/files/usr/var/lib/dpkg/available \
  /data/data/com.termux/files/usr/var/lib/dpkg/diversion \
  /data/data/com.termux/files/usr/var/lib/dpkg/updates
```

## Step 5: Install Required Packages

Install the prepackaged DEB files:

1. Download the package archives from the `prebuilt/debs` directory
2. Extract them to your download folder
3. Install using dpkg:

```bash
dpkg --force-all -i /storage/emulated/0/Download/debs/*.deb
```

## Step 6: Install Custom Binaries

Install the precompiled binaries for tools not available through standard repositories:

### Ngrok

```bash
cp /storage/emulated/0/Download/prebuilt/bin/ngrok /data/data/com.termux/files/usr/bin/
chmod +x /data/data/com.termux/files/usr/bin/ngrok
```

### Cloudflared

```bash
cp /storage/emulated/0/Download/prebuilt/bin/cloudflared /data/data/com.termux/files/usr/bin/
chmod +x /data/data/com.termux/files/usr/bin/cloudflared
```

## Step 7: Configure the Shell Environment

### 1. Set Up `~/.shrc`

Open or create the `~/.shrc` file and add the following lines to configure the environment:

```bash
# Add to ~/.shrc
export PATH=/data/data/com.termux/files/usr/bin:$PATH
export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib:$LD_LIBRARY_PATH
export PREFIX=/data/data/com.termux/files/usr

export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8

export SHELL=/data/data/com.termux/files/usr/bin/zsh

# Start Zsh automatically
exec zsh
```

### 2. Set Up `~/.zshrc`

Open or create the `~/.zshrc` file and add the following aliases for convenience:

```bash
# Add to ~/.zshrc
alias ngrok="termux-chroot ngrok"
alias cloudflared="termux-chroot cloudflared"
```

### 3. Apply the Changes

To apply the new settings, run the following command in TermOnePlus:

```bash
source ~/.shrc
```

### 4. Optional: Enhance Zsh

If you want to enhance your Zsh experience, follow these optional steps:

1. Install Oh My Zsh:
   ```bash
   sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
   ```

2. Install Zsh Syntax Highlighting:
   ```bash
   git clone https://github.com/zsh-users/zsh-syntax-highlighting.git
   echo "source ${(q-)PWD}/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh" >> ${ZDOTDIR:-$HOME}/.zshrc
   ```

3. Install PowerLevel10k Theme:
   ```bash
   git clone --depth=1 https://github.com/romkatv/powerlevel10k.git $ZSH_CUSTOM/themes/powerlevel10k
   ```

4. Update `~/.zshrc` to Use PowerLevel10k:
   ```bash
   sed -i 's|ZSH_THEME="robbyrussell"|ZSH_THEME="powerlevel10k/powerlevel10k"|' ~/.zshrc
   ```

5. Apply the Changes:
   ```bash
   source ~/.zshrc
   ```

## Step 8: Install Server Management Scripts

1. Copy the server management script:
   ```bash
   cp /storage/emulated/0/Download/scripts/server.sh ~/
   chmod +x ~/server.sh
   ```

2. Copy the battery monitoring script:
   ```bash
   cp /storage/emulated/0/Download/scripts/battery.php ~/
   chmod +x ~/battery.php
   ```

## Step 9: Configure Server Components

### Web Server Setup

1. Create the Default Web Directory

   Ensure the directory for the web server exists:

   ```bash
   mkdir -p /data/data/com.termux/files/usr/share/nginx/html
   ```

3. Create a Sample Index File

   Create a test `index.html` file in the directory:

   ```bash
   echo "Hello World" > /data/data/com.termux/files/usr/share/nginx/html/index.html
   ```

5. Start the server using the provided script:

   ```bash
   ~/server.sh start
   ```

### SSH Access Setup

1. Generate SSH keys for secure access:

   ```bash
   ssh-keygen -t ed25519
   ```

   Press `Enter` to accept the default values.

2. Configure password-less login (optional)

   If you want to enable password-less login, add your public key to `authorized_keys`:

   ```bash
   mkdir -p ~/.ssh
   touch ~/.ssh/authorized_keys
   cat ~/.ssh/id_ed25519.pub >> ~/.ssh/authorized_keys
   ```

3. Start the SSH server:

   ```bash
   sshd
   ```

## Step 10: Testing Your Setup

1. Test if the web server is running:
   ```bash
   curl localhost:8080
   ```

2. Test if PHP is working:
   ```bash
   php -v
   ```

3. Test the server script:
   ```bash
   ~/server.sh start
   ```

## Step 11: Configure Nginx, PHP-FPM, and SSHD

1. Download `config-files.zip` from the `setup` directory to your device

2. Extract the archive to your internal storage (preferably to `/storage/emulated/0/Download/`)

3. Copy the configuration files to their respective directories:
   ```bash
   cp /storage/emulated/0/Download/nginx/nginx.conf /data/data/com.termux/files/usr/etc/nginx/nginx.conf
   cp /storage/emulated/0/Download/nginx/sites-available/default /data/data/com.termux/files/usr/etc/nginx/sites-available/default
   cp /storage/emulated/0/Download/php/php.ini /data/data/com.termux/files/usr/etc/php.ini
   cp /storage/emulated/0/Download/php/php-fpm.conf /data/data/com.termux/files/usr/etc/php-fpm.conf
   cp /storage/emulated/0/Download/ssh/sshd_config /data/data/com.termux/files/usr/etc/ssh/sshd_config
   ```

## Troubleshooting

If you encounter any issues, refer to [troubleshooting.md](troubleshooting.md) for common problems and solutions.

## Next Steps

Now that your environment is set up, learn how to use the server management scripts in [scripts-usage.md](scripts-usage.md).
