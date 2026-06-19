<?php namespace Controllers;

use Models\Database;
use PDO;

class CardController {
/**
     * カード検索API (究極の高速化：UNIONマッピング方式)
     */
/**
     * カード検索API (安定・高速化版)
     */
/**
     * カード検索API (究極の高速化：フラット・マッピング方式)
     * 計算（EXISTS）を排除し、結合（JOIN）のみで構成
     */
/**
     * カード検索API (爆速フラグ・無限スクロール・詳細フィルタ・ゾーン振り分け対応)
     */
    public function cardsApi() {
        // パラメータ取得
        $q = $_GET['q'] ?? '';
        $scope = isset($_GET['scope']) ? explode(',', $_GET['scope']) : ['name'];
        $costMin = $_GET['cost_min'] ?? '';
        $costMax = $_GET['cost_max'] ?? '';
        $powMin = $_GET['pow_min'] ?? '';
        $powMax = $_GET['pow_max'] ?? '';
        $civs = isset($_GET['civs']) ? explode(',', $_GET['civs']) : [];
        $races = isset($_GET['races']) ? explode(',', $_GET['races']) : [];
        $abilities = isset($_GET['abilities']) ? explode(',', $_GET['abilities']) : [];
        $regulations = isset($_GET['reg']) ? explode(',', $_GET['reg']) : [];
        $civType = $_GET['civ_type'] ?? '';
        $excludeCivs = isset($_GET['exclude_civs']) ? explode(',', $_GET['exclude_civs']) : [];
        $civMatchType = $_GET['civ_match_type'] ?? 'include';
        
        $raceLogic = $_GET['race_logic'] ?? 'OR';
        $abilityLogic = $_GET['ability_logic'] ?? 'OR';

        $characteristics = isset($_GET['characteristics']) ? explode(',', $_GET['characteristics']) : [];
        $cardtypes = isset($_GET['cardtypes']) ? explode(',', $_GET['cardtypes']) : [];
        $characteristicLogic = $_GET['characteristic_logic'] ?? 'OR';
        $cardtypeLogic = $_GET['cardtype_logic'] ?? 'OR';

        $limit = 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        try {
            $pdo = \Models\Database::connect();
            $params = [];

            // --- ステップ1: 検索条件に合致する「ベース名」のリストを抽出する一時的なクエリ ---
            // 裏面がヒットしても表面の名前に変換するロジックを維持
            $searchSql = "
                SELECT DISTINCT 
                    CASE 
                        WHEN ccb_search.card_id IS NOT NULL THEN c_front.card_name
                        ELSE c_search.card_name
                    END as target_name
                FROM card c_search
                JOIN card_detail cd_search ON c_search.card_id = cd_search.card_id
                LEFT JOIN card_combination ccb_search ON c_search.card_id = ccb_search.card_id
                LEFT JOIN card_combination ccb_front ON ccb_search.combination_id = ccb_front.combination_id AND ccb_front.is_main_side = 1
                LEFT JOIN card c_front ON ccb_front.card_id = c_front.card_id
                WHERE 1=1";
            if ($q !== '') {
                $conds = [];
                if (in_array('name', $scope)) {
                    $conds[] = "(c_search.card_name LIKE :q_name OR c_search.reading LIKE :q_read)";
                    $params[':q_name'] = $params[':q_read'] = "%$q%";
                }
                if (in_array('text', $scope)) {
                    $conds[] = "c_search.text LIKE :q_text"; // テキストカラムを検索
                    $params[':q_text'] = "%$q%";
                }
                if (!empty($conds)) {
                    $searchSql .= " AND (" . implode(' OR ', $conds) . ")";
                }
            }

            if ($costMin !== '') { $searchSql .= " AND c_search.cost >= :cMin"; $params[':cMin'] = (int)$costMin; }
            if ($costMax !== '') { $searchSql .= " AND c_search.cost <= :cMax"; $params[':cMax'] = (int)$costMax; }
            if ($powMin !== '') { $searchSql .= " AND c_search.pow >= :pMin"; $params[':pMin'] = (int)$powMin; }
            if ($powMax !== '') { $searchSql .= " AND c_search.pow <= :pMax"; $params[':pMax'] = (int)$powMax; }

// 修正対象：cardsApi メソッド内の $searchSql の WHERE条件結合（文明に関する部分のみを差し替え）

            // ★ 修正：単色・多色による絞り込みの連動
            if ($civType === 'single') {
                $searchSql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) = 1";
            } elseif ($civType === 'multi') {
                $searchSql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) >= 2";
            } elseif ($civType === 'none') {
                $searchSql .= " AND 1=0";
            }

