> [!NOTE]
> This repository contains modified versions of open-source software. The modifications are made to enhance compatibility with older Android devices. All original licenses and attributions are preserved.

# j3-server-setup

A specialized setup for running server applications on Android devices using a modified TermOnePlus environment. This repository is particularly useful for older Android devices (Android 5-6) that only support termux-21.

## Overview

This repository provides a complete setup for establishing a functional server environment on Android devices using a modified TermOnePlus distribution. It includes:

- Custom TermOnePlus APK with the package name `com.termux`
- Enhanced terminal font (DejaVuSansMNerdFontMono-Regular)
- Pre-configured binary packages
- Scripts for server management and automation
- Step-by-step installation instructions

## Requirements

- Android device running Android 5 or 6
- At least 1GB of free storage space
- Internet connection for initial setup

## Repository Structure

```
j3-server-setup/
+-- apk/
|   +-- termoneplus-custom.apk
|   \-- termoneplus-font-dejavu-nerd.apk
+-- prebuilt/
|   +-- bin/
|   |   +-- ngrok
|   |   \-- cloudflared
|   \-- debs/
|       +-- essential-packages.zip
|       \-- additional-packages.zip
+-- scripts/
|   +-- server.sh
|   \-- battery.php
+-- setup/
|   +-- termux-bootstrap.zip
|   \-- config-files.zip
\-- docs/
    +-- installation.md
    +-- troubleshooting.md
    \-- scripts-usage.md
```

## Quick Start

1. Download and install the custom TermOnePlus APK from the `apk` directory
2. Install the Nerd Font APK for enhanced terminal display
3. Follow the detailed installation guide in [docs/installation.md](docs/installation.md)

## Detailed Installation

See the [Installation Guide](docs/installation.md) for complete step-by-step instructions.

## Scripts

### server.sh

A utility script to manage server services (nginx, php-fpm, cloudflared, sshd):

```
Usage: server.sh {start|stop} [--force]
```

### battery.php

A PHP script for monitoring battery status and performing related operations.

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2.0 (CSSM-ULv2). See the [LICENSE](LICENSE) file for details.
