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
cp -r /storage/emulated/0/Download/data/data/com.termux/files/usr/ /data/data/com.termux/files/.
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
mkdir -p /data/data/com.termux/files/usr/var/lib/dpkg
mkdir -p /data/data/com.termux/files/usr/var/log/apt
mkdir -p /data/data/com.termux/files/usr/var/cache/apt/archives
mkdir -p /data/data/com.termux/files/usr/etc/apt/sources.list.d
mkdir -p /data/data/com.termux/files/usr/etc/apt/apt.conf.d
mkdir -p /data/data/com.termux/files/usr/etc/apt/preferences.d
mkdir -p /data/data/com.termux/files/usr/tmp

touch /data/data/com.termux/files/usr/var/lib/dpkg/status
touch /data/data/com.termux/files/usr/var/lib/dpkg/available
touch /data/data/com.termux/files/usr/var/lib/dpkg/diversion
touch /data/data/com.termux/files/usr/var/lib/dpkg/updates
```

## Step 5: Install Required Packages

Install the prepackaged DEB files:

1. Download the package archives from the `prebuilt/debs` directory
2. Extract them to your download folder
4. Install using dpkg:

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

## Step 7: Set Up Shell Configuration

Configure your shell environment (assuming ZSH):

1. Create or edit `~/.zshrc`:
   ```bash
   # Add to ~/.zshrc
   export PATH=/data/data/com.termux/files/usr/bin:$PATH
   export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib:$LD_LIBRARY_PATH
   export PREFIX=/data/data/com.termux/files/usr

   export LANG=en_US.UTF-8
   export LC_ALL=en_US.UTF-8

   export SHELL=/data/data/com.termux/files/usr/bin/zsh

   # Add useful aliases
   alias ngrok="termux-chroot ngrok"
   alias cloudflared="termux-chroot cloudflared"
   ```

2. Install Oh My Zsh (optional):
   ```bash
   sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
   ```

3. Install ZSH syntax highlighting:
   ```bash
   git clone https://github.com/zsh-users/zsh-syntax-highlighting.git
   echo "source ${(q-)PWD}/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh" >> ${ZDOTDIR:-$HOME}/.zshrc
   ```

4. Install PowerLevel10k:
   ```bash
   git clone --depth=1 https://github.com/romkatv/powerlevel10k.git $ZSH_CUSTOM/themes/powerlevel10k
   ```

5. Update your `~/.zshrc` file to use PowerLevel10k:
   ```bash
   sed -i 's|ZSH_THEME="robbyrussell"|ZSH_THEME="powerlevel10k/powerlevel10k"|' ~/.zshrc
   ```

6. Apply the changes:
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

1. Configure Nginx:
   ```bash
   mkdir -p ~/www
   echo "Hello World" > /data/data/com.termux/files/usr/share/nginx/html/index.html
   ```

2. Start the server:
   ```bash
   ~/server.sh start
   ```

### SSH Access Setup

1. Generate SSH keys:
   ```bash
   ssh-keygen -t ed25519
   ```

2. Configure password-less login (optional):
   ```bash
   mkdir -p ~/.ssh
   touch ~/.ssh/authorized_keys
   # Add your public key to authorized_keys
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

## Troubleshooting

If you encounter any issues, refer to [troubleshooting.md](troubleshooting.md) for common problems and solutions.

## Next Steps

Now that your environment is set up, learn how to use the server management scripts in [scripts-usage.md](scripts-usage.md).
