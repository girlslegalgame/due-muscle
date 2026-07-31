<?php namespace Controllers;

use Models\Database;
use PDO;

class CardController {

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
        $goods = isset($_GET['goods']) ? explode(',', $_GET['goods']) : [];
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
                        WHEN ccb_search.card_id IS NOT NULL AND c_search.card_id <> c_front.card_id THEN c_front.card_name
                        ELSE c_search.card_name
                    END as target_name,
                    IF(ccb_search.card_id IS NOT NULL, 1, 0) as is_combo
                FROM card c_search
                LEFT JOIN card_detail cd_search ON c_search.card_id = cd_search.card_id
                LEFT JOIN card_combination ccb_search ON c_search.card_id = ccb_search.card_id
                LEFT JOIN card_combination ccb_front ON ccb_search.combination_id = ccb_front.combination_id AND ccb_front.is_main_side = 1
                LEFT JOIN card c_front ON ccb_front.card_id = c_front.card_id
                WHERE 1=1";             
if ($q !== '') {
                // 送信されたキーワードを「カタカナ」と「ひらがな」の両方に変換してバインド用変数を作成
                $q_kata = mb_convert_kana($q, "C", "UTF-8"); // ひらがな -> カタカナ
                $q_hira = mb_convert_kana($q, "c", "UTF-8"); // カタカナ -> ひらがな

                // 比較用として、中黒（・）やスペース（半角・全角）を除去したクエリを作成
                $q_clean = str_replace(['・', ' ', '　'], '', $q);
                $q_kata_clean = str_replace(['・', ' ', '　'], '', $q_kata);
                $q_hira_clean = str_replace(['・', ' ', '　'], '', $q_hira);

                $conds = [];
                if (in_array('name', $scope)) {
                    // データベース側のカード名からも中黒・スペースを取り除いて比較します
                    $conds[] = "(
                        REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_clean
                        OR REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_kata_clean
                        OR REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_hira_clean
                    )";
                    $params[':q_name_clean'] = "%$q_clean%";
                    $params[':q_name_kata_clean'] = "%$q_kata_clean%";
                    $params[':q_name_hira_clean'] = "%$q_hira_clean%";
                }
                
