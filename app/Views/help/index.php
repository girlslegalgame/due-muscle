<!-- app/Views/help/index.php -->
<style>
    .help-doc-container {
        max-width: 850px;
        margin: 0 auto;
        padding: 25px 20px;
        font-family: sans-serif;
        color: #333;
        line-height: 1.7;
    }

    /* --- 各大項目セクション --- */
    .help-section {
        background: #fff;
        border: 1px solid #e5e5ea;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .help-section-title {
        font-size: 1.3rem;
        font-weight: bold;
        color: #111;
        border-bottom: 2px solid #333;
        padding-bottom: 8px;
        margin-top: 0;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* --- リスト・仕様記述 --- */
    .help-list {
        margin: 0;
        padding-left: 20px;
    }
    .help-list li {
        margin-bottom: 12px;
        font-size: 0.95rem;
    }
    .help-list li::marker {
        color: #007bff;
    }

    /* 補足や強調テキスト */
    .txt-bold {
        font-weight: bold;
        color: #111;
    }
    .txt-highlight {
        background: #fff3cd;
        padding: 2px 4px;
        border-radius: 4px;
        font-weight: bold;
    }

    /* --- ルール・ルビ具体例のグリッド --- */
    .example-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 15px;
        margin-top: 15px;
        padding: 10px 0;
    }
    .example-card {
        background: #f8f9fa;
        border: 1px solid #e5e5ea;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        box-sizing: border-box;
    }
    .example-card-title {
        font-weight: bold;
        font-size: 0.9rem;
        color: #222;
        margin-bottom: 4px;
    }
    .example-card-ruby {
        font-size: 0.8rem;
        color: #007bff;
        font-weight: bold;
        margin-bottom: 8px;
    }
    .example-img-holder {
        width: 100%;
        max-width: 160px;
        aspect-ratio: 110/154;
        margin: 0 auto;
        border: 1px solid #ccc;
        border-radius: 4px;
        overflow: hidden;
        background: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .example-img-holder img {
        width: 100%;
        height: 100%;
        object-fit: fill;
    }
</style>

<div id="container">
    <div style="flex: 1; overflow-y: auto; padding-bottom: 40px;">
        <div class="help-doc-container">

            <!-- 1. デッキ作成画面について -->
            <div class="help-section">
                <h3 class="help-section-title">1. デッキ作成</h3>
                <ul class="help-list">
                    <li>
                        カード画像は、データベースに登録されている中で<span class="txt-bold">「発売日が最も古いバージョン」</span>のものが検索結果に優先して表示されます。
                    </li>
                    <li>
                        カードは、検索結果から<span class="txt-bold">ドラッグ＆ドロップ</span>するか、カードをクリックして詳細モーダルを開き、<span class="txt-bold">プラス（+）ボタン</span>を押すことでデッキに追加できます。
                    </li>
                    <li>
                        サイキック、ドラグハート、GRクリーチャー、デュエルメイト、《終焉の禁断 ドルマゲドンX》、《零龍》をメインデッキエリアにドラッグ＆ドロップした場合、<span class="txt-bold">それぞれの適正なカードリストへ自動的に登録</span>されます。
                    </li>
                    <li>
                        メインデッキ、超次元ゾーン、超GRゾーンのカードを削除したい場合は、<span class="txt-bold">ゴミ箱アイコンにドラッグ＆ドロップ</span>するか、各カードのモーダルから<span class="txt-bold">マイナス（-）ボタン</span>を押すことで削除できます。
                    </li>
                    <li>
                        《終焉の禁断 ドルマゲドンX》と《零龍》は、特殊タブ内の<span class="txt-bold">「追加する」ボタン</span>から追加できます。削除する際は、カード詳細モーダルのマイナスボタンを押すか、特殊タブ内の<span class="txt-bold">「削除する」ボタン</span>を押してください。
                    </li>
                    <li>
                        検索設定で「カード名」にチェックが入っている場合は、<span class="txt-bold">ルビでの検索</span>も可能です。
                    </li>
                    <li>
                        データベースに登録されているカード名、ルビ、テキスト内の英数字および記号は、<span class="txt-highlight">「・」</span>と<span class="txt-highlight">「～」</span>を除き、<span class="txt-bold">すべて半角</span>で統一されています。
                    </li>
                    <li>
                        検索時に「ひらがな」で入力した場合でも、内部で自動変換され、対象のカードが正常に検索結果に表示されます。
                    </li>
                    <li>
                        カード名にのルビは、ルビに明記されている場合を除き、原則として<span class="txt-bold">「ひらがな」「カタカナ」「英数字」のみ</span>で構成され、「・」などの記号は省いて登録されています。ルビ表記のない英数字はそのまま登録されています。
                    </li>
                </ul>

                <!-- ルビ検索の具体例エリア -->
                <div style="margin-top: 20px; background: #fafafa; border: 1px dashed #ccc; padding: 15px; border-radius: 8px;">
                    <div style="font-weight: bold; font-size: 0.9rem; color: #444; margin-bottom: 10px;">【ルビ登録と検索の具体例】</div>
                    <div class="example-grid">
                        
                        <!-- 例1 -->
                        <div class="example-card">
                            <div class="example-card-title">《否男》</div>
                            <div class="example-card-ruby">ルビ：いなおとこ</div>
                            <div class="example-img-holder">
                                <!-- ★必要に応じて実際の画像パス（例：/images/card/xxxx.jpg）に書き換えてください -->
                                <img src="/images/card/dm37/dm37-046.webp" onerror="this.src='/images/card/noimage.webp'" alt="否男">
                            </div>
                        </div>

                        <!-- 例2 -->
                        <div class="example-card">
                            <div class="example-card-title">《凶戦士ブレイズ・クロー》</div>
                            <div class="example-card-ruby">ルビ：きょうせんしブレイズクロー</div>
                            <div class="example-img-holder">
                                <img src="/images/card/dm01/dm01-100.webp" onerror="this.src='/images/card/noimage.webp'" alt="凶戦士ブレイズ・クロー">
                            </div>
                        </div>

                        <!-- 例3 -->
                        <div class="example-card">
                            <div class="example-card-title">《その子供、凶暴につき》</div>
                            <div class="example-card-ruby">ルビ：そのこどもきょうぼうにつき</div>
                            <div class="example-img-holder">
                                <img src="/images/card/promoy11/promoy11-066.webp" onerror="this.src='/images/card/noimage.webp'" alt="その子供、凶暴につき">
                            </div>
                        </div>

                        <!-- 例4 -->
                        <div class="example-card">
                            <div class="example-card-title">《青銅の鎧》</div>
                            <div class="example-card-ruby">ルビ：ブロンズ・アーム・トライブ</div>
                            <div class="example-img-holder">
                                <img src="/images/card/dm01/dm01-106.webp" onerror="this.src='/images/card/noimage.webp'" alt="青銅の鎧">
                            </div>
                        </div>

                        <!-- 例5 -->
                        <div class="example-card">
                            <div class="example-card-title">《凶鬼03号 ガシャゴズラ》</div>
                            <div class="example-card-ruby">ルビ：きょうき03ごう ガシャゴズラ</div>
                            <div class="example-img-holder">
                                <img src="/images/card/dmrp02/dmrp02-s07.webp" onerror="this.src='/images/card/noimage.webp'" alt="天啓 CX-20">
                            </div>
                        </div>

                        <!-- 例6 -->
                        <div class="example-card">
                            <div class="example-card-title">《サイレンス トパーズ》</div>
                            <div class="example-card-ruby">ルビ：呪文たちの沈黙 トパーズ</div>
                            <div class="example-img-holder">
                                <img src="/images/card/dmr09/dmr09-013.webp" onerror="this.src='/images/card/noimage.webp'" alt="サイレンス トパーズ">
                            </div>
                        </div>

                    </div>
                </div>

                <ul class="help-list" style="margin-top: 15px;">
                    <li>
                        初期状態における各種ゾーンの並び順は、<span class="txt-bold">「自由ソート」</span>です。画面右下の「並び替え」から、コスト順や文明順など別の整列ルールを適用できます。
                    </li>
                </ul>
            </div>

            <!-- 2. トップ(自分のデッキ一覧) -->
            <div class="help-section">
                <h3 class="help-section-title">2. トップ (自分のデッキ一覧)</h3>
                <ul class="help-list">
                    <li>
                        フッターメニューにある「デッキ作成」リンクだけでなく、トップ画面上部の<span class="txt-bold">「新規作成」</span>からでも新しくデッキの作成を開始できます。
                    </li>
                    <li>
                        作成したデッキは、<span class="txt-bold">「最終更新日時が新しい順」</span>で表示されます。
                    </li>
                    <li>
                        <span class="txt-bold">「内容表示」</span>で、該当デッキの登録カードリスト、文明配分、コスト帯配分の集計グラフをすぐに確認できます。表示されたリスト内のカードをクリックすると、その効果テキストの詳細が表示されます。
                    </li>
                    <li>
                        <span class="txt-bold">「画像出力」</span>で、作成したデッキリストを画像として出力し、保存できます。
                    </li>
                    <li>
                        <span class="txt-bold">「編集」</span>で、登録済みのデッキ内容を引き継いだ状態でデッキ作成画面を開き、内容をいつでも再編集できます。
                    </li>
                    <li>
                        <span class="txt-bold">「×」</span>で、デッキを削除できます。<span class="txt-bold" style="color: #dc3545;">※一度削除したデッキデータは復元できません。</span>
                    </li>
                </ul>
            </div>

            <!-- 3. デッキ検索画面 -->
            <div class="help-section" style="margin-bottom: 10px;">
                <h3 class="help-section-title">3. デッキ検索</h3>
                <ul class="help-list">
                    <li>
                        他ユーザーによって公開設定されたデッキが、一覧に<span class="txt-bold">「公開日時が新しい順」</span>で並びます。
                    </li>
                    <li>
                        <span class="txt-bold">「内容表示」</span>の機能は自分のデッキ一覧と同様で、他ユーザーのデッキのコストバランスやテキストを詳細に分析・確認できます。
                    </li>
                    <li>
                        公開されているデッキの<span class="txt-bold">「コピー」</span>ボタンを押すと、そのレシピを丸ごと自分のデッキとして複製できます。コピーされたデッキは、タイトルが自動的に「(元のデッキ名)のコピー」に設定されてマイデッキに保存されます。
                    </li>
                </ul>
            </div>

        </div>
    </div>
</div>