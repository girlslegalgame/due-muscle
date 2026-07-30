<?php
/**
 * @var array $deck デッキ情報
 * @var string $context 呼び出し元のコンテキスト ('index' または 'search')
 * @var PDO|null $pdo_db データベース接続オブジェクト
 */
?>

<?php
// 文明の初期化と取得
$deck_colors = [
    'light'  => false,
    'water'  => false,
    'dark'   => false,
    'fire'   => false,
    'nature' => false,
];

if (!empty($pdo_db) && !empty($deck['deck_id'])) {
    try {
        // ツインパクト（twinpactが真）であれば、同じcombination_idを持つすべてのカードの文明も取得する
        $stmt_colors = $pdo_db->prepare("
            SELECT DISTINCT c_civ.civilization_id
            FROM deck_cards dc
            -- 1. カード詳細テーブルを結合して twinpact フラグを確認
            JOIN card_detail cd ON dc.card_id = cd.card_id
            
            -- 2. デッキのカードに対応する combination_id を取得
            LEFT JOIN card_combination cc 
                ON dc.card_id = cc.card_id
                
            -- 3. twinpact が true（1）の場合のみ、同じ combination_id に紐づく全ての card_id を結合
            LEFT JOIN card_combination cc_all 
                ON cc.combination_id = cc_all.combination_id 
                AND (cd.twinpact = 1 OR cd.twinpact = 'true' OR cd.twinpact IS TRUE)
                
            -- 4. 元の card_id、またはツインパクトの組み合わせに含まれる card_id の文明を取得
            JOIN card_civilization c_civ 
                ON c_civ.card_id = dc.card_id 
                OR c_civ.card_id = cc_all.card_id
                
            WHERE dc.deck_id = :deck_id
              AND (dc.card_type_in_deck IS NULL OR dc.card_type_in_deck = 'main')
        ");
        $stmt_colors->execute(['deck_id' => $deck['deck_id']]);
        $colors_res = $stmt_colors->fetchAll(PDO::FETCH_COLUMN);
        
        if ($colors_res) {
            $deck_colors['light']  = in_array(1, $colors_res);
            $deck_colors['water']  = in_array(2, $colors_res);
            $deck_colors['dark']   = in_array(3, $colors_res);
            $deck_colors['fire']   = in_array(4, $colors_res);
            $deck_colors['nature'] = in_array(5, $colors_res);
        }
    } catch (\Exception $e) {
        // 例外時も動作継続
    }
}

// サムネイル画像パスの解決
$thumbPath = '/images/card/noimage.webp';
if (!empty($deck['thumbnail_imagepath'])) {
    $path = $deck['thumbnail_imagepath'];
    $thumbPath = '/images/card' . (str_starts_with($path, '/') ? $path : '/' . $path);
}
?>

<div class="deck-item">
    <!-- 1. デッキ名 -->
    <h3><?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES, 'UTF-8'); ?></h3>

    <!-- 2. サムネイル画像 -->
    <div class="deck-thumbnail-wrapper">
        <!-- ★修正: onclick内の引数を文字列から dataset 参照に変更。XSSテスト用テキスト（シングルクォーテーション含む）でも文法を壊さず安全に値を渡せます -->
        <img src="<?php echo htmlspecialchars($thumbPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Thumbnail" class="deck-thumbnail" 
             data-deck-name="<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES, 'UTF-8'); ?>"
             onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, this.dataset.deckName)" 
             onerror="this.src='/images/card/noimage.webp'; this.onerror=null;">
    </div>

    <!-- 3. メインデッキ採用文明表示 -->
    <div class="deck-civ-badges">
        <?php if ($deck_colors['light']): ?><span class="civ-badge civ-bg-light">光</span><?php endif; ?>
        <?php if ($deck_colors['water']): ?><span class="civ-badge civ-bg-water">水</span><?php endif; ?>
        <?php if ($deck_colors['dark']): ?><span class="civ-badge civ-bg-dark">闇</span><?php endif; ?>
        <?php if ($deck_colors['fire']): ?><span class="civ-badge civ-bg-fire">火</span><?php endif; ?>
        <?php if ($deck_colors['nature']): ?><span class="civ-badge civ-bg-nature">自然</span><?php endif; ?>
    </div>

    <!-- 4. 製作者名（検索時、データが存在する場合のみ表示） -->
    <?php if (!empty($deck['creator_name'])): ?>
        <div class="deck-creator">
            製作者: <?php echo htmlspecialchars($deck['creator_name'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- 5. フォーマット 最終更新日 -->
    <div class="deck-meta-info">
        <span class="format-badge"><?php echo htmlspecialchars($deck['format_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span><?php echo date('Y/m/d', strtotime($deck['updated_at'])); ?></span>
    </div>

    <!-- 6. ボタン群 (コンテキストによって分岐) -->
    <div class="btn-group" style="margin-top: auto; padding-top: 5px;">
        <?php if ($context === 'search'): ?>
            <!-- 【公開デッキ検索用】ボタン -->
            <!-- ★修正: onclick内の文字列引数を dataset 参照に変更 -->
            <button class="btn-view" 
                    data-deck-name="<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, this.dataset.deckName)">内容表示</button>
            <button class="btn-edit" onclick="copyDeck(<?php echo $deck['deck_id']; ?>)" style="background-color: #ffc107; color: #212529;">コピー</button>
        <?php elseif ($context === 'index'): ?>
            <!-- 【マイデッキ一覧用】ボタン -->
            <!-- ★修正: onclick内の文字列引数を dataset 参照に変更 -->
            <button class="btn-view" 
                    data-deck-name="<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, this.dataset.deckName)">内容表示</button>
            <button class="btn-image" 
                    data-deck-name="<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    data-format-name="<?php echo htmlspecialchars($deck['format_name'], ENT_QUOTES, 'UTF-8'); ?>"
                    onclick="exportDeckImage(<?php echo $deck['deck_id']; ?>, this.dataset.deckName, this.dataset.formatName, this)">画像出力</button>
            <a href="/decks/edit?deck_id=<?php echo $deck['deck_id']; ?>" class="btn-edit">編集</a>
            <button class="btn-delete" onclick="deleteDeck(<?php echo $deck['deck_id']; ?>)">✕</button>
        <?php endif; ?>
    </div>
</div>