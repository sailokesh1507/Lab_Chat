# Lab Chat

This is a small PHP chat room for a local lab network. The computer running XAMPP is the host. Everyone connected to the same network opens the host computer's IPv4 address in a browser.

## Start it with XAMPP

1. Open the XAMPP Control Panel.
2. Start **Apache**. MySQL is not required.
3. If Apache cannot start because port 80 is occupied by IIS, stop the IIS service, or change Apache's `Listen` port to `8080` in `C:\xampp\apache\conf\httpd.conf`.
4. On the host computer, open PowerShell and run `ipconfig`. Find the active adapter's **IPv4 Address**, for example `192.168.1.24`.
5. On the host, test `http://localhost/chat_project/` or, when using port 8080, `http://localhost:8080/chat_project/`.
6. Other lab computers open `http://HOST_IP/chat_project/` or `http://HOST_IP:8080/chat_project/`.

## Login and registration

The chat requires an account. The private `config.php` file is intentionally not included in Git. Before first use, copy `config.example.php` to `config.php`, then replace the placeholders with your own values.

Generate a password hash with:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"
```

Generate an encryption key with:

```powershell
C:\xampp\php\php.exe -r "echo base64_encode(random_bytes(32));"
```

Registered users, encrypted messages, uploads, and runtime state are intentionally kept out of the repository.

People can also select **Create an account** on the login page. Registered usernames and password hashes are stored in `data/users.json`; plaintext passwords are never stored.

## Windows network access

When Windows Firewall prompts for Apache, allow it on **Private networks**. If there is no prompt, create an inbound rule for the Apache port in Windows Defender Firewall. All devices must be on the same Wi-Fi or LAN, and the network must not use client isolation.

## Notes

- Messages are stored in `data/messages.json` and the most recent 200 messages are retained.
- New message names and contents are encrypted at rest with AES-256-GCM; the encryption key is in `config.php` and should be backed up securely.
- Files can be attached up to 25 MB using the paperclip button. In HTTP mode, uploads and downloads pass through the XAMPP host; files are kept in the protected `data/files` directory.
- Allowed file types include common images, PDFs, Office documents, text files, and ZIP archives. Executable files are rejected.
- The `data` directory is blocked from direct browser access with `.htaccess`.
- This is intended for a trusted lab network, not for public internet hosting.