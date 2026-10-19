<!-- app/Views/deck/create.php -->
<link rel="stylesheet" href="/css/tutorial.css?v=<?php echo time(); ?>">

<style>
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; /* body自体にスクロールバーを出さない */
    }

    main {
        padding-top: 75px !important;    
        padding-bottom: 20px !important; 
        height: 100vh;
        box-sizing: border-box;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    #container { 
        width: 100%; 
        max-width: 1280px; 
        height: 100%;      
        max-height: 100%; 
        margin: 0 auto;    
        display: flex; 
        flex-direction: row; 
        box-sizing: border-box;
        position: relative; 
        overflow: hidden;
        background-color: #fff; 
        box-shadow: 0 0 25px rgba(0,0,0,0.1); 
    }

    #container.left-handed {
        flex-direction: row-reverse;
    }

    #deck-area { 
        flex: 1; 
        min-width: 0; 
        min-height: 0; 
        display: flex; 
        flex-direction: column; 
        padding: 15px; 
        padding-bottom: 15px; 
        box-sizing: border-box;
        overflow-y: auto;
    }
    #deck-tabs { display: flex; gap: 5px; height: 35px; flex-shrink: 0; }
    .tab { 
        background: #eee; padding: 5px 15px; border: 1px solid #ccc; 
        cursor: pointer; font-weight: bold; border-radius: 4px 4px 0 0; font-size: 14px; 
    }
    .tab.active { background: #fff; border-bottom: none; color: #007bff; }
    
    #deck-header { display: flex; justify-content: space-between; align-items: center; height: 40px; flex-shrink: 0; }
    #deck-header h3 { margin: 0; font-size: 16px; }

    .deck-content { display: none; width: 100%; flex: 1; min-height: 0; }
    .deck-content.active { display: grid; }

    #main-deck-list {
        grid-template-columns: repeat(8, 1fr); 
        background-color: #fff; border: 1px solid #ccc;
        padding: 0; gap: 0; overflow-y: hidden; overflow-x: hidden;
        margin-bottom: 20px !important; 
    }
    #main-deck-list img { 
        width: 100%; 
        height: 100%; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        cursor: pointer; 
        display: block; 
    }
    #main-deck-list.stacked-mode { grid-auto-rows: 45px; }

    #extra-deck-area { 
        display: none; 
        flex-direction: row; 
        gap: 32px; 
        padding: 20px; 
        border: 1px solid #ccc; 
        background: #f9f9f9; 
        width: 100%;
        box-sizing: border-box;
        justify-content: center; 
        align-items: flex-start;
    }
    #extra-deck-area.active { display: flex !important; }
    
    .zone-container { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        width: 100%;
        max-width: 464px; 
        min-width: 0; 
        flex: 0 1 auto; 
    }
    .zone-container h4 {
        width: 100%;
        text-align: center;
        font-size: 1.1rem;
        margin: 0 0 10px 0;
        padding-bottom: 5px;
    }

    .extra-list { 
        display: grid !important; 
        grid-template-columns: repeat(4, 1fr) !important; 
        gap: 4px; 
        background: #fff; 
        border: 1px solid #ddd; 
        padding: 6px; 
        box-sizing: border-box;
        width: 100%; 
    }
    .extra-list img { 
        width: 100% !important; 
        height: auto !important; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        border-radius: 6px; 
        display: block;
    }

    #gr-list {
        width: 100%;
        aspect-ratio: 464 / 482; 
    }

    #super-dim-list {
        width: 100%;
        aspect-ratio: 464 / 324; 
    }

    #special-deck-list.active {
        display: flex !important; 
        flex-direction: row !important;
        justify-content: center; align-items: stretch;
        background: #fff; border: 1px solid #ccc;
    }
    .special-zone { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 20px; }
    .special-v-line { width: 2px; background-color: #ccc; margin: 20px 0; flex-shrink: 0; }
    .special-label { margin: 0 0 10px 0; font-size: 15px; font-weight: bold; }
    .btn-add-special { margin-bottom: 20px; padding: 8px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    
    .special-box {
        width: 80%;                   
        max-width: 280px;             
        aspect-ratio: 110 / 154;      
        height: auto;                 
        border: 2px dashed #bbb;
        display: flex; justify-content: center; align-items: center;
        border-radius: 8px; background: #fff; overflow: hidden;
        box-sizing: border-box;
    }
    .special-box img {
        width: 100%;
        height: 100%;
        object-fit: contain; 
        cursor: pointer;
    }
    .special-box.active { border: 2px solid #28a745; }

    #search-section {
        position: relative;      
        width: 380px;            
        min-width: 380px;        
        height: 100%;        
        box-sizing: border-box;
        background: rgba(249, 249, 249, 0.98); 
        border-left: 3px solid #333; 
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.15); 
        padding: 10px; 
        display: flex; 
        flex-direction: column; 
        gap: 8px;
        transition: width 0.3s ease, min-width 0.3s ease, padding 0.3s ease;
    }

    #container.left-handed #search-section {
        border-left: none;
        border-right: 3px solid #333;
        box-shadow: 5px 0 20px rgba(0, 0, 0, 0.15);
    }

    #search-section.collapsed {
        width: 0px !important;
        min-width: 0px !important;
        padding: 0px !important;
        border: none !important;
        overflow: hidden;
    }

    #search-toggle-btn {
        position: absolute;
        top: 50%;
        right: 380px; 
        transform: translateY(-50%);
        width: 32px;
        height: 64px;
        padding: 0;
        background: #333;
        color: #fff;
        border: none;
        border-radius: 8px 0 0 8px;
        cursor: pointer;
        font-weight: bold;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        writing-mode: vertical-rl; 
        text-orientation: mixed;
        box-shadow: -3px 0 10px rgba(0,0,0,0.15);
        z-index: 2001; 
        transition: right 0.3s ease, left 0.3s ease;
    }
    #search-toggle-btn:hover {
        background: #444;
    }

    #search-results { 
        flex: 1; 
        display: grid; 
        grid-template-columns: repeat(2, 1fr); 
        gap: 8px; 
        padding: 5px 0; 
        overflow-y: auto; 
        overflow-x: hidden;
        align-content: start;
    }
    #search-results img { 
        width: 100%; 
        height: auto; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        border: 1px solid #ccc; 
        border-radius: 6px;
        box-sizing: border-box;
        background-color: #fff; 
        transition: transform 0.15s ease;
    }
    #search-results img:hover {
        transform: scale(1.03);
    }
    
    #search-controls-wrapper { 
        display: flex; 
        flex-direction: column; 
        gap: 8px; 
        border-bottom: 1px solid #ddd;
        padding-bottom: 10px;
    }
    #search-controls { 
        display: flex; 
        flex-direction: row; 
        align-items: center;
        gap: 6px; 
        height: auto; 
    }
    #search-buttons-row {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }
    #search-buttons-row button {
        flex: 1;
    }

    #card-search-input { 
        flex: 1; 
        min-width: 0; 
        padding: 10px 8px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        font-size: 14px; 
    }
    .btn-filter, .btn-sort, .btn-analysis { 
        padding: 10px 8px; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold; 
        font-size: 13px;
        box-sizing: border-box;
        text-align: center;
    }
    .btn-filter { background: #17a2b8; }
    .btn-sort { background: #6c757d; }
    .btn-analysis { background: #28a745; }
    .btn-analysis:hover { background: #218838; }
    
    .search-scope { 
        display: flex; 
        gap: 12px;               
        font-size: 12px; 
        color: #666; 
        justify-content: flex-start; 
        padding-left: 2px;
        align-items: center;     
        flex-wrap: nowrap;       
    }
    .hand-switch-btn {
        background: #e2e8f0;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 4px 8px;        
        font-size: 11px;         
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        white-space: nowrap;     
    }
    .hand-switch-btn:hover {
        background: #cbd5e1;
    }

    #cardDetailModal, #filterModal, .sub-modal { 
        display: none; position: fixed; z-index: 3000; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); 
    }
    .detail-content, .filter-content, .sub-modal-content { 
        background: white; margin: 5vh auto; padding: 25px; 
        border-radius: 12px; position: relative; box-sizing: border-box; 
    }

    #cardDetailModal {
        display: none;
        position: fixed;
        z-index: 3000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        overflow-y: auto; 
    }    
    .detail-content {
        width: 90%;
        max-width: 850px;
        max-height: 90vh; 
        overflow-y: auto; 
        display: flex;
        flex-direction: column;
        background: white;
        margin: 5vh auto;
        padding: 25px;
        border-radius: 12px;
        position: relative;
        box-sizing: border-box;
    }
    .detail-grid { 
        display: grid; 
        grid-template-columns: 260px 1fr; 
        grid-template-rows: auto auto 1fr; 
        gap: 0 25px; 
        margin-bottom: 15px; 
    }
    #detail-name { 
        grid-column: 2 / 3; 
        grid-row: 1 / 2; 
        margin: 0 0 15px 0; 
        font-size: 1.5rem; 
        border-bottom: 2px solid #eee; 
        padding-bottom: 10px; 
    }
    .detail-image { 
        grid-column: 1 / 2; 
        grid-row: 1 / 3; 
        text-align: center; 
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .detail-image img { 
        max-width: 100%; 
        max-height: 360px; 
        height: auto;
        width: auto;
        aspect-ratio: 110 / 154;
        object-fit: contain;
        border-radius: 8px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
    }
    .detail-qty { 
        grid-column: 1 / 2; 
        grid-row: 3 / 4; 
    }
    .detail-desc { 
        grid-column: 2 / 3; 
        grid-row: 2 / 4; 
        min-width: 0; 
    }
    #detail-text { 
        white-space: pre-wrap; 
        word-wrap: break-word; 
        background: #f8f9fa; 
        padding: 15px; 
        border-radius: 5px; 
        font-size: 0.95rem; 
        line-height: 1.6; 
        max-height: 220px; 
        overflow-y: auto; 
    }
    .version-list { display: flex; overflow-x: auto; gap: 12px; padding: 10px 5px; }
    .version-list img { height: 100px; cursor: pointer; border: 3px solid transparent; border-radius: 4px; flex-shrink: 0; }
    .version-list img.selected { border-color: #007bff; }
    .qty-controls { display: flex; align-items: center; gap: 20px; margin: 20px 0; justify-content: center; font-size: 1.2rem; }
    .btn-qty { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #ccc; cursor: pointer; font-size: 1.5rem; background: #fff; }
    .detail-close-btn { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 30px; z-index: 10; line-height: 1; }

    #filterModal {
        display: none;
        align-items: center;
        justify-content: center;
    }
    #filterModal[style*="display: block"] {
        display: flex !important;
    }
    .filter-content { 
        width: 100%;
        max-width: 500px; 
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        border: none;
        margin: 0;
        overflow: hidden;       
        padding: 0 !important;  
    }
    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #333;       
        padding: 16px 20px;     
        margin-bottom: 0;
    }
    .filter-header h3 {
        margin: 0;
        font-size: 0.95rem;
        color: #fff;            
        font-weight: bold;
    }
    .filter-close {
        font-size: 24px;
        color: #ccc;            
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }
    .filter-close:hover {
        color: #fff;
    }
    .filter-scroll { 
        flex: 1;
        overflow-y: auto; 
        padding: 20px 20px 0 20px; 
        margin-bottom: 0;
    }
    .filter-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .filter-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .filter-scroll::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }

    .filter-group {
        margin-bottom: 20px;
    }
    .filter-group > label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #444;
        margin-bottom: 8px;
    }

    .civ-checkboxes, #filter-exclude-civ-area { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 8px; 
    }
    .civ-checkboxes {
        margin-bottom: 12px;
    }
    .civ-checkboxes label, #filter-exclude-civ-area label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 4px;
        background: #f5f5f7;
        border: 1px solid #e5e5ea;
        border-radius: 8px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
        margin: 0;
        font-weight: normal;
        color: #555;
    }
    .civ-checkboxes input, #filter-exclude-civ-area input {
        margin: 0;
    }
    .civ-checkboxes label:has(input:checked) {
        background: #e6f2ff;
        border-color: #007bff;
        color: #007bff;
        font-weight: bold;
    }
    #filter-exclude-civ-area label:has(input:checked) {
        background: #ffebe6;
        border-color: #ff3b30;
        color: #ff3b30;
        font-weight: bold;
    }

    .range-inputs {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .range-inputs input[type="number"] {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #d1d1d6;
        border-radius: 8px;
        font-size: 0.9rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .range-inputs input[type="number"]:focus {
        border-color: #007bff;
    }
    .range-separator {
        color: #8e8e93;
        font-size: 0.9rem;
    }

    .select-trigger { 
        width: 100% !important; 
        padding: 10px 14px; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        cursor: pointer; 
        background: #fff; 
        box-sizing: border-box; 
        min-height: 40px; 
        font-size: 0.85rem; 
        color: #333; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: nowrap;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .select-trigger:hover {
        border-color: #007bff;
        background: #fafafa;
    }
    .select-trigger::after {
        content: "▼";
        font-size: 10px;
        color: #8e8e93;
    }

    .logic-switch { 
        display: inline-flex; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        overflow: hidden; 
        background: #f2f2f7; 
        padding: 2px;
    }
    .logic-switch label { 
        text-align: center; 
        cursor: pointer; 
        font-size: 0.75rem; 
        font-weight: bold;
        margin: 0; 
        padding: 4px 12px; 
        border-radius: 6px;
        transition: all 0.2s;
        color: #666;
    }
    .logic-switch input { display: none; }
    .logic-switch input:checked + span { 
        background: #fff; 
        color: #007bff; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        display: block; 
        padding: 4px 12px;
        border-radius: 6px;
        margin: -4px -12px; 
    }

    .filter-actions {
        display: flex;
        gap: 12px;
        padding: 15px 20px 20px 20px; 
        background: #fff;
    }
    .btn-filter-apply {
        flex: 2;
        padding: 12px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: bold;
        font-size: 0.9rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-filter-apply:hover {
        background: #0056b3;
    }
    .btn-filter-clear {
        flex: 1;
        padding: 12px;
        background: #f2f2f7;
        color: #ff3b30;
        border: none;
        border-radius: 10px;
        font-weight: bold;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-filter-clear:hover {
        background: #ffebe6;
    }  
    
    .sub-modal { 
        display: none; 
        position: fixed; 
        z-index: 3000; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); 
        align-items: center;
        justify-content: center;
    }
    .sub-modal[style*="display: block"] {
        display: flex !important;
    }
    .sub-modal-content { 
        background: white; 
        width: 100%;
        max-width: 400px; 
        height: 75vh; 
        border-radius: 16px; 
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex; 
        flex-direction: column; 
        box-sizing: border-box;
        overflow: hidden; 
        margin: 0;
        padding: 0 !important;  
    }
    .sub-modal-header { 
        padding: 16px 20px; 
        background: #333; 
        color: #fff; 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
    }
    .sub-modal-header span {
        font-weight: bold;
        font-size: 0.95rem;
    }
    .sub-modal-body { 
        flex: 1; 
        overflow-y: auto; 
        padding: 10px 20px; 
    }
    .sub-modal-content input[type="text"] { 
        width: 100% !important; 
        box-sizing: border-box; 
        padding: 10px 14px; 
        margin-bottom: 5px; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .sub-modal-content input[type="text"]:focus {
        border-color: #007bff;
    }
    .list-item { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        padding: 12px 8px; 
        border-bottom: 1px solid #f2f2f7; 
        cursor: pointer; 
        font-size: 0.85rem; 
        color: #333;
        transition: background 0.2s;
    }
    .list-item:hover {
        background: #f5f5f7;
    }
    .list-item input[type="checkbox"] {
        margin: 0;
        cursor: pointer;
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #d1d1d6;
        transition: .3s;
        border-radius: 24px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle-switch input:checked + .toggle-slider {
        background-color: #28a745; 
    }
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px); 
    }

    .selected-badges-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 5px;
        margin-bottom: 10px;
        max-height: 85px;
        overflow-y: auto;
        padding: 5px;
        background: #fdfdfd;
        border: 1px dashed #ccc;
        border-radius: 4px;
    }
    .selected-badge {
        background-color: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
        border-radius: 16px;
        padding: 2px 10px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: bold;
    }
    .selected-badge-remove {
        cursor: pointer;
        color: #ef4444;
        font-weight: bold;
        font-size: 0.95rem;
        line-height: 1;
        display: inline-block;
        margin-left: 2px;
    }
    .selected-badge-remove:hover {
        color: #b91c1c;
    }

    .btn-settings {
        padding: 10px 8px;
        background: #6c757d;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        box-sizing: border-box;
        text-align: center;
    }
    .btn-settings:hover { background: #5a6268; }

    @media (max-width: 768px) {
        .btn-settings {
            padding: 8px 12px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }
        #settings-position-group {
            display: none !important;
        }
        #deck-tabs {
            height: auto !important;
            gap: 4px !important;
        }
        .tab {
            font-size: 11px !important;
            padding: 6px 4px !important;
            white-space: nowrap !important; 
            flex: 1; 
            text-align: center;
        }

        #deck-header {
            height: auto !important;
            margin: 8px 0 !important;
            display: flex !important;
            justify-content: flex-end !important; 
        }
        #deck-header h3 {
            display: none !important; 
        }
        #deck-header > div {
            justify-content: flex-end !important; 
            gap: 8px !important;
        }
        
        .btn-analysis, #save-deck-btn {
            flex: none !important; 
            font-size: 12px !important;
            padding: 6px 12px !important;
        }
        
        #deck-area {
            padding: 8px;
            padding-bottom: 210px !important; 
        }
        #deck-area.search-collapsed {
            padding-bottom: 110px !important;
        }

        #main-deck-list {
            grid-template-columns: repeat(8, 1fr) !important; 
            gap: 1px;
        }

        #extra-deck-area {
            flex-direction: column !important;
            gap: 15px;
            align-items: center;
            overflow-y: auto;
        }
        .zone-container {
            width: 100%;
        }
        
        #gr-list, #super-dim-list {
            width: 100% !important;
            max-width: 380px; 
            height: auto !important;
            aspect-ratio: auto !important;
            grid-template-columns: repeat(4, 1fr) !important;
            grid-template-rows: none !important;
            grid-auto-rows: auto !important;
        }
        .extra-list img {
            width: 100% !important;
            height: auto !important;
        }

        #special-deck-list.active {
            flex-direction: row !important; 
            justify-content: space-around;
            align-items: center;
            gap: 10px;
            padding: 5px !important;
        }
        .special-zone {
            padding: 5px !important;
            flex: 1;
            min-width: 0;
        }
        .special-label {
            font-size: 11px !important; 
            margin: 0 0 4px 0 !important;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        .btn-add-special {
            font-size: 11px !important;
            padding: 4px 10px !important; 
            margin-bottom: 8px !important;
        }
        .special-box {
            width: 38vw !important; 
            max-width: 160px !important; 
            height: auto !important; 
            aspect-ratio: 110 / 154 !important; 
            border-radius: 6px;
        }
        .special-v-line {
            display: block !important; 
            height: 120px; 
            margin: 5px 0 !important;
        }

        #search-section {
            position: fixed !important;
            bottom: 0 !important;
            left: 50% !important;
            transform: translateX(-50%) translateY(0) !important;
            width: 100% !important;
            max-width: 900px !important;
            height: 200px !important;
            border-top: 3px solid #333 !important;
            border-left: none !important;
            border-right: none !important;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15) !important;
            padding: 5px 10px !important;
            transition: transform 0.3s ease !important;
            display: flex !important;
            flex-direction: column !important; 
            gap: 4px !important;
        }
        #search-section.collapsed {
            width: 100% !important;
            min-width: 100% !important;
            padding: 5px 10px !important;
            border-top: 3px solid #333 !important;
            transform: translateX(-50%) translateY(200px) !important;
        }
        
        #search-toggle-btn {
            position: absolute !important;
            top: -32px !important;
            left: auto !important;
            right: 20px !important;
            transform: none !important;
            width: auto !important;
            height: 32px !important;
            writing-mode: horizontal-tb !important;
            border-radius: 8px 8px 0 0 !important;
            transition: none !important;
        }

        #search-results {
            display: flex !important;
            flex-direction: row !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            white-space: nowrap !important;
            height: 120px !important; 
            padding: 2px 0 !important;
            gap: 8px !important;
            order: 1 !important; 
            touch-action: pan-x;
            -webkit-overflow-scrolling: touch; 
        }
        #search-results img {
            height: 110px !important; 
            width: auto !important; 
            aspect-ratio: 110/154 !important; 
            object-fit: contain !important; 
            background-color: #fff; 
            flex-shrink: 0;
            touch-action: pan-x; 
        }

        #search-controls-wrapper {
            border-bottom: none !important;
            padding-bottom: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 4px !important;
            order: 2 !important; 
            width: 100% !important;
        }

        #search-controls {
            display: flex !important;
            flex-direction: row !important; 
            align-items: center !important;
            gap: 6px !important;
            width: 100% !important;
            height: auto !important;
        }
        
        #card-search-input {
            flex: 1 !important; 
            padding: 8px !important;
            font-size: 14px !important;
            box-sizing: border-box !important;
        }

        #search-buttons-row {
            display: flex !important;
            gap: 4px !important;
            flex-shrink: 0 !important; 
        }

        .btn-filter, .btn-sort {
            padding: 8px 12px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }

        .search-scope {
            display: flex !important;
            justify-content: flex-start !important; 
            align-items: center !important;
            padding-left: 4px !important;
            gap: 15px !important;
            height: auto !important;
            margin-top: 2px !important;
        }
        .search-scope label {
            white-space: nowrap;     
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0;
            cursor: pointer;
        }

        .detail-content {
            width: 92% !important;
            margin: 3vh auto !important;
            padding: 15px !important;
            max-height: 94vh;
            overflow-y: auto;
        }
        .detail-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin-bottom: 15px !important;
        }
        #detail-name {
            order: 1;
            font-size: 1.2rem !important;
            margin: 15px 0 5px 0 !important;
            padding-bottom: 8px !important;
            padding-right: 25px; 
        }
        .detail-image {
            order: 2;
            width: 100%;
            max-width: 200px; 
            margin: 0 auto !important;
        }
        .detail-qty {
            order: 3;
        }
        .qty-controls {
            margin: 5px 0 !important;
        }
        .detail-desc {
            order: 4;
            width: 100%;
        }
        #detail-text {
            max-height: 160px !important;
            padding: 10px !important;
            font-size: 0.85rem !important;
        }
        .detail-close-btn {
            right: 15px !important;
            top: 12px !important;
            font-size: 28px !important;
        }
        .version-section h4 {
            margin: 10px 0 5px 0 !important;
            font-size: 0.9rem;
        }
        .btn-sort, .btn-analysis, #save-deck-btn {
            flex: none !important; 
            font-size: 12px !important;
            padding: 6px 12px !important;
        }
    }
    #main-deck-list img, 
    .extra-list img, 
    .special-box img, 
    #search-results img {
        -webkit-user-drag: none !important; 
        user-drag: none !important;         
        user-select: none !important;       
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div id="container">
    <div id="deck-area">
        <div id="deck-tabs">
            <div id="tab-main" class="tab active" onclick="switchDeckTab('main')">メイン 0</div>
            <div id="tab-extra" class="tab" onclick="switchDeckTab('extra')">GR/超次元 0</div>
            <div id="tab-special" class="tab" onclick="switchDeckTab('special')">特殊</div>
        </div>
        <div id="deck-header">
            <h3>デッキ内容</h3>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button class="btn-sort" onclick="toggleSortModal()">並び替え</button>
                <button class="btn-analysis" onclick="openAnalysisModal()">分析</button>
                <button id="save-deck-btn" onclick="openSaveModal()" style="padding: 8px 20px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">保存する</button>
            </div>
        </div>

        <div id="main-deck-list" class="deck-content active"></div>

        <div id="extra-deck-area" class="deck-content" style="display:none; flex-direction:row; gap:20px; padding:10px;">
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #007bff;">超GR</h4>
                <div id="gr-list" class="extra-list gr-layout"></div>
            </div>
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #dc3545;">超次元ゾーン</h4>
                <div id="super-dim-list" class="extra-list dim-layout"></div>
            </div>
        </div>

        <div id="special-deck-list" class="deck-content">
            <div class="special-zone">
                <h4 class="special-label">終焉の禁断 ドルマゲドンX</h4>
                <button id="btn-doru" class="btn-add-special" onclick="toggleSpecial('slot-dolmagedon')">追加する</button>
                <div id="slot-dolmagedon" class="special-box empty"></div>
            </div>
            <div class="special-v-line"></div>
            <div class="special-zone">
                <h4 class="special-label">零龍</h4>
                <button id="btn-zero" class="btn-add-special" onclick="toggleSpecial('slot-zeroron')">追加する</button>
                <div id="slot-zeroron" class="special-box empty"></div>
            </div>
        </div>    
    </div>
    <button id="search-toggle-btn" onclick="toggleSearchSection()">◀ 検索を隠す</button>
    
    <div id="search-section">
        <div id="search-controls-wrapper">
            <div id="search-controls">
                <input type="text" id="card-search-input" placeholder="カード名を入力..." autocomplete="off">
                <div id="search-buttons-row">
                    <button class="btn-filter" onclick="toggleFilterModal()">絞り込み</button>
                    <button class="btn-settings" onclick="toggleSettingsModal()">設定</button>
                </div>
            </div>
            <div class="search-scope">
                <label><input type="checkbox" class="scope-check" value="name" checked> カード名</label>
                <label><input type="checkbox" class="scope-check" value="text"> テキスト</label>
                <label><input type="checkbox" class="scope-check" value="race"> 種族</label>
            </div>
        </div>
        
        <div id="search-results"></div>
    </div>
