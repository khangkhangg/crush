INSERT INTO ui_translations (lang, `key`, value) VALUES

-- Hotel
('vi','Hotel','Khách sạn'),
('es','Hotel','Hotel'),
('zh','Hotel','酒店'),
('hi','Hotel','होटल'),
('pt','Hotel','Hotel'),
('fr','Hotel','Hôtel'),
('ko','Hotel','호텔'),
('ja','Hotel','ホテル'),
('th','Hotel','โรงแรม'),

-- hotel name
('vi','hotel name','tên khách sạn'),
('es','hotel name','nombre del hotel'),
('zh','hotel name','酒店名称'),
('hi','hotel name','होटल का नाम'),
('pt','hotel name','nome do hotel'),
('fr','hotel name','nom de l''hôtel'),
('ko','hotel name','호텔 이름'),
('ja','hotel name','ホテル名'),
('th','hotel name','ชื่อโรงแรม')

ON DUPLICATE KEY UPDATE value = VALUES(value);
