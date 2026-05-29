{{/*
Shared template helpers.
*/}}

{{- define "jperdior.image" -}}
{{- $cfg := .image -}}
{{- printf "%s/%s-%s:%s" .Values.global.imageRegistry .Values.global.imageRepositoryPrefix $cfg.name $cfg.tag -}}
{{- end -}}

{{- define "jperdior.appEnv" -}}
{{- range $key, $value := .Values.appConfig }}
- name: {{ $key }}
  value: {{ $value | quote }}
{{- end }}
- name: APP_SECRET
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.appSecret }}
      key: APP_SECRET
- name: DATABASE_URL
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.databaseUrl }}
      key: DATABASE_URL
- name: REDIS_URL
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.redisUrl }}
      key: REDIS_URL
- name: JWT_SECRET_KEY
  value: /var/jwt/private.pem
- name: JWT_PUBLIC_KEY
  value: /var/jwt/public.pem
- name: JWT_PASSPHRASE
  valueFrom:
    secretKeyRef:
      name: {{ .Values.secrets.jwt }}
      key: JWT_PASSPHRASE
{{- end -}}

{{- define "jperdior.jwtVolume" -}}
- name: jwt-keys
  secret:
    secretName: {{ .Values.secrets.jwt }}
    items:
      - { key: private.pem, path: private.pem, mode: 0400 }
      - { key: public.pem,  path: public.pem,  mode: 0444 }
{{- end -}}