</div>

<div id="cardDetailModal">
    <div class="detail-content">
        <span class="detail-close-btn" onclick="closeDetailModal()">&times;</span>
        
        <div class="detail-grid">
            <h2 id="detail-name"></h2>
            
            <div class="detail-image">
                <img id="detail-main-img" src="">
            </div>
            
            <div class="detail-qty">
                <div class="qty-controls">
                    <button class="btn-qty" onclick="adjustQty(-1)">-</button>
                    <span id="detail-qty">0</span> / <span id="detail-limit">4</span>枚
                    <button class="btn-qty" onclick="adjustQty(1)">+</button>
                </div>
            </div>
            
            <div class="detail-desc">
                <div id="detail-text"></div>
            </div>
        </div>
        
        <div id="combination-section" style="display: none; margin-bottom: 15px;">
            <h4 style="margin: 5px 0 8px 0; font-size: 0.9rem;">面の切り替え（両面・関連面）</h4>
            <div id="detail-combination-list" class="version-list"></div>
        </div>

        <div class="version-section">
            <h4>バージョン切り替え</h4>
            <div id="detail-version-list" class="version-list"></div>
        </div>
    </div>
</div>

<div id="filterModal">
    <div class="filter-content">
        <div class="filter-header">
            <h3>詳細絞り込み</h3>
            <span class="filter-close" onclick="toggleFilterModal()">&times;</span>
        </div>
        
        <div class="filter-scroll">
            <div class="filter-group">
                <label>文明</label>
                <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-civ-single" checked onchange="toggleFilterCivType()"> 単色
                    </label>
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-civ-multi" checked onchange="toggleFilterCivType()"> 多色
                    </label>
                </div>
                
                <div class="civ-checkboxes">
                    <label><input type="checkbox" class="civ-check" value="1"> 光</label>
                    <label><input type="checkbox" class="civ-check" value="2"> 水</label>
                    <label><input type="checkbox" class="civ-check" value="3"> 闇</label>
                    <label><input type="checkbox" class="civ-check" value="4"> 火</label>
                    <label><input type="checkbox" class="civ-check" value="5"> 自然</label>
                    <label><input type="checkbox" class="civ-check" value="6"> ゼロ</label>
                </div>

                <div style="display: flex; gap: 15px; margin: 12px 0; font-size: 12px; color: #555;">
                    <label style="cursor: pointer; font-weight: normal; display: flex; align-items: center; gap: 4px;"><input type="radio" name="filter-civ-match-type" value="include" checked> 選択した文明を含む</label>
                    <label style="cursor: pointer; font-weight: normal; display: flex; align-items: center; gap: 4px;"><input type="radio" name="filter-civ-match-type" value="match"> 選択した文明のみ持つ</label>
                </div>

                <div id="filter-exclude-civ-area" style="border-top: 1px solid #eaeaea; padding-top: 12px; margin-top: 12px;">
                    <span style="font-size: 11px; color: #666; grid-column: span 3; font-weight: bold; margin-bottom: 4px;">含まれない文明を指定（除外）</span>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="1"> 光</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="2"> 水</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="3"> 闇</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="4"> 火</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="5"> 自然</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="6"> ゼロ</label>
                </div>
            </div>

            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">特殊タイプ</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="characteristic_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="characteristic_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="characteristics-trigger" class="select-trigger" onclick="openSubModal('characteristics')">特殊タイプを選択</div>
            </div>

            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">カードタイプ</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="cardtype_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="cardtype_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="cardtype-trigger" class="select-trigger" onclick="openSubModal('cardtype')">カードタイプを選択</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 16px; margin-bottom: 20px;">
                <div class="filter-group" style="margin: 0;">
                    <label>コスト</label>
                    <div class="range-inputs">
                        <input type="number" id="cost-min" placeholder="下限">
                        <span class="range-separator">〜</span>
                        <input type="number" id="cost-max" placeholder="上限">
                    </div>
                </div>
                <br>
                <div class="filter-group" style="margin: 0;">
                    <label>パワー</label>
                    <div class="range-inputs">
                        <input type="number" id="pow-min" placeholder="下限">
                        <span class="range-separator">〜</span>
                        <input type="number" id="pow-max" placeholder="上限">
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">種族</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="race_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="race_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="race-trigger" class="select-trigger" onclick="openSubModal('race')">種族を選択</div>
            </div>

            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">特殊能力</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="ability_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="ability_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="ability-trigger" class="select-trigger" onclick="openSubModal('ability')">特殊能力を選択</div>
            </div>

            <div class="filter-group">
                <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin-bottom: 8px;">収録商品</label>
                <div id="goods-trigger" class="select-trigger" onclick="openSubModal('goods')">収録商品を選択</div>
            </div>

            <div class="filter-group">
                <label>ツインパクト</label>
                <div style="display: flex; gap: 15px;">
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-twinpact-only" onchange="toggleTwinpactExclusive('only')"> ツインパクトカードのみ
                    </label>
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-twinpact-exclude" onchange="toggleTwinpactExclusive('exclude')"> ツインパクトカードを除く
                    </label>
                </div>
            </div>            

            <div class="filter-group" style="margin: 0;">
                <label>レギュレーション</label>
                <div style="display: flex; gap: 15px;">
                    <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 4px;"><input type="checkbox" class="reg-check" value="2"> 殿堂</label>
                    <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 4px;"><input type="checkbox" class="reg-check" value="3"> プレミアム殿堂</label>
                </div>
            </div>
        </div>

        <div class="filter-actions">
            <button class="btn-filter-clear" onclick="clearAllFilters()">クリア</button>
            <button class="btn-filter-apply" onclick="applyFilters()">適用する</button>
        </div>
    </div>
