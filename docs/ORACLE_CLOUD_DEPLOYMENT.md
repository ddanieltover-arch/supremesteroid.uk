# Oracle Cloud deployment

Target: Ubuntu LTS on an Ampere A1 ARM64 VM, FrankenPHP on localhost, Caddy as the public HTTPS server, Laravel 13, PHP 8.4, Neon PostgreSQL. The domain is `https://supremesteroid.uk`.

This phase prepares the server. It does not deploy the store, change DNS, or put payment data on the machine. Docker is not required and must not be installed for this path.

Do not commit `/var/www/supremesteroid/shared/.env`. Do not print `APP_KEY`, `DATABASE_URL`, or mail passwords. Do not reset Neon.

The public IP comes from the Oracle instance page after the VM exists. Do not invent one.

## 1. Create the VM

In Oracle Cloud, create an Always Free Ampere A1 instance:

- Shape: VM.Standard.A1.Flex
- Architecture: `aarch64`
- 2 OCPU
- Memory: allocate as much of the free-tier allowance as capacity allows, up to 12 GB
- Boot volume: the default persistent volume is enough. Do not add a large extra volume for swap.
- Image: Ubuntu LTS (24.04 preferred)
- Add only the operator SSH public key in the instance create form

Do not paste the private key into chat, Git, or this document.

Oracle's default security list and UFW must both allow only the ports in section 5. A service can be open in Ubuntu and still blocked by the Oracle security list, or the reverse.

## 2. System update

Connect with the SSH key and confirm the prompt is the new VM before updating.

```bash
sudo apt update
sudo apt upgrade -y
sudo reboot
```

Reconnect after reboot. Unattended upgrades may be enabled later. Do not enable automatic reboot until SSH key login has been proven.

Install only the baseline packages:

```bash
sudo apt install -y git curl unzip ca-certificates ufw fail2ban build-essential
```

`build-essential` is for Composer or PHP extensions that compile native code on ARM64. Supervisor is not installed. systemd runs FrankenPHP and Caddy. Docker is not installed.

## 3. ARM64 packages

Confirm the machine before downloading binaries:

```bash
uname -m
dpkg --print-architecture
```

Both must report `aarch64` or `arm64`. Reject any FrankenPHP, Node, or Caddy artifact named `amd64`, `x86_64`, or `linux64`.

Use these native packages:

| Component | Source |
|---|---|
| PHP 8.4 | `ppa:ondrej/php`, architecture `arm64` |
| Composer | official installer, which downloads the Composer PHAR and uses the system PHP |
| Node.js 22 | NodeSource or the official `linux-arm64` tarball. Do not change `package.json` versions. |
| pnpm 12.4.2 | `corepack prepare pnpm@12.4.2 --activate` |
| FrankenPHP | GitHub release asset `frankenphp-linux-aarch64` only, checksum verified |
| Caddy | official Cloudsmith `caddy-stable` apt repository, `arm64` |

`composer.lock` accepts PHP `^8.3 || ^8.4 || ^8.5`. This server uses 8.4 so it matches the FrankenPHP build. Laravel, React, and Neon are architecture-neutral once those ARM64 runtimes exist.

## 4. Users and directories

Do not run Laravel as root.

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /var/www/supremesteroid/releases \
  /var/www/supremesteroid/shared/storage/app/public \
  /var/www/supremesteroid/shared/storage/framework/cache/data \
  /var/www/supremesteroid/shared/storage/framework/sessions \
  /var/www/supremesteroid/shared/storage/framework/views \
  /var/www/supremesteroid/shared/storage/logs
sudo chown -R deploy:www-data /var/www/supremesteroid
sudo chmod 750 /var/www/supremesteroid
sudo chmod 750 /var/www/supremesteroid/shared
sudo chmod 770 /var/www/supremesteroid/shared/storage
```

`current` is created by `scripts/deploy.sh`. Until the first deploy, it does not exist. That is expected in this phase.

| Path | Owner | Mode | Writable by www-data |
|---|---|---|---|
| `releases/` and code | `deploy:www-data` | 750 | no |
| `shared/.env` | `deploy:www-data` | 640 | read |
| `shared/storage` | `deploy:www-data` | 770 | yes |
| `current/bootstrap/cache` | `deploy:www-data` | 770 | yes |
| `current/public` | `deploy:www-data` | 755 | no |

Do not use `chmod -R 777`.

Copy `.env.production.example` to `shared/.env` on the server when secrets are ready. Required values, names only: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://supremesteroid.uk`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `DATABASE_URL`, `DATABASE_URL_UNPOOLED`, `DB_SSLMODE=require`.

