package deck

import (
	"fmt"
	"html/template"
	"io"
	"time"

	models "deck-maker/app/Models" // 必要に応じて実際のパッケージパスに合わせてください
)

// IndexViewData はビューに渡されるパラメータ構造体です
type IndexViewData struct {
	Decks       []map[string]interface{}
	TotalPages  int
	CurrentPage int
	UserID      *int // セッションの user_id (未ログイン時はnil)
}

func RenderIndex(w io.Writer, data IndexViewData) error {
	// Database::connect() の挙動
	pdo_db, err := models.DatabaseConnect()
	if err != nil {
		pdo_db = nil
	}
	_ = pdo_db

	var currentUserIDStr string
	if data.UserID != nil {
		currentUserIDStr = fmt.Sprintf("%d", *data.UserID)
	}

	// --- HTML Header & CSS (静的コンテンツ) ---
	fmt.Fprintf(w, `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイデッキ一覧</title>
    <link rel="stylesheet" href="/css/style.css?v=%d">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="/js/ccfolia_exporter.js"></script>
<style>
/* 3. 画像出力用の一時的な非表示コンテナのスタイル（1200px固定）のみ残します */
#deck-export-container {
    position: fixed;
    left: -9999px;
    top: 0;
    width: 1200px;
    background-color: #000000;
    color: #ffffff;
    font-family: sans-serif;
    padding: 30px;
    box-sizing: border-box;
    pointer-events: none;
    z-index: -1;
}

/* 画像化レイアウトのCSS */
.export-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #333333; /* 暗めの境界線 */
    padding-bottom: 15px;
    margin-bottom: 20px;
}
.export-title {
    font-size: 2.2rem;
    font-weight: bold;
    margin: 0;
    color: #ffffff;
}
.export-meta {
    text-align: right;
}
.export-colors {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
    margin-bottom: 8px;
}
.export-color-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: #fff;
    font-weight: bold;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 1px 1px 3px rgba(0,0,0,0.5);
}
/* 文明色マッピング */
.bg-fire    { background-color: #e6193c; } 
.bg-water   { background-color: #1972e6; } 
.bg-light   { background-color: #e6b800; color: #000 !important; } 
.bg-dark    { background-color: #4b2c80; } 
.bg-nature  { background-color: #2ca043; } 
.bg-zero    { background-color: #7d8285; }

.export-format {
    font-size: 1.2rem;
    color: #cccccc; /* 黒背景で見やすい明るいグレー */
    font-weight: bold;
}

.export-body {
    display: flex;
    gap: 30px;
}
.export-body.single-column .export-main-deck-wrapper {
    width: 100%;
}
.export-body.two-column .export-main-deck-wrapper {
    width: 72%;
}
.export-body.two-column .export-sub-decks-wrapper {
    width: 28%;
}

.export-section-title {
    font-size: 1.4rem;
    font-weight: bold;
    text-align: center;
    border-bottom: 1px solid #333333; /* 暗めの境界線 */
    padding-bottom: 5px;
    margin: 0 0 15px 0;
    color: #ffffff;
}

.export-card-grid {
    display: grid;
    gap: 6px;
}
.grid-main {
    grid-template-columns: repeat(8, 1fr);
}
.grid-sub {
    grid-template-columns: repeat(4, 1fr);
}

.export-card-item {
    position: relative;
    aspect-ratio: 51 / 73;
    overflow: hidden;
    border-radius: 4px;
    border: 1px solid #222222;
    background-color: #111111;
}
.export-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.export-sub-zone {
    margin-bottom: 25px;
}

.export-footer {
    text-align: center;
    margin-top: 30px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.export-footer-logo {
    height: 45px;
    object-fit: contain;
}

/* プロキシ選択モード中の各種操作無効化 */
.proxy-select-mode .deck-item .btn-group,
.proxy-select-mode .deck-item .btn-deck-public-toggle {
    pointer-events: none;
    opacity: 0.4;
}
.proxy-select-mode .deck-item .deck-thumbnail {
    pointer-events: none; /* サムネイルクリックによるモーダル起動を無効化 */
}
/* --- デッキ操作メニュー用ボタンスタイル --- */
.deck-menu-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 15px;
}

.deck-menu-btn {
    width: 100%;
    height: 44px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #2d3748;
    font-size: 0.95rem;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    box-sizing: border-box;
}

.deck-menu-btn:hover {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}

/* メイン操作「確認」：サイトヘッダーに合わせたシックな黒 */
.deck-menu-btn-primary {
    background-color: #1a202c;
    color: #ffffff;
    border-color: #1a202c;
}
.deck-menu-btn-primary:hover {
    background-color: #2d3748;
    border-color: #2d3748;
}

/* 危険操作「削除」：落ち着いた薄赤アウトライン */
.deck-menu-btn-danger {
    color: #e53e3e;
    background-color: #fff;
    border-color: #fed7d7;
    margin-top: 4px;
}
.deck-menu-btn-danger:hover {
    background-color: #fff5f5;
    border-color: #feb2b2;
}
/* マイデッキ一覧のカード全体をクリック可能に */
.mydeck-clickable {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.mydeck-clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    border-color: #bbb;
}

/* 公開状態切り替えボタン */
    .btn-deck-public-toggle {
        border: none;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 0.75rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-deck-public-toggle.status-public {
        background-color: #e6f4ea;
        color: #137333;
        border: 1px solid #ceead6;
    }
    .btn-deck-public-toggle.status-public:hover {
        background-color: #ceead6;
    }
    .btn-deck-public-toggle.status-private {
        background-color: #f1f3f4;
        color: #5f6368;
        border: 1px solid #dadce0;
    }
    .btn-deck-public-toggle.status-private:hover {
        background-color: #e8eaed;
    }
/* --- ページネーション（サイトに合わせたモノトーンデザイン） --- */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 30px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    background-color: #ffffff;
    color: #2d3748 !important;
    text-decoration: none !important;
    font-size: 0.9rem;
    font-weight: bold;
    transition: all 0.15s ease;
    box-sizing: border-box;
}

.pagination-link:hover {
    background-color: #f7fafc;
    border-color: #cbd5e0;
}

/* 現在のページ */
.pagination-link.active {
    background-color: #1a202c !important;
    color: #ffffff !important;
    border-color: #1a202c !important;
    cursor: default;
    pointer-events: none;
}

/* 無効化（前へ・次への端） */
.pagination-link.disabled {
    opacity: 0.35;
    pointer-events: none;
    cursor: not-allowed;
    background-color: #edf2f7;
}

.pagination-ellipsis {
    padding: 0 4px;
    color: #a0aec0;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="container">
    <h2>マイデッキ一覧</h2>
    <div style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
        <a href="/decks/new" class="create-btn" style="margin-bottom: 0;">＋ 新規作成</a>
        <button type="button" id="btn-proxy-action" class="create-btn" style="margin-bottom: 0; border: none; cursor: pointer;" onclick="handleProxyButtonClick()">
            プロキシPDF出力
        </button>
    </div>

    <div class="deck-list">
`, time.Now().Unix())

	// --- デッキ一覧ループ ---
	if len(data.Decks) > 0 {
		context := "index"

		// 1. テンプレートをあらかじめパース
		itemTmpl, err := template.ParseFiles("app/Views/deck/deck_item.html")
		if err != nil {
			fmt.Fprintf(w, "<p>テンプレート読み込みエラー: %v</p>", err)
			return err
		}

		for _, dMap := range data.Decks {
			// 2. map[string]interface{} から deck_item.go の Deck 構造体に変換
			d := Deck{
				DeckName:           fmt.Sprintf("%v", dMap["deck_name"]),
				ThumbnailImagePath: fmt.Sprintf("%v", dMap["thumbnail_imagepath"]),
				FormatName:         fmt.Sprintf("%v", dMap["format_name"]),
			}

			// DeckID の安全な型変換
			if idVal, ok := dMap["deck_id"]; ok && idVal != nil {
				fmt.Sscanf(fmt.Sprintf("%v", idVal), "%d", &d.DeckID)
			}

			// 公開フラグの判定
			if p, ok := dMap["is_public"]; ok && (p == 1 || p == "1" || p == true) {
				d.IsPublic = true
			}

			// 3. 正しい引数 (5つ) を渡して実行
			if err := RenderDeckItem(w, itemTmpl, d, context, pdo_db); err != nil {
				fmt.Fprintf(w, "<p>レンダリングエラー: %v</p>", err)
			}
		}
	} else {
		fmt.Fprint(w, `        <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #fff; border-radius: 8px; border: 1px solid #ddd;">`+"\n")
		if data.UserID == nil {
			fmt.Fprint(w, `            <p style="font-size: 1.1rem; color: #333; font-weight: bold; margin-bottom: 10px;">アカウントを作成すると、デッキを保存できます。</p>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 25px;">作成したデッキをクラウドに保存して、いつでも編集や公開ができるようになります。</p>
            <a href="/register" style="display: inline-block; padding: 12px 30px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 1rem;">アカウントを作成する</a>`+"\n")
		} else {
			fmt.Fprint(w, `            <p style="color: #666;">デッキが登録されていません。</p>`+"\n")
		}
		fmt.Fprint(w, `        </div>`+"\n")
	}
	fmt.Fprint(w, `    </div>`+"\n")

	// --- ページネーション ---
	if data.TotalPages > 1 {
		prevDisabled := ""
		if data.CurrentPage <= 1 {
			prevDisabled = "disabled"
		}
		fmt.Fprintf(w, `    <nav class="pagination-wrapper" aria-label="Page navigation">
        <a href="?page=%d" class="pagination-link %s" aria-label="Previous">«</a>
`, data.CurrentPage-1, prevDisabled)

		startPage := data.CurrentPage - 2
		if startPage < 1 {
			startPage = 1
		}
		endPage := data.CurrentPage + 2
		if endPage > data.TotalPages {
			endPage = data.TotalPages
		}

		if startPage > 1 {
			fmt.Fprint(w, `        <a href="?page=1" class="pagination-link">1</a>`+"\n")
			if startPage > 2 {
				fmt.Fprint(w, `        <span class="pagination-ellipsis">…</span>`+"\n")
			}
		}

		for p := startPage; p <= endPage; p++ {
			activeClass := ""
			if p == data.CurrentPage {
				activeClass = "active"
			}
			fmt.Fprintf(w, `        <a href="?page=%d" class="pagination-link %s">%d</a>`+"\n", p, activeClass, p)
		}

		if endPage < data.TotalPages {
			if endPage < data.TotalPages-1 {
				fmt.Fprint(w, `        <span class="pagination-ellipsis">…</span>`+"\n")
			}
			fmt.Fprintf(w, `        <a href="?page=%d" class="pagination-link">%d</a>`+"\n", data.TotalPages, data.TotalPages)
		}

		nextDisabled := ""
		if data.CurrentPage >= data.TotalPages {
			nextDisabled = "disabled"
		}
		fmt.Fprintf(w, `        <a href="?page=%d" class="pagination-link %s" aria-label="Next">»</a>
    </nav>
`, data.CurrentPage+1, nextDisabled)
	}

	fmt.Fprint(w, `</div>`+"\n")

	// --- モーダルインクルード ---
	// include __DIR__ . '/deck_detail_modal.go'
	RenderDeckDetailModal(w)

	// --- モーダルHTML & JavaScript ---
	fmt.Fprint(w, `<!-- デッキ操作メニューモーダル -->
<div id="deck-action-menu-modal" class="sub-modal" style="display: none;">
    <div class="sub-modal-content" style="max-width: 320px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div class="sub-modal-header" style="background: #1a202c; padding: 12px 16px;">
            <span id="action-menu-deck-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; font-size: 0.95rem;">デッキメニュー</span>
            <button type="button" onclick="closeDeckActionMenu()" style="background:none; border:none; color:#a0aec0; font-size:1.4rem; cursor:pointer; line-height: 1;">×</button>
        </div>
        <div class="deck-menu-list">
            <button type="button" class="deck-menu-btn deck-menu-btn-primary" onclick="onMenuAction('view')">デッキを確認</button>
            <button type="button" class="deck-menu-btn" onclick="onMenuAction('edit')">編集する</button>
            <button type="button" class="deck-menu-btn" onclick="onMenuAction('share')">共有URLをコピー</button>
            <button type="button" class="deck-menu-btn" onclick="onMenuAction('export')">画像 / ZIPで出力</button>
            <button type="button" class="deck-menu-btn" onclick="onMenuAction('copy')">デッキを複製</button>
            <button type="button" class="deck-menu-btn deck-menu-btn-danger" onclick="onMenuAction('delete')">削除する</button>
        </div>
    </div>
</div>
<!-- デッキ出力方法選択モーダル -->
<div id="deck-export-choice-modal" class="sub-modal" style="display: none;">
    <div class="sub-modal-content" style="max-width: 420px;">
        <div class="sub-modal-header">
            <span>デッキ出力</span>
            <button type="button" onclick="closeExportChoiceModal()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">×</button>
        </div>
        <div class="sub-modal-body" style="padding: 25px; text-align: left;">
            <p style="margin-top: 0; margin-bottom: 15px; font-weight: bold; color: #333; text-align: center;">出力形式を選択してください</p>
            
            <div style="display: flex; gap: 15px; justify-content: center; margin-bottom: 20px;">
                <button type="button" id="btn-export-image" style="flex: 1; padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">画像で出力</button>
                <button type="button" id="btn-export-zip" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">ZIPで出力</button>
            </div>

            <!-- ZIP出力時のオプション設定 -->
            <div id="zip-options-wrapper" style="border-top: 1px solid #ddd; padding-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: bold; cursor: pointer; color: #444; font-size: 0.9rem;">
                    <input type="checkbox" id="zip-include-text" style="width: 16px; height: 16px;">
                    ZIP出力時にカードテキストを含める (memoに記載)
                </label>
                <label style="display: flex; align-items: center; gap: 8px; font-weight: bold; cursor: pointer; color: #444; font-size: 0.9rem;">
                    <input type="checkbox" id="zip-separate-partner" style="width: 16px; height: 16px;">
                    パートナー（サムネイル）をデッキから独立して出力する (デュエパーティ用)
                </label>
                <!-- ★追加: ゾーン別出力オプション -->
                <div style="border-top: 1px dashed #eee; padding-top: 8px; display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #444; font-size: 0.85rem;">
                        <input type="checkbox" id="zip-include-dim" checked style="width: 16px; height: 16px;">
                        超次元ゾーンを出力する
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #444; font-size: 0.85rem;">
                        <input type="checkbox" id="zip-include-gr" checked style="width: 16px; height: 16px;">
                        超GRゾーンを出力する
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #444; font-size: 0.85rem;">
                        <input type="checkbox" id="zip-include-special" checked style="width: 16px; height: 16px;">
                        特殊カード（ドキンダム / ドルマゲドン / 零龍等）を出力する
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: #444; font-size: 0.85rem;">
                        <input type="checkbox" id="zip-include-linked" checked style="width: 16px; height: 16px;">
                        リンク後のカードを出力する
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ZIP出力中待機モーダル -->
<div id="zip-export-loading-modal" class="sub-modal" style="display: none; z-index: 3100;">
    <div class="sub-modal-content" style="max-width: 400px; text-align: center;">
        <div class="sub-modal-header" style="justify-content: center;">
            <span>出力処理中</span>
        </div>
        <div class="sub-modal-body" style="padding: 30px 20px;">
            <p style="font-weight: bold; color: #d9534f; font-size: 1.05rem; margin: 0 0 10px 0;">
                出力中です。他の画面に移動しないでください。
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 0;">
                データを生成・圧縮しています。完了までしばらくお待ちください。
            </p>
        </div>
    </div>
</div>

<!-- 出力完了モーダル（広告スペース付き） -->
<div id="export-complete-modal" class="sub-modal" style="display: none; z-index: 3100;">
    <div class="sub-modal-content" style="max-width: 450px; text-align: center;">
        <div class="sub-modal-header" style="justify-content: center;">
            <span>ダウンロード完了</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px;">
            <p style="font-weight: bold; color: #28a745; font-size: 1.1rem; margin: 0 0 8px 0;">
                ファイルの生成が完了しました！
            </p>
            <p style="color: #666; font-size: 0.85rem; margin: 0 0 15px 0;">
                自動的にダウンロードが開始されない場合は、再度お試しください。
            </p>

            <!-- 広告枠（誤タップを防ぐため上下マージンを確保しラベルを明記） -->
            <div style="margin: 15px 0;">
                <div style="font-size: 0.75rem; color: #888; margin-bottom: 4px;">スポンサーリンク</div>
                <div style="min-height: 250px; background-color: #f9f9f9; display: flex; align-items: center; justify-content: center;">
                    <!-- AdSense 広告ユニットタグ -->
                    <ins class="adsbygoogle"
                         style="display:block; width:100%;"
                         data-ad-client="ca-pub-XXXXXXXXXXXXXXXX"
                         data-ad-slot="YYYYYYYYYY"
                         data-ad-format="rectangle"></ins>
                </div>
            </div>

            <!-- 広告との誤タップを防ぐため、十分な余白を取った閉じるボタン -->
            <button type="button" onclick="closeExportCompleteModal()" style="margin-top: 15px; padding: 10px 30px; background: #6c757d; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
                閉じる
            </button>
        </div>
    </div>
</div>

<!-- プロキシPDF出力設定モーダル -->
<div id="proxy-export-modal" class="sub-modal" style="display: none;">
    <div class="sub-modal-content" style="max-width: 480px;">
        <div class="sub-modal-header">
            <span>プロキシ印刷用PDF出力</span>
            <button type="button" onclick="closeProxyModal()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">×</button>
        </div>
        <div class="sub-modal-body" style="padding: 20px; text-align: left;">
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; font-size: 0.9rem; color: #333;">対象カード:</label>
                <div style="display: flex; gap: 20px; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="proxy_card_target" value="all" checked> 全カードを出力
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="proxy_card_target" value="unowned"> 未購入のカードのみ出力
                    </label>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label for="proxy-spacing" style="font-weight: bold; font-size: 0.9rem; color: #333;">カード同士の間隔 (mm):</label>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 5px;">
                    <input type="number" id="proxy-spacing" min="0" max="10" step="0.5" value="0" style="width: 80px; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;">
                    <span style="font-size: 0.85rem; color: #666;">mm (推奨: 0 〜 3mm / A4用紙 3×3配置)</span>
                </div>
            </div>

            <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px; font-size: 0.8rem; color: #555; margin-bottom: 20px;">
                ※ A4用紙に 63mm × 88mm で最大9枚（3×3）印刷されます。<br>
                ※ 選択中のデッキ数: <strong id="proxy-selected-count">0</strong> 件
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeProxyModal()" style="padding: 8px 16px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">キャンセル</button>
                <button type="button" id="btn-generate-proxy" onclick="generateProxyPdf()" style="padding: 8px 20px; background: #6f42c1; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">PDFを生成・ダウンロード</button>
            </div>
        </div>
    </div>
</div>
<script>
/**
 * デッキ削除
 */
function deleteDeck(deckId) {
...
/* --- [JavaScript部分は変更なしのため省略: generateProxyPdf内のセッション出力箇所まで] --- */
...
        // 1. 各デッキのカードリストを取得
        let allPrintCards = [];
`)

	// <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : '' ?> の再現
	fmt.Fprintf(w, "        const currentUserId = '%s';\n", currentUserIDStr)

	fmt.Fprint(w, `
...
/* --- [以降のJavaScriptも変更なしのため省略: 元ファイルの続きから </html> までそのまま記載] --- */
...
function getIndexCardImageUrl(card) {
    return getCardImageUrl(card);
}
</script>

</body>
</html>
`)

	return nil
}