</div>

<div id="sortModal" class="sub-modal">
    <div class="sub-modal-content" style="width: 400px; height: auto; max-height: 90vh; margin: 15vh auto;">
        <div class="sub-modal-header" style="padding: 15px; background: #333; color: #fff; display: flex; justify-content: space-between; border-radius: 12px 12px 0 0;">
            <span style="font-weight: bold;">並び替え設定</span>
            <span onclick="toggleSortModal()" style="cursor:pointer; font-size: 24px;">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">並び替え項目</label>
                <select id="sort-key" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: white; font-size: 14px;">
                    <option value="free">自由に並べ替える</option>
                    <option value="civ">文明順</option>
                    <option value="cost">コスト順</option>
                    <option value="name">カード名順</option>
                    <option value="count">採用順</option>
                </select>
            </div>
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">並び順</label>
                <div style="display: flex; gap: 20px;">
                    <label style="cursor: pointer; font-size: 14px;"><input type="radio" name="sort-order" value="asc" checked style="margin-right: 5px;">昇順</label>
                    <label style="cursor: pointer; font-size: 14px;"><input type="radio" name="sort-order" value="desc" style="margin-right: 5px;">降順</label>
                </div>
            </div>
        </div>
        <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px;">
            <button onclick="toggleSortModal()" style="flex: 1; padding: 10px; background: #ccc; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">キャンセル</button>
            <button onclick="applySort()" style="flex: 1; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">適用する</button>
        </div>
    </div>
</div>

<div id="raceSelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>種族選択</span><span onclick="closeSubModal('race')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;">
        <input type="text" id="race-list-search" placeholder="検索..." style="width:100%; padding:8px; margin-bottom:5px;">
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
            <button type="button" onclick="bulkSelectVisible('race')" style="flex: 1; padding: 6px; font-size: 12px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">表示中を一括選択</button>
            <button type="button" onclick="clearSubSelection('race')" style="flex: 1; padding: 6px; font-size: 12px; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">全選択解除</button>
        </div>
        <div id="race-selected-badges" class="selected-badges-container" style="display: none;"></div>
    </div>
    <div id="race-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('race')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<div id="abilitySelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>特殊能力選択</span><span onclick="closeSubModal('ability')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;">
        <input type="text" id="ability-list-search" placeholder="検索..." style="width:100%; padding:8px; margin-bottom:5px;">
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
            <button type="button" onclick="bulkSelectVisible('ability')" style="flex: 1; padding: 6px; font-size: 12px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">表示中を一括選択</button>
            <button type="button" onclick="clearSubSelection('ability')" style="flex: 1; padding: 6px; font-size: 12px; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">全選択解除</button>
        </div>
        <div id="ability-selected-badges" class="selected-badges-container" style="display: none;"></div>
    </div>
    <div id="ability-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('ability')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<div id="characteristicsSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>特殊タイプ選択</span>
            <span onclick="closeSubModal('characteristics')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div style="padding:10px 20px 0 20px;">
            <div id="characteristics-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="characteristics-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('characteristics')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<div id="cardtypeSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>カードタイプ選択</span>
            <span onclick="closeSubModal('cardtype')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div style="padding:10px 20px 0 20px;">
            <div id="cardtype-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="cardtype-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('cardtype')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<div id="goodsSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>収録商品選択</span>
            <span onclick="closeSubModal('goods')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div style="padding: 12px 15px; border-bottom: 1px solid #f2f2f7; background-color: #fafafa;">
            <span style="font-size: 11px; color: #666; font-weight: bold; display: block; margin-bottom: 8px;">シリーズで一括選択</span>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-shobu" class="era-check" value="shobu"> 勝舞編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-katta" class="era-check" value="katta"> 勝太編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-joe" class="era-check" value="joe"> ジョー編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-win" class="era-check" value="win"> ウィン編
                </label>
            </div>
        </div>
         
        <div style="padding:10px;">
            <input type="text" id="goods-list-search" placeholder="商品を検索..." style="width:100%; padding:8px; margin-bottom:5px;">
            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <button type="button" onclick="bulkSelectVisible('goods')" style="flex: 1; padding: 6px; font-size: 12px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">表示中を一括選択</button>
                <button type="button" onclick="clearSubSelection('goods')" style="flex: 1; padding: 6px; font-size: 12px; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">全選択解除</button>
            </div>
            <div id="goods-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="goods-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('goods')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<div id="deckSaveModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 460px; height: auto; max-height: 90vh;">
        <div class="sub-modal-header">
            <span>デッキ保存設定</span>
            <span onclick="closeSaveModal()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        
        <div class="sub-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">デッキ名</label>
                <input type="text" id="save-deck-name" placeholder="デッキ名を入力してください" style="width: 100%; padding: 10px; border: 1px solid #d1d1d6; border-radius: 8px; box-sizing: border-box; outline: none; font-size: 14px;">
            </div>
            
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">フォーマット</label>
                <select id="save-deck-format" style="width: 100%; padding: 10px; border: 1px solid #d1d1d6; border-radius: 8px; box-sizing: border-box; background: white; font-size: 14px; outline: none; cursor: pointer;">
                </select>
            </div>
            
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">デッキサムネイル</label>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div id="thumbnail-preview-box" style="width: 80px; height: 112px; border: 2px dashed #d1d1d6; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f9f9f9; flex-shrink: 0;">
                        <span id="thumbnail-placeholder" style="font-size: 11px; color: #999; text-align: center; padding: 5px;">未選択</span>
                        <img id="thumbnail-preview-img" style="display: none; width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div style="flex: 1;">
                        <p style="font-size: 11px; color: #666; margin: 0 0 8px 0; line-height: 1.4;">デッキに採用されているカードの中から、1枚をサムネイル画像（看板）として登録できます。</p>
                        <button type="button" onclick="openThumbnailSelector()" style="padding: 8px 16px; background: #17a2b8; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; transition: background 0.2s;">カードを選択する</button>
                    </div>
                </div>
                <input type="hidden" id="save-deck-thumbnail-id" value="">
            </div>

            <div style="margin-top: 10px; border-top: 1px solid #f2f2f7; padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                <div style="padding-right: 15px; flex: 1;">
                    <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 2px; color: #444;">デッキを公開する</label>
                    <span style="font-size: 11px; color: #666; display: block; line-height: 1.4;">公開すると、他のユーザーがデッキ検索で閲覧・コピーできるようになります。</span>
                </div>
                
                <label class="toggle-switch" style="flex-shrink: 0;">
                    <input type="checkbox" id="save-deck-public">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
        
        <div style="padding: 15px 20px 20px 20px; border-top: 1px solid #f2f2f7; display: flex; gap: 12px; background: #fff;">
            <button onclick="closeSaveModal()" style="flex: 1; padding: 12px; background: #f2f2f7; color: #555; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;">キャンセル</button>
            <button onclick="submitDeckSave()" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;">保存する</button>
        </div>
    </div>
