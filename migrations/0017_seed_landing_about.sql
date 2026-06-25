INSERT INTO ui_translations (lang, `key`, value) VALUES
-- tagline
('vi','Send your crush a date — anonymously, adorably.','Rủ người bạn thầm thương đi hẹn — ẩn danh, dễ thương cực.'),
('es','Send your crush a date — anonymously, adorably.','Invita a tu crush a una cita — en secreto y con mucho estilo.'),
('zh','Send your crush a date — anonymously, adorably.','偷偷约你喜欢的人出来——匿名又可爱。'),
('hi','Send your crush a date — anonymously, adorably.','अपने crush को डेट पर बुलाओ — गुमनाम और प्यारे अंदाज़ में।'),
('pt','Send your crush a date — anonymously, adorably.','Chame seu crush pra um date — anônimo e fofo.'),
('fr','Send your crush a date — anonymously, adorably.','Propose un rendez-vous à ton crush — en secret et tout mignon.'),
('ko','Send your crush a date — anonymously, adorably.','좋아하는 사람에게 데이트 신청 — 익명으로, 귀엽게.'),
('ja','Send your crush a date — anonymously, adorably.','好きな人をデートに誘おう — 匿名で、かわいく。'),
('th','Send your crush a date — anonymously, adorably.','ชวนคนที่แอบชอบไปเดต — แบบลับ ๆ และน่ารัก'),
-- Start button
('vi','Start','Bắt đầu'),('es','Start','Empezar'),('zh','Start','开始'),('hi','Start','शुरू करें'),('pt','Start','Começar'),('fr','Start','Commencer'),('ko','Start','시작'),('ja','Start','はじめる'),('th','Start','เริ่ม'),
-- About link
('vi','What is Crush?','Crush là gì?'),('es','What is Crush?','¿Qué es Crush?'),('zh','What is Crush?','Crush 是什么？'),('hi','What is Crush?','Crush क्या है?'),('pt','What is Crush?','O que é o Crush?'),('fr','What is Crush?','C''est quoi Crush ?'),('ko','What is Crush?','Crush가 뭐야?'),('ja','What is Crush?','Crushって何？'),('th','What is Crush?','Crush คืออะไร?'),
-- About hero
('vi','Real life, but make it a date','Đời thực, nhưng biến nó thành một buổi hẹn'),('es','Real life, but make it a date','La vida real, pero en plan cita'),('zh','Real life, but make it a date','真实生活，但来场约会吧'),('hi','Real life, but make it a date','असली ज़िंदगी, पर एक डेट जैसी'),('pt','Real life, but make it a date','A vida real, só que vira um date'),('fr','Real life, but make it a date','La vraie vie, version rendez-vous'),('ko','Real life, but make it a date','현실인데, 데이트로 만들어봐'),('ja','Real life, but make it a date','リアルな日常を、デートにしよう'),('th','Real life, but make it a date','ชีวิตจริง แต่ทำให้เป็นเดต')
ON DUPLICATE KEY UPDATE value = VALUES(value);
