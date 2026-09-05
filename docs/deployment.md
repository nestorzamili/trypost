# TryPost Production Deployment

Production runs are started manually from the Actions tab via **Run workflow**
on the `TryPost Production Pipeline` workflow. The workflow builds an image,
tags it with the first seven characters of the commit SHA, pushes it to GHCR,
and — unless started in build-only mode — deploys that image to the VM
through SSH.

## Starting a run

| Input | Description |
|---|---|
| `mode` | `build-and-deploy` (default) or `build-only` (image only, VM untouched) |
| `public_host` | Optional per-run host override. Empty falls back to the stored variable |
| `platform` | Target image platform (`linux/amd64` default). Match the VM architecture |

The host must be a bare hostname and is validated before the build. Note it
only affects the build-time `VITE_REVERB_HOST`; the server steps below (VM
`.env`, Nginx, DNS, certificate) stay manual.

## GitHub Actions configuration

### Secrets

Create the following secrets under the GitHub Environment named `production`.
Only the deploy job uses this environment.

| Name | Required | Description |
|---|---:|---|
| `SSH_HOST` | Yes | VM hostname or IP, for example `srv1900584` |
| `SSH_USER` | Yes | SSH user for deployment, for example `dck` |
| `SSH_PORT` | Yes | SSH port, for example `22` |
| `SSH_PRIVATE_KEY` | Yes | Private key for the VM SSH user |
| `GITHUB_TOKEN` | Automatic | GitHub-provided token used to push and pull the GHCR image; no manual setup required |

### Variables

Create this repository variable (Actions variables tab, not the environment):

| Name | Required | Description |
|---|---:|---|
| `TRYPOST_PUBLIC_HOST` | Yes, unless `public_host` is passed | Public hostname, for example `post.example.com` |

> If it still lives under the `production` environment variables, move it to
> a repository variable and delete the environment copy.

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
sudo usermod -aG docker $USER
```

The VM must have `curl`, Docker Compose v2, and an `/opt/trypost` directory.
A Git checkout on the VM is not required; the workflow copies the exact
`compose.prod.yaml` artifact before deployment.

Create the application directory if needed:

```bash
sudo mkdir -p /opt/trypost
sudo chown -R $USER:$USER /opt/trypost
```

Keep the production `.env` file at `/opt/trypost/.env` with mode `600`.

## Host Nginx

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