</div>

<div id="thumbnailSelectModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 400px; height: 75vh;">
        <div class="sub-modal-header">
            <span>サムネイルにするカードを選択</span>
            <span onclick="closeThumbnailSelector()" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div id="thumbnail-cards-list" class="sub-modal-body" style="padding: 15px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; align-content: start;">
        </div>
    </div>
</div>

<div id="searchSettingsModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 400px; height: auto; margin: 15vh auto;">
        <div class="sub-modal-header">
            <span>検索・表示設定</span>
            <span onclick="toggleSettingsModal()" style="cursor:pointer; font-size: 24px;">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 20px;">
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">検索結果の並び順</label>
                <select id="search-sort-select" style="width: 100%; padding: 10px; border: 1px solid #d1d1d6; border-radius: 8px; font-size: 14px; background: #fff; cursor: pointer;" onchange="changeSearchSort(this.value)">
                    <option value="release_desc">発売日が新しい順</option>
                    <option value="release_asc">発売日が古い順</option>
                    <option value="cost_desc">コストが高い順</option>
                    <option value="cost_asc">コストが低い順</option>
                    <option value="pow_desc">パワーが高い順</option>
                    <option value="pow_asc">パワーが低い順</option>
                    <option value="name_asc">カード名 50音順 (昇順)</option>
                    <option value="name_desc">カード名 50音順 (降順)</option>
                </select>
            </div>

            <div style="border-top: 1px solid #eee; padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                <div style="padding-right: 15px; flex: 1;">
                    <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 2px; color: #444;">検索ワードの自動変換</label>
                    <span style="font-size: 11px; color: #666; display: block; line-height: 1.4;">OFFにすると、ひらがな/カタカナ変換などを行わず、入力した文字通りのままで検索します。</span>
                </div>
                <label class="toggle-switch" style="flex-shrink: 0;">
                    <input type="checkbox" id="search-convert-toggle" checked onchange="toggleSearchConvert(this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="settings-position-group" style="border-top: 1px solid #eee; padding-top: 15px;">
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">検索エリアの表示位置</label>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="hand-switch-btn" style="flex: 1; padding: 10px; font-size: 13px;" onclick="toggleSearchPosition()">⇄ 左右位置を切り替える</button>
                </div>
            </div>
        </div>
        <div style="padding: 15px 20px; border-top: 1px solid #eee; background: #fff;">
            <button onclick="toggleSettingsModal()" style="width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">閉じる</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/analysis_modal.php'; ?>

<!-- チュートリアル制御スクリプトの読み込み -->
<script src="/js/tutorial.js?v=<?php echo time(); ?>"></script>

<script>
let isInitializing = true; 
let isDeckSaved = false;    
const isEdit = <?php echo !empty($isEdit) ? 'true' : 'false'; ?>;
const deckId = <?php echo isset($deck['deck_id']) ? $deck['deck_id'] : 'null'; ?>;
const initialDeckName = <?php echo isset($deck['deck_name']) ? json_encode($deck['deck_name']) : '"マイデッキ"'; ?>;
const initialCards = <?php echo !empty($initialCards) ? json_encode($initialCards) : '[]'; ?>;
const currentSort = { key: null, order: 'asc' };

const cardCache = {};
const formatsFromPhp = <?php echo isset($formats) ? json_encode($formats) : '[]'; ?>;

const mainList = document.getElementById('main-deck-list');
const superDimList = document.getElementById('super-dim-list');
const grList = document.getElementById('gr-list');
const resultsDiv = document.getElementById('search-results');
const trashArea = document.getElementById('trash-area');
const input = document.getElementById('card-search-input');

let currentFilters = { 
    q: '', scope: ['name'], civs: [], cost_min: '', cost_max: '', 
    pow_min: '', pow_max: '', races: [], abilities: [], 
    characteristics: [], cardtypes: [],
    goods: [],
    race_logic: 'OR', ability_logic: 'OR', 
    characteristic_logic: 'OR', cardtype_logic: 'OR',
    reg: [],
    civ_type: '', civ_match_type: 'include', exclude_civs: [],
    twinpact: '',
    sort: 'release_desc',
    raw_query: 0 
};

function toggleSettingsModal() {
    const m = document.getElementById('searchSettingsModal');
    m.style.display = (m.style.display === 'block') ? 'none' : 'block';
}

function toggleSearchConvert(isAutoConvert) {
    currentFilters.raw_query = isAutoConvert ? 0 : 1;
    searchCards();
}

function changeSearchSort(val) {
    currentFilters.sort = val;
    searchCards();
}
let currentOffset = 0, isFetching = false, hasMoreCards = true;
let searchTimeout = null, abortController = null;

let selectedCardData = null, allVersions = [], activeClickedElement = null;
let isSubmittingDeck = false;

function getCardImagePath(card) {
    if (!card.imagepath) return '/images/card/noimage.webp';
    const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
    return '/images/card' + path;
}

function handleImageError(img) {
    img.src = '/images/card/noimage.webp';
    img.onerror = null;
}

window.addEventListener('DOMContentLoaded', () => {
    if (formatsFromPhp && formatsFromPhp.length > 0) {
        renderFormatSelect(formatsFromPhp);
    }
    if (isEdit && initialCards.length > 0) {
        initialCards.forEach(card => { 
            cardCache[card.card_id] = card; 
            const qty = parseInt(card.quantity, 10) || 1;
            for (let i = 0; i < qty; i++) {
                addCardToDeck(card, card.card_type_in_deck); 
            }
        });
        updateDeckDisplay();
        document.getElementById('save-deck-btn').innerText = "上書き保存";
        
        <?php if (isset($deck['format_id'])): ?>
            document.getElementById('save-deck-format').value = <?php echo $deck['format_id']; ?>;
        <?php endif; ?>

        <?php if (isset($deck['is_public'])): ?>
            const isPublicVal = <?php echo $deck['is_public'] ? 'true' : 'false'; ?>;
            document.getElementById('save-deck-public').checked = isPublicVal;
            const toggleRadio = document.querySelector(`input[name="deck-public-toggle"][value="${isPublicVal ? '1' : '0'}"]`);
            if (toggleRadio) toggleRadio.checked = true;
        <?php endif; ?>
        <?php if (isset($deck['thumbnail_card_id']) && isset($deck['thumbnail_imagepath'])): ?>
            setThumbnail(
                '<?php echo $deck['thumbnail_card_id']; ?>', 
                '<?php echo $deck['thumbnail_imagepath']; ?>'
            );
        <?php endif; ?>
    }

    fetch('/api/master-data')
        .then(res => res.json())
        .then(data => {
            renderMasterList('race', data.races);
            renderMasterList('ability', data.abilities);
            
            if (data.characteristics) {
                data.characteristics.sort((a, b) => a.characteristics_id - b.characteristics_id);
                renderMasterList('characteristics', data.characteristics);
            }
            if (data.cardtypes) {
                data.cardtypes.sort((a, b) => a.cardtype_id - b.cardtype_id);
                renderMasterList('cardtype', data.cardtypes);
            }
        });

    fetch('/api/master-data-extended')
        .then(res => res.json())
        .then(data => {
            if (data.goods) {
                renderMasterList('goods', data.goods);
            }
        })
        .catch(err => console.error("商品データの取得に失敗しました", err));

    setTimeout(adjustMainDeckRows, 100);

    const resizeObserver = new ResizeObserver(entries => {
        for (let entry of entries) {
            if (entry.contentRect.width > 0) {
                adjustMainDeckRows();
            }
        }
    });
    const deckArea = document.getElementById('deck-area');
    if (deckArea) resizeObserver.observe(deckArea);

    searchCards();
    const nameInput = document.getElementById('save-deck-name');
    const formatSelect = document.getElementById('save-deck-format');
    if (nameInput) nameInput.addEventListener('input', saveDraftToLocalStorage);
    if (formatSelect) formatSelect.addEventListener('change', saveDraftToLocalStorage);

    setTimeout(checkAndRestoreDraft, 250);
});

function renderFormatSelect(formats) {
    const select = document.getElementById('save-deck-format');
    select.innerHTML = '';
    formats.sort((a, b) => a.format_id - b.format_id); 
    formats.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.format_id;
        opt.innerText = f.format_name;
        select.appendChild(opt);
    });
}

function addCardToDeck(card, forcedType = null, forcedSlotId = null) {
    cardCache[card.card_id] = card;
    const img = document.createElement('img');
    img.src = getCardImagePath(card);
    img.draggable = false; 
    img.dataset.cardId = card.card_id;
    img.dataset.cardName = card.card_name;
    img.dataset.reading = card.reading || '';
    img.dataset.comboNames = card.combo_names || '';
    img.dataset.charIds = card.char_ids;
    img.dataset.imagepath = card.imagepath;
    img.dataset.cardLimit = card.card_limit !== undefined && card.card_limit !== null ? card.card_limit : '';
    img.dataset.civ = card.civilization || card.civ || card.civ_ids || '';
    img.dataset.cost = card.cost !== undefined ? card.cost : '';
    img.alt = card.card_name;
    img.onerror = () => handleImageError(img);

    const name = card.card_name;
    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');
    const isSpecial = isDoru || isZero;

    const type = forcedType || (isSpecial ? 'special' : determineZoneType(card.char_ids));

    if (type === 'special') {
        const slotId = forcedSlotId || (isDoru ? 'slot-dolmagedon' : 'slot-zeroron');
        const slot = document.getElementById(slotId);
        if (slot) {
            slot.innerHTML = ''; 
            slot.appendChild(img);
            slot.classList.add('active');
            slot.classList.remove('empty');
            updateSaveButton(slotId, true);
        }
    } else {
        let targetList = mainList;
        if (type === 'super_dimensional') targetList = superDimList;
        else if (type === 'gr') targetList = grList;
        targetList.appendChild(img);
    }
    updateDeckDisplay();
}

function determineZoneType(charIdsStr) {
    const ids = (charIdsStr || '').split(',');
    if (ids.includes('3') || ids.includes('6')) return 'super_dimensional';
    if (ids.includes('10')) return 'gr';
    return 'main';
}

[mainList, superDimList, grList].forEach(list => list.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') openCardDetail(e.target.dataset.cardId, e.target);
}));
resultsDiv.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') {
        const cardId = e.target.dataset.cardId;
        openCardDetail(cardId, e.target);
    }
});  

const deckSortableConfig = {
    group: { 
        name: 'shared', 
        pull: true, 
        put: true 
    }, 
    animation: 150,
    touchStartThreshold: 10, 
    forceFallback: true,      
    fallbackTolerance: 5,     
    fallbackOnBody: true,

    onEnd: function(evt) {
        const e = evt.originalEvent;
        if (!e) {
            updateDeckDisplay();
            return;
        }

        const touch = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
        const clientX = e.clientX || (touch ? touch.clientX : 0);
        const clientY = e.clientY || (touch ? touch.clientY : 0);

        const searchSection = document.getElementById('search-section');
        if (searchSection) {
            const sRect = searchSection.getBoundingClientRect();
            const isOverSearch = (clientX >= sRect.left && clientX <= sRect.right &&
                                  clientY >= sRect.top && clientY <= sRect.bottom);
            
            if (isOverSearch && evt.from !== resultsDiv) {
                evt.item.remove();
                updateDeckDisplay();
                return;
            }
        }

        const validLists = [mainList, superDimList, grList];
        document.querySelectorAll('.special-box').forEach(box => {
            validLists.push(box);
        });

        let isInside = false;
        for (const list of validLists) {
            const rect = list.getBoundingClientRect();
            if (clientX >= rect.left && clientX <= rect.right &&
                clientY >= rect.top && clientY <= rect.bottom) {
                isInside = true;
                break;
            }
        }

        if (!isInside && evt.from !== resultsDiv) {
            evt.item.remove();
        }

        updateDeckDisplay();
    },
    onAdd: function(evt) {
        const item = evt.item;
        const name = item.dataset.cardName;
        const charIds = (item.dataset.charIds || '').split(',');
        
        const limitVal = item.dataset.cardLimit;
        const nameLimit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

        const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
        const isZero = (name === '零龍' || name === '滅亡の起源 零無');

        if (isDoru || isZero) {
            const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
            const slot = document.getElementById(slotId);
            if (slot.querySelectorAll('img').length > 0) {
                alert("既に登録されています。");
                item.remove();
                return;
            }
            slot.innerHTML = ''; 
            slot.appendChild(item);
            slot.classList.add('active');
            slot.classList.remove('empty');
            updateSaveButton(slotId, true);
            updateDeckDisplay();
            return;
        }

        const comboNames = item.dataset.comboNames || '';

        if (charIds.includes('3') || charIds.includes('6')) {
            superDimList.appendChild(item);
            if (!checkLimit(name, nameLimit, superDimList, item, 8, "超次元ゾーン", comboNames)) {
                item.remove();
            }
        } else if (charIds.includes('10')) {
            grList.appendChild(item);
            if (!checkLimit(name, nameLimit, grList, item, 12, "超GRゾーン", comboNames)) {
                item.remove();
            }
        } else {
            mainList.appendChild(item);
            if (!checkLimit(name, nameLimit, mainList, item, 60, "メインデッキ", comboNames)) {
                item.remove();
            }
        }
        updateDeckDisplay();
    }
};

