# ビルドステージ
FROM golang:1.22-alpine AS builder

WORKDIR /app

# 依存関係のダウンロード
COPY go.mod go.sum ./
RUN go mod download

# アプリのビルド
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o app main.go

# 実行ステージ（軽量コンテナ）
FROM alpine:latest

WORKDIR /app

# ビルドしたバイナリと、実行に必要な静的ファイル・テンプレート群をコピー
COPY --from=builder /app/app .
COPY --from=builder /app/public ./public
COPY --from=builder /app/app/Views ./app/Views
COPY --from=builder /app/storage ./storage

# 実行権限を確実に付与
RUN chmod +x ./app

EXPOSE 8080

CMD ["./app"]