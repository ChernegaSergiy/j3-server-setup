# Samsung Galaxy J3 Server Setup

This repository contains installation instructions and necessary files to set up a fully functional server environment on Android devices with limited system resources, specifically targeting devices that only support termux-21 or running Android 5-6.

## Features

- Preconfigured Termux environment with essential system files
- Modified APK with custom package name (com.termux)
- Enhanced terminal font (DejaVuSansMNerdFontMono-Regular)
- Automated server management scripts
- Pre-built dpkg packages for offline installation

## Requirements

- Android device (works best on Android 5-6)
- Minimum 100MB of free storage
- File access permissions
- Installation of custom APKs enabled in settings

## Quick Start

1. Download and install the [modified Termux APK](https://github.com/yourusername/j3-server-setup/releases/download/v1.0/termux-modified.apk)

2. Extract the environment files to your device

3. Run the setup script:
   ```bash
   sh setup.sh
   ```

4. Start the server:
   ```bash
   server.sh start
   ```

## Detailed Instructions

See the [Installation Guide](docs/INSTALLATION.md) for step-by-step instructions.

## Available Scripts

- `server.sh` - Manages server processes (start/stop)
- `battery.php` - Monitors battery level and performs actions based on status

## Packages Included

### Utilities
- BusyBox 1.31.1
- Sed 4.7
- Grep 3.3
- Findutils 4.7.0
- Diffutils 3.7
- Coreutils 8.31
- Tar 1.32
- Bzip2 1.0.8
- Zlib 1.2.11
- Gzip 1.10

### Networking
- libcurl 7.67.0
- libnghttp2 1.39.2
- libiconv 1.16
- libandroid-support 24-6

### Security
- OpenSSL 1.1.1d
- libgpg-error 1.36
- libgcrypt 1.8.5
- Gpgv 2.2.17
- CA Certificates 1.0

### Package Management
- Apt 1.4.10
- Dpkg 1.19.7

### Development Tools
- Composer 2.8.2
- libc++ 20-3
- Nano 4.6
- Less 551

### System Tools
- Termux Licenses 1.1
- libbz2 1.0.8
- libandroid-glob 0.6
- Ncurses 6.1
- XZ-utils 5.2.4

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the CSSM Unlimited License v2 (CSSM-ULv2). See the [LICENSE](LICENSE) file for details.

## Acknowledgements

- Based on the original TermOnePlus application
- Special thanks to all contributors to the Termux project