            // ★ 修正：含む文明 / のみ持つ文明（AND完全一致）の連動
            if (!empty($civs)) {
                $civList = implode(',', array_map('intval', $civs));
                
                if ($civMatchType === 'match') {
                    // のみ持つ（完全一致AND結合）
                    foreach ($civs as $cId) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = " . (int)$cId . ")";
                    }
                    $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id NOT IN ($civList))";
                } else {
                    // 含む
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($civList))";
                }
            }

            // ★ 追加：多色カードから除外する文明の連動
            if (!empty($excludeCivs)) {
                $excludeList = implode(',', array_map('intval', $excludeCivs));
                $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($excludeList))";
            }
            
            if (!empty($races)) {
                $ids = array_map('intval', $races);
                if ($raceLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id = :r$i)";
                        $params[":r$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id IN ($idList))";
                }
            }

            if (!empty($abilities)) {
                $ids = array_map('intval', $abilities);
                if ($abilityLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id = :a$i)";
                        $params[":a$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id IN ($idList))";
                }
            }

            // ★ 新規追加：特殊タイプ（characteristics）の絞り込み処理
            if (!empty($characteristics)) {
                $ids = array_map('intval', $characteristics);
                if ($characteristicLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c_search.card_id AND c_char.characteristics_id = :char$i)";
                        $params[":char$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c_search.card_id AND c_char.characteristics_id IN ($idList))";
                }
            }

            // ★ 新規追加：カードタイプ（cardtype）の絞り込み処理
            if (!empty($cardtypes)) {
                $ids = array_map('intval', $cardtypes);
                if ($cardtypeLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c_search.card_id AND c_type.cardtype_id = :ctype$i)";
                        $params[":ctype$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c_search.card_id AND c_type.cardtype_id IN ($idList))";
                }
            }
            
            if (!empty($regulations)) {
                $regList = implode(',', array_map('intval', $regulations));
                $searchSql .= " AND cd_search.regulation IN ($regList)";
            }

            // ★ ここから追記：フィルター条件が何も指定されていないかを判定
            $isFiltered = (
                $q !== '' 
                || $costMin !== '' 
                || $costMax !== '' 
                || $powMin !== '' 
                || $powMax !== '' 
                || $civType !== ''
                || !empty($civs) 
                || !empty($excludeCivs)
                || !empty($races) 
                || !empty($abilities) 
                || !empty($characteristics) 
                || !empty($cardtypes) 
                || !empty($regulations)
            );

            // 共通の洗練されたソート順：
            // 発売日が新しい順 ＞ 収録商品IDの降順 ＞ カードIDの降順
            $orderBy = "ORDER BY cd.release_date DESC, cd.goods_id, c.card_id ";

            // 絞り込みの有無によってSQLクエリを分岐（ソート順はどちらも統一）
            if (!$isFiltered) {
                // ① 絞り込みがない初期状態：
                $sql = "
                    SELECT 
                        c.*, cd.modelnum, cd.imagepath,
                        (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                        IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    WHERE cd.is_primary_version = 1
                    AND (ccb.combination_id IS NULL OR ccb.is_main_side = 1)
                    $orderBy
                    LIMIT :limit OFFSET :offset";
            } else {
                // ② 絞り込み条件がある状態：
                $sql = "
                    SELECT 
                        c.*, cd.modelnum, cd.imagepath,
                        (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                        IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    JOIN ($searchSql) as matched_names ON c.card_name = matched_names.target_name
                    WHERE cd.is_primary_version = 1
                    AND (ccb.combination_id IS NULL OR ccb.is_main_side = 1)
                    $orderBy
                    LIMIT :limit OFFSET :offset";
            }


            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
public function masterDataApi() {
        try {
            $pdo = Database::connect();
            
            // 既存の種族と特殊能力
            $races = $pdo->query("SELECT race_id, race_name, reading FROM race ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, race_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $abilities = $pdo->query("SELECT ability_id, ability_name, reading FROM ability ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, ability_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            
            // ★ 新規追加：特殊タイプ（昇順）
            $characteristics = $pdo->query("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            
            // ★ 新規追加：カードタイプ（昇順）
            $cardtypes = $pdo->query("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'races' => $races,
                'abilities' => $abilities,
                'characteristics' => $characteristics, // JSONに追加
                'cardtypes' => $cardtypes             // JSONに追加
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
public function cardVersionsApi() {
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) return;

        try {
            $pdo = \Models\Database::connect();
            $stmtName = $pdo->prepare("SELECT card_name FROM card WHERE card_id = :id");
            $stmtName->execute([':id' => $cardId]);
            $card = $stmtName->fetch();
            if (!$card) return;

            // ★修正：SELECT項目に char_ids を追加
            $sql = "SELECT c.card_id, c.card_name, c.text, c.pow, c.cost, cd.modelnum, cd.imagepath, cd.release_date,
                           (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                           CASE WHEN ccb.card_id IS NOT NULL THEN 1 ELSE 0 END as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    WHERE c.card_name = :name
                    ORDER BY cd.release_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $card['card_name']]);
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // --- CardController.php 内（クラスの最後など）に追記するメソッド ---

    public function cardCombinationApi() {
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['error' => 'Card ID is missing']);
            return;
        }

        try {
            $pdo = \Models\Database::connect();
            
            // 1. まず card_combination 経由で同じ combination_id を持つ両面（すべての面）カード情報を取得を試みる
            $sql = "SELECT 
                        c.card_id, 
                        c.card_name, 
                        c.text, 
                        cd.imagepath
                    FROM card_combination cc
                    JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
                    JOIN card c ON cc_all.card_id = c.card_id
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    WHERE cc.card_id = :card_id
                    ORDER BY cc_all.card_id ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':card_id' => $cardId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. もし裏表（combinationデータ）が存在しない通常カードだった場合は、そのカード単体を取得して返す
            if (empty($results)) {
                $sqlFallback = "SELECT 
                                    c.card_id, 
                                    c.card_name, 
                                    c.text, 
                                    cd.imagepath
                                FROM card c
                                JOIN card_detail cd ON c.card_id = cd.card_id
                                WHERE c.card_id = :card_id
                                LIMIT 1";
                $stmtFallback = $pdo->prepare($sqlFallback);
                $stmtFallback->execute([':card_id' => $cardId]);
                $results = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            }

            header('Content-Type: application/json');
            echo json_encode($results);
            
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

/**
     * 修正：特殊タイプ、カードタイプ、収録商品のマスターデータを取得するAPI
     * （プロジェクト共通の Database::connect() に接続を統一しました）
     */
    public function masterDataExtendedApi() {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo = Database::connect();

            // カードタイプ
            $cardtypes = $pdo->query("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            // 特殊タイプ
            $characteristics = $pdo->query("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            // 収録商品 (降順)
            $goods = $pdo->query("SELECT goods_id, goods_name FROM goods ORDER BY goods_id DESC")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'cardtypes' => $cardtypes,
                'characteristics' => $characteristics,
                'goods' => $goods
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 新規追加：ヘルプ画面用の高度な絞り込みカード検索API（文明完全一致・全バージョン表示・新着順ソート完全版）
     */
/**
     * 新規追加：ヘルプ画面用の高度な絞り込みカード検索API（クリンナップ完全版）
     */
    public function helpSearchApi() {
        header('Content-Type: application/json; charset=utf-8');
        
        $q = $_GET['q'] ?? '';
        $scopes = isset($_GET['scope']) ? explode(',', $_GET['scope']) : ['name'];
        $civType = $_GET['civ_type'] ?? '';
        $civs = isset($_GET['civs']) ? explode(',', $_GET['civs']) : [];
        $excludeCivs = isset($_GET['exclude_civs']) ? explode(',', $_GET['exclude_civs']) : [];
        $civMatchType = $_GET['civ_match_type'] ?? 'include';
        
        $races = isset($_GET['races']) ? explode(',', $_GET['races']) : [];
        $abilities = isset($_GET['abilities']) ? explode(',', $_GET['abilities']) : [];
        $characteristics = isset($_GET['characteristics']) ? explode(',', $_GET['characteristics']) : [];
        $cardtypes = isset($_GET['cardtypes']) ? explode(',', $_GET['cardtypes']) : [];
        $goods = isset($_GET['goods']) ? explode(',', $_GET['goods']) : [];

        // ページング用パラメータの取得（デフォルト値：100件、0オフセット）
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        try {
            $pdo = Database::connect();
            $params = [];

            // ベースとなるSQLクエリ
            $sql = "
                SELECT DISTINCT c.card_id, c.card_name, cd.imagepath, cd.release_date
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                WHERE cd.imagepath IS NOT NULL 
                AND cd.imagepath <> ''
            ";
            // キーワード検索（スコープ対応）
            if ($q !== '') {
                $conds = [];
                if (in_array('name', $scopes)) {
                    $conds[] = "(c.card_name LIKE :q_name OR c.reading LIKE :q_read)";
                    $params[':q_name'] = $params[':q_read'] = "%$q%";
                }
                if (in_array('text', $scopes)) {
                    $conds[] = "c.text LIKE :q_text";
                    $params[':q_text'] = "%$q%";
                }
                if (in_array('race', $scopes)) {
                    $conds[] = "EXISTS (SELECT 1 FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id AND r.race_name LIKE :q_race)";
                    $params[':q_race'] = "%$q%";
                }
                if (!empty($conds)) {
                    $sql .= " AND (" . implode(' OR ', $conds) . ")";
                }
            }

            // 単色・多色による絞り込み
            if ($civType === 'single') {
                $sql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) = 1";
            } elseif ($civType === 'multi') {
                $sql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) >= 2";
            } elseif ($civType === 'none') {
                $sql .= " AND 1=0";
            }

            // 含む文明 / のみ持つ文明の結合処理
            if (!empty($civs)) {
                if (in_array(-1, $civs)) {
                    // 「未設定（文明なし）」を処理
                    $otherCivs = array_filter($civs, function($v) { return $v != -1; });
                    if (!empty($otherCivs)) {
                        $civList = implode(',', array_map('intval', $otherCivs));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN ($civList)))";
                    } else {
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id)";
                    }
                } else {
                    $civList = implode(',', array_map('intval', $civs));
                    if ($civMatchType === 'match') {
                        // 「のみ持つ（完全一致AND結合）」
                        foreach ($civs as $cId) {
                            $sql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id = " . (int)$cId . ")";
                        }
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id NOT IN ($civList))";
                    } else {
                        // 「含む」
                        $sql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN ($civList))";
                    }
                }
            }

            // 含まれない文明の除外
            if (!empty($excludeCivs)) {
                $excludeList = implode(',', array_map('intval', $excludeCivs));
                $sql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN ($excludeList))";
            }

            // 種族絞り込み (未設定対応)
            if (!empty($races)) {
                if (in_array(-1, $races)) {
                    $otherRaces = array_filter($races, function($v) { return $v != -1; });
                    if (!empty($otherRaces)) {
                        $idList = implode(',', array_map('intval', $otherRaces));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id AND cr.race_id IN ($idList)))";
                    } else {
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id)";
                    }
                } else {
                    $idList = implode(',', array_map('intval', $races));
                    $sql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id AND cr.race_id IN ($idList))";
                }
            }

            // 特殊能力絞り込み (未設定対応)
            if (!empty($abilities)) {
                if (in_array(-1, $abilities)) {
                    $otherAbilities = array_filter($abilities, function($v) { return $v != -1; });
                    if (!empty($otherAbilities)) {
                        $idList = implode(',', array_map('intval', $otherAbilities));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id AND ca.ability_id IN ($idList)))";
                    } else {
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id)";
                    }
                } else {
                    $idList = implode(',', array_map('intval', $abilities));
                    $sql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id AND ca.ability_id IN ($idList))";
                }
            }

            // 特殊タイプ絞り込み (未設定対応)
            if (!empty($characteristics)) {
                if (in_array(-1, $characteristics)) {
                    $otherChars = array_filter($characteristics, function($v) { return $v != -1; });
                    if (!empty($otherChars)) {
                        $idList = implode(',', array_map('intval', $otherChars));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id AND c_char.characteristics_id IN ($idList)))";
                    } else {
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id)";
                    }
                } else {
                    $idList = implode(',', array_map('intval', $characteristics));
                    $sql .= " AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id AND c_char.characteristics_id IN ($idList))";
                }
            }

            // カードタイプ絞り込み (未設定対応)
            if (!empty($cardtypes)) {
                if (in_array(-1, $cardtypes)) {
                    $otherTypes = array_filter($cardtypes, function($v) { return $v != -1; });
                    if (!empty($otherTypes)) {
                        $idList = implode(',', array_map('intval', $otherTypes));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id AND c_type.cardtype_id IN ($idList)))";
                    } else {
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id)";
                    }
                } else {
                    $idList = implode(',', array_map('intval', $cardtypes));
                    $sql .= " AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id AND c_type.cardtype_id IN ($idList))";
                }
            }

            // 収録商品絞り込み (未設定対応)
            if (!empty($goods)) {
                if (in_array(-1, $goods)) {
                    $otherGoods = array_filter($goods, function($v) { return $v != -1; });
                    if (!empty($otherGoods)) {
                        $idList = implode(',', array_map('intval', $otherGoods));
                        $sql .= " AND (cd.goods_id IS NULL OR cd.goods_id IN ($idList))";
                    } else {
                        $sql .= " AND cd.goods_id IS NULL";
                    }
                } else {
                    $idList = implode(',', array_map('intval', $goods));
                    $sql .= " AND cd.goods_id IN ($idList)";
                }
            }

            // 発売日の新しい順に並び替え
            $sql .= " ORDER BY cd.release_date DESC, c.cost ASC, c.reading ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 新規追加：ヘルプ詳細カード情報表示用API
     */
    public function helpDetailApi() {
        header('Content-Type: application/json; charset=utf-8');
        
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) {
            http_response_code(400);
            echo json_encode(['error' => 'Card ID is required']);
            exit;
        }

        try {
            $pdo = Database::connect();

            $sql = "
                SELECT 
                    c.card_id,
                    c.card_name,
                    c.reading,
                    c.pow,
                    c.cost,
                    c.text,
                    c.flavortext,
                    cd.imagepath,
                    g.goods_name,
                    (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civilizations_ids,
                    (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                    (SELECT GROUP_CONCAT(ability_id) FROM card_ability WHERE card_id = c.card_id) as ability_ids
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                LEFT JOIN goods g ON cd.goods_id = g.goods_id
                WHERE c.card_id = :card_id
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':card_id' => $cardId]);
            $cardInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cardInfo) {
                http_response_code(404);
                echo json_encode(['error' => 'Card not found']);
                exit;
            }

            echo json_encode($cardInfo, JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * 新規追加：カード詳細情報の更新（中間テーブル再登録対応）
     */
    public function helpUpdateApi() {
        header('Content-Type: application/json; charset=utf-8');

        // JSONリクエストデータの取得
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['card_id'])) {
            http_response_code(400);
            echo json_encode(['error' => '無効なデータです。']);
            exit;
        }

        $cardId = (int)$input['card_id'];
        $cardName = $input['card_name'] ?? '';
        $reading = $input['reading'] ?? '';
        $cost = $input['cost'] !== '' ? (int)$input['cost'] : null;
        $pow = $input['pow'] !== '' ? (int)$input['pow'] : null;
        $text = $input['text'] ?? '';
        $flavortext = $input['flavortext'] ?? '';

        $civs = $input['civilizations'] ?? []; // 文明IDの配列
        $races = $input['races'] ?? [];             // 種族IDの配列
        $abilities = $input['abilities'] ?? [];     // 特殊能力IDの配列

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction(); // トランザクション開始

            // 1. cardテーブル（基本情報）のアップデート
            $sqlCard = "
                UPDATE card 
                SET card_name = :card_name, reading = :reading, cost = :cost, pow = :pow, text = :text, flavortext = :flavortext 
                WHERE card_id = :card_id
            ";
            $stmtCard = $pdo->prepare($sqlCard);
            $stmtCard->execute([
                ':card_name' => $cardName,
                ':reading'   => $reading,
                ':cost'      => $cost,
                ':pow'       => $pow,
                ':text'      => $text,
                ':flavortext'=> $flavortext,
                ':card_id'   => $cardId
            ]);

            // 2. 文明中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_civilization WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($civs)) {
                $stmtCiv = $pdo->prepare("INSERT INTO card_civilization (card_id, civilization_id) VALUES (:id, :civ_id)");
                foreach ($civs as $civId) {
                    $stmtCiv->execute([':id' => $cardId, ':civ_id' => (int)$civId]);
                }
            }

            // 3. 種族中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_race WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($races)) {
                $stmtRace = $pdo->prepare("INSERT INTO card_race (card_id, race_id) VALUES (:id, :race_id)");
                foreach ($races as $raceId) {
                    $stmtRace->execute([':id' => $cardId, ':race_id' => (int)$raceId]);
                }
            }

            // 4. 特殊能力中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_ability WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($abilities)) {
                $stmtAbility = $pdo->prepare("INSERT INTO card_ability (card_id, ability_id) VALUES (:id, :ability_id)");
                foreach ($abilities as $abilityId) {
                    $stmtAbility->execute([':id' => $cardId, ':ability_id' => (int)$abilityId]);
                }
            }

            $pdo->commit(); // コミット
            echo json_encode(['success' => true]);

        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack(); // エラー時はロールバック
            }
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }   
}
