# Troubleshooting Guide

This guide addresses common issues you might encounter when setting up and using the j3-server environment on older Android devices.

## Installation Issues

### APK Installation Fails

**Symptoms:**
- "App not installed" error
- Installation process completes but app doesn't appear

**Possible Solutions:**
1. Ensure "Install from Unknown Sources" is enabled in your security settings
2. Check if you have enough storage space (at least 1GB free)
3. If a previous version exists, uninstall it first
4. Restart your device and try again

### Permission Denied Errors

**Symptoms:**
- "Permission denied" when running commands
- Unable to access files or directories

**Possible Solutions:**
1. Check file permissions:
   ```bash
   ls -la /path/to/file
   ```
2. Set proper permissions:
   ```bash
   chmod +x /path/to/file
   ```
3. For system directories, ensure TermOnePlus has proper permissions in Android settings

## Environment Setup Issues

### Missing Directories or Files

**Symptoms:**
- "No such file or directory" errors
- Commands not found

**Possible Solutions:**
1. Create missing directories manually (see installation.md)
2. Check if the bootstrap extraction completed correctly
3. Verify environment variables are set properly:
   ```bash
   echo $PATH
   echo $PREFIX
   echo $LD_LIBRARY_PATH
   ```

### Package Installation Failures

**Symptoms:**
- dpkg errors when installing packages
- "Dependency problems" messages

**Possible Solutions:**
1. Use force installation for essential packages:
   ```bash
   dpkg --force-all -i /path/to/package.deb
   ```
2. Install dependencies manually:
   ```bash
   dpkg -i /path/to/dependency.deb
   ```
3. Check for corrupt package files and re-download if necessary

## Runtime Issues

### Services Won't Start

**Symptoms:**
- `server.sh start` shows failure messages
- Services start but terminate immediately

**Possible Solutions:**
1. Check for port conflicts:
   ```bash
   netstat -tulpn
   ```
2. Ensure all dependencies are installed:
   ```bash
   for cmd in nginx php-fpm cloudflared sshd php; do
       command -v $cmd || echo "$cmd missing"
   done
   ```
3. Check service logs for errors:
   ```bash
   cat /data/data/com.termux/files/usr/var/log/nginx/error.log
   ```

### Cloudflared Tunnel Issues

**Symptoms:**
- Cloudflared starts but no connection is established
- "termux-chroot: not found" error

**Possible Solutions:**
1. Ensure termux-chroot is available:
   ```bash
   which termux-chroot
   ```
   If missing, install proot package:
   ```bash
   dpkg -i /storage/emulated/0/Download/debs/proot*.deb
   ```
2. Verify correct tunnel configuration
3. Check if the authentication token is valid

### Battery Script Not Working

**Symptoms:**
- Battery monitoring not running
- No log entries being created

**Possible Solutions:**
1. Check if PHP is installed and working:
   ```bash
   php -v
   ```
2. Verify script permissions:
   ```bash
   chmod +x ~/battery.php
   ```
3. Run the script manually to check for errors:
   ```bash
   php ~/battery.php
   ```

## Performance Issues

### Slow Terminal Response

**Symptoms:**
- Commands take a long time to execute
- Terminal feels sluggish

**Possible Solutions:**
1. Reduce the number of running services:
   ```bash
   ./server.sh stop cloudflared
   ```
2. Clear package caches:
   ```bash
   rm -rf /data/data/com.termux/files/usr/var/cache/apt/archives/*
   ```
3. Use lighter alternatives for some services (e.g., lighttpd instead of nginx)

### High Battery Drain

**Symptoms:**
- Battery depletes quickly when server is running
- Device gets unusually warm

**Possible Solutions:**
1. Disable unnecessary services
2. Adjust battery.php parameters for more aggressive power saving
3. Use a lower refresh rate for monitoring services
4. Consider adding a cooling solution if using the device for extended periods

## Connectivity Issues

### Cannot Access Web Server

**Symptoms:**
- Unable to connect to the web server
- Connection refused errors

**Possible Solutions:**
1. Check if nginx is running:
   ```bash
   pgrep -l nginx
   ```
2. Verify the listening port:
   ```bash
   netstat -tulpn | grep nginx
   ```
3. Make sure your firewall or Android settings aren't blocking connections
4. Check the nginx configuration for errors:
   ```bash
   nginx -t
   ```

### SSH Connection Problems

**Symptoms:**
- Cannot connect via SSH
- "Connection refused" or timeout errors

**Possible Solutions:**
1. Verify sshd is running:
   ```bash
   pgrep -l sshd
   ```
2. Check ssh configuration:
   ```bash
   cat /data/data/com.termux/files/usr/etc/ssh/sshd_config
   ```
3. Ensure the port is not blocked by Android system
4. Try connecting with verbose output for more information:
   ```bash
   ssh -vvv user@host
   ```

## Getting Additional Help

If you're still experiencing issues after trying these troubleshooting steps:

1. Check the GitHub repository issues section for similar problems
2. Include detailed information when reporting problems:
   - Android version
   - Device model
   - Exact error messages
   - Steps to reproduce the issue
3. Consider joining relevant communities like the Termux subreddit or forums for additional support
4. 
