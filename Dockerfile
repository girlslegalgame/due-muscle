# ==========================================
# ステージ 1: Node.js でフロントエンド（Vite）をビルド
# ==========================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ==========================================
# ステージ 2: FrankenPHP（モダンなPHP超高速サーバー）環境の構築
# ==========================================
FROM dunglas/frankenphp:latest-php8.3

# 必要なシステムパッケージとPHP拡張のインストール
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# FrankenPHPの公開ディレクトリ設定（Laravelのpublicを指定）
ENV FRANKENPHP_DOCUMENT_ROOT=/app/public

WORKDIR /app

# プロジェクトファイルのコピー
COPY . .

# ステージ1でビルドしたアセットをコピー
COPY --from=frontend-builder /app/public/build ./public/build

# Composerのインストールと実行
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 権限設定
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache
