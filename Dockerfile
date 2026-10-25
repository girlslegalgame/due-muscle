# 1. ビルドステージ
FROM golang:alpine AS builder

WORKDIR /app

COPY go.mod go.sum ./
COPY main.go ./
COPY app/ ./app/

RUN go mod tidy

# ★ 出力ファイル名を app から server に変更
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-w -s" -o server main.go

# 2. 実行ステージ
FROM alpine:latest
WORKDIR /app

RUN mkdir -p /app/storage /app/public /app/app/Views

# ★ server をコピー
COPY --from=builder /app/server ./server
COPY public/ ./public/
COPY app/Views/ ./app/Views/
COPY storage/ ./storage/

# ★ server に実行権限を付与
RUN chmod +x ./server

EXPOSE 8080

# ★ 起動コマンドを ./server に変更
CMD ["./server"]