new Sortable(mainList, deckSortableConfig);
new Sortable(superDimList, deckSortableConfig);
new Sortable(grList, deckSortableConfig);

const searchSortable = new Sortable(resultsDiv, { 
    group: { 
        name: 'shared', 
        pull: 'clone', 
        put: false 
    }, 
    sort: false, 
    animation: 150,
    forceFallback: true,      
    fallbackTolerance: 10,    
    fallbackOnBody: true      
});

let touchStartX = 0;
let touchStartY = 0;
let isTrackingTouch = false;
let isScrollMode = false;

resultsDiv.addEventListener('touchstart', (e) => {
    if (e.touches.length !== 1) return;
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    isTrackingTouch = true;
    isScrollMode = false;
}, { capture: true, passive: true });

document.addEventListener('touchmove', (e) => {
    if (!isTrackingTouch || e.touches.length !== 1) return;

    const dx = e.touches[0].clientX - touchStartX;
    const dy = e.touches[0].clientY - touchStartY;
    const dist = Math.hypot(dx, dy);

    if (!isScrollMode && dist >= 5) {
        let deg = Math.atan2(dx, -dy) * (180 / Math.PI);
        if (deg < 0) deg += 360;

        if (deg > 45 && deg < 320) {
            isScrollMode = true;
        }
    }

    if (isScrollMode) {
        e.stopImmediatePropagation();
    }
}, { capture: true, passive: true });

const resetSearchTouch = () => {
    isTrackingTouch = false;
    isScrollMode = false;
};

document.addEventListener('touchend', resetSearchTouch, { capture: true, passive: true });
document.addEventListener('touchcancel', resetSearchTouch, { capture: true, passive: true });

document.querySelectorAll('.special-box').forEach(box => {
    new Sortable(box, {
        group: {
            name: 'shared',
            pull: false, 
            put: function (to, from, dragEl) {
                const cardName = dragEl.dataset.cardName || '';
                if (to.el.id === 'slot-dolmagedon') {
                    return cardName.includes('ドルマゲドン');
                }
                if (to.el.id === 'slot-zeroron') {
                    return cardName.includes('零龍');
                }
                return false;
            }
        },
        animation: 150, 
        filter: '.empty img',
        onAdd: function(evt) {
            if (evt.to.querySelectorAll('img').length > 1) {
                alert("既に登録されています。");
                evt.item.remove();
                return;
            }
            evt.to.classList.remove('empty');
            evt.to.classList.add('active');
            updateSaveButton(evt.to.id, true);
            updateDeckDisplay();
        },
        onRemove: (evt) => {
            evt.to.classList.add('empty');
            evt.to.classList.remove('active');
            updateSaveButton(evt.to.id, false);
            updateDeckDisplay();
        }
    });
});

function checkLimit(name, nameLimit, listElement, itemElement, totalLimit, zoneName, comboNames = '') {
    const isPreAdd = (itemElement === null);
    
    const totalCount = listElement.querySelectorAll('img').length + (isPreAdd ? 1 : 0);
    if (totalCount > totalLimit) {
        alert(`${zoneName}は合計${totalLimit}枚までです。`);
        return false;
    }
    
    const selector = getCardSelector(name, comboNames);
    const nameCount = listElement.querySelectorAll(selector).length + (isPreAdd ? 1 : 0);
    
    if (nameCount > nameLimit) {
        alert(`${name} は${zoneName}に最大${nameLimit}枚までです。`);
        return false;
    }
    return true;
}

function switchDeckTab(type) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.deck-content').forEach(c => {
        c.classList.remove('active');
        c.style.display = 'none';
    });
    
    const targetTab = document.getElementById('tab-' + type);
    const targetContent = type === 'extra' ? document.getElementById('extra-deck-area') : document.getElementById(type + '-deck-list');

    if (targetTab) targetTab.classList.add('active');
    if (targetContent) {
        targetContent.classList.add('active');
        if (type === 'extra' || type === 'special') {
            targetContent.style.display = 'flex';
        } else {
            targetContent.style.display = 'grid';
        }
    }

    if (type === 'main') {
        setTimeout(adjustMainDeckRows, 100); 
    }
}

function updateDeckDisplay() {
    if (currentSort.key) {
        sortDeckList(mainList, currentSort.key, currentSort.order);
        sortDeckList(superDimList, currentSort.key, currentSort.order);
        sortDeckList(grList, currentSort.key, currentSort.order);
    }
    const mCount = mainList.querySelectorAll('img').length;
    const gCount = grList.querySelectorAll('img').length;
    const sCount = superDimList.querySelectorAll('img').length;

    const isMobile = window.innerWidth <= 768;
    document.getElementById('tab-main').innerText = `メイン ${mCount}`;
    if (isMobile) {
        document.getElementById('tab-extra').innerText = `GR/次元 ${gCount}/${sCount}`;
    } else {
        document.getElementById('tab-extra').innerText = `GR ${gCount}/12 超次元 ${sCount}/8`;
    }

    adjustMainDeckRows();
    saveDraftToLocalStorage();
}

let currentCombinationSides = [];

function loadCombinationSides(cardId) {
    const comboSection = document.getElementById('combination-section');
    const comboList = document.getElementById('detail-combination-list');
    comboSection.style.display = 'none';
    comboList.innerHTML = '';

    fetch('/api/cards/combination?card_id=' + cardId)
        .then(res => res.json())
        .then(sides => {
            currentCombinationSides = sides;
            if (sides.length > 1 && (!sides[0].twinpact || sides[0].twinpact == 0)) {
                comboSection.style.display = 'block';
                sides.forEach(side => {
                    const sideImg = document.createElement('img');
                    sideImg.src = getCardImagePath(side);
                    sideImg.title = side.card_name;
                    if (side.card_id == selectedCardData.card_id) sideImg.className = 'selected';
                    
                    sideImg.onclick = () => {
                        document.getElementById('detail-name').innerText = side.card_name;
                        document.getElementById('detail-text').innerText = side.text || "効果なし";
                        document.getElementById('detail-main-img').src = getCardImagePath(side);
                        comboList.querySelectorAll('img').forEach(img => img.classList.remove('selected'));
                        sideImg.classList.add('selected');
                    };
                    comboList.appendChild(sideImg);
                });
            }
        })
        .catch(err => console.error("構成面の取得に失敗しました:", err));
}

function openCardDetail(cardId, el) {
    activeClickedElement = el;
    
    let cachedCard = cardCache[cardId];
    if ((!cachedCard || !cachedCard.text) && el && el.dataset) {
        cachedCard = {
            card_id: cardId,
            card_name: el.dataset.cardName || '',
            combo_names: el.dataset.comboNames || '',
            char_ids: el.dataset.charIds || '',
            imagepath: el.dataset.imagepath || '',
            card_limit: el.dataset.cardLimit || '',
            civilization: el.dataset.civ || '',
            cost: el.dataset.cost || '',
            text: cachedCard && cachedCard.text ? cachedCard.text : "詳細効果を読み込み中..."
        };
        cardCache[cardId] = cachedCard;
    }

    if (cachedCard) {
        selectedCardData = cachedCard;
        allVersions = [cachedCard];
        renderDetailModal();
        document.getElementById('detail-version-list').innerHTML = '<span style="font-size:12px;color:#999;padding:5px;">読み込み中...</span>';
    }

    fetch('/api/cards/versions?card_id=' + cardId)
        .then(res => res.json())
        .then(versions => {
            allVersions = versions;
            const freshData = versions.find(v => v.card_id == cardId) || versions[0];
            if (freshData) {
                selectedCardData = freshData;
                cardCache[cardId] = freshData;
            }
            renderDetailModal();
        });

    const comboSection = document.getElementById('combination-section');
    const comboList = document.getElementById('detail-combination-list');
    comboSection.style.display = 'none';
    comboList.innerHTML = '';

    fetch('/api/cards/combination?card_id=' + cardId)
        .then(res => res.json())
        .then(sides => {
            currentCombinationSides = sides;
            if (sides.length > 1 && (!sides[0].twinpact || sides[0].twinpact == 0)) {
                comboSection.style.display = 'block';
                sides.forEach(side => {
                    const sideImg = document.createElement('img');
                    sideImg.src = getCardImagePath(side);
                    sideImg.title = side.card_name;
                    if (side.card_id == selectedCardData.card_id) sideImg.className = 'selected';
                    
                    sideImg.onclick = () => {
                        document.getElementById('detail-name').innerText = side.card_name;
                        document.getElementById('detail-text').innerText = side.text || "効果なし";
                        document.getElementById('detail-main-img').src = getCardImagePath(side);
                        comboList.querySelectorAll('img').forEach(img => img.classList.remove('selected'));
                        sideImg.classList.add('selected');
                    };
                    comboList.appendChild(sideImg);
                });
            }
        })
        .catch(err => console.error("構成面の取得に失敗しました:", err));
}

function renderDetailModal() {
    const modal = document.getElementById('cardDetailModal');
    const versionList = document.getElementById('detail-version-list');
    document.getElementById('detail-name').innerText = selectedCardData.card_name;
    document.getElementById('detail-text').innerText = selectedCardData.text || "効果なし";
    document.getElementById('detail-main-img').src = getCardImagePath(selectedCardData);

    const name = selectedCardData.card_name;
    let count = 0;
    
    const comboNames = selectedCardData.combo_names || '';
    const selector = getCardSelector(name, comboNames);

    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');

    if (isDoru || isZero) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        count = document.getElementById(slotId).classList.contains('active') ? 1 : 0;
    }
    else {
        const type = determineZoneType(selectedCardData.char_ids);
        let targetList = type === 'super_dimensional' ? superDimList : (type === 'gr' ? grList : mainList);
        count = targetList.querySelectorAll(selector).length; 
    }
    
    document.getElementById('detail-qty').innerText = count;

    const limitVal = selectedCardData.card_limit;
    const limitText = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? limitVal : "∞";
    document.getElementById('detail-limit').innerText = limitText;

    versionList.innerHTML = '';
    allVersions.forEach(v => {
        const vImg = document.createElement('img');
        vImg.src = getCardImagePath(v);
        if (v.card_id == selectedCardData.card_id) vImg.className = 'selected';
        vImg.onclick = () => {
            selectedCardData = v;
            if (activeClickedElement && activeClickedElement.parentNode !== resultsDiv) {
                activeClickedElement.src = vImg.src;
                activeClickedElement.dataset.cardId = v.card_id;
            }
            renderDetailModal();
            loadCombinationSides(v.card_id);
        };
        versionList.appendChild(vImg);
    });
    modal.style.display = 'block';
}

