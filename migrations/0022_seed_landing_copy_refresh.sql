INSERT INTO ui_translations (lang, `key`, value) VALUES
('vi','Ask your crush out — stay anonymous, keep it cute.','Rủ người ấy đi hẹn — ẩn danh mà vẫn cute.'),
('vi','your email','email của bạn'),
('vi','make a password','tạo mật khẩu'),
('vi','Start my invite','Tạo lời mời'),
('vi','Make a password so you can hop back in later.','Tạo mật khẩu để lát nữa quay lại dễ nha.'),
('vi','Wait, what is Crush?','Ủa Crush là gì?'),
('vi','Check your email — your magic link is going to','Check email nha — link đăng nhập đang bay tới'),
('vi','Open it and we’ll keep going.','Mở link rồi mình đi tiếp.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
