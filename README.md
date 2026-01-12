分享一個為了加強內部資安合規所開發的後台小工具。

我們都知道 wp-content/uploads 往往是惡意腳本 (Web Shell/Backdoor) 藏匿的重災區。雖然有 WAF 防護，但為了落實縱深防禦 (Defense in Depth)，我寫了這支監控外掛，專門盯著那些「本該是靜態檔案」的目錄。

核心特色 (v1.2.4)：

精準防禦：採用「白名單掃描 + 黑名單排除」邏輯，只監控 Uploads 與自選靜態目錄，自動避開 Plugins/Themes，零誤判。

即時儀表板：後台 Widget 直覺顯示安全狀態（綠燈安全 / 紅燈警示）。

效能友善：內建快取機制 (Transients)，避免每次載入後台都消耗 I/O 資源。

稽核紀錄：顯示最後掃描時間戳記，並內建純 CSS 的使用引導 Tooltip。

程式碼極度輕量，沒有複雜設定，單純做好「看門」這件事。有需要的社團朋友歡迎自取使用。

#WordPress #Security #DevOps #MyUploadsSentry