function adjustQty(diff) {
    const name = selectedCardData.card_name;
    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');
    const isSpecial = isDoru || isZero;

    if (isSpecial) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        
        if (diff > 0) {
            if (!slot.classList.contains('active')) {
                addCardToDeck(selectedCardData, 'special', slotId);
            }
            document.getElementById('detail-qty').innerText = "1"; 
        } else {
            removeSpecialCard(slotId);
            document.getElementById('detail-qty').innerText = "0";
        }
        return;
    }

    const type = determineZoneType(selectedCardData.char_ids);
    let targetList = mainList, limit = 4, totalLimit = 60, zoneName = "メインデッキ";

    const limitVal = selectedCardData.card_limit;
    limit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

    if (type === 'super_dimensional') { targetList = superDimList; totalLimit = 8; zoneName = "超次元ゾーン"; }
    else if (type === 'gr') { targetList = grList; totalLimit = 12; zoneName = "超GRゾーン"; }

    const comboNames = selectedCardData.combo_names || '';
    const selector = getCardSelector(name, comboNames);
    const count = targetList.querySelectorAll(selector).length;
    const totalCount = targetList.querySelectorAll('img').length;

    if (diff > 0) {
        if (count >= limit) {
            return alert(`${name} は${zoneName}に最大${limit}枚までです。`);
        }
        if (totalCount >= totalLimit) return alert(`${zoneName}は合計${totalLimit}枚までです。`);
        addCardToDeck(selectedCardData);
    } else {
        if (count <= 0) return;
        const target = targetList.querySelector(selector);
        if (target) {
            target.remove();
            if (activeClickedElement === target) activeClickedElement = null;
        }
    }
    
    updateDeckDisplay();
    document.getElementById('detail-qty').innerText = targetList.querySelectorAll(selector).length;
}

function closeDetailModal() { document.getElementById('cardDetailModal').style.display = 'none'; activeClickedElement = null; }

function adjustMainDeckRows() {
    const list = document.getElementById('main-deck-list');
    if (!list || !list.classList.contains('active')) return;

    list.style.height = '';
    list.style.width = '';

    const container = document.getElementById('container');
    const containerHeight = container.clientHeight;
    if (containerHeight <= 0) return;

    const parent = list.parentNode;
    const maxWidth = (parent.clientWidth || 900) - 20; 
    
    const searchSection = document.getElementById('search-section');
    const isSearchCollapsed = searchSection.classList.contains('collapsed');
    
    let paddingBottom = 10;
    if (window.innerWidth <= 768) {
        paddingBottom = isSearchCollapsed ? 110 : 210;
    }
    parent.style.paddingBottom = `${paddingBottom}px`;
        
    const excludeHeight = 35 + 40 + 10 + 20 + paddingBottom + 10;
    
    const maxContainerHeight = containerHeight - excludeHeight;
    if (maxWidth <= 0 || maxContainerHeight <= 0) return;

    const cards = list.querySelectorAll('img');
    const count = cards.length;
    const cols = 8;
    
    const h_byHeight = maxContainerHeight / 5;
    const h_byWidth = (maxWidth / cols) * (154 / 110);
    const baseCardHeight = Math.min(h_byHeight, h_byWidth);
    const cardWidth = baseCardHeight * (110 / 154);

    list.style.width = `${cardWidth * cols}px`;
    list.style.margin = '0 auto'; 

    const rows = Math.ceil(count / cols) || 1;

    if (rows <= 5) {
        const targetContainerHeight = baseCardHeight * 5;
        list.style.height = `${targetContainerHeight}px`;
        list.style.gridTemplateRows = `repeat(5, ${baseCardHeight}px)`;
        list.style.gridAutoRows = `${baseCardHeight}px`;

        cards.forEach(img => {
            img.style.height = `${baseCardHeight}px`;
            img.style.objectFit = 'contain'; 
        });
    } else {
        list.style.gridTemplateRows = 'none';
        
        const targetContainerHeight = baseCardHeight * 5;
        list.style.height = `${targetContainerHeight}px`;

        cards.forEach(img => {
            img.style.height = `${baseCardHeight}px`;
            img.style.objectFit = 'contain';
        });

        const availableHeight = targetContainerHeight - baseCardHeight - 2;
        let rowHeight = availableHeight / (rows - 1);

        list.style.gridAutoRows = `${rowHeight}px`;
    }
}

window.addEventListener('resize', adjustMainDeckRows);

function toggleSearchSection() {
    const searchSection = document.getElementById('search-section');
    const deckArea = document.getElementById('deck-area');
    
    const isCollapsed = searchSection.classList.toggle('collapsed');
    
    if (isCollapsed) {
        if (deckArea) deckArea.classList.add('search-collapsed');
    } else {
        if (deckArea) deckArea.classList.remove('search-collapsed');
    }
    
    syncSearchToggleButton();
    adjustMainDeckRows();
    setTimeout(adjustMainDeckRows, 300);
}

function syncSearchToggleButtonText() {
    const searchSection = document.getElementById('search-section');
    const container = document.getElementById('container');
    const btn = document.getElementById('search-toggle-btn');
    if (!btn) return;

    const isCollapsed = searchSection.classList.contains('collapsed');
    const isLeftHanded = container.classList.contains('left-handed');

    if (window.innerWidth <= 768) {
        btn.innerHTML = isCollapsed ? '▲ カード検索を開く' : '▼ 検索を隠す';
        return;
    }

    if (isLeftHanded) {
        btn.style.right = 'auto';
        btn.style.left = isCollapsed ? '0px' : '380px'; 
        btn.style.borderRadius = '0 8px 8px 0'; 
        btn.innerHTML = isCollapsed ? '開く ▶' : '◀ 隠す';
    } else {
        btn.style.left = 'auto';
        btn.style.right = isCollapsed ? '0px' : '380px'; 
        btn.style.borderRadius = '8px 0 0 8px'; 
        btn.innerHTML = isCollapsed ? '◀ 開く' : '隠す ▶';
    }
}
window.addEventListener('resize', syncSearchToggleButtonText);

input.addEventListener('input', () => {
    currentFilters.q = input.value;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchCards, 300);
});

document.querySelectorAll('.scope-check').forEach(el => {
    el.onchange = () => {
        currentFilters.scope = Array.from(document.querySelectorAll('.scope-check:checked')).map(c => c.value);
        searchCards(); 
    };
});

function searchCards() {
    if (abortController) abortController.abort();
    isFetching = false; currentOffset = 0; hasMoreCards = true; resultsDiv.innerHTML = '';
    
    fetchAndRender();
}

function loadMoreCards() { 
    if (isFetching || !hasMoreCards) return; 
    currentOffset += 50; 
    fetchAndRender(); 
}

function fetchAndRender() {
    if (isFetching) return;
    abortController = new AbortController();
    isFetching = true;
    if (currentOffset === 0) resultsDiv.innerHTML = '<div class="search-msg">検索中...</div>';

    const p = new URLSearchParams();
    if (currentFilters.q) {
        p.append('q', currentFilters.q);
    }
    
    p.append('scope', currentFilters.scope.join(',')); 
    if (currentFilters.civs.length) {
        p.append('civs', currentFilters.civs.join(','));
        p.append('civ_match_type', currentFilters.civ_match_type);
    }
    if (currentFilters.civ_type) {
        p.append('civ_type', currentFilters.civ_type);
    }   
    if (currentFilters.exclude_civs.length) {
        p.append('exclude_civs', currentFilters.exclude_civs.join(','));
    }  
    if (currentFilters.cost_min) p.append('cost_min', currentFilters.cost_min);
    if (currentFilters.cost_max) p.append('cost_max', currentFilters.cost_max);
    if (currentFilters.pow_min) p.append('pow_min', currentFilters.pow_min);
    if (currentFilters.pow_max) p.append('pow_max', currentFilters.pow_max);
    if (currentFilters.races.length) p.append('races', currentFilters.races.join(','));
    p.append('race_logic', currentFilters.race_logic);
    if (currentFilters.abilities.length) p.append('abilities', currentFilters.abilities.join(','));
    p.append('ability_logic', currentFilters.ability_logic);

    if (currentFilters.goods && currentFilters.goods.length) {
        p.append('goods', currentFilters.goods.join(','));
    }

    if (currentFilters.characteristics.length) p.append('characteristics', currentFilters.characteristics.join(','));
    p.append('characteristic_logic', currentFilters.characteristic_logic);
    if (currentFilters.cardtypes.length) p.append('cardtypes', currentFilters.cardtypes.join(','));
    p.append('cardtype_logic', currentFilters.cardtype_logic);

    if (currentFilters.twinpact) {
        p.append('twinpact', currentFilters.twinpact);
    }
        
    if (currentFilters.reg.length) p.append('reg', currentFilters.reg.join(','));
    p.append('offset', currentOffset);

    if (currentFilters.raw_query) {
        p.append('raw_query', '1');
    }

    if (currentFilters.sort) {
        p.append('sort', currentFilters.sort);
    }

    fetch('/api/cards?' + p.toString(), { signal: abortController.signal })
        .then(res => res.json())
        .then(data => {
            if (!Array.isArray(data)) {
                console.error("サーバーエラー:", data);
                resultsDiv.innerHTML = '<div class="search-msg">検索中にエラーが発生しました。</div>';
                return;
            }
            if (currentOffset === 0) resultsDiv.innerHTML = '';
            if (data.length === 0 && currentOffset === 0) {
                resultsDiv.innerHTML = '<div class="search-msg">検索条件に該当するカードがみつかりませんでした。</div>';
                hasMoreCards = false;
            } else {
                if (data.length < 50) hasMoreCards = false;
                data.forEach(card => {
                    cardCache[card.card_id] = card; 
                    const img = document.createElement('img');
                    img.src = getCardImagePath(card);
                    img.draggable = false; 
                    img.dataset.cardId = card.card_id;
                    img.dataset.cardName = card.card_name;
                    img.dataset.reading = card.reading || '';
                    img.dataset.comboNames = card.combo_names || ''; 
                    img.dataset.charIds = card.char_ids;
                    img.dataset.imagepath = card.imagepath;
                    img.dataset.cardLimit = card.card_limit !== undefined && card.card_limit !== null ? card.card_limit : ''; 
                    img.dataset.civ = card.civilization || card.civ || card.civ_ids || '';
                    img.dataset.cost = card.cost !== undefined ? card.cost : '';
                    img.onerror = () => handleImageError(img);
                    resultsDiv.appendChild(img);
                });
            }
        })
        .catch(err => { if (err.name !== 'AbortError') console.error(err); })
        .finally(() => { isFetching = false; });
}

resultsDiv.onscroll = () => { 
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        if (resultsDiv.scrollLeft + resultsDiv.clientWidth >= resultsDiv.scrollWidth - 100) {
            loadMoreCards();
        }
    } else {
        if (resultsDiv.scrollTop + resultsDiv.clientHeight >= resultsDiv.scrollHeight - 100) {
            loadMoreCards();
        }
    }
};

function toggleFilterModal() { 
    const m = document.getElementById('filterModal'); 
    m.style.display = (m.style.display === 'block') ? 'none' : 'block'; 
}

function openSubModal(type) { document.getElementById(`${type}SelectModal`).style.display = 'block'; }
function closeSubModal(type) { document.getElementById(`${type}SelectModal`).style.display = 'none'; }

function renderMasterList(type, list) {
    const container = document.getElementById(`${type}-list-container`);
    container.innerHTML = '';
    list.forEach(item => {
        const id = item[`${type}_id`], name = item[`${type}_name`];
        const div = document.createElement('label');
        div.className = 'list-item';
        div.dataset.search = (name + (item.reading || '')).toLowerCase();
        div.innerHTML = `<span>${name}</span><input type="checkbox" class="${type}-check" value="${id}" data-name="${name}" onchange="updateTriggerText('${type}')">`;
        container.appendChild(div);
    });
}

['race', 'ability', 'goods'].forEach(type => {
    const searchInput = document.getElementById(`${type}-list-search`);
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll(`#${type}-list-container .list-item`).forEach(el => {
                el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
            });
        });
    }
});

function updateTriggerText(type) {
    const checked = Array.from(document.querySelectorAll(`.${type}-check:checked`));
    const trigger = document.getElementById(`${type}-trigger`);
    trigger.innerText = checked.length > 0 ? checked.map(el => el.dataset.name).join(', ') : '選択';
    trigger.style.color = checked.length > 0 ? '#333' : '#666';

    const badgesContainer = document.getElementById(`${type}-selected-badges`);
    if (badgesContainer) {
        badgesContainer.innerHTML = '';
        if (checked.length === 0) {
            badgesContainer.style.display = 'none';
        } else {
            badgesContainer.style.display = 'flex';
            checked.forEach(el => {
                const badge = document.createElement('div');
                badge.className = 'selected-badge';
                badge.innerHTML = `
                    <span>${escapeHTML(el.dataset.name)}</span>
                    <span class="selected-badge-remove" onclick="removeSubModalSelection('${type}', '${escapeSelectorValue(el.value)}')">×</span>
                `;
                badgesContainer.appendChild(badge);
            });
        }
    }
}

function clearSubSelection(type) {
    document.querySelectorAll(`.${type}-check`).forEach(el => el.checked = false);
    if (type === 'goods') {
        document.querySelectorAll('.era-check').forEach(el => el.checked = false);
    }
    updateTriggerText(type);
}

