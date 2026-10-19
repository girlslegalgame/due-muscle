// public/js/tutorial.js

var TutorialManager = (function() {
    function isMobile() {
        return window.innerWidth <= 768;
    }

    function getStepData(stepNum) {
        var mobile = isMobile();
        var data = {
            1: {
                title: "ステップ 1 / 9：デッキの新規作成",
                text: mobile 
                    ? "デッキは、マイデッキ一覧の「＋ 新規作成」か、画面右上のメニューの「デッキ作成」から行うことができます。"
                    : "デッキは、マイデッキ一覧の「＋ 新規作成」か、画面上部の「デッキ作成」から行うことができます。",
                // ★ 「＋ 新規作成」と「ヘッダーのデッキ作成」の両方を指定
                target: mobile ? '#btn-create-deck, .menu-toggle' : '#btn-create-deck, header a[href="/decks/new"]'
            },
            2: {
                title: "ステップ 2 / 9：カードの追加",
                text: mobile
                    ? "画面下部のカードをドラッグ＆ドロップするか、カードをタップして「＋」ボタンをタップして追加できます。"
                    : "画面右側のカードをドラッグ＆ドロップするか、カードをクリックして「＋」ボタンをクリックして追加できます。",
                target: '#search-results img:first-child',
                hasDragAnim: true
            },
            3: {
                title: "ステップ 3 / 9：検索設定",
                text: mobile
                    ? "「設定」から、検索結果の並び替えができます。"
                    : "「設定」から、検索結果の並び替えや、検索結果の表示場所の変更ができます。",
                target: '.btn-settings'
            },
            4: {
                title: "ステップ 4 / 9：デッキの振り分け",
                text: "超GRや超次元ゾーンなど、メインデッキに入らないカードは自動で適正なリストに入ります。",
                target: '#deck-tabs'
            },
            5: {
                title: "ステップ 5 / 9：デッキの保存",
                text: mobile
                    ? "デッキができたら「保存する」をタップして、デッキ名、フォーマット、サムネイルを設定して保存しましょう。"
                    : "デッキができたら「保存する」をクリックして、デッキ名、フォーマット、サムネイルを設定して保存しましょう。",
                target: '#save-deck-btn'
            },
            6: {
                title: "ステップ 6 / 9：保存したデッキの確認",
                text: mobile
                    ? "保存したデッキをタップして、一人回しと、デッキの価格査定を行ってみましょう。"
                    : "保存したデッキをクリックして、一人回しと、デッキの価格査定を行ってみましょう。",
                target: '.deck-item:first-child'
            },
            7: {
                title: "ステップ 7 / 9：一人回し機能",
                text: "一人回しは「分析」から行うことができます。ドロップダウンリストを開き、「一人回しページ」を表示してみましょう。",
                target: '.btn-analysis, #btn-analysis-menu'
            },
            8: {
                title: "ステップ 8 / 9：機能案内",
                text: "この画面は現在開発中ですが、デッキのシミュレーションをスムーズに行うことができます。",
                target: null
            },
            9: {
                title: "ステップ 9 / 9：デッキ価格査定",
                text: "価格査定では、楽天市場からカードの最安値を取得して、デッキの価格査定を行ったり、購入済みのカードをマークしたりできます。",
                target: null
            }
        };
        return data[stepNum];
    }

    var currentStep = 1;
    var isActive = false;
    var dragAnimInterval = null;

    function init() {
        var savedActive = localStorage.getItem('tutorial_active');
        var savedStep = localStorage.getItem('tutorial_step');

        if (savedActive === 'true') {
            isActive = true;
            currentStep = savedStep ? parseInt(savedStep, 10) : 1;
            renderTutorialUI();
            applyStep(currentStep);
        }

        setupEscKeyHandler();
    }

    function start(fromStep) {
        isActive = true;
        currentStep = fromStep || 1;
        localStorage.setItem('tutorial_active', 'true');
        localStorage.setItem('tutorial_step', currentStep.toString());
        renderTutorialUI();
        applyStep(currentStep);
    }

    function stop() {
        isActive = false;
        localStorage.removeItem('tutorial_active');
        localStorage.removeItem('tutorial_step');
        removeTutorialUI();
    }

    function jumpToStep(stepNum) {
        if (!isActive) return;
        currentStep = stepNum;
        localStorage.setItem('tutorial_step', currentStep.toString());
        applyStep(currentStep);
    }

    function applyStep(stepNum) {
        clearHighlights();
        stopDragAnimation();

        var step = getStepData(stepNum);
        if (!step) {
            stop();
            return;
        }

        var titleEl = document.getElementById('tut-step-title');
        var bodyEl = document.getElementById('tut-body-text');
        var prevBtn = document.getElementById('tut-btn-prev');
        var nextBtn = document.getElementById('tut-btn-next');
        var escNote = document.getElementById('tut-esc-note');

        if (titleEl) titleEl.innerText = step.title;
        if (bodyEl) bodyEl.innerText = step.text;
        if (prevBtn) prevBtn.style.display = (stepNum > 1) ? 'inline-block' : 'none';
        if (nextBtn) nextBtn.innerText = (stepNum === 9) ? '完了' : '次へ';

        if (escNote) {
            escNote.innerText = isMobile() ? "※ 上の「中止」でいつでも終了できます" : "※ Escキーで中止できます";
        }

        // ★修正: querySelectorAll を使い、指定された全ての対象要素（「＋ 新規作成」と上部「デッキ作成」）に同時に赤枠を付与
        if (step.target) {
            var targetEls = document.querySelectorAll(step.target);
            for (var i = 0; i < targetEls.length; i++) {
                targetEls[i].classList.add('tutorial-highlight');
            }
        }

        if (step.hasDragAnim) {
            setTimeout(startDragAnimation, 300);
        }
    }

    function startDragAnimation() {
        stopDragAnimation();
        var startCard = document.querySelector('#search-results img:first-child');
        var dropArea = document.getElementById('main-deck-list');

        if (!startCard || !dropArea) return;

        var pointer = document.getElementById('tut-drag-pointer');
        if (!pointer) {
            pointer = document.createElement('div');
            pointer.id = 'tut-drag-pointer';
            pointer.className = 'tutorial-drag-pointer';
            pointer.innerHTML = '<div class="tutorial-pointer-card"></div><div class="tutorial-pointer-hand">👆</div>';
            document.body.appendChild(pointer);
        }

        function runAnim() {
            var startRect = startCard.getBoundingClientRect();
            var dropRect = dropArea.getBoundingClientRect();

            pointer.style.transition = 'none';
            pointer.style.opacity = '1';
            pointer.style.transform = 'translate(' + (startRect.left + 15) + 'px, ' + (startRect.top + 15) + 'px)';

            setTimeout(function() {
                pointer.style.transition = 'transform 1.3s cubic-bezier(0.25, 1, 0.5, 1)';
                var destX = dropRect.left + (dropRect.width / 2) - 20;
                var destY = dropRect.top + (dropRect.height / 2) - 20;
                pointer.style.transform = 'translate(' + destX + 'px, ' + destY + 'px)';
            }, 100);

            setTimeout(function() {
                pointer.style.opacity = '0';
            }, 1500);
        }

        runAnim();
        dragAnimInterval = setInterval(runAnim, 2300);
    }

    function stopDragAnimation() {
        if (dragAnimInterval) {
            clearInterval(dragAnimInterval);
            dragAnimInterval = null;
        }
        var pointer = document.getElementById('tut-drag-pointer');
        if (pointer) pointer.remove();
    }

    function clearHighlights() {
        var highlights = document.querySelectorAll('.tutorial-highlight');
        for (var i = 0; i < highlights.length; i++) {
            highlights[i].classList.remove('tutorial-highlight');
        }
    }

    function renderTutorialUI() {
        if (document.getElementById('tutorial-bubble')) return;

        var backdrop = document.createElement('div');
        backdrop.id = 'tutorial-backdrop';
        backdrop.className = 'tutorial-backdrop';
        document.body.appendChild(backdrop);

        var bubble = document.createElement('div');
        bubble.id = 'tutorial-bubble';
        bubble.className = 'tutorial-bubble';
        bubble.innerHTML = [
            '<div class="tutorial-header">',
            '  <span id="tut-step-title" class="tutorial-step-title"></span>',
            '  <div style="display:flex; gap:6px; align-items:center;">',
            '    <button class="tutorial-abort-btn" onclick="TutorialManager.openEscModal()">中止する</button>',
            '    <button class="tutorial-minimize-btn" onclick="TutorialManager.minimize()" title="最小化">－</button>',
            '  </div>',
            '</div>',
            '<div id="tut-body-text" class="tutorial-body"></div>',
            '<div class="tutorial-footer">',
            '  <span id="tut-esc-note" class="tutorial-esc-note">※ Escキーで中止できます</span>',
            '  <div class="tutorial-nav-btns">',
            '    <button id="tut-btn-prev" class="tutorial-btn tutorial-btn-prev" onclick="TutorialManager.prev()">戻る</button>',
            '    <button id="tut-btn-next" class="tutorial-btn tutorial-btn-next" onclick="TutorialManager.next()">次へ</button>',
            '  </div>',
            '</div>'
        ].join('');
        document.body.appendChild(bubble);

        var minibar = document.createElement('div');
        minibar.id = 'tutorial-minibar';
        minibar.className = 'tutorial-minibar';
        minibar.innerHTML = '💬 チュートリアルを開く';
        minibar.onclick = function() {
            TutorialManager.restore();
        };
        document.body.appendChild(minibar);

        var escModal = document.createElement('div');
        escModal.id = 'tutorial-esc-modal';
        escModal.className = 'tutorial-esc-modal';
        escModal.innerHTML = [
            '<div class="tutorial-esc-dialog">',
            '  <h3>チュートリアルを中止しますか？</h3>',
            '  <p>中止しても、マイデッキ画面などからいつでも再開できます。</p>',
            '  <div class="tutorial-esc-actions">',
            '    <button class="btn-tut-stop" onclick="TutorialManager.confirmStop()">中止する</button>',
            '    <button class="btn-tut-continue" onclick="TutorialManager.closeEscModal()">続ける</button>',
            '  </div>',
            '</div>'
        ].join('');
        document.body.appendChild(escModal);
    }

    function removeTutorialUI() {
        clearHighlights();
        stopDragAnimation();
        var ids = ['tutorial-backdrop', 'tutorial-bubble', 'tutorial-minibar', 'tutorial-esc-modal'];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (el) el.remove();
        }
    }

    function minimize() {
        var bubble = document.getElementById('tutorial-bubble');
        var minibar = document.getElementById('tutorial-minibar');
        if (bubble) bubble.style.display = 'none';
        if (minibar) minibar.style.display = 'block';
    }

    function restore() {
        var bubble = document.getElementById('tutorial-bubble');
        var minibar = document.getElementById('tutorial-minibar');
        if (bubble) bubble.style.display = 'flex';
        if (minibar) minibar.style.display = 'none';
    }

    function setupEscKeyHandler() {
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isActive) {
                openEscModal();
            }
        });
    }

    function openEscModal() {
        var modal = document.getElementById('tutorial-esc-modal');
        if (modal) modal.style.display = 'flex';
    }

    function closeEscModal() {
        var modal = document.getElementById('tutorial-esc-modal');
        if (modal) modal.style.display = 'none';
    }

    function confirmStop() {
        closeEscModal();
        stop();
    }

    function next() {
        if (currentStep >= 9) {
            alert("チュートリアルが完了しました！お疲れ様でした。");
            stop();
            return;
        }
        currentStep++;
        localStorage.setItem('tutorial_step', currentStep.toString());
        applyStep(currentStep);
    }

    function prev() {
        if (currentStep > 1) {
            currentStep--;
            localStorage.setItem('tutorial_step', currentStep.toString());
            applyStep(currentStep);
        }
    }

    return {
        init: init,
        start: start,
        stop: stop,
        jumpToStep: jumpToStep,
        next: next,
        prev: prev,
        minimize: minimize,
        restore: restore,
        openEscModal: openEscModal,
        closeEscModal: closeEscModal,
        confirmStop: confirmStop
    };
})();

window.addEventListener('DOMContentLoaded', TutorialManager.init);