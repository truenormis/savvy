# Savvy Helm chart

Production-grade Helm chart for **Savvy** — a self-contained Laravel personal-finance
application. The container image bundles nginx, php-fpm, a queue worker and the
scheduler under supervisord, and ships with health endpoints (`/livez`, `/readyz`).

## Install

```bash
helm repo add savvy https://charts.trysavvy.dev
helm repo update
helm install savvy savvy/savvy -n savvy --create-namespace
helm test savvy -n savvy
```

Each released git tag (`v1.2.3`) is packaged and pushed to the
[ChartMuseum](https://charts.trysavvy.dev) repository as chart version `1.2.3`
(with the app image pinned to the same tag) by the `Release Chart` workflow.
Charts are immutable — re-pushing an existing version fails. List what's
available:

```bash
helm search repo savvy --versions
```

Or straight from a checkout of this repo (the in-repo `version`/`appVersion` are
`0.0.0` placeholders, so pin the image explicitly):

```bash
helm install savvy ./deploy/helm/savvy -n savvy --create-namespace \
  --set image.tag=latest
```

## Design: single container, SQLite-only

Savvy runs as **one pod** — nginx, php-fpm, the queue worker and the scheduler
together under supervisord — backed by a **single SQLite file** on a persistent
volume. This is the application's design, and the chart honours it:

- **`replicaCount` is fixed at 1.** SQLite is single-writer; the chart rejects
  any other value to prevent silent database corruption. No HPA, no external
  databases, no datastore sidecars.
- `updateStrategy: Recreate` — one pod owns the single-attach ReadWriteOnce PVC,
  so a rolling update would deadlock.
- Zero external dependencies — works on any conformant cluster (k3s, EKS, GKE,
  AKS, kind).

### Who owns the `.env`

By default (`env.manage=false`) the container self-bootstraps its `.env` on the
volume: it generates a random `APP_KEY`, provisions the SQLite file and runs
migrations + seeders on first boot. Simple, but the `APP_KEY` is tied to the
volume — recreate the PVC and every encrypted value / signed cookie breaks.

Set `env.manage=true` to let Helm own the `.env` (rendered into a Secret and
seeded onto the volume by an init container). Use it for a **stable,
GitOps-controlled `APP_KEY`** and app settings (name, URL, log level, mail) —
the database stays SQLite-on-volume; this does not change the topology.

```yaml
env:
  manage: true
  values:
    APP_KEY: "base64:...."          # stable; better via env.existingSecret
    APP_URL: https://savvy.example.com
    LOG_LEVEL: warning
    # DB_CONNECTION stays sqlite — SQLite-only by design
```

For the `APP_KEY`, prefer `env.existingSecret` (a Secret with a single
`.env_config` key holding the full env file) populated by External Secrets
Operator / Vault / SOPS rather than putting it in values.

## Ingress vs Gateway API

Enable **exactly one** of:

- `ingress.enabled=true` — classic `networking.k8s.io/v1` Ingress.
- `httpRoute.enabled=true` — Gateway API `HTTPRoute` (attach to an existing
  Gateway via `httpRoute.parentRefs`; TLS lives on the Gateway listener).

## Security defaults

- Runs as non-root `www-data` (uid/gid 82), `seccompProfile: RuntimeDefault`.
- `allowPrivilegeEscalation: false`, all capabilities dropped except
  `NET_BIND_SERVICE` (needed for non-root nginx to bind `:80`).
- ServiceAccount token is not mounted (the app never calls the Kubernetes API).
- Optional default-deny `NetworkPolicy`, `PodDisruptionBudget`, and
  `ServiceMonitor`.

## Key values

| Key | Default | Description |
| --- | --- | --- |
| `replicaCount` | `1` | Fixed at 1 (single-writer SQLite); other values are rejected. |
| `image.repository` | `docker.io/truenormis/savvy` | Image repo (Docker Hub). |
| `image.tag` | `""` (→ `appVersion`) | Image tag. |
| `image.digest` | `""` | Pin by digest; overrides tag. |
| `env.manage` | `false` | Let Helm own the `.env` (stable APP_KEY). |
| `env.existingSecret` | `""` | Use an external Secret (`.env_config` key). |
| `persistence.enabled` | `true` | Persist `/data` (SQLite + .env + backups). |
| `persistence.size` | `2Gi` | PVC size. |
| `persistence.storageClass` | `""` | StorageClass (`-` disables provisioning). |
| `updateStrategy.type` | `Recreate` | Fixed — single-attach PVC. |
| `service.type` | `ClusterIP` | Service type. |
| `ingress.enabled` | `false` | Classic Ingress. |
| `httpRoute.enabled` | `false` | Gateway API HTTPRoute. |
| `podDisruptionBudget.enabled` | `false` | PDB. |
| `networkPolicy.enabled` | `false` | Default-deny ingress NetworkPolicy. |
| `resources` | 100m/256Mi → 1/512Mi | Requests/limits. |

See [`values.yaml`](./values.yaml) for the fully documented set; values are
validated against [`values.schema.json`](./values.schema.json).

## Uninstall

```bash
helm uninstall savvy -n savvy
```

The PVC is kept by default (`persistence.retain=true`). Delete it manually to
discard data:

```bash
kubectl delete pvc -n savvy -l app.kubernetes.io/instance=savvy
```
