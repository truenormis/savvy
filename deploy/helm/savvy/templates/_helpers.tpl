{{/*
Expand the name of the chart.
*/}}
{{- define "savvy.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
We truncate at 63 chars because some Kubernetes name fields are limited to this (by the DNS naming spec).
*/}}
{{- define "savvy.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "savvy.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "savvy.labels" -}}
helm.sh/chart: {{ include "savvy.chart" . }}
{{ include "savvy.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
app.kubernetes.io/part-of: savvy
{{- with .Values.commonLabels }}
{{ toYaml . }}
{{- end }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "savvy.selectorLabels" -}}
app.kubernetes.io/name: {{ include "savvy.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Common annotations
*/}}
{{- define "savvy.annotations" -}}
{{- with .Values.commonAnnotations }}
{{ toYaml . }}
{{- end }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "savvy.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "savvy.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Name of the Secret that holds the managed .env (or the user-provided existing secret).
*/}}
{{- define "savvy.secretName" -}}
{{- if .Values.env.existingSecret }}
{{- .Values.env.existingSecret }}
{{- else }}
{{- printf "%s-env" (include "savvy.fullname" .) }}
{{- end }}
{{- end }}

{{/*
Name of the PersistentVolumeClaim.
*/}}
{{- define "savvy.pvcName" -}}
{{- if .Values.persistence.existingClaim }}
{{- .Values.persistence.existingClaim }}
{{- else }}
{{- printf "%s-data" (include "savvy.fullname" .) }}
{{- end }}
{{- end }}

{{/*
Resolve the container image reference, preferring a pinned digest over a tag.
*/}}
{{- define "savvy.image" -}}
{{- $tag := default .Chart.AppVersion .Values.image.tag -}}
{{- if .Values.image.digest -}}
{{- printf "%s@%s" .Values.image.repository .Values.image.digest -}}
{{- else -}}
{{- printf "%s:%s" .Values.image.repository $tag -}}
{{- end -}}
{{- end }}

{{/*
Render the .env file content from values.env.values (string|map) merged with the chart-managed defaults.
Only used when env.manage=true and env.existingSecret is empty.
*/}}
{{- define "savvy.envFile" -}}
{{- range $k, $v := .Values.env.values }}
{{ $k }}={{ $v }}
{{- end }}
{{- end }}

{{/*
Fail fast on obviously dangerous configurations.
*/}}
{{- define "savvy.validateValues" -}}
{{- if gt (int .Values.replicaCount) 1 -}}
{{- fail "savvy: replicaCount must be 1. Savvy is a single-container, SQLite-only application — its database is single-writer and cannot be shared across pods." -}}
{{- end -}}
{{- if and .Values.env.manage (not .Values.env.existingSecret) (not (hasKey .Values.env.values "APP_KEY")) -}}
{{- fail "savvy: env.manage=true bypasses the container's APP_KEY auto-generation, so you must supply env.values.APP_KEY (or env.existingSecret). Generate one with: php artisan key:generate --show" -}}
{{- end -}}
{{- if and .Values.env.manage (not .Values.env.existingSecret) (hasKey .Values.env.values "DB_CONNECTION") (ne (toString (get .Values.env.values "DB_CONNECTION")) "sqlite") -}}
{{- fail "savvy: DB_CONNECTION must be sqlite. Savvy is SQLite-only by design — this chart does not wire external databases." -}}
{{- end -}}
{{- end }}
