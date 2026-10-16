<?php namespace Controllers;

use Models\Database;
use PDO;

class RuleBotController {

    public function chatApi() {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim($input['message'] ?? '');

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['error' => 'メッセージを入力してください。']);
            exit;
        }

        try {
            $pdo = Database::connect();

            // 1. 【提示されたSQLを活用】質問文のカードを正確に特定し、相方テキストも含めて取得
            $cardNames = [];
            if (preg_match_all('/《([^》]+)》/u', $message, $m)) {
                $cardNames = array_unique($m[1]);
            }

            $matchedCards = [];
            if (!empty($cardNames)) {
                $inQuery = implode(',', array_fill(0, count($cardNames), '?'));
                
                $sql = "
                    WITH ranked_cards AS (
                        SELECT 
                            c.card_id,
                            c.card_name,
                            c.reading,
                            c.text,
                            c.cost,
                            c.pow,
                            ROW_NUMBER() OVER (
                                PARTITION BY 
                                    c.card_name, 
                                    c.reading, 
                                    cd.twinpact,
                                    partner.card_name
                                ORDER BY 
                                    c.card_id DESC
                            ) AS rn
                        FROM 
                            card c
                        JOIN 
                            card_detail cd ON c.card_id = cd.card_id
                        LEFT JOIN 
                            card_combination cc ON c.card_id = cc.card_id
                        LEFT JOIN 
                            card_combination cc_partner 
                                ON cc.combination_id = cc_partner.combination_id 
                                AND cc.card_id <> cc_partner.card_id
                        LEFT JOIN 
                            card partner ON cc_partner.card_id = partner.card_id
                    )
                    SELECT 
                        card_id,
                        card_name,
                        reading,
                        text,
                        cost,
                        pow
                    FROM 
                        ranked_cards
                    WHERE 
                        rn = 1
                        AND card_name IN ($inQuery)
                    ORDER BY 
                        reading
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($cardNames));
                $matchedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 関連カード情報のテキスト構築
            $cardContext = "";
            if (!empty($matchedCards)) {
                $cardContext .= "【関連カード情報】\n";
                foreach ($matchedCards as $c) {
                    $cardContext .= "■ 《" . $c['card_name'] . "》 (コスト:" . ($c['cost'] ?? '-') . " / パワー:" . ($c['pow'] ?? '-') . ")\n";
                    $cardContext .= "効果テキスト: " . $c['text'] . "\n\n";
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
プレイヤーの質問に対し、提示された【総合ゲームルール原文】およびカード情報のみに基づいて、
【結論から端的に、聞かれたことだけに絞って】回答してください。

【厳格な回答ルール】
1. 挨拶や前置き（「ご質問ありがとうございます」等）は一切不要です。
2. 1行目は必ず【結論】（例: 「可能です。」「破壊されません。」「〜という能力です。」等）から始めてください。
3. 続けて【根拠】として、該当する条文番号（例: 803.1など）やカードテキストに基づいた処理順序を簡潔に解説してください。
4. 質問で聞かれていない余談や私見は一切語らず、文章が途中で切れないよう必ず文末まで完結させてください。";

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

            $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key=' . $apiKey;
            $userPrompt = "【デュエル・マスターズ 総合ゲームルール原文】\n{$comprehensiveRules}\n\n"
                        . "{$cardContext}\n\n"
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
                    'maxOutputTokens' => 2500 // 途切れ防止
                ]
            ];

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 40
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
            $reply = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '回答を取得できませんでした。';

            echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}