# MAOMAO 電子商務管理系統 🌟

## 專案介紹 📝

**MAOMAO** 是一款精心設計與開發的後台管理系統，旨在為管理者提供高效且直觀的工具，用於管理會員資訊、商品數據及訂單流程，並支援數據報表生成。本專案完整整合了前端介面與後端邏輯，實現從數據存取到視覺呈現的全棧解決方案，涵蓋 CRUD 操作（創建、讀取、更新、刪除）及數據分析功能，致力於提升業務管理的精準性與操作效率。

- **開發目標** 🚀: 構建一個功能完善且易於維護的管理平台，優化操作流程並提升使用者體驗。
- **應用場景** 🌐: 適用於中小型平台的管理需求，例如會員制服務、租賃系統或其他需要後台管理的應用。
- **開發進度** ⏳: 自啟動以來持續迭代，截至 2025 年 4 月 9 日仍處於活躍開發階段。

---

## 專案技術 💻

本專案採用現代化的前端與後端技術棧，確保高效能與良好的用戶體驗。

### 前端技術 🎨
- **HTML5**: 結構化頁面內容。
- **CSS3 / Bootstrap** 🌈: 響應式設計與統一風格，採用自定義配色方案（`--maocolor01` 至 `--maocolor09`）。
- **JavaScript**:  
  - **Vue.js** ⚡: 動態數據綁定與組件化（部分頁面）。  
  - **jQuery**: 表單處理與 DOM 操作（部分頁面）。  
- **Axios** 📡: 與後端 API 的非同步通信。
- **Chart.js** 📈: 生成數據報表圖表。
- **SweetAlert2** 🔔: 美觀的通知與提示框。

### 後端技術 🛠️
- **PHP**: 核心後端邏輯，使用參數化查詢防範 SQL 注入。
- **MySQL** 🗄️: 數據存儲，使用 `utf8mb4` 編碼支援多語言。
- **資料庫資訊**:  
  - 主機: `sql102.infinityfree.com`  
  - 數據庫: `if0_38646811_cy_mao`  
- **檔案上傳** 📤: 支援圖片上傳（商品與會員頭像），限制格式與大小。

### 其他 ⚙️
- **環境需求**: PHP 7.4+，MySQL 5.7+。
- **部署方式**: 可部署於任何支援 PHP 的伺服器（如 Apache 或 Nginx）。

---

## 功能簡介 ✨

### 會員管理 👤
- **註冊與登入**: 用戶可註冊帳號並登入，支援密碼加密與頭像上傳。
- **資料管理**: 管理員可查看會員列表、編輯會員資料（等級、地區等）或刪除會員。
- **等級系統** ⭐: 根據訂單數量自動更新會員等級（一般會員、VIP、VVIP），最高等級 管理員 享有特殊權限。
- **API 支援**:  
  - 註冊: `POST /users_api.php?action=register`  
  - 獲取所有會員: `GET /users_api.php?action=getalldata`

### 商品管理 🛍️
- **新增與編輯**: 支援新增商品（含圖片）並更新商品資訊。
- **列表展示**: 商品列表支援篩選（類別、狀態）與排序。
- **唯一性檢查** ✅: 商品名稱需唯一，避免重複。
- **API 支援**:  
  - 新增商品: `POST /products_api.php?action=insert`  
  - 獲取所有商品: `GET /products_api.php?action=getalldata`

### 賣場管理（訂單） 📋
- **訂單處理**: 支援新增訂單（含明細）、更新狀態（已下單、已出貨等）與刪除訂單。
- **折扣機制** 💸: 根據會員等級自動計算折扣（20% 或 10%）。
- **詳細查詢**: 以手風琴方式展示訂單明細，包括商品名稱與租賃日期。
- **API 支援**:  
  - 新增訂單: `POST /orders_api.php?action=insert`  
  - 獲取所有訂單: `GET /orders_api.php?action=getalldata`

### 前端功能 🌟
- **響應式設計** 📱: 適配桌面與行動裝置。
- **數據可視化** 📉: 報表頁面提供折線圖、圓餅圖等分析工具。
- **即時互動** ⚡: 使用 Vue.js 實現動態更新，SweetAlert2 提供友善提示。

---

## 頁面展示 🖼️

以下是專案的主要頁面展示，分為前台與後台兩部分：

### 前台頁面 🌍
1. **前台首頁** 🏠  
   ![前台首頁](images/git-img/mao-index.png)  
   *展示平台核心內容與導航，提供直觀的用戶入口*

2. **會員中心** 👤  
   ![會員中心](images/git-img/mao-profile.png)  
   *用戶可查看個人資料、訂單記錄並進行帳戶管理*

3. **商品購物車** 🛒  
   ![商品購物車](images/git-img/mao-product.png)  
   *顯示已選商品並支援結帳與修改購物內容*

### 後台頁面 ⚙️
1. **會員管理** 👥  
   ![會員管理](images/git-img/mao-user-control.png)  
   *顯示會員列表並支援篩選與編輯*

2. **商品管理** 📦  
   ![商品管理](images/git-img/mao-product-control.png)  
   *展示商品資訊並支援新增與更新*

3. **訂單管理** 📋  
   ![訂單管理](images/git-img/mao-order-control.png)  
   *以手風琴方式展示訂單詳情並支援狀態更新*

4. **報表管理** 📊  
   ![報表管理](images/git-img/mao-chart.png)  
   *提供銷售趨勢與會員分佈圖表*

---

## Figma Prototype 🔗

想了解更多設計細節？請查看以下 Figma 原型連結：  
👉 [MAOMAO Project Prototype](https://www.figma.com/proto/VjX8SG3qy9NFmw7HpA3zMg/MAOMAO-project?node-id=4029-627&t=pDeKKYDPtCn7PKBM-0&scaling=scale-down-width&content-scaling=fixed&page-id=0%3A1&starting-point-node-id=4029%3A627)

---

## 聯絡作者 📬

如有任何問題或建議，歡迎聯繫作者：

- **Email**: cccy7357@gmail.com  

---
