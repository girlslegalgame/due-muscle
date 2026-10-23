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
            // 1-3. カード名も特殊能力名もない場合、効果テキスト本文から実在カードを1件取得
            if (empty($matchedCards) && empty($matchedAbilityIds)) {
                $searchPhrase = '';
                if (preg_match('/「([^」]+)」/u', $message, $km)) {
                    $searchPhrase = trim($km[1]);
                } else {
                    $cleanMsg = preg_replace('/(とはなんですか|について教えて|とは|ってなに|ですか|ください|また|例として|カードを).*$/u', '', $message);
                    $searchPhrase = trim($cleanMsg);
                }

                if (mb_strlen($searchPhrase) >= 4) {
                    $stmtTextSearch = $pdo->prepare("
                        SELECT c.card_name, c.text, c.cost, c.pow 
                        FROM card c 
                        JOIN card_detail cd ON c.card_id = cd.card_id 
                        WHERE c.text LIKE :phrase 
                        ORDER BY cd.release_date DESC, c.card_id DESC 
                        LIMIT 1
                    ");
                    $stmtTextSearch->execute([':phrase' => '%' . $searchPhrase . '%']);
                    $textCard = $stmtTextSearch->fetch(PDO::FETCH_ASSOC);

                    if ($textCard) {
                        $cardContext .= "【該当効果を持つ実在の例文カード】\n";
                        $cardContext .= "■ 《" . $textCard['card_name'] . "》\n";
                        $cardContext .= "  コスト: " . ($textCard['cost'] ?? '-') . " / パワー: " . ($textCard['pow'] ?? '-') . "\n";
                        $cardContext .= "  効果テキスト: " . $textCard['text'] . "\n\n";
                    }
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

// 3. システム指示（公認ジャッジの思考手順 ＆ 厳格な判定ルール）
            $systemInstruction = "あなたはTCG「デュエル・マスターズ」の公認ジャッジです。
提示された【総合ゲームルール原文】、カード情報、および特殊能力情報に基づいて、
以下の【思考プロセス】を経て、【結論から端的に、聞かれたことだけに絞って】回答してください。

【ジャッジ思考プロセス（内部で必ず実行すること）】
1. 関係するカードテキストや種族名、能力の分類（常時・誘発・置換・状態定義）を正確に確認する。
2. 処理タイミング（出る前/出た後）、AP優先、置換効果の非連鎖、タイムスタンプ（後出し優先）を照合する。
3. 公式Q&Aに確定裁定がある場合はそれを最優先し、客観的な事実のみで結論を確定させる。

【厳格な回答ルール】
1. 挨拶や前置き（「ご質問ありがとうございます」等）は一切不要です。
2. 1行目は必ず【結論】（例: 「可能です。」「対象になります。」「〜という能力です。」等）から始めてください。
3. 【種族に関する重要な判定ルール】：
・複数種族はスラッシュ「 / 」で区切られて提示されます。種族名に含まれる中黒「・」（例: 「アウトレイジ・ドラゴン」「アーマード・ドラゴン」等）は種族名の一部であり、これで「1つの独立した種族名」です。「・」で勝手に分割しないでください。
・デュエル・マスターズのルール上、種族を指定する効果（例: 「ドラゴン」「コマンド」等）は、種族名の中にその文字列を含んでいる場合に有効です。例えば「アウトレイジ・ドラゴン」は名前に「ドラゴン」を含むため、「ドラゴン」を指定するすべての効果の対象になります。
4. 【特殊能力についての質問の場合】：
能力の概要・ルール処理を簡潔に説明してください。
提供された【例文カード情報】がある場合はその名前とテキストをそのまま引用してください。
提示されていない場合は、カード名やテキストを勝手に捏造・創作することは固く禁じます。
5. 【カードの効果テキスト、文明、種族、コスト、パワーなどカード情報自体の質問の場合】：
総合ルールの条文番号や項番は一切記載せず、提示されたカード情報の内容のみをわかりやすく回答してください。
6. 【ルールの処理・挙動・裁定に関する質問の場合】：
根拠として、該当する総合ルールの条文番号（例: 803.1 など）や効果の解決順序を簡潔に示してください。
7. 【状態定義効果（出る時/出す時）のルール】：
「出る時」や「出す時」と書かれている効果は状態定義効果と言います。これらはバトルゾーンに出る前に処理されます。
置換効果によってカードが実際にそのゾーンに置かれない場合、状態定義効果は何もしません。
また、状態定義効果で他のクリーチャーになったとき、そのクリーチャーが「EXライフ」のような別の状態定義効果を持っていた場合、適用するタイミングが遅れているため、「EXライフ」は適用できません。
8.総合ルールで判断しきれない部分があるのでそれについて説明します。
《Library of Alexandria》のような、「攻撃の前に」の能力はメインステップに行う処理です。
 
「中央G・リンク」を持つゴッドは、自分や、他のゴッドが出る時、自分のゴッドのリンクを好きなだけ外し、その後、「右G・リンク」または「左G・リンク」を持つゴッドにリンクさせることができます。そのため、《ヨミとイズモの計画》で「クリーチャーを1体、自分の墓地から出してもよい。その後、それにリンクできる好きな数のゴッドを、自分の墓地からリンクして出してもよい。」で「中央G・リンク」を持つゴッドを出した場合、それが左右両方でリンクしている場合でも、墓地にあるすべての「右G・リンク」または「左G・リンク」を持つゴッドを、出すときにリンクを外し、それを、それにリンクできるクリーチャーにリンクして出す、ということを繰り返し、結果的にすべて出すことができます。
 
進化などによってカードの下に置かれているカードは通常のゲーム中は特性がすべて無視されますが、他のカード効果やルールにカード指定で指定される時のみ、特性が参照されます。(200.3a)

例:

カードの下に置かれている《晴舞龍 ズンドコ・モモキング》は、《イデア・パラドックス》のようなカードを指定する効果によって破壊できますが、《晴舞龍 ズンドコ・モモキング》の「このカードが離れた時」の能力はトリガーしません。
 
特定の能力を無視する効果は、それに該当する行のすべての能力を無視します。

例:

自分の《コアラ大佐の虹勲章》と《禁断英雄 モモキングダムX》がいる時、《禁断英雄 モモキングダムX》の「このクリーチャーは、カードが6枚以上含まれていなければ、攻撃できず、相手に選ばれない。」がすべて無視され、攻撃できるようになり、選ばれるようになります。
 
《連戦の精霊龍 カイザルルール》の「自分のターンのはじめに、自分の山札の上から1枚を見てもよい。それがエンジェル・コマンドなら出してもよい。こうして出さなければ、それをシールド化する。」のような能力で山札の上のカードを見られなかった時、参照する対象が無いため、「こうして出さなければ」の処理を行う事はできません。
 
「出た時にトリガーする能力を1つ使う」能力では、「○○によって出た時」のような条件付きの物も使うことができます。

例1:

自分の《勝熱百覇 モモキングReVo》がバトルゾーンにいる時、自分は《にやにYAH》を出しました。「出た時」の能力で「Jビッグ1する。」を選び、それで《勝熱百覇 モモキングReVo》を選んだ場合、「キリフダReVo」を使うことができます。
 
「出た時」にトリガーする能力を2つ以上持つクリーチャーには「自分のクリーチャーが出て、それの能力がトリガーする時」能力は、それらすべてに適応されます。

例:

《凶鬼90号 ゾレーゴ / 「大当たり!もう一本!!」》の呪文側を唱えてから、自分のクリーチャーをバトルゾーンに出しました。そのクリーチャーがバトルゾーンに出た時にトリガーする能力を２つ以上持っている場合、そのクリーチャーのすべての出た時にトリガーする能力が2回トリガーします。
 
何かしらの処理に置換効果が適応された場合、その処理は行ったことにはなりません。

例:

自分のシールドゾーンに表向きの《深き知識の神殿 ブルー・ツナミ》と相手の《魔素縫合 アモデゴラス》が状況で、自分のターン中、自分がこのターン2枚目のカードを引く時、それはかわりにマナゾーンに置かれます。結果的にカードを引いたことにはならないので、《深き知識の神殿 ブルー・ツナミ》の「自分がカードを引いた時」の能力はトリガーしません。
 
「○○する。それが××なら、△△する。その後□□する。」のような能力で「それが××なら」の条件に合わない場合、その後の同一テキストの処理はすべて行う事はできません。

例1:

《叡光の精霊アレックス》の「出た時」の能力で山札の上から1枚マナゾーンに置いたところ、それがエンジェル・コマンド・カードではなかったので、「その後、カードを1枚、自分のマナゾーンから手札に戻してもよい」は行えません。
 
自分のクリーチャーに能力を与える能力や、進化する時に、進化元に付与されている効果を受け継ぐ処理は、出る直前に適応されます。これは状態定義効果の後に適応されます。

例1:

《サイバー・B・フィッシュα》の「サバイバー」能力で自分のサバイバーすべてに「ジャストダイバー」が与えられている時、自分がサバイバー・クリーチャーを出したら、出た後に「ジャストダイバー」が与えられて、トリガータイミングを失って選ばれなくなることはなく、出る前に「ジャストダイバー」が与えられ、そのクリーチャーが出て、「ジャストダイバー」の「出た時」がトリガーします。

例2:

自分の《アカシック・パラレル》が「出た時、または自分のターンのはじめに」の能力で《天災 デドダム》になっています。これが攻撃する時、「SSS級侵略[天災]」で《SSS級天災 デッドダムド》に進化させましたが、出る前に《天災 デドダム》になるため、《SSS級天災 デッドダムド》の「このクリーチャーを他のクリーチャーの上に重ねた時」の能力はトリガーしませんが、《天災 デドダム》の「出た時」の能力はトリガーします。
 
バトルゾーン以外のいずれかのゾーンに複数のカードが移動する時、それらはすべて同時に移動します。これにターンプレイヤーは優先されません。
 
バトルゾーンから墓地に移動することはすべて破壊として扱われます。そのため、進化クリーチャーの下にあるカードが墓地に置かれたとき、「カードが破壊された時」の能力がトリガーします。
 
攻撃ステップは以下のように行われます。

攻撃クリーチャー指定ステップ、ブロッククリーチャー指定ステップ、(クリーチャーを攻撃している、またはブロックなどで攻撃先がクリーチャーに変更されている場合)バトルステップ(プレイヤーを攻撃している場合)ダイレクトアタックステップ、攻撃終了ステップ

まだ攻撃できるクリーチャーがいるなら、再び攻撃クリーチャー指定ステップを行うことができます。攻撃を行わずにターン終了ステップに移行することもできます。

「攻撃中」に継続する効果は、攻撃終了ステップに消滅します。攻撃クリーチャーがバトルゾーンを離れた場合、その時点で解決中の効果と待機中の効果をすべて解決し終わった後で攻撃終了ステップへ移行します。
 
コストを指定し、そのカードを実行する際、「コストが○○以下のカードを選ぶ。そのカードを実行する。」のように、カードを選ぶテキストとそのカードを実行するテキストが「。」で分かれているテキストの場合、選んだカードがツインパクトなら、もう片方が指定条件に合わない場合でも、そちら側を選んで実行ができます。

「コストが○○以下のカードを実行する。」のようなテキストの効果でツインパクトカードを選んだ場合、もう片方が指定条件に合わない場合、そちら側を選んで実行はできません。

例1:

《サイバー・I・チョイス》の「このクリーチャーが出た時、「S・トリガー」を持つカードを1枚、自分の手札から選んでもよい。選んだカードをコストを支払わずに使う。」の効果で「S・トリガー」を持つ《バーンメア・ザ・シルバー / オラオラ・スラッシュ》の呪文側を選び、クリーチャー側を実行できます。

例2:

《仙祖電融 テラスネスク》の「このクリーチャーが出た時、コスト4以下のカードを1枚、自分の手札からコストを支払わずに使ってもよい。」で《バーンメア・ザ・シルバー / オラオラ・スラッシュ》の呪文側はコスト4以下ですが、これを選んでクリーチャー側を使うことはできません。
 
総合ルールの605.2eに則り、ツインパクトクリーチャーがバトルゾーンで自身の呪文側を唱える効果がトリガーした時、その効果を処理する前に他のゾーンに移動した場合でも、呪文を唱えることができます。
 
手札や山札やマナゾーン、墓地にある、上面と下面で別々の単一の文明を持つツインパクトカードは、カード指定で選ばれる時、多色カード扱いになります。ただし、出そうとしていたり、実行しようとしている場合、そちら側の文明のみが参照されます。

例1:

《ピクシー・ライフ》で《「色災」の頂 カラーレス・レインボー / 天上天下輪廻天頂》は手札に戻せません。

例2:

相手の《神化のカルマ シフォン》がいる時、自分の《魔導管理室 カリヤドネ / ハーミット・サークル》のコストは2多くなりません。

例3:
自分の手札が3枚の状況です。《劇場戦艦 カァテンコヲル》の「出た時」の能力で、呪文側のコストが3の《魔導管理室 カリヤドネ/ハーミット・サークル》は、≪ハーミットサークル≫が光文明のため選択できないので《魔導管理室 カリヤドネ》は召喚できません。

 
自分がカードの能力で「相手がクリーチャーを選ぶ時、かわりに自分が選ぶ。」を受けている状況で、相手がエレメントを選ぶ能力を使うとき、まず相手がどのエレメントを選びたいか決め、それがクリーチャーだった場合のみ、かわりに自分が選ぶことができます。クリーチャーではなかった場合、結果的に選びたかったエレメントが選ばれます。
 
自分の「相手がクリーチャーを選ぶ時、可能ならこのクリーチャーを選ぶ。」を持つクリーチャーがいる状況で、相手がエレメントを選ぶ能力を使うとき、相手がクリーチャーではないエレメントを選びたくても、「相手がクリーチャーを選ぶ時、可能ならこのクリーチャーを選ぶ。」を持つクリーチャーを選ばなければなりません。

 
9. 質問で聞かれていない余談や私見は語らず、文章が途中で切れないよう必ず文末まで完結させてください。";   
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