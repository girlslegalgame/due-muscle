<?php
/**
 * @var array $deck デッキ情報
 * @var string $context 呼び出し元のコンテキスト ('index' または 'search')
 * @var PDO|null $pdo_db データベース接続オブジェクト
 */

// 最初の1回だけCSSを出力するための制御（HTMLの肥大化を防ぎます）
static $deck_item_css_rendered = false;
if (!$deck_item_css_rendered):
    $deck_item_css_rendered = true;
?>
<style>
    /* --- デッキカード共通スタイル --- */
    .deck-item {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .deck-item h3 { 
        margin: 0; 
        font-size: 1.1rem; 
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .deck-thumbnail-wrapper {
        width: 100%;
        height: 120px;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #eee;
        background-color: #f9f9f9;
    }
    .deck-thumbnail {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 25%;
        cursor: pointer !important;
    }
    .deck-creator {
        font-size: 0.85rem;
        color: #555;
        font-weight: bold;
        margin-top: -4px;
    }
    .deck-meta-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.85rem;
        color: #666;
    }
    .format-badge {
        font-size: 0.8rem;
        background: #eee;
        padding: 2px 6px;
        border-radius: 4px;
    }

    /* --- ボタン群 --- */
    .btn-group { 
        display: flex; 
        gap: 5px; 
        margin-top: 10px; 
    }
    .btn-view { 
        flex: 2; 
        padding: 10px; 
        background: #28a745; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold; 
    }
    .btn-edit { 
        flex: 1; 
        padding: 10px; 
        background: #ffc107; 
        color: #212529; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold; 
        text-decoration: none; 
        text-align: center; 
        font-size: 0.9rem; 
    }
    .btn-delete { 
        padding: 10px; 
        background: #dc3545; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
    }
    .btn-image {
        flex: 1.5;
        padding: 10px;
        background: #17a2b8;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 0.9rem;
    }
    .btn-image:hover {
        background: #138496;
    }

    /* --- デッキ文明バッジ --- */
    .deck-civ-badges {
        display: flex;
        gap: 4px;
        margin-top: -4px;
    }
    .civ-badge {
        font-size: 0.75rem;
        font-weight: bold;
        color: #fff !important;
        padding: 2px 8px;
        border-radius: 4px;
        text-align: center;
        line-height: 1.2;
    }
    .civ-bg-light   { background-color: #e6b800; }
    .civ-bg-water   { background-color: #1972e6; }
    .civ-bg-dark    { background-color: #444444; }
    .civ-bg-fire    { background-color: #e6193c; }
    .civ-bg-nature  { background-color: #2ca043; }
</style>
<?php endif; ?>

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
        $stmt_colors = $pdo_db->prepare("
            SELECT DISTINCT cc.civilization_id
            FROM deck_cards dc
            JOIN card_civilization cc ON dc.card_id = cc.card_id
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
    <h3><?php echo htmlspecialchars($deck['deck_name']); ?></h3>

    <!-- 2. サムネイル画像 -->
    <div class="deck-thumbnail-wrapper">
        <img src="<?php echo htmlspecialchars($thumbPath); ?>" alt="Thumbnail" class="deck-thumbnail" 
             onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')" 
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
            製作者: <?php echo htmlspecialchars($deck['creator_name']); ?>
        </div>
    <?php endif; ?>

    <!-- 5. フォーマット 最終更新日 -->
    <div class="deck-meta-info">
        <span class="format-badge"><?php echo htmlspecialchars($deck['format_name']); ?></span>
        <span><?php echo date('Y/m/d', strtotime($deck['updated_at'])); ?></span>
    </div>

    <!-- 6. ボタン群 (コンテキストによって分岐) -->
    <div class="btn-group" style="margin-top: auto; padding-top: 5px;">
        <?php if ($context === 'search'): ?>
            <!-- 【公開デッキ検索用】ボタン -->
            <button class="btn-view" onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')">内容表示</button>
            <button class="btn-edit" onclick="copyDeck(<?php echo $deck['deck_id']; ?>)" style="background-color: #ffc107; color: #212529;">コピー</button>
        <?php elseif ($context === 'index'): ?>
            <!-- 【マイデッキ一覧用】ボタン -->
            <button class="btn-view" onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')">内容表示</button>
            <button class="btn-image" onclick="exportDeckImage(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($deck['format_name'], ENT_QUOTES); ?>', this)">画像出力</button>
            <a href="/decks/edit?deck_id=<?php echo $deck['deck_id']; ?>" class="btn-edit">編集</a>
            <button class="btn-delete" onclick="deleteDeck(<?php echo $deck['deck_id']; ?>)">✕</button>
        <?php endif; ?>
    </div>
</div>