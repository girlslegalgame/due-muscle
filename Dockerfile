FROM golang:1.22-alpine AS builder

WORKDIR /app

# ソースコードとモジュール定義をコピー
COPY . .

# go mod download を挟まず、直接ビルドを実行（エラーがあればログに詳細が出ます）
RUN CGO_ENABLED=0 GOOS=linux go build -o app main.go

# 実行環境
FROM alpine:latest
WORKDIR /app

RUN mkdir -p /app/storage /app/public /app/app/Views

COPY --from=builder /app/app .
COPY --from=builder /app/public ./public
COPY --from=builder /app/app/Views ./app/Views
COPY storage/ ./storage/

RUN chmod +x ./app

EXPOSE 8080

CMD ["./app"]