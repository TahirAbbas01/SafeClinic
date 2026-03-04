# HTTPS & Digital Certificate Setup Guide

## Step 1: Generate Self-Signed Certificate (Development Only)

For development/testing, create a self-signed certificate:

```bash
# Create a directory for certificates
mkdir -p /opt/lampp/etc/ssl.crt
mkdir -p /opt/lampp/etc/ssl.key

# Generate 2048-bit RSA private key
openssl genrsa -out /opt/lampp/etc/ssl.key/server.key 2048

# Create a certificate signing request (CSR)
openssl req -new -key /opt/lampp/etc/ssl.key/server.key -out /tmp/server.csr \
  -subj "/C=US/ST=State/L=City/O=SafeClinic/CN=safeclinic.local"

# Generate self-signed X.509 certificate (valid 365 days)
openssl x509 -req -days 365 -in /tmp/server.csr \
  -signkey /opt/lampp/etc/ssl.key/server.key \
  -out /opt/lampp/etc/ssl.crt/server.crt

# Set proper permissions
chmod 600 /opt/lampp/etc/ssl.key/server.key
chmod 644 /opt/lampp/etc/ssl.crt/server.crt

echo "✓ Self-signed certificate created successfully!"
```

**Certificate Details:**
- **File:** `/opt/lampp/etc/ssl.crt/server.crt` (public)
- **Key:** `/opt/lampp/etc/ssl.key/server.key` (keep secret!)
- **Validity:** 365 days
- **Algorithm:** RSA 2048-bit
- **Fingerprint:** View with: `openssl x509 -in server.crt -text -noout`

---

## Step 2: Configure Apache for HTTPS

Edit `/opt/lampp/etc/httpd.conf` and ensure these modules are enabled:

```apache
LoadModule ssl_module modules/mod_ssl.so
LoadModule rewrite_module modules/mod_rewrite.so
```

Then edit or create `/opt/lampp/etc/extra/httpd-ssl.conf`:

```apache
# HTTPS Virtual Host for SafeClinic
<VirtualHost *:443>
    ServerName safeclinic.local
    ServerAlias www.safeclinic.local
    DocumentRoot "/opt/lampp/htdocs/SafeClinic/secure"
    
    # Enable SSL/TLS
    SSLEngine on
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLCipherSuite "ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256"
    SSLHonorCipherOrder on
    SSLCompression off
    
    # Certificate paths
    SSLCertificateFile      "/opt/lampp/etc/ssl.crt/server.crt"
    SSLCertificateKeyFile   "/opt/lampp/etc/ssl.key/server.key"
    
    # HSTS Header (tells browser to always use HTTPS)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    
    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Logging
    ErrorLog "/opt/lampp/logs/safeclinic_ssl_error.log"
    CustomLog "/opt/lampp/logs/safeclinic_ssl_access.log" combined
</VirtualHost>

# HTTP to HTTPS Redirect
<VirtualHost *:80>
    ServerName safeclinic.local
    ServerAlias www.safeclinic.local
    DocumentRoot "/opt/lampp/htdocs/SafeClinic/secure"
    
    # Redirect all HTTP to HTTPS
    RewriteEngine On
    RewriteCond %{SERVER_PORT} 80
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
```

Also add in `/opt/lampp/etc/httpd.conf`:
```apache
Include etc/extra/httpd-ssl.conf
```

---

## Step 3: Update PHP Session Configuration

Edit `/opt/lampp/etc/php.ini` to enable HTTPS-only cookies:

```ini
# Session Security Settings
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_secure = 1          ; ONLY send over HTTPS
session.cookie_samesite = Strict   ; CSRF protection
session.gc_maxlifetime = 1800      ; 30 minutes
```

Or via PHP code in `config.php`:
```php
ini_set('session.cookie_secure', 1);        // Only HTTPS
ini_set('session.cookie_httponly', 1);      // No JavaScript access
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
```

---

## Step 4: Update /etc/hosts for Local Testing

Add to `/etc/hosts`:
```
127.0.0.1   safeclinic.local
```

---

## Step 5: Restart Apache and Test

```bash
# Restart Apache with XAMPP
cd /opt/lampp
./bin/apachectl restart

# Or if using system Apache
sudo systemctl restart apache2

# Test HTTPS connectivity
curl -k https://safeclinic.local/

# Verify certificate details
openssl s_client -connect safeclinic.local:443 -showcerts

# Check HSTS header
curl -i -k https://safeclinic.local/ | grep Strict-Transport-Security
```

---

## Step 6: Browser Testing

Visit: **https://safeclinic.local/**

**Expected Behavior:**
1. ⚠️ Browser shows certificate warning (self-signed is not trusted)
2. Click: "Advanced" → "Proceed anyway"
3. ✓ Login page loads
4. ✓ Address bar shows 🔒 (lock icon)
5. ✓ Sensitive data transmitted under HTTPS

