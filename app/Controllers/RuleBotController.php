<?php namespace Controllers;

use Models\Database;
use PDO;

class RuleBotController {

    public function chatApi() {
        // ★ タイムアウトを120秒に延長、メモリを512MBに緩和
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        header('Content-Type: application/json; charset=utf-8');

        // ★ PHPのFatal Error等の発生時にもHTMLではなくJSONを返すシャットダウンハンドラ
        register_shutdown_function(function() {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                http_response_code(500);
                echo json_encode(['error' => 'サーバー内部エラー: ' . $err['message'] . ' (行: ' . $err['line'] . ')'], JSON_UNESCAPED_UNICODE);
            }
        });

        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim($input['message'] ?? '');

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => 'メッセージを入力してください。']);
            exit;
        }

        try {
            $pdo = Database::connect();

            // 1. 質問文のカードを特定（ツインパクトの「上面 / 下面」も分解して検索）
            $rawCardNames = [];
            if (preg_match_all('/《([^》]+)》/u', $message, $m)) {
                $rawCardNames = array_unique($m[1]);
            }

            $searchNames = [];
            foreach ($rawCardNames as $rName) {
                if (strpos($rName, '/') !== false) {
                    $parts = array_map('trim', explode('/', $rName));
                    foreach ($parts as $p) {
                        if ($p !== '') $searchNames[] = $p;
                    }
                } else {
                    $searchNames[] = trim($rName);
                }
            }
            $searchNames = array_unique($searchNames);

            $matchedCards = [];
            if (!empty($searchNames)) {
                $inQuery = implode(',', array_fill(0, count($searchNames), '?'));
                
                $sql = "
                    WITH target_base_cards AS (
                        SELECT c.card_id, c.card_name, cd.twinpact,
                               ROW_NUMBER() OVER (PARTITION BY c.card_name, cd.twinpact ORDER BY c.card_id DESC) AS rn
                        FROM card c
                        JOIN card_detail cd ON c.card_id = cd.card_id
                        WHERE c.card_name IN ($inQuery)
                    ),
                    latest_targets AS (
                        SELECT card_id FROM target_base_cards WHERE rn = 1
                    ),
                    all_related_card_ids AS (
                        -- 通常カード（組み合わせなし）
                        SELECT lt.card_id, NULL AS combination_id, 1 AS sort_order
                        FROM latest_targets lt
                        LEFT JOIN card_combination cc ON lt.card_id = cc.card_id
                        WHERE cc.combination_id IS NULL

                        UNION ALL

                        -- 組み合わせカード（同じcombination_idを持つ全構成面：card_id昇順で上面/下面を判定）
                        SELECT cc_all.card_id, cc_all.combination_id, cc_all.card_id AS sort_order
                        FROM latest_targets lt
                        JOIN card_combination cc ON lt.card_id = cc.card_id
                        JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
                    )
                    SELECT DISTINCT 
                        c.card_id,
                        c.card_name,
                        c.reading,
                        c.cost,
                        c.pow,
                        c.text,
                        cd.twinpact,
                        cd.hypermode,
                        rel.combination_id,
                        rel.sort_order,
                        (SELECT GROUP_CONCAT(civ.civilization_name SEPARATOR ' / ') 
                         FROM card_civilization cciv 
                         JOIN civilization civ ON cciv.civilization_id = civ.civilization_id 
                         WHERE cciv.card_id = c.card_id) AS civ_names,
                        (SELECT GROUP_CONCAT(r.race_name SEPARATOR ' / ') 
                         FROM card_race cr 
                         JOIN race r ON cr.race_id = r.race_id 
                         WHERE cr.card_id = c.card_id) AS race_names,
                        (SELECT GROUP_CONCAT(ct.typename SEPARATOR ' / ') 
                         FROM card_cardtype ct 
                         WHERE ct.card_id = c.card_id) AS typenames
                    FROM all_related_card_ids rel
                    JOIN card c ON rel.card_id = c.card_id
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    ORDER BY rel.combination_id, rel.sort_order ASC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($searchNames));
                $matchedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 関連カード情報のテキスト構築（文明・種族・タイプ・ツインパクト/ハイパーモード・複数面対応）
            $cardContext = "";
            if (!empty($matchedCards)) {
                $cardContext .= "【関連カード情報】\n";

                // combination_id ごとにグループ化
                $grouped = [];
                foreach ($matchedCards as $c) {
                    $key = $c['combination_id'] ? 'combo_' . $c['combination_id'] : 'single_' . $c['card_id'];
                    $grouped[$key][] = $c;
                }

                foreach ($grouped as $cards) {
                    if (count($cards) > 1) {
                        $first = $cards[0];
                        $flags = [];
                        if (!empty($first['twinpact'])) $flags[] = 'ツインパクト';
                        if (!empty($first['hypermode'])) $flags[] = 'ハイパーモード';
                        $flagStr = !empty($flags) ? '【' . implode('/', $flags) . '】' : '【複数面カード】';

                        $cardContext .= "■ {$flagStr} (構成面数: " . count($cards) . ")\n";
                        foreach ($cards as $idx => $c) {
                            $sideNum = $idx + 1;
                            $cardContext .= "  [面{$sideNum}] 《" . $c['card_name'] . "》\n";
                            $cardContext .= "    タイプ: " . ($c['typenames'] ?: 'なし') . "\n";
                            $cardContext .= "    文明: " . ($c['civ_names'] ?: '無色') . "\n";
                            $cardContext .= "    種族: " . ($c['race_names'] ?: 'なし') . "\n";
                            $cardContext .= "    コスト: " . ($c['cost'] ?? '-') . " / パワー: " . ($c['pow'] ?? '-') . "\n";
                            $cardContext .= "    効果テキスト: " . $c['text'] . "\n\n";
                        }
                    } else {
                        $c = $cards[0];
                        $flags = [];
                        if (!empty($c['twinpact'])) $flags[] = 'ツインパクト';
                        if (!empty($c['hypermode'])) $flags[] = 'ハイパーモード';
                        $flagStr = !empty($flags) ? ' 【' . implode('/', $flags) . '】' : '';

                        $cardContext .= "■ 《" . $c['card_name'] . "》{$flagStr}\n";
                        $cardContext .= "  タイプ: " . ($c['typenames'] ?: 'なし') . "\n";
                        $cardContext .= "  文明: " . ($c['civ_names'] ?: '無色') . "\n";
                        $cardContext .= "  種族: " . ($c['race_names'] ?: 'なし') . "\n";
                        $cardContext .= "  コスト: " . ($c['cost'] ?? '-') . " / パワー: " . ($c['pow'] ?? '-') . "\n";
                        $cardContext .= "  効果テキスト: " . $c['text'] . "\n\n";
                    }
                }
            }
            // 1-2. 質問文から該当・類似する特殊能力を抽出し、代表カード（例文テキスト）を取得
            $abilityContext = "";
            $stmtAllAbilities = $pdo->query("SELECT ability_id, ability_name FROM ability");
            $allAbilities = $stmtAllAbilities->fetchAll(PDO::FETCH_ASSOC);

            // 表記揺れ（中黒やスペース）を除去した質問文
            $cleanMessage = str_replace(['・', ' ', '　'], '', $message);
            $matchedAbilityIds = [];

            foreach ($allAbilities as $ab) {
                $cleanAbName = str_replace(['・', ' ', '　'], '', $ab['ability_name']);
                if (mb_strlen($cleanAbName) >= 2 && mb_stripos($cleanMessage, $cleanAbName) !== false) {
                    $matchedAbilityIds[$ab['ability_id']] = $ab['ability_name'];
                }
            }

            if (!empty($matchedAbilityIds)) {
                $abilityContext .= "【関連する特殊能力と例文カード】\n";
                $stmtExample = $pdo->prepare("
                    SELECT c.card_name, c.text 
                    FROM card_ability ca 
                    JOIN card c ON ca.card_id = c.card_id 
                    WHERE ca.ability_id = :aid AND c.text IS NOT NULL AND c.text <> ''
                    ORDER BY c.card_id DESC 
                    LIMIT 2
                ");

                foreach ($matchedAbilityIds as $aid => $aname) {
                    $stmtExample->execute([':aid' => $aid]);
                    $examples = $stmtExample->fetchAll(PDO::FETCH_ASSOC);

                    $abilityContext .= "■ 特殊能力名: {$aname}\n";
                    if (!empty($examples)) {
                        foreach ($examples as $ex) {
                            $abilityContext .= "  【例文カード】《" . $ex['card_name'] . "》\n";
                            $abilityContext .= "  効果テキスト: " . $ex['text'] . "\n";
                        }
                    }
                    $abilityContext .= "\n";
                }
            }

            // 2. 総合ルールテキストを確実に読み込み（UTF-8自動変換 ＆ パス自動検出）
            $path1 = dirname(__DIR__) . '/storage/rules.txt';
            $path2 = dirname(dirname(__DIR__)) . '/storage/rules.txt';
            $ruleFilePath = file_exists($path1) ? $path1 : (file_exists($path2) ? $path2 : '');
            $rawRules = $ruleFilePath ? file_get_contents($ruleFilePath) : '';

            $encoding = mb_detect_encoding($rawRules, ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'SJIS', 'EUC-JP'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $comprehensiveRules = mb_convert_encoding($rawRules, 'UTF-8', $encoding);
            } else {
                $comprehensiveRules = $rawRules;
            }

            // 3. システム指示（結論ベース ＆ 聞かれたこと以外話さない ＆ 文末完結指示）
            $systemInstruction = "あなたはTCG「デュエル・マスターズ」の公認ジャッジです。
                                    プレイヤーの質問に対し、提示された【総合ゲームルール原文】、カード情報、および特殊能力情報に基づいて、
                                    【結論から端的に、聞かれたことだけに絞って】回答してください。

                                    【厳格な回答ルール】
                                    1. 挨拶や前置き（「ご質問ありがとうございます」等）は一切不要です。
                                    2. 1行目は必ず【結論】（例: 「可能です。」「対象になります。」「〜という能力です。」等）から始めてください。
                                    3. 【種族に関する重要な判定ルール】：
                                    ・複数種族はスラッシュ「 / 」で区切られて提示されます。種族名に含まれる中黒「・」（例: 「アウトレイジ・ドラゴン」「アーマード・ドラゴン」等）は種族名の一部であり、これで「1つの独立した種族名」です。「・」で勝手に分割しないでください。
                                    ・デュエル・マスターズのルール上、種族を指定する効果（例: 「ドラゴン」「コマンド」等）は、種族名の中にその文字列を含んでいる場合に有効です。例えば「アウトレイジ・ドラゴン」は名前に「ドラゴン」を含むため、「ドラゴン」を指定するすべての効果の対象になります。
                                    4. 【特殊能力についての質問の場合】：
                                    能力の概要・ルール処理を簡潔に説明した上で、必ず提供された【例文カード】の名前とテキストを引用し、具体例として提示してください。
                                    5. 【カードの効果テキスト、文明、種族、コスト、パワーなどカード情報自体の質問の場合】：
                                    総合ルールの条文番号や項番は一切記載せず、提示されたカード情報の内容のみをわかりやすく回答してください。
                                    6. 【ルールの処理・挙動・裁定に関する質問の場合】：
                                    根拠として、該当する総合ルールの条文番号（例: 803.1 など）や効果の解決順序を簡潔に示してください。
                                    7.「出る時」や「出す時」と書かれている効果は状態定義効果と言います。これらはバトルゾーンに出る前に処理されます。
                                    置換効果によってカードが実際にそのゾーンに置かれない場合、状態定義効果は何もしません。
                                    また、状態定義効果で他のクリーチャーになったとき、そのクリーチャーが「EXライフ」のような別の状態定義効果を持っていた場合、適用するタイミングが遅れているため、「EXライフ」は適用できません。
                                    8. 質問で聞かれていない余談や私見は語らず、文章が途中で切れないよう必ず文末まで完結させてください。";
            // 4. 有料Gemini API へ総合ルールとカード情報・質問を送信
            $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
            if (!$apiKey && file_exists(dirname(dirname(__DIR__)) . '/.env')) {
                $lines = file(dirname(dirname(__DIR__)) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, 'GEMINI_API_KEY=') === 0) {
                        $apiKey = trim(substr($line, 15));
                        break;
                    }
                }
            }

            // ★ gemini-1.5-flash（無料枠対象モデル）に変更
            $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key=' . $apiKey;
            $userPrompt = "【デュエル・マスターズ 総合ゲームルール原文】\n{$comprehensiveRules}\n\n"
                        . "{$cardContext}\n\n"
                        . "{$abilityContext}\n\n"
                        . "【プレイヤーからの質問】\n{$message}";
            $payload = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]]
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [['text' => $userPrompt]]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.0,
                    'maxOutputTokens' => 4096
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 60 // タイムアウトを60秒に調整
            ]);

            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError || $httpCode !== 200) {
                http_response_code(500);
                echo json_encode(['error' => 'Gemini APIの呼び出しに失敗しました: ' . ($curlError ?: $response)]);
                exit;
            }

            $resData = json_decode($response, true);

            // 分割された全パートのテキストを結合
            $reply = '';
            if (!empty($resData['candidates'][0]['content']['parts'])) {
                foreach ($resData['candidates'][0]['content']['parts'] as $part) {
                    $reply .= $part['text'] ?? '';
                }
            }

            if ($reply === '') {
                $reply = '回答を取得できませんでした。';
            }

            echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * 専用画面（デッキ作成ライクな大画面）を表示
     */
    public function index() {
        $pageTitle = 'デュエマ ルール相談AI';
        
        ob_start();
        require_once dirname(__DIR__) . '/Views/rule_bot/index.php';
        $content = ob_get_clean();

        require_once dirname(__DIR__) . '/Views/layouts/app.php';
    }
}