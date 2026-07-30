# ==========================================
# ステージ 1: Node.js でフロントエンド（Vite）をビルド
# ==========================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app

# 依存関係のインストールとビルド
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# ==========================================
# ステージ 2: PHP + Apache 環境の構築
# ==========================================
FROM php:8.3-apache

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

# Apacheの設定（ドキュメントルートを /public に変更）
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite

# 一度すべてのMPM設定リンクを物理削除し、preforkのみを手動でリンクする（Apacheコマンド不使用）
RUN rm -f /etc/apache2/mods-enabled/mpm_*
RUN ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
RUN ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# 【デバッグ用】有効になっているモジュール一覧をビルドログに強制出力する
RUN ls -la /etc/apache2/mods-enabled/


# 作業ディレクトリの設定
WORKDIR /var/www/html

# プロジェクトファイルのコピー
COPY . .

# ステージ1でビルドしたアセット（CSS/JSなど）をコピー
COPY --from=frontend-builder /app/public/build ./public/build

# Composerのインストールと実行
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# ディレクトリの権限設定（Laravelの書き込み用）
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ポートの開放（Apacheのデフォルト）
EXPOSE 80
