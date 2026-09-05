# TryPost Production Deployment

Production runs are started manually from the Actions tab via **Run workflow**
on the `TryPost Production Pipeline` workflow. The workflow builds an image,
tags it with the first seven characters of the commit SHA, pushes it to GHCR,
and — unless started in build-only mode — deploys that image to the VM
through SSH.

TryPost uses its own PostgreSQL and Redis containers. Existing containers such
as `scsme-db`, `scsme-redis`, and `redis-shared` are not used or modified.
The existing host Nginx proxies application traffic to `127.0.0.1:8000` and
Reverb traffic to `127.0.0.1:8080`.

## Starting a run

Two inputs are offered when starting the workflow:

| Input | Type | Description |
|---|---|---|
| `mode` | `build-and-deploy` (default) or `build-only` | `build-only` builds and pushes the image without touching the VM |
| `public_host` | Optional string | Override the public hostname for this run only, for example `post.example.com`. Empty means the `TRYPOST_PUBLIC_HOST` repository variable is used, so the variable never has to be edited just to target another host |

The resolved host must be a bare hostname (no protocol, path, or port) and is
validated before the build starts. The run summary shows the mode, image tag
and digest, and resolved host. In `build-only` mode the summary also shows
the manual `docker compose` command to deploy the built image later.

Note that the host input only affects the **build-time** `VITE_REVERB_HOST`
baked into the image. Pointing a new domain at the stack still requires the
manual server steps below (VM `.env`, Nginx, DNS, certificate).

When changing the workflow itself, validate in this order: first a
`build-only` run without input (uses the stored variable), then a
`build-only` run with a `public_host` override (check the summary shows the
override), and only then a regular `build-and-deploy` run to confirm no
regression.

## GitHub Actions configuration

### Secrets

Create the following secrets under the GitHub Environment named `production`.
Only the deploy job uses this environment.

| Name | Required | Description |
|---|---:|---|
| `SSH_CONFIG` | Yes | SSH endpoint in the format `user@host:port`, for example `dck@srv1900584:22` |
| `SSH_PRIVATE_KEY` | Yes | Private key for the VM SSH user |
| `GITHUB_TOKEN` | Automatic | GitHub-provided token used to push and pull the GHCR image; no manual setup required |

`SSH_CONFIG` contains only the endpoint. Keep the private key in
`SSH_PRIVATE_KEY`; do not combine them.

### Variables

Create the following variable under the repository's **Actions variables**
(Settings → Secrets and variables → Actions → Variables tab), not under the
environment. It is a public hostname, not a secret, and the build job reads
it without the production environment so build-only runs stay frictionless.

| Name | Required | Description |
|---|---:|---|
| `TRYPOST_PUBLIC_HOST` | Yes, unless `public_host` is passed | Repository variable containing the public hostname without protocol or path, for example `post.example.com` |
| Other variables | No | No optional GitHub variables are currently used |

> One-time move: if `TRYPOST_PUBLIC_HOST` still lives under the `production`
> environment variables, recreate it as a repository variable with the same
> value and delete the environment copy.

### Build arguments

| Argument | Value | Secret |
|---|---|---:|
| `VITE_APP_NAME` | `TryPost` | No |
| `VITE_REVERB_APP_KEY` | `trypost-reverb-key` | No |
| `VITE_REVERB_HOST` | Resolved host (`public_host` input, else `${TRYPOST_PUBLIC_HOST}`) | No |
| `VITE_REVERB_PORT` | `443` | No |
| `VITE_REVERB_SCHEME` | `https` | No |

Application secrets are not used during the Docker build. They remain on the
VM in `/opt/trypost/.env` and are not committed to Git.

## VM prerequisites

The SSH user must be able to run Docker without an interactive password prompt:

```bash
docker ps
docker compose version
```

If required, grant the deployment user Docker access. Docker group membership
is root-equivalent:

```bash
sudo usermod -aG docker <deploy-user>
```

The VM must have `curl`, Docker Compose v2, and an `/opt/trypost` directory.
A Git checkout on the VM is not required; the workflow copies the exact
`compose.prod.yaml` artifact before deployment.

Create the application directory if needed:

```bash
sudo mkdir -p /opt/trypost
sudo chown <deploy-user>:<deploy-user> /opt/trypost
```

Keep the production `.env` file at `/opt/trypost/.env` with mode `600`.

## Host Nginx

The production VM runs Ubuntu Nginx `1.24.0` as a systemd service. It includes
`/etc/nginx/sites-enabled/*` and already owns ports 80 and 443. Ports 8000 and
8080 are available for the TryPost container bindings.

Create a dedicated HTTP site file without changing the existing virtual
hosts. Replace `post.example.com` with the value of `TRYPOST_PUBLIC_HOST`:

```bash
PUBLIC_HOST=post.example.com

sudo tee /etc/nginx/sites-available/trypost.conf > /dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${PUBLIC_HOST};

    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    location /apps/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
NGINX

sudo ln -sfn /etc/nginx/sites-available/trypost.conf \
  /etc/nginx/sites-enabled/trypost.conf
sudo nginx -t
sudo systemctl reload nginx
```

Make sure DNS already points `${PUBLIC_HOST}` to the VM. Use the existing
Certbot installation to obtain and configure the HTTPS certificate:

```bash
sudo certbot --nginx --redirect -d post.example.com
```

Certbot will add the TLS directives and HTTP-to-HTTPS redirect to the site.
Verify automatic renewal:

```bash
systemctl is-enabled certbot.timer
sudo certbot renew --dry-run
```

If the timer is not enabled:

```bash
sudo systemctl enable --now certbot.timer
```

Do not enable the Caddy profile because host Nginx already owns ports 80 and
443.

## Compose and image reference

`compose.prod.yaml` is self-contained. It binds the application and Reverb
ports to localhost and keeps PostgreSQL and Redis in the `trypost` Compose
project. The production image runs Horizon, Reverb, and the scheduler through
Supervisor; do not add bare-metal Supervisor or cron entries for this stack.

The official guide also lists `UserSeeder` for the initial admin account. The
current container entrypoint does not run it; create the first account through
the intended application setup and never use the seeder's default credentials
in production.

The deployed image uses a seven-character commit SHA, for example:

```text
ghcr.io/OWNER/REPOSITORY:a1b2c3d
```

A concise pre-deployment validation is:

```bash
cd /opt/trypost
TRYPOST_IMAGE=ghcr.io/OWNER/REPOSITORY:SHORT_SHA \
  docker compose --env-file .env -p trypost -f compose.prod.yaml config --quiet
```

The local application health endpoint is:

```bash
curl --fail http://127.0.0.1:8000/up
```
