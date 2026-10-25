# 1. ビルドステージ
FROM golang:alpine AS builder

WORKDIR /app

# モジュール定義とGoコードをコピー
COPY go.mod go.sum ./
COPY main.go ./
COPY app/ ./app/

# ★ ここを追加: 自動で依存関係を完璧に同期・整列させます
RUN go mod tidy

# コンパイル実行
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o app main.go

# 2. 実行ステージ（以降はそのまま）
FROM alpine:latest
WORKDIR /app

RUN mkdir -p /app/storage /app/public /app/app/Views

COPY --from=builder /app/app ./app
COPY public/ ./public/
COPY app/Views/ ./app/Views/
COPY storage/ ./storage/

RUN chmod +x ./app

EXPOSE 8080

CMD ["./app"]