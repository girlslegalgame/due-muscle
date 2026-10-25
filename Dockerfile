FROM golang:1.22-alpine AS builder

WORKDIR /app

# go.sum が存在しなくてもエラーにならない書き方に変更
COPY go.mod go.sum* ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o app main.go

FROM alpine:latest
WORKDIR /app

# 必要なディレクトリをあらかじめ作成
RUN mkdir -p /app/storage /app/public /app/app/Views

COPY --from=builder /app/app .
COPY --from=builder /app/public ./public
COPY --from=builder /app/app/Views ./app/Views
# storage フォルダが存在する場合のみコピー（無くても落ちない）
COPY --from=builder /app/storage* ./storage/

RUN chmod +x ./app

EXPOSE 8080

CMD ["./app"]