function toggleFilterCivType() {
    const isMulti = document.getElementById('filter-civ-multi').checked;
    const excludeArea = document.getElementById('filter-exclude-civ-area');
    excludeArea.style.display = isMulti ? 'grid' : 'none'; 
}

function toggleTwinpactExclusive(type) {
    if (type === 'only' && document.getElementById('filter-twinpact-only').checked) {
        document.getElementById('filter-twinpact-exclude').checked = false;
    } else if (type === 'exclude' && document.getElementById('filter-twinpact-exclude').checked) {
        document.getElementById('filter-twinpact-only').checked = false;
    }
}

function applyFilters() {
    currentFilters.civs = Array.from(document.querySelectorAll('.civ-check:checked')).map(el => el.value);
    
    const singleChecked = document.getElementById('filter-civ-single').checked;
    const multiChecked = document.getElementById('filter-civ-multi').checked;
    if (singleChecked && !multiChecked) currentFilters.civ_type = 'single';
    else if (!singleChecked && multiChecked) currentFilters.civ_type = 'multi';
    else if (!singleChecked && !multiChecked) currentFilters.civ_type = 'none';
    else currentFilters.civ_type = ''; 

    currentFilters.civ_match_type = document.querySelector('input[name="filter-civ-match-type"]:checked').value;
    currentFilters.exclude_civs = Array.from(document.querySelectorAll('.filter-exclude-civ-check:checked')).map(el => el.value);

    currentFilters.cost_min = document.getElementById('cost-min').value;
    currentFilters.cost_max = document.getElementById('cost-max').value;
    currentFilters.pow_min = document.getElementById('pow-min').value;
    currentFilters.pow_max = document.getElementById('pow-max').value;
    currentFilters.races = Array.from(document.querySelectorAll('.race-check:checked')).map(el => el.value);
    currentFilters.race_logic = document.querySelector('input[name="race_logic"]:checked').value;
    currentFilters.abilities = Array.from(document.querySelectorAll('.ability-check:checked')).map(el => el.value);
    currentFilters.ability_logic = document.querySelector('input[name="ability_logic"]:checked').value;
    currentFilters.goods = Array.from(document.querySelectorAll('.goods-check:checked')).map(el => el.value);
    currentFilters.characteristics = Array.from(document.querySelectorAll('.characteristics-check:checked')).map(el => el.value);
    currentFilters.characteristic_logic = document.querySelector('input[name="characteristic_logic"]:checked').value;
    currentFilters.cardtypes = Array.from(document.querySelectorAll('.cardtype-check:checked')).map(el => el.value);
    currentFilters.cardtype_logic = document.querySelector('input[name="cardtype_logic"]:checked').value;

    if (document.getElementById('filter-twinpact-only').checked) {
        currentFilters.twinpact = 'only';
    } else if (document.getElementById('filter-twinpact-exclude').checked) {
        currentFilters.twinpact = 'exclude';
    } else {
        currentFilters.twinpact = '';
    }

    currentFilters.reg = Array.from(document.querySelectorAll('.reg-check:checked')).map(el => el.value);
    
    toggleFilterModal();
    searchCards();
}

function clearAllFilters() {
    document.querySelectorAll('#filterModal input[type="checkbox"]').forEach(el => {
        el.checked = false;
    });
    document.querySelectorAll('#filterModal input[type="number"]').forEach(el => {
        el.value = '';
    });
    
    document.getElementById('filter-civ-single').checked = true;
    document.getElementById('filter-civ-multi').checked = true;
    document.querySelector('input[name="filter-civ-match-type"][value="include"]').checked = true;
    toggleFilterCivType();

    input.value = '';
    currentFilters = { 
        q: '', scope: ['name'], civs: [], cost_min: '', cost_max: '', 
        pow_min: '', pow_max: '', races: [], abilities: [], 
        characteristics: [], cardtypes: [],
        goods: [],
        race_logic: 'OR', ability_logic: 'OR', reg: [],
        characteristic_logic: 'OR', cardtype_logic: 'OR',
        civ_type: '', civ_match_type: 'include', exclude_civs: [],
        twinpact: '' 
    };
    
    clearSubSelection('race');
    clearSubSelection('ability');
    clearSubSelection('characteristics');
    clearSubSelection('cardtype');
    clearSubSelection('goods');
    searchCards();
}

function openSaveModal() {
    const mainCardsCount = mainList.querySelectorAll('img').length;
    const extraCardsCount = grList.querySelectorAll('img').length + superDimList.querySelectorAll('img').length;
    const specialCardsCount = document.querySelectorAll('.special-box.active img').length;
    
    if (mainCardsCount === 0 && extraCardsCount === 0 && specialCardsCount === 0) {
        return alert("カードを1枚以上入れてください。");
    }

    const nameInput = document.getElementById('save-deck-name');
    if (!nameInput.value) {
        nameInput.value = isEdit ? initialDeckName : "";
    }

    const currentThumbId = document.getElementById('save-deck-thumbnail-id').value;
    if (!currentThumbId) {
        const firstCard = mainList.querySelector('img') || grList.querySelector('img') || superDimList.querySelector('img') || document.querySelector('.special-box.active img');
        if (firstCard) {
            setThumbnail(firstCard.dataset.cardId, firstCard.dataset.imagepath);
        }
    }

    document.getElementById('deckSaveModal').style.display = 'block';
}

function closeSaveModal() {
    document.getElementById('deckSaveModal').style.display = 'none';
}

function openThumbnailSelector() {
    const container = document.getElementById('thumbnail-cards-list');
    container.innerHTML = '';

    const selectors = [
        '#main-deck-list img',
        '#gr-list img',
        '#super-dim-list img',
        '.special-box.active img'
    ];
    const cards = document.querySelectorAll(selectors.join(','));
    
    const seenIds = new Set();
    const uniqueCards = [];
    cards.forEach(img => {
        const id = img.dataset.cardId;
        if (!seenIds.has(id)) {
            seenIds.add(id);
            uniqueCards.push({
                card_id: id,
                card_name: img.dataset.cardName,
                imagepath: img.dataset.imagepath
            });
        }
    });

    if (uniqueCards.length === 0) {
        alert("デッキ内に選択可能なカードがありません。");
        return;
    }

    uniqueCards.forEach(card => {
        const img = document.createElement('img');
        img.src = getCardImagePath(card);
        img.alt = card.card_name;
        img.style.width = '100%';
        img.style.cursor = 'pointer';
        img.style.borderRadius = '4px';
        img.style.border = '2px solid transparent';
        
        if (card.card_id === document.getElementById('save-deck-thumbnail-id').value) {
            img.style.borderColor = '#007bff';
        }

        img.onclick = () => {
            setThumbnail(card.card_id, card.imagepath);
            closeThumbnailSelector();
        };
        img.onerror = () => handleImageError(img);
        container.appendChild(img);
    });

    document.getElementById('thumbnailSelectModal').style.display = 'block';
}

function closeThumbnailSelector() {
    document.getElementById('thumbnailSelectModal').style.display = 'none';
}

function setThumbnail(cardId, imagepath) {
    document.getElementById('save-deck-thumbnail-id').value = cardId;
    const imgEl = document.getElementById('thumbnail-preview-img');
    const placeholder = document.getElementById('thumbnail-placeholder');
    
    imgEl.src = getCardImagePath({ imagepath: imagepath });
    imgEl.style.display = 'block';
    placeholder.style.display = 'none';
}

function submitDeckSave() {
    if (isSubmittingDeck) return;

    const name = document.getElementById('save-deck-name').value.trim();
    const formatSelect = document.getElementById('save-deck-format');
    const formatId = formatSelect ? formatSelect.value : null;
    const thumbnailCardId = document.getElementById('save-deck-thumbnail-id').value;

    const isPublic = document.getElementById('save-deck-public').checked ? 1 : 0;

    if (!name) {
        return alert("デッキ名を入力してください。");
    }
    if (!formatId || isNaN(parseInt(formatId, 10))) {
        return alert("有効なフォーマットを選択してください。");
    }
    
    const mainCards = Array.from(mainList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'main' }));
    const superDimCards = Array.from(superDimList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'super_dimensional' }));
    const grCards = Array.from(grList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'gr' }));
    const specialCards = Array.from(document.querySelectorAll('.special-box.active img')).map(img => ({ id: img.dataset.cardId, type: 'special' }));

    const allCards = [...mainCards, ...superDimCards, ...grCards, ...specialCards];    
    if (!allCards.length) return alert("カードを1枚以上入れてください。");

    const submitBtn = document.querySelector('#deckSaveModal button[onclick="submitDeckSave()"]');
    isSubmittingDeck = true;
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = '保存中...';
    }

    const payload = { 
        deck_id: deckId, 
        deck_name: name, 
        cards: allCards, 
        format_id: parseInt(formatId, 10),
        thumbnail_card_id: thumbnailCardId ? parseInt(thumbnailCardId, 10) : null,
        is_public: isPublic
    };

    fetch('/api/decks', {
        method: isEdit ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => {
        if (res.status === 401) {
            localStorage.setItem('pending_deck_payload', JSON.stringify(payload));
            localStorage.setItem('pending_deck_save', 'true');
            
            alert("デッキを保存するにはログインが必要です。ログイン画面に移動します。\n（ログイン完了後、自動的に保存されます）");
            window.location.href = '/login';
            throw new Error("REDIRECTED_TO_LOGIN");
        }
        return res.json();
    })
    .then(data => {
        if (!data) return;
        if (data.success) { 
            isDeckSaved = true; 
            localStorage.removeItem('unsaved_deck_draft');
            localStorage.removeItem('pending_deck_save');
            localStorage.removeItem('pending_deck_payload');

            // ★追加: スライド2の仕様（メッセージ完了前に保存された場合、ステップ6へスキップして進める）
            if (typeof TutorialManager !== 'undefined') {
                TutorialManager.jumpToStep(6);
            }

            alert("保存が完了しました！"); 
            window.location.href = '/mydecks'; 
        } else { 
            alert("保存に失敗しました: " + data.error); 
            isSubmittingDeck = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = '保存する';
            }
        }
    })
    .catch(err => {
        if (err.message !== "REDIRECTED_TO_LOGIN") {
            console.error(err);
            isSubmittingDeck = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerText = '保存する';
            }
        }
    });
}

function toggleSpecial(slotId) {
    const slot = document.getElementById(slotId);
    if (slot.classList.contains('active')) removeSpecialCard(slotId);
    else fetchSpecialCard(slotId);
}

function fetchSpecialCard(slotId) {
    const targetNames = slotId === 'slot-dolmagedon' 
        ? ['終焉の禁断 ドルマゲドンX', 'FORBIDDEN STAR ～世界最後の日～'] 
        : ['零龍', '滅亡の起源 零無'];

    fetch(`/api/cards?q=${encodeURIComponent(targetNames[0])}`)
        .then(res => res.json())
        .then(data => {
            const exactCard = data.find(c => targetNames.includes(c.card_name));
            if (exactCard) {
                const slot = document.getElementById(slotId);
                slot.innerHTML = '';
                addCardToDeck(exactCard, 'special', slotId);
            }
        });
}

function removeSpecialCard(slotId) {
    const slot = document.getElementById(slotId);
    slot.innerHTML = '';
    slot.classList.remove('active');
    slot.classList.add('empty');
    updateSaveButton(slotId, false);
    updateDeckDisplay();
}

function updateSaveButton(slotId, isActive) {
    const btn = document.getElementById(slotId === 'slot-dolmagedon' ? 'btn-doru' : 'btn-zero');
    if (btn) {
        btn.innerText = isActive ? "削除する" : "追加する";
        btn.style.backgroundColor = isActive ? "#dc3545" : "#007bff";
    }
}

function toggleSortModal() {
    const m = document.getElementById('sortModal');
    m.style.display = (m.style.display === 'block') ? 'none' : 'block';
}

function applySort() {
    const key = document.getElementById('sort-key').value;
    const order = document.querySelector('input[name="sort-order"]:checked').value;
    const selectedKey = document.getElementById('sort-key').value;
    sortDeckList(mainList, key, order);
    sortDeckList(superDimList, key, order);
    sortDeckList(grList, key, order);

    if (selectedKey === 'free') {
        currentSort.key = null; 
    } else {
        currentSort.key = selectedKey;
        currentSort.order = document.querySelector('input[name="sort-order"]:checked').value;
    }
    
    updateDeckDisplay();
    toggleSortModal();
}

function getCivWeight(civStr) {
    if (!civStr) return 999;
    const parts = civStr.toString().split(',').map(Number).filter(n => !isNaN(n)).sort((a, b) => a - b);
    if (parts.length === 0) return 999;
    if (parts.length === 1) return parts[0]; 
    
    let weight = 10 * parts.length;
    parts.forEach((p, idx) => {
        weight += p * Math.pow(0.1, idx + 1);
    });
    return weight;
}