## 5. Firewall

Allow SSH, HTTP, and HTTPS only. Do this in both UFW and the Oracle security list.

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

Do not allow `5432`, `6379`, `8000`, or `8080`. FrankenPHP binds `127.0.0.1:8080` and is reached only by local Caddy. Neon is outbound TLS on 443, not a local Postgres port.

Enable UFW only while an SSH session is already open. If the session drops, use the Oracle serial console before changing anything else.

## 6. SSH

Prefer the key added at instance creation.

1. From a second terminal, confirm `ssh -i KEY deploy@PUBLIC_IP` works.
2. Only then set `PasswordAuthentication no` and `KbdInteractiveAuthentication no` in `/etc/ssh/sshd_config.d/10-hardening.conf`.
3. Keep `PubkeyAuthentication yes`.
4. Run `sudo sshd -t` before `sudo systemctl reload ssh`.
5. Do not close the original session until the second login succeeds.

Do not disable SSH, and do not set `PasswordAuthentication no`, before that second login works. Do not copy a private key onto the server.

## 7. Fail2ban

```bash
sudo install -m 644 deploy/fail2ban/jail.d/supremesteroid.local /etc/fail2ban/jail.d/supremesteroid.local
sudo systemctl enable --now fail2ban
sudo fail2ban-client status sshd
```

The jail bans an SSH source after 5 failures in 10 minutes, for 1 hour. Localhost is ignored. That is deliberate so an operator is not banned for a single mistyped key. The SSH log on Ubuntu is `/var/log/auth.log`; the jail uses the systemd journal backend.

## 8. Swap

Check the real allocation before creating swap:

```bash
free -h
```

If memory is 8 GB or more, do not add swap. Vite and Composer fit without consuming boot-volume space.

If memory is below 4 GB, create a 2 GB file. The frontend build in this repository transforms more than 2,000 modules and can be killed on a small free-tier allocation. Two gigabytes is a safety margin, not a disk-filling cache.

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Do not create an 8 GB or larger swap file. The Always Free boot volume is small.

## 9. PHP 8.4

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt install -y php8.4-cli php8.4-xml php8.4-curl php8.4-mbstring \
  php8.4-pgsql php8.4-bcmath php8.4-zip php8.4-intl php8.4-opcache
php -v
php -m
```

Confirm these modules are listed: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_pgsql`, `session`, `tokenizer`, `xml`. Do not install `php8.4-fpm`. Caddy does not use PHP-FPM. Do not install unused database extensions.

`php -v` must show 8.4.x and `NTS` or the distro build, with architecture `aarch64` in `php -i | grep Architecture`.

## 10. Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
php /tmp/composer-setup.php --install-dir=/tmp --filename=composer.phar
php /tmp/composer.phar --version
sudo mv /tmp/composer.phar /usr/local/bin/composer
sudo chmod 755 /usr/local/bin/composer
composer --version
rm -f /tmp/composer-setup.php
```

Do not run `composer global require`. Project dependencies are installed per release by `scripts/deploy.sh`.

## 11. Node and pnpm

Install Node.js 22 for `linux-arm64`. Then:

```bash
corepack enable
corepack prepare pnpm@12.4.2 --activate
node --version
pnpm --version
```

Do not run `pnpm update`. The lockfile stays as committed.

## 12. FrankenPHP

Download only `frankenphp-linux-aarch64` from the official FrankenPHP release, check the published SHA256, and install it as `/usr/local/bin/frankenphp`.

```bash
frankenphp version
file /usr/local/bin/frankenphp
```

`file` must say `ARM aarch64`, not `x86-64`.

`deploy/frankenphp/Caddyfile` listens on `127.0.0.1:8080` and serves `/var/www/supremesteroid/current/public`. Do not change it to `0.0.0.0` or port 443. After a release exists, `ss -ltnp | grep 8080` must show `127.0.0.1` only.

## 13. Caddy

Install Caddy from the official ARM64 apt repository, then:

```bash
sudo install -m 644 deploy/Caddyfile /etc/caddy/Caddyfile
caddy validate --config /etc/caddy/Caddyfile
```

`www.supremesteroid.uk` redirects permanently to `https://supremesteroid.uk`. The apex proxies to `127.0.0.1:8080`. Caddy obtains certificates when DNS points at this VM and ports 80 and 443 are open. Do not start that site block against a domain that still points elsewhere. Do not add `Strict-Transport-Security` until a browser has loaded the site over HTTPS without a certificate warning. `HSTS_ENABLED` stays `false`.