                // 読み仮名検索（同様にスペースを除去して比較）
                if (in_array('name', $scope) || in_array('reading', $scope)) {
                    $conds[] = "(
                        REPLACE(REPLACE(c_search.reading, ' ', ''), '　', '') LIKE :q_read_kata_clean
                        OR REPLACE(REPLACE(c_search.reading, ' ', ''), '　', '') LIKE :q_read_hira_clean
                    )";
                    $params[':q_read_kata_clean'] = "%$q_kata_clean%";
                    $params[':q_read_hira_clean'] = "%$q_hira_clean%";
                }
                
                if (in_array('text', $scope)) {
                    $conds[] = "c_search.text LIKE :q_text";
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

            // === 【修正】単色・多色による絞り込みの連動 ===
            if ($civType === 'single') {
                // 文明数が1つ以下（通常の単色＝1、もしくは文明を持たない無色＝0）のカードを対象とする
                $searchSql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) <= 1";
            } elseif ($civType === 'multi') {
                $searchSql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) >= 2";
            } elseif ($civType === 'none') {
                $searchSql .= " AND 1=0";
            }

            // === 【修正】含む文明 / のみ持つ文明（AND完全一致）の連動 ===
            if (!empty($civs)) {
                $hasZero = in_array(6, $civs); // ゼロ（無色）が選択されているか
                $otherCivs = array_filter($civs, function($v) { return $v != 6; }); // ゼロ以外の文明
                
                if ($civMatchType === 'match') {
                    // のみ持つ（完全一致AND結合）
                    if ($hasZero && empty($otherCivs)) {
                        // 「無色のみ」を選択した場合：
                        // レコードが 6 である、もしくは中間テーブルにレコードが存在しないカード
                        $searchSql .= " AND (
                            EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                            OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                        )";
                        // かつ、他の5文明を持たない
                        $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id != 6)";
                    } else {
                        // ゼロと他の文明を同時に持つ多色（ジョーカーズ＋火文明など）
                        foreach ($civs as $cId) {
                            if ($cId == 6) {
                                $searchSql .= " AND (
                                    EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                                    OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                                )";
                            } else {
                                $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = " . (int)$cId . ")";
                            }
                        }
                        $civList = implode(',', array_map('intval', $civs));
                        $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id NOT IN ($civList))";
                    }
                } else {
                    // 含む（OR結合）
                    if ($hasZero) {
                        if (!empty($otherCivs)) {
                            $otherCivList = implode(',', array_map('intval', $otherCivs));
                            // 「無色（DBレコードなし、またはID:6）」、または「選択された他の文明」のいずれかを含む
                            $searchSql .= " AND (
                                EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($otherCivList, 6))
                                OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                            )";
                        } else {
                            // 無色のみを含む（レコードが6、またはレコードが存在しないカード）
                            $searchSql .= " AND (
                                EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                                OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                            )";
                        }
                    } else {
                        // 通常の文明検索
                        $civList = implode(',', array_map('intval', $civs));
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($civList))";
                    }
                }
            }

            // === 【修正】多色カードから除外する文明の連動 ===
            if (!empty($excludeCivs)) {
                $hasExcludeZero = in_array(6, $excludeCivs);
                $otherExcludeCivs = array_filter($excludeCivs, function($v) { return $v != 6; });
                
                if (!empty($otherExcludeCivs)) {
                    $excludeList = implode(',', array_map('intval', $otherExcludeCivs));
                    if ($hasExcludeZero) {
                        // 他の文明を除外、かつ無色も除外（＝文明テーブルにレコードが存在し、指定された文明でも6でもないカードのみを許可）
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)";
                        $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($excludeList, 6))";
                    } else {
                        $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($excludeList))";
                    }
                } elseif ($hasExcludeZero) {
                    // 無色（レコードなし、またはレコード6）のみを除外（＝文明テーブルにレコードが少なくとも1つ存在し、かつ6ではないカードのみを許可）
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)";
                    $searchSql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)";
                }
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

            // 特殊タイプ（characteristics）の絞り込み処理
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

            // カードタイプ（cardtype）の絞り込み処理
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

            if (!empty($goods)) {
                $goodsList = implode(',', array_map('intval', $goods));
                $searchSql .= " AND cd_search.goods_id IN ($goodsList)";
            }
            
            // フィルター条件が何も指定されていないかを判定
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
                || !empty($goods)
            );

            // 共通の洗練されたソート順：
            // 発売日が新しい順 ＞ 収録商品IDの降順 ＞ カードIDの降順
            $orderBy = "ORDER BY cd.release_date DESC, cd.goods_id, c.card_id ";

            // 絞り込みの有無によってSQLクエリを分岐（ソート順はどちらも統一）
            if (!$isFiltered) {
                // ① 絞り込みがない初期状態（変更なし）：
                $sql = "
                    SELECT 
                        c.*, cd.modelnum, cd.imagepath, cd.`limit` as card_limit,
                        (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                        (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                        (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                         FROM card_combination cc_ref 
                         JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                         JOIN card c_all ON cc_all.card_id = c_all.card_id 
                         WHERE cc_ref.card_id = c.card_id) as combo_names,
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
                        c.*, cd.modelnum, cd.imagepath, cd.`limit` as card_limit,
                        (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                        (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                        (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                         FROM card_combination cc_ref 
                         JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                         JOIN card c_all ON cc_all.card_id = c_all.card_id 
                         WHERE cc_ref.card_id = c.card_id) as combo_names,
                        IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    /* ★ 結合条件を 「代表カード名」かつ「組み合わせ有無(通常orツインパクト)の一致」に変更 */
                    JOIN ($searchSql) as matched_names ON 
                        c.card_name = matched_names.target_name 
                        AND IF(ccb.card_id IS NOT NULL, 1, 0) = matched_names.is_combo
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
            
            // 新規追加：特殊タイプ（昇順）
            $characteristics = $pdo->query("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            
            // 新規追加：カードタイプ（昇順）
            $cardtypes = $pdo->query("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'races' => $races,
                'abilities' => $abilities,
                'characteristics' => $characteristics,
                'cardtypes' => $cardtypes
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
            
            // 1. ターゲットとなるカードの名前と、組み合わせ（ツインパクト等）IDを事前に取得します
            $stmtTarget = $pdo->prepare("
                SELECT c.card_name, ccb.combination_id 
                FROM card c 
                LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id 
                WHERE c.card_id = :id
            ");
            $stmtTarget->execute([':id' => $cardId]);
            $target = $stmtTarget->fetch();
            if (!$target) return;

            // 組み合わせIDが空でない場合、ツインパクト（または両面カード）と判定します
            $isCombo = !empty($target['combination_id']);

            $sql = "SELECT c.card_id, c.card_name, c.text, c.pow, c.cost, cd.modelnum, cd.imagepath, cd.release_date, cd.`limit` as card_limit,
                           (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                           (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                            FROM card_combination cc_ref 
                            JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                            JOIN card c_all ON cc_all.card_id = c_all.card_id 
                            WHERE cc_ref.card_id = c.card_id) as combo_names,
                           CASE WHEN ccb.card_id IS NOT NULL THEN 1 ELSE 0 END as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    WHERE c.card_name = :name ";

            // 2. ツインパクト版と通常カード版で取得対象をSQL段階で完全に分岐させます
            if ($isCombo) {
                // ツインパクト（組み合わせあり）の場合：
                // 組み合わせ（ccb.combination_id）が存在し、かつ「構成するカード名の組み合わせ全体（combo_names）」が完全に一致するもののみを取得
                $sql .= " AND ccb.combination_id IS NOT NULL ";
                $sql .= " AND (
                    SELECT GROUP_CONCAT(c_all2.card_name ORDER BY cc_all2.card_id ASC SEPARATOR '|||') 
                    FROM card_combination cc_ref2 
                    JOIN card_combination cc_all2 ON cc_ref2.combination_id = cc_all2.combination_id 
                    JOIN card c_all2 ON cc_all2.card_id = c_all2.card_id 
                    WHERE cc_ref2.card_id = c.card_id
                ) = (
                    SELECT GROUP_CONCAT(c_all3.card_name ORDER BY cc_all3.card_id ASC SEPARATOR '|||') 
                    FROM card_combination cc_ref3 
                    JOIN card_combination cc_all3 ON cc_ref3.combination_id = cc_all3.combination_id 
                    JOIN card c_all3 ON cc_all3.card_id = c_all3.card_id 
                    WHERE cc_ref3.card_id = :target_id
                )";
            } else {
                // 通常カードの場合：
                // 組み合わせテーブルに登録されていない（ツインパクトではない）同名カードのみを取得
                $sql .= " AND ccb.combination_id IS NULL ";
            }

            $sql .= " ORDER BY cd.release_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $bindParams = [':name' => $target['card_name']];
            if ($isCombo) {
                $bindParams[':target_id'] = $cardId; // ツインパクト時は同一構成チェック用のターゲットIDをバインド
            }
            
            $stmt->execute($bindParams);
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function cardCombinationApi() {
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['error' => 'Card ID is missing']);
            return;
        }

        try {
            $pdo = \Models\Database::connect();
            
            // 1. combination_id に紐づく全カードの詳細情報を取得
            $sql = "SELECT 
                        c.card_id, 
                        c.card_name, 
                        c.text, 
                        cd.imagepath,
                        (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                        (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids
                    FROM card_combination cc
                    JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
                    JOIN card c ON cc_all.card_id = c.card_id
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    WHERE cc.card_id = :card_id
                    ORDER BY cc_all.card_id ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':card_id' => $cardId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. combinationデータが存在しない通常カード用のフォールバック
            if (empty($results)) {
                $sqlFallback = "SELECT 
                                    c.card_id, 
                                    c.card_name, 
                                    c.text, 
                                    cd.imagepath,
                                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids
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
     * 特殊タイプ、カードタイプ、収録商品のマスターデータを取得するAPI
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

            // レアリティマスタ（昇順）の取得
            $rarities = $pdo->query("SELECT rarity_id, rarity_name FROM rarity ORDER BY rarity_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'cardtypes' => $cardtypes,
                'characteristics' => $characteristics,
                'goods' => $goods,
                'rarities' => $rarities
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * ヘルプ画面用の高度な絞り込みカード検索API（クリンナップ版）
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
        $rarities = isset($_GET['rarities']) ? explode(',', $_GET['rarities']) : []; // ★ 追加
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
                $q_kata = mb_convert_kana($q, "C", "UTF-8");
                $q_hira = mb_convert_kana($q, "c", "UTF-8");

                // 比較用として、中黒（・）やスペース（半角・全角）を除去したクエリを作成
                $q_clean = str_replace(['・', ' ', '　'], '', $q);
                $q_kata_clean = str_replace(['・', ' ', '　'], '', $q_kata);
                $q_hira_clean = str_replace(['・', ' ', '　'], '', $q_hira);

                $conds = [];
                if (in_array('name', $scopes)) {
                    $conds[] = "(
                        REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_clean
                        OR REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_kata_clean
                        OR REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_hira_clean
                        OR REPLACE(REPLACE(c.reading, ' ', ''), '　', '') LIKE :q_read_kata_clean
                        OR REPLACE(REPLACE(c.reading, ' ', ''), '　', '') LIKE :q_read_hira_clean
                    )";
                    $params[':q_name_clean'] = "%$q_clean%";
                    $params[':q_name_kata_clean'] = "%$q_kata_clean%";
                    $params[':q_name_hira_clean'] = "%$q_hira_clean%";
                    $params[':q_read_kata_clean'] = "%$q_kata_clean%";
                    $params[':q_read_hira_clean'] = "%$q_hira_clean%";
                }

                // ★ 追加：構築した条件式をSQL文に結合します
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
                    // ★ 修正: c_search.card_id から c.card_id へ変更
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

            if (!empty($rarities)) {
                if (in_array(-1, $rarities)) {
                    // 「未設定（レコードなし）」が含まれる場合
                    $otherRarities = array_filter($rarities, function($v) { return $v != -1; });
                    if (!empty($otherRarities)) {
                        // 「未設定」または「選択した他のレアリティ」を持つカード
                        $idList = implode(',', array_map('intval', $otherRarities));
                        $sql .= " AND (NOT EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id AND cr_rarity.rarity_id IN ($idList)))";
                    } else {
                        // 「未設定」のみ（card_rarityテーブルに紐づくレコードが存在しないカード）
                        $sql .= " AND NOT EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id)";
                    }
                } else {
                    // 通常のレアリティ指定（card_rarityテーブルに合致するレコードが存在するカード）
                    $idList = implode(',', array_map('intval', $rarities));
                    $sql .= " AND EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id AND cr_rarity.rarity_id IN ($idList))";
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
     * ヘルプ詳細カード情報表示用API
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
                    (SELECT GROUP_CONCAT(ability_id) FROM card_ability WHERE card_id = c.card_id) as ability_ids,
                    (SELECT GROUP_CONCAT(rarity_id) FROM card_rarity WHERE card_id = c.card_id) as rarity_ids,
                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as characteristic_ids,
                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids
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
     * カード詳細情報の更新（中間テーブル再登録対応）
     */
    public function helpUpdateApi() {
        header('Content-Type: application/json; charset=utf-8');

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

        $civs = $input['civilizations'] ?? [];
        $races = $input['races'] ?? [];
        $abilities = $input['abilities'] ?? [];
        
        $rarities = $input['rarities'] ?? [];
        $characteristics = $input['characteristics'] ?? [];
        $cardtypes = $input['cardtypes'] ?? [];

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

            // 5. レアリティ中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_rarity WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($rarities)) {
                $stmtRarity = $pdo->prepare("INSERT INTO card_rarity (card_id, rarity_id) VALUES (:id, :rarity_id)");
                foreach ($rarities as $rarityId) {
                    $stmtRarity->execute([':id' => $cardId, ':rarity_id' => (int)$rarityId]);
                }
            }

            // 6. 特殊タイプ中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_characteristics WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($characteristics)) {
                $stmtChar = $pdo->prepare("INSERT INTO card_characteristics (card_id, characteristics_id) VALUES (:id, :char_id)");
                foreach ($characteristics as $charId) {
                    $stmtChar->execute([':id' => $cardId, ':char_id' => (int)$charId]);
                }
            }

            // 7. カードタイプ中間テーブルの削除＆再登録
            $pdo->prepare("DELETE FROM card_cardtype WHERE card_id = :id")->execute([':id' => $cardId]);
            if (!empty($cardtypes)) {
                $stmtType = $pdo->prepare("INSERT INTO card_cardtype (card_id, cardtype_id) VALUES (:id, :type_id)");
                foreach ($cardtypes as $typeId) {
                    $stmtType->execute([':id' => $cardId, ':type_id' => (int)$typeId]);
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