function compareCardsByKey(a, b, key) {
    if (key === 'civ') {
        const valA = getCivWeight(a.dataset.civ);
        const valB = getCivWeight(b.dataset.civ);
        return valA - valB;
    }
    if (key === 'cost') {
        const nameA = a.dataset.cardName || '';
        const nameB = b.dataset.cardName || '';

        const valA = nameA.includes('禁断 ～封印されしX～') ? 99 : (parseInt(a.dataset.cost, 10) || 0);
        const valB = nameB.includes('禁断 ～封印されしX～') ? 99 : (parseInt(b.dataset.cost, 10) || 0);

        return valA - valB;
    }
    if (key === 'name') {
        const valA = a.dataset.cardName || '';
        const valB = b.dataset.cardName || '';
        return valA.localeCompare(valB, 'ja');
    }
    return 0;
}

function compareReading(a, b) {
    const readA = a.dataset.reading || a.dataset.cardName || '';
    const readB = b.dataset.reading || b.dataset.cardName || '';
    const res = readA.localeCompare(readB, 'ja');
    if (res !== 0) return res;

    const nameA = a.dataset.cardName || '';
    const nameB = b.dataset.cardName || '';
    const nameRes = nameA.localeCompare(nameB, 'ja');
    if (nameRes !== 0) return nameRes;

    return (parseInt(a.dataset.cardId, 10) || 0) - (parseInt(b.dataset.cardId, 10) || 0);
}

function sortDeckList(listElement, key, order) {
    const imgs = Array.from(listElement.querySelectorAll('img'));
    if (imgs.length === 0) return;

    const counts = {};
    if (key === 'count') {
        imgs.forEach(img => {
            const name = img.dataset.cardName;
            counts[name] = (counts[name] || 0) + 1;
        });
    }

    const direction = order === 'asc' ? 1 : -1;

    imgs.sort((a, b) => {
        let res = 0;

        if (key === 'count') {
            const countA = counts[a.dataset.cardName] || 0;
            const countB = counts[b.dataset.cardName] || 0;
            res = countA - countB;
        } else if (key === 'name') {
            res = compareReading(a, b);
        } else {
            res = compareCardsByKey(a, b, key);
        }

        if (res !== 0) return res * direction;

        if (key === 'cost') {
            return compareReading(a, b);
        }
        if (key === 'civ') {
            const costRes = compareCardsByKey(a, b, 'cost');
            if (costRes !== 0) return costRes;
            return compareReading(a, b);
        }
        if (key === 'count') {
            const costRes = compareCardsByKey(a, b, 'cost');
            if (costRes !== 0) return costRes;
            return compareReading(a, b);
        }

        return compareReading(a, b);
    });

    imgs.forEach(img => listElement.appendChild(img));
}

function hiraToKata(str) {
    return str.replace(/[\u3041-\u3096]/g, function(match) {
        const chr = match.charCodeAt(0) + 0x60;
        return String.fromCharCode(chr);
    });
}
function escapeSelectorValue(str) {
    if (!str) return '';
    return str.replace(/"/g, '\\"');
}
function updatePublicValue(val) {
    document.getElementById('save-deck-public').checked = (val === 1);
}
function addCardToDeckFromSearch(cardData) {
    const name = cardData.card_name;
    const charIds = (cardData.char_ids || '').split(',');
    
    const limitVal = cardData.card_limit;
    const nameLimit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');

    if (isDoru || isZero) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        if (slot && slot.querySelectorAll('img').length > 0) {
            alert("既に登録されています。");
            return;
        }
        addCardToDeck(cardData, 'special', slotId); 
        return;
    }

    const comboNames = cardData.combo_names || '';

    if (charIds.includes('3') || charIds.includes('6')) {
        if (checkLimit(name, nameLimit, superDimList, null, 8, "超次元ゾーン", comboNames)) {
            addCardToDeck(cardData, 'super_dimensional');
        }
    } else if (charIds.includes('10')) {
        if (checkLimit(name, nameLimit, grList, null, 12, "超GRゾーン", comboNames)) {
            addCardToDeck(cardData, 'gr');
        }
    } else {
        if (checkLimit(name, nameLimit, mainList, null, 60, "メインデッキ", comboNames)) {
            addCardToDeck(cardData, 'main');
        }
    }
}
function removeSubModalSelection(type, val) {
    const cb = document.querySelector(`.${type}-check[value="${val}"]`);
    if (cb) {
        cb.checked = false;
        updateTriggerText(type);
    }
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#39;');
}

function getCardSelector(cardName, comboNames) {
    if (comboNames) {
        return `img[data-combo-names="${escapeSelectorValue(comboNames)}"]`;
    }
    return `img[data-card-name="${escapeSelectorValue(cardName)}"][data-combo-names=""]`;
}

function toggleSearchPosition() {
    const container = document.getElementById('container');
    const isLeftHanded = container.classList.toggle('left-handed');
    
    localStorage.setItem('deck_builder_left_handed', isLeftHanded ? 'true' : 'false');
    syncSearchToggleButton();
}

function syncSearchToggleButton() {
    const searchSection = document.getElementById('search-section');
    const container = document.getElementById('container');
    const btn = document.getElementById('search-toggle-btn');
    if (!btn) return;

    const isCollapsed = searchSection.classList.contains('collapsed');
    const isLeftHanded = container.classList.contains('left-handed');

    if (window.innerWidth <= 768) {
        btn.style.left = '';
        btn.style.right = '20px';
        btn.style.top = '-32px';
        btn.style.transform = '';
        btn.style.borderRadius = '8px 8px 0 0';
        btn.innerHTML = isCollapsed ? '▲ カード検索を開く' : '▼ 検索を隠す';
        return;
    }

    btn.style.top = '50%';
    btn.style.transform = 'translateY(-50%)';

    if (isLeftHanded) {
        btn.style.right = 'auto';
        btn.style.left = isCollapsed ? '0px' : '380px'; 
        btn.style.borderRadius = '0 8px 8px 0'; 
        btn.innerHTML = isCollapsed ? '開く ▶' : '◀ 隠す';
    } else {
        btn.style.left = 'auto';
        btn.style.right = isCollapsed ? '0px' : '380px'; 
        btn.style.borderRadius = '8px 0 0 8px'; 
        btn.innerHTML = isCollapsed ? '◀ 開く' : '隠す ▶';
    }
}

window.addEventListener('DOMContentLoaded', () => {
    const isLeftHanded = localStorage.getItem('deck_builder_left_handed') === 'true';
    if (isLeftHanded) {
        document.getElementById('container').classList.add('left-handed');
    }
    syncSearchToggleButton();
});

window.addEventListener('resize', syncSearchToggleButton);

document.querySelectorAll('.era-check').forEach(el => {
    el.addEventListener('change', function() {
        const era = this.value;
        const isChecked = this.checked;

        if (isChecked) {
            document.querySelectorAll('.era-check').forEach(other => {
                if (other !== this) other.checked = false;
            });
            document.querySelectorAll('.goods-check').forEach(g => {
                g.checked = false;
            });
            document.querySelectorAll('.goods-check').forEach(g => {
                const goodsId = parseInt(g.value, 10);
                if (isNaN(goodsId)) return;

                let shouldCheck = false;
                if (era === 'shobu') {
                    shouldCheck = (goodsId >= 1 && goodsId <= 124);
                } else if (era === 'katta') {
                    shouldCheck = (goodsId >= 125 && goodsId <= 218);
                } else if (era === 'joe') {
                    shouldCheck = (goodsId >= 219 && goodsId <= 325) || goodsId === 340;
                } else if (era === 'win') {
                    shouldCheck = (goodsId >= 326);
                }

                if (shouldCheck) {
                    g.checked = true;
                }
            });
        } else {
            document.querySelectorAll('.goods-check').forEach(g => {
                g.checked = false;
            });
        }

        updateTriggerText('goods');
    });
});

const goodsContainer = document.getElementById('goods-list-container');
if (goodsContainer) {
    goodsContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('goods-check')) {
            document.querySelectorAll('.era-check').forEach(el => {
                el.checked = false;
            });
        }
    });
}

function getSerializedCards() {
    const cards = [];
    const serializeImg = (img, type) => {
        const cardId = img.dataset.cardId;
        const cached = cardCache[cardId];
        return {
            card_id: cardId,
            card_name: img.dataset.cardName || (cached ? cached.card_name : ''),
            combo_names: img.dataset.comboNames || (cached ? cached.combo_names : ''),
            char_ids: img.dataset.charIds || (cached ? cached.char_ids : ''),
            imagepath: img.dataset.imagepath || (cached ? cached.imagepath : ''),
            card_limit: img.dataset.cardLimit || (cached ? cached.card_limit : ''),
            civilization: img.dataset.civ || (cached ? cached.civilization : ''),
            cost: img.dataset.cost || (cached ? cached.cost : ''),
            card_type_in_deck: type
        };
    };

    mainList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'main')));
    superDimList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'super_dimensional')));
    grList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'gr')));
    document.querySelectorAll('.special-box.active img').forEach(img => {
        const item = serializeImg(img, 'special');
        item.slotId = img.parentNode.id; 
        cards.push(item);
    });

    return cards;
}

function saveDraftToLocalStorage() {
    if (isInitializing || isDeckSaved) return;

    const cards = getSerializedCards();
    const nameEl = document.getElementById('save-deck-name');
    const formatEl = document.getElementById('save-deck-format');
    const deckName = nameEl ? nameEl.value : '';
    const formatId = formatEl ? formatEl.value : null;

    if (cards.length === 0) {
        localStorage.removeItem('unsaved_deck_draft');
        return;
    }

    const draft = {
        deckId: deckId,
        deckName: deckName,
        formatId: formatId,
        cards: cards,
        updatedAt: Date.now()
    };
    localStorage.setItem('unsaved_deck_draft', JSON.stringify(draft));
}

function checkAndRestoreDraft() {
    const draftStr = localStorage.getItem('unsaved_deck_draft');
    if (!draftStr) {
        isInitializing = false; 
        return;
    }

    try {
        const draft = JSON.parse(draftStr);
        const isSameDeck = (draft.deckId === deckId);

        if (isSameDeck && draft.cards && draft.cards.length > 0) {
            const confirmRestore = confirm("前回の未保存データが見つかりました。\n途中から作成・編集を再開しますか？");
            if (confirmRestore) {
                restoreDraft(draft);
            } else {
                localStorage.removeItem('unsaved_deck_draft');
            }
        }
    } catch (e) {
        console.error("ドラフトの復元処理中にエラーが発生しました", e);
    } finally {
        isInitializing = false; 
    }
}

function restoreDraft(draft) {
    mainList.innerHTML = '';
    superDimList.innerHTML = '';
    grList.innerHTML = '';
    document.querySelectorAll('.special-box').forEach(slot => {
        slot.innerHTML = '';
        slot.classList.remove('active');
        slot.classList.add('empty');
        updateSaveButton(slot.id, false);
    });

    draft.cards.forEach(card => {
        const reconstructedCard = {
            card_id: card.card_id,
            card_name: card.card_name,
            combo_names: card.combo_names,
            char_ids: card.char_ids,
            imagepath: card.imagepath,
            card_limit: card.card_limit,
            civilization: card.civilization,
            cost: card.cost
        };
        
        cardCache[card.card_id] = reconstructedCard;

        if (card.card_type_in_deck === 'special') {
            addCardToDeck(reconstructedCard, 'special', card.slotId);
        } else {
            addCardToDeck(reconstructedCard, card.card_type_in_deck);
        }
    });

    if (draft.deckName) {
        const nameInput = document.getElementById('save-deck-name');
        if (nameInput) nameInput.value = draft.deckName;
    }
    if (draft.formatId) {
        const formatSelect = document.getElementById('save-deck-format');
        if (formatSelect) formatSelect.value = draft.formatId;
    }

    updateDeckDisplay();
    alert("未保存の作業データを復元しました。");
}

function bulkSelectVisible(type) {
    const visibleItems = document.querySelectorAll(`#${type}-list-container .list-item`);
    visibleItems.forEach(el => {
        if (el.style.display !== 'none') {
            const checkbox = el.querySelector(`.${type}-check`);
            if (checkbox) {
                checkbox.checked = true;
            }
        }
    });

    if (type === 'goods') {
        document.querySelectorAll('.era-check').forEach(el => {
            el.checked = false;
        });
    }

    updateTriggerText(type);
}
</script>