Headers set here: nosniff, DENY framing, referrer policy, and a closed permissions policy. A restrictive Content-Security-Policy is still not enabled.

## 14. systemd

`deploy/systemd/supremesteroid.service` runs `/usr/local/bin/frankenphp` as `www-data`, reads `shared/.env`, restarts on failure, and caps memory at 4 GB. It does not use root.

```bash
sudo install -m 644 deploy/systemd/supremesteroid.service /etc/systemd/system/supremesteroid.service
sudo systemctl daemon-reload
sudo systemd-analyze verify supremesteroid.service
```

Do not `systemctl start` it until `current` exists. Validation is `systemd-analyze verify`, not a live store.

Enable Caddy only after DNS is correct:

```bash
sudo systemctl enable caddy
```

## 15. Scheduler and logs

Cron for `www-data`, after `current` exists:

```bash
* * * * * cd /var/www/supremesteroid/current && php artisan schedule:run >> /dev/null 2>&1
```

`inventory:release-expired` is already scheduled every five minutes with overlap protection. Do not add a second release command.

```bash
sudo install -m 644 deploy/logrotate/supremesteroid /etc/logrotate.d/supremesteroid
sudo logrotate -d /etc/logrotate.d/supremesteroid
```

Daily Laravel logs are kept for 14 days and rotated again by logrotate. `logrotate -d` is a dry run.

## 16. Database, storage, mail

Do not install PostgreSQL on the VM. Outbound TLS to Neon uses `DATABASE_URL` for PHP requests and `DATABASE_URL_UNPOOLED` for migrations. `DB_SSLMODE=require`. Test connectivity from the server only after `.env` exists, and do not print the URL.

Product images and payment proofs use S3 or R2 later. Local `shared/storage` is for logs, compiled views, and framework cache. It is not the web root.

Generate `APP_KEY` once with `php artisan key:generate --show`, store it in `shared/.env`, and reuse it. Do not run `key:generate` during ordinary deploys. Changing it invalidates encrypted cookies, sessions, and encrypted columns. Never commit or log it.

## 17. DNS and HTTPS

Do not change DNS from the server. When the Oracle public IP is known, create:

- `A` record for `supremesteroid.uk` to that IP
- `A` or `CNAME` for `www.supremesteroid.uk` to the same host

IPv6, if the VM has one, needs matching `AAAA` records. Until those records exist, Caddy cannot complete certificates and the domain is not live.

HTTP to HTTPS is Caddy's automatic redirect after certificates exist. Application HSTS stays off until that redirect has been checked in a browser.

## 18. Backups

- Neon point-in-time recovery stays in the Neon project.
- Enable bucket versioning when object storage is configured.
- Keep `shared/.env` in a password manager, not in `public/` or the media bucket.
- `scripts/deploy.sh` keeps five releases. That is the code rollback path. It does not roll back database schema.

Do not create a local database dump in this phase.

## 19. What not to do in this phase

- Do not run `scripts/deploy.sh` against production traffic.
- Do not run `migrate:fresh`, `migrate:rollback`, or `migrate:reset`.
- Do not put bank, crypto, or customer payment data in `.env` yet.
- Do not install Docker.
- Do not expose `/internal/readiness` without its bearer secret.
- `/api/health` and `/up` are the public health routes after a release exists.

## 20. Verification commands

Run these on the VM. Record the output. Do not treat this document as proof they passed.

```bash
uname -m
free -h
nproc
php -v
php -m
composer --version
node --version
pnpm --version
frankenphp version
caddy version
sudo ufw status verbose
sudo fail2ban-client status sshd
sudo systemd-analyze verify supremesteroid.service
sudo logrotate -d /etc/logrotate.d/supremesteroid
ss -ltnp
```

`ss` must not show `0.0.0.0:8080` or `0.0.0.0:5432`. After a later deploy, also run `php artisan schedule:list` from `current`.
