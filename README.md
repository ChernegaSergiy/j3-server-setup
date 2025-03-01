# Samsung Galaxy J3 Server Setup

This repository contains detailed instructions for setting up a development environment on Android devices with TermOnePlus, especially for those running Android 5-6 and supporting only Termux 21. The repository includes the necessary files for installing packages, automating tasks, and customizing the terminal environment.

## Setup Instructions

Follow these steps to set up your development environment on an Android device with TermOnePlus.

### 1. Install Ngrok (for armv6 architecture)

Ngrok is not available for armv7l architecture via the standard repositories. You need to download the appropriate package manually from the official website.

#### Option 1: Download and Extract Ngrok Manually

1. Download the `ngrok` tarball for `armv6` from the official website: [Ngrok Downloads](https://ngrok.com/download).
2. Once downloaded, extract the files:

```bash
tar -xvzf /storage/emulated/0/Download/ngrok-v3-stable-linux-arm.tgz -C /data/data/com.termux/files/usr/bin
```

#### Option 2: Use the precompiled binary

Alternatively, if you have the `ngrok` binary, you can place it in the `/usr/bin/` directory:

```bash
cp /storage/emulated/0/Download/ngrok /data/data/com.termux/files/usr/bin/
chmod +x /data/data/com.termux/files/usr/bin/ngrok
```

### 2. Copy Termux Files to Internal Storage

Copy the necessary files from any folder that contains the libraries and utilities to your Termux environment.

```bash
cp -r /storage/emulated/0/Download/data/data/com.termux/files/usr/ /data/data/com.termux/files/.
```

### 3. Set Environment Variables

You need to export the following environment variables to configure the Termux environment:

```bash
export PATH=/data/data/com.termux/files/usr/bin:$PATH
export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib:$LD_LIBRARY_PATH
export PREFIX=/data/data/com.termux/files/usr
```

### 4. Create Required Directories and Files

You must create the required directories for dpkg and apt configurations:

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

### 5. Install Dependencies Using dpkg

Now, install the required dependencies by using dpkg:

```bahs
dpkg --force-all -i /storage/emulated/0/Download/debs/*.deb
```

### 6. Set Shell Startup

If you're using zsh or another shell, set up your shell startup by exporting the following:

```bash
# Set environment variables for TermOnePlus
export PATH=/data/data/com.termux/files/usr/bin:$PATH
export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib:$LD_LIBRARY_PATH
export PREFIX=/data/data/com.termux/files/usr

export LANG=en_US.UTF-8
export LC_ALL=en_US.UTF-8

export SHELL=/data/data/com.termux/files/usr/bin/zsh
zsh
```

### 7. Install zsh, PowerLevel10k, and Customize

If you're using zsh, install it and customize it with Oh My Zsh and the PowerLevel10k theme:

1. Install `zsh` if you haven't already:
    ```bash
    pkg install zsh
    ```

2. Install Oh My Zsh:
   ```bash
   sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
   ```

3. Install PowerLevel10k:
   ```bash
   git clone --depth=1 https://github.com/romkatv/powerlevel10k.git $ZSH_CUSTOM/themes/powerlevel10k
   ```

4. Update your `~/.zshrc` file to use PowerLevel10k:
   ```bash
   sed -i 's|ZSH_THEME="robbyrussell"|ZSH_THEME="powerlevel10k/powerlevel10k"|' ~/.zshrc
   ```

5. Apply the changes:
   ```bash
   source ~/.zshrc
   ```

### 7. Install zsh and Customize

If you're using zsh, install it and customize it with Oh My Zsh and plugins:

```bash
sh -c "$(curl -fsSL https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
git clone https://github.com/zsh-users/zsh-syntax-highlighting.git
echo "source ${(q-)PWD}/zsh-syntax-highlighting/zsh-syntax-highlighting.zsh" >> ${ZDOTDIR:-$HOME}/.zshrc
```

### 8. Add Useful Aliases

`termux-chroot` is required to run utilities like `ngrok` and `cloudflared` because these tools need access to an environment not available in the default Termux setup. They require a chroot environment to function properly. 

To make using these tools more convenient, aliases can be created to automatically invoke `termux-chroot` when running these utilities. This eliminates the need to type the full command each time.

Add the following lines to your `~/.zshrc` file:

```bash
alias ngrok="termux-chroot ngrok"
alias cloudflared="termux-chroot cloudflared"
```

Now, you can run these tools simply by typing their names, and they will automatically execute with `termux-chroot`.

### 9. Additional Customizations

You can add more customizations to your terminal, such as an MOTD greeting and neofetch for system info.

```bash
cd ~
toilet -f smmono9 "TermOnePlus"
echo -e "Welcome to TermOnePlus."
echo -e "\nPackage management commands:"
echo -e " - \033[34mpkg search <query>\033[0m: Search for a package"
echo -e " - \033[34mpkg install <package>\033[0m: Install a package"
echo -e " - \033[34mpkg upgrade\033[0m: Upgrade installed packages"
echo -e "\nFor support and documentation, visit our website: https://termoneplus.com\n"
```
