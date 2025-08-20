# Git Setup and SSH Configuration on Termux

This guide explains how to install Git, generate an SSH key, and configure it to work with GitHub on Termux for Samsung Galaxy J3.

## 1. Install Git in Termux

Open Termux and run:
```sh
pkg upgrade
pkg install git
```

## 2. Generate an SSH Key

In Termux, generate a new SSH key:
```sh
ssh-keygen -t ed25519 -C "your_email@example.com"
```
- When prompted for a location, press Enter to use the default.
- Optionally, set a passphrase for extra security.

If `ed25519` is not supported, use:
```sh
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"
```

## 3. Start the SSH Agent and Add Your Key

Start the SSH agent:
```sh
eval "$(ssh-agent -s)"
```

Add your new key to the agent:
```sh
ssh-add ~/.ssh/id_ed25519
```
(Or use `~/.ssh/id_rsa` if you created an RSA key.)

## 4. Add Your SSH Public Key to GitHub

First, display your public key:
```sh
cat ~/.ssh/id_ed25519.pub
```
Copy the entire output.

Then:
1. Go to [https://github.com/settings/keys](https://github.com/settings/keys) on your browser.
2. Click **New SSH key**.
3. Give your key a title and paste the copied key into the "Key" field.
4. Click **Add SSH key**.

## 5. Test SSH Connection to GitHub

In Termux, check your connection:
```sh
ssh -T git@github.com
```

If successful, you will see:
```
Hi username! You've successfully authenticated, but GitHub does not provide shell access.
```

---

Git is now ready to use with GitHub via SSH on Termux.

For more details, see:
- [GitHub Docs: Connecting to GitHub with SSH](https://docs.github.com/en/authentication/connecting-to-github-with-ssh)