**Test Session Security:**
1. Login successfully
2. Check browser DevTools → Application → Cookies
3. View session cookie properties:
   - **HttpOnly:** ✓ (JavaScript cannot access)
   - **Secure:** ✓ (Only sent over HTTPS)
   - **SameSite:** Strict (CSRF protection)

---

## Production Certificate Setup

For production use a **CA-signed certificate**:

### Option 1: Let's Encrypt (Free, Auto-Renewal)

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Generate certificate (requires DNS pointing to server)
sudo certbot certonly --apache -d safeclinic.com -d www.safeclinic.com

# Certificates stored in: /etc/letsencrypt/live/safeclinic.com/

# Auto-renewal (runs daily)
sudo certbot renew --dry-run
```

Update Apache config:
```apache
SSLCertificateFile      "/etc/letsencrypt/live/safeclinic.com/fullchain.pem"
SSLCertificateKeyFile   "/etc/letsencrypt/live/safeclinic.com/privkey.pem"
```

### Option 2: Commercial Certificate Authority

1. Generate CSR:
```bash
openssl req -new -key server.key -out server.csr
```

2. Submit CSR to CA (Comodo, DigiCert, etc.)
3. CA verifies domain ownership
4. CA returns signed certificate
5. Configure Apache with signed certificate + CA chain

---

## Certificate Explanation for Security

### How HTTPS Works

```
Client (Browser)              Server (SafeClinic)
    |                                |
    |-- TLS Handshake Starts --------|
    |<-- Send Certificate ----------|
    |       (Contains public key)     |
    |                                |
    |-- Verify Certificate Chain ----|
    |       (Is it signed by trusted CA?)
    |                                |
    |-- Agree on Encryption Key -----|
    |       (Using public key)        |
    |                                |
    |<-- Encrypted Data Starts ------|
    |       (Using shared secret key) |
    |                                |
```

### Certificate Components

```
X.509 Certificate contains:
├── Subject: safeclinic.com
├── Issuer: Let's Encrypt
├── Valid From: 2024-01-01
├── Valid Until: 2025-01-01
├── Public Key: RSA 2048-bit
├── Serial Number: Unique ID
├── Signature: CA's verification
└── Extensions: SANs, Trust policies
```

### Trust Chain

```
End-Entity (safeclinic.com) Certificate
    ↓ Signed by
Intermediate CA Certificate
    ↓ Signed by
Root CA Certificate (Pre-installed in browsers)
    ↓ Self-signed
```

Browser trusts root CA → verifies intermediate signature → verifies site certificate.

---

## Security Headers Explained

Added in Apache configuration:

| Header | Purpose | Value |
|--------|---------|-------|
| `Strict-Transport-Security` | Force HTTPS for 1 year | `max-age=31536000` |
| `X-Frame-Options` | Prevent clickjacking | `SAMEORIGIN` |
| `X-Content-Type-Options` | Prevent MIME sniffing | `nosniff` |
| `X-XSS-Protection` | Legacy XSS protection | `1; mode=block` |
| `Referrer-Policy` | Control referrer info | `strict-origin-when-cross-origin` |

---

## Troubleshooting

### Certificate Not Trusted
```bash
# Self-signed certificates will always warn
# For production, use CA-signed certificate from Let's Encrypt or commercial CA
```

### HTTPS Not Working
```bash
# Check if SSL module is loaded
apachectl -M | grep ssl_module

# Verify certificate paths exist
ls -la /opt/lampp/etc/ssl.crt/server.crt
ls -la /opt/lampp/etc/ssl.key/server.key

# Check Apache syntax
apachectl configtest

# View error log
tail -f /opt/lampp/logs/error.log
```

### Mixed Content Warning
- Ensure ALL resources (JS, CSS, images) are loaded over HTTPS
- Check HTML doesn't contain `http://` links
- Use relative paths or `https://` URLs

### Session Not Working over HTTPS
```php
// Ensure in config.php:
ini_set('session.cookie_secure', 1);  // Must be 1 for HTTPS
```

---

## Compliance & Security Standards

- **PCI-DSS:** Requires TLS 1.2+ for cardholder data
- **HIPAA:** Requires encryption in transit for health data
- **GDPR:** Requires encryption for personal data
- **NIST:** Recommends TLS 1.2 minimum (1.3 preferred)

---

## Monitoring & Maintenance

```bash
# Monitor SSL certificate expiration
echo | openssl s_client -servername safeclinic.local -connect safeclinic.local:443 2>/dev/null | openssl x509 -noout -dates

# Set reminder for certificate renewal
crontab -e
# Add: 0 0 1 * * /usr/bin/certbot renew --quiet
```

---

## Summary

✅ **Self-signed certificate:** Good for development/testing  
✅ **CA-signed certificate:** Required for production  
✅ **HSTS header:** Prevents HTTP fallback attacks  
✅ **Secure cookies:** Protected from JavaScript access  
✅ **TLS 1.2+:** Strong encryption algorithms  

Your medical data is now encrypted in transit! 🔒
