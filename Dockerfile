# 1. ビルドステージ（Goのプログラムだけをコピーして超軽量・爆速ビルド）
FROM golang:alpine AS builder

WORKDIR /app

# モジュール定義とGoプログラムのみをピンポイントでコピー（2.8GBの画像は一切触らない）
COPY go.mod go.sum ./
COPY main.go ./
COPY app/ ./app/

# コンパイル実行（メモリを食わず一瞬で終わります）
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o app main.go

# 2. 実行ステージ
FROM alpine:latest

WORKDIR /app

RUN mkdir -p /app/storage /app/public /app/app/Views

# ビルド済みバイナリを配置
COPY --from=builder /app/app ./app

# Web表示に必要なファイル群を配置
COPY public/ ./public/
COPY app/Views/ ./app/Views/
COPY storage/ ./storage/

RUN chmod +x ./app

EXPOSE 8080

CMD ["./app"]