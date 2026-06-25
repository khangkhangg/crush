-- Vietnamese translations for the redesigned sender-flow strings
-- (new.php / dashboard.php / created.php / confirmed.php) that the v9 redesign
-- introduced but were never seeded, so they fell back to English on /invites/new.
-- Casual ~17-year-old tone, consistent with the existing vi copy.
INSERT INTO ui_translations (lang, `key`, value) VALUES
('vi','Who is it for?','Gửi cho ai nè?'),
('vi','Keep it private until you are ready.','Giữ bí mật cho tới khi bạn sẵn sàng.'),
('vi','I will share the link','Mình sẽ tự gửi link'),
('vi','A little message','Lời nhắn nhỏ'),
('vi','optional','không bắt buộc'),
('vi','Secret mode','Chế độ bí mật'),
('vi','Choose how much to reveal.','Chọn xem tiết lộ bao nhiêu nha.'),
('vi','Secret admirer','Người thầm thương'),
('vi','Send anonymously.','Gửi ẩn danh.'),
('vi','Reveal after answer','Tiết lộ sau khi trả lời'),
('vi','Let them know it is you later.','Cho người ấy biết là bạn sau nha.'),
('vi','Let them choose','Để người ấy chọn'),
('vi','I want to approve','Mình muốn duyệt trước'),
('vi','Add a vibe','Thêm một vibe'),
('vi','Suggest a vibe','Gợi ý một vibe'),
('vi','They pick','Người ấy chọn'),
('vi','Optional, but it makes the invite easier to answer.','Không bắt buộc, nhưng giúp người ấy dễ trả lời hơn.'),
('vi','Invite preview','Xem trước lời mời'),
('vi','A private date quest','Nhiệm vụ hẹn hò bí mật'),
('vi','Date quest','Nhiệm vụ hẹn hò'),
('vi','Close','Đóng'),
('vi','Your date quests','Nhiệm vụ hẹn hò của bạn'),
('vi','Track every invite from sent to answered.','Theo dõi mọi lời mời từ lúc gửi tới khi được trả lời.'),
('vi','No invites yet','Chưa có lời mời nào'),
('vi','Send your first one when you are ready.','Gửi cái đầu tiên khi bạn sẵn sàng nha.'),
('vi','Invite timeline','Tiến trình lời mời'),
('vi','Sent, opened, answered, confirmed. The next step is always highlighted.','Đã gửi, đã mở, đã trả lời, đã xác nhận. Bước tiếp theo luôn được tô sáng.'),
('vi','Invite ready','Lời mời xong rồi'),
('vi','Share your private invite link.','Chia sẻ link mời riêng của bạn.'),
('vi','What happens next?','Tiếp theo là gì nè?'),
('vi','They open the invite, pick a time and vibe, and you see the answer here.','Người ấy mở lời mời, chọn giờ và vibe, rồi bạn xem câu trả lời ở đây.')
ON DUPLICATE KEY UPDATE value = VALUES(